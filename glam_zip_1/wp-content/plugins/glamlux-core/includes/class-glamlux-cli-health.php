<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class GlamLux_CLI_Health {

	/**
	 * Run enterprise health checks for GlamLux.
	 *
	 * ## EXAMPLES
	 *
	 *     wp glamlux health
	 */
	public function __invoke( $args, $assoc_args ) {
		$debug_log_state = $this->get_debug_log_state();
		$failures        = array();

		$this->check_schema( $failures );
		$summary = $this->check_operations_endpoint( $failures );
		$this->check_active_memberships_integer( $summary, $failures );
		$this->check_debug_log_for_sql_warnings( $debug_log_state, $failures );

		if ( ! empty( $failures ) ) {
			WP_CLI::error_multi_line(
				array_merge(
					array( 'GlamLux health check failed:' ),
					$failures
				)
			);
		}

		WP_CLI::success( 'GlamLux health check passed.' );
	}

	private function check_schema( array &$failures ) {
		global $wpdb;

		$schema = array(
			'gl_memberships' => array(
				'columns' => array( 'id' ),
				'indexes' => array( 'PRIMARY' ),
			),
			'gl_appointments' => array(
				'columns' => array( 'id', 'appointment_time', 'status' ),
				'indexes' => array( 'PRIMARY', 'salon_time' ),
			),
			'gl_staff' => array(
				'columns' => array( 'id', 'is_active' ),
				'indexes' => array( 'PRIMARY' ),
			),
			'gl_service_logs' => array(
				'columns' => array( 'id', 'notes', 'logged_at' ),
				'indexes' => array( 'PRIMARY' ),
			),
		);

		foreach ( $schema as $table_suffix => $constraints ) {
			$table_name = $wpdb->prefix . $table_suffix;
			$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
			if ( $exists !== $table_name ) {
				$failures[] = sprintf( '- Missing required table: %s', $table_name );
				continue;
			}

			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}", 0 );
			foreach ( $constraints['columns'] as $column ) {
				if ( ! in_array( $column, $columns, true ) ) {
					$failures[] = sprintf( '- Missing required column: %s.%s', $table_name, $column );
				}
			}

			$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table_name}", ARRAY_A );
			$keys    = array();
			foreach ( $indexes as $index ) {
				if ( isset( $index['Key_name'] ) ) {
					$keys[] = $index['Key_name'];
				}
			}

			foreach ( $constraints['indexes'] as $index_name ) {
				if ( ! in_array( $index_name, $keys, true ) ) {
					$failures[] = sprintf( '- Missing required index: %s.%s', $table_name, $index_name );
				}
			}
		}

		$table_name          = $wpdb->prefix . 'gl_memberships';
		$membership_columns  = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}", 0 );
		$has_status_column   = in_array( 'status', $membership_columns, true );
		$has_is_active_field = in_array( 'is_active', $membership_columns, true );
		if ( ! $has_status_column && ! $has_is_active_field ) {
			$failures[] = sprintf( '- %s must include either status or is_active column for active membership tracking', $table_name );
		}
	}

	private function check_operations_endpoint( array &$failures ) {
		$admin_users = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
				'number' => 1,
			)
		);

		if ( empty( $admin_users[0] ) ) {
			$failures[] = '- Unable to validate /operations/summary endpoint: no administrator user found.';
			return array();
		}

		wp_set_current_user( (int) $admin_users[0] );
		$request  = new WP_REST_Request( 'GET', '/glamlux/v1/operations/summary' );
		$response = rest_do_request( $request );
		$status   = $response->get_status();

		if ( 200 !== $status ) {
			$failures[] = sprintf( '- /wp-json/glamlux/v1/operations/summary returned HTTP %d', $status );
			return array();
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			$failures[] = '- operations summary response is not an object/array.';
			return array();
		}

		$this->assert_type( $data, 'generated_at', 'string', $failures );
		$this->assert_type( $data, 'health', 'string', $failures );
		$this->assert_type( $data, 'database', 'array', $failures );
		$this->assert_type( $data, 'operations', 'array', $failures );

		if ( isset( $data['database'] ) && is_array( $data['database'] ) ) {
			$this->assert_type( $data['database'], 'required_tables', 'array', $failures, 'database' );
			$this->assert_type( $data['database'], 'missing_tables', 'array', $failures, 'database' );
			$this->assert_type( $data['database'], 'status', 'string', $failures, 'database' );
		}

		if ( isset( $data['operations'] ) && is_array( $data['operations'] ) ) {
			$operation_keys = array(
				'appointments_today',
				'pending_appointments',
				'active_memberships',
				'active_staff',
				'open_leads',
				'service_errors_24h',
			);
			foreach ( $operation_keys as $operation_key ) {
				$this->assert_type( $data['operations'], $operation_key, 'integer', $failures, 'operations' );
			}
		}

		return $data;
	}

	private function check_active_memberships_integer( array $summary, array &$failures ) {
		if ( ! isset( $summary['operations']['active_memberships'] ) ) {
			$failures[] = '- operations.active_memberships is missing from summary output.';
			return;
		}

		if ( ! is_int( $summary['operations']['active_memberships'] ) ) {
			$failures[] = '- operations.active_memberships is not an integer.';
		}
	}

	private function check_debug_log_for_sql_warnings( $state, array &$failures ) {
		if ( empty( $state['available'] ) ) {
			$failures[] = '- WP_DEBUG_LOG is not enabled or debug log file is not readable; cannot verify SQL warnings.';
			return;
		}

		$path         = $state['path'];
		$offset       = $state['size'];
		$current_size = file_exists( $path ) ? filesize( $path ) : 0;
		if ( false === $current_size ) {
			$current_size = 0;
		}

		$delta = '';
		if ( $current_size > $offset ) {
			$handle = fopen( $path, 'r' );
			if ( false !== $handle ) {
				fseek( $handle, $offset );
				$delta = stream_get_contents( $handle );
				fclose( $handle );
			}
		}

		if ( '' === $delta ) {
			return;
		}

		if ( preg_match( '/(WordPress database error|SQLSTATE|\bwarning\b)/i', $delta ) ) {
			$failures[] = '- SQL warnings/errors detected in debug log while running health check.';
		}
	}

	private function get_debug_log_state() {
		$debug_log_enabled = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
		$default_path      = WP_CONTENT_DIR . '/debug.log';
		$debug_log_path    = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : $default_path;

		if ( ! $debug_log_enabled || ! is_readable( $debug_log_path ) ) {
			return array(
				'available' => false,
				'path'      => $debug_log_path,
				'size'      => 0,
			);
		}

		$size = filesize( $debug_log_path );
		if ( false === $size ) {
			$size = 0;
		}

		return array(
			'available' => true,
			'path'      => $debug_log_path,
			'size'      => $size,
		);
	}

	private function assert_type( array $data, $key, $expected_type, array &$failures, $scope = 'root' ) {
		if ( ! array_key_exists( $key, $data ) ) {
			$failures[] = sprintf( '- Missing %s.%s key in operations summary response', $scope, $key );
			return;
		}

		$value = $data[ $key ];
		$is_ok = false;

		switch ( $expected_type ) {
			case 'string':
				$is_ok = is_string( $value );
				break;
			case 'array':
				$is_ok = is_array( $value );
				break;
			case 'integer':
				$is_ok = is_int( $value );
				break;
		}

		if ( ! $is_ok ) {
			$failures[] = sprintf( '- Expected %s.%s to be %s', $scope, $key, $expected_type );
		}
	}
}

WP_CLI::add_command( 'glamlux health', 'GlamLux_CLI_Health' );
