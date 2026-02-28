<?php
/**
 * GlamLux Payroll Module
 *
 * Calculates staff commission from completed appointments and generates payroll records.
 * Provides admin UI with CSV export for financial reporting.
 *
 * Phase 13: Real SQL commission calculation + CSV payroll export.
 */
class GlamLux_Payroll {

	public function __construct() {
		add_action( 'admin_post_glamlux_run_payroll', array( $this, 'handle_run_payroll' ) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Admin UI
	// ─────────────────────────────────────────────────────────────────────────

	public function render_admin_page() {

		if ( ! current_user_can( 'manage_glamlux_franchise' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access Denied.', 'glamlux-core' ) );
		}

		// Handle CSV export
		if ( isset( $_GET['export'] ) && 'csv' === $_GET['export'] ) {
			check_admin_referer( 'glamlux_export_payroll' );
			$this->export_csv();
			exit;
		}

		global $wpdb;

		$payroll_records = $wpdb->get_results(
			"SELECT p.*, u.display_name AS staff_name, s.name AS salon_name
			 FROM {$wpdb->prefix}gl_payroll p
			 LEFT JOIN {$wpdb->prefix}gl_staff st ON p.staff_id = st.id
			 LEFT JOIN {$wpdb->users} u ON st.wp_user_id = u.ID
			 LEFT JOIN {$wpdb->prefix}gl_salons s ON p.salon_id = s.id
			 ORDER BY p.period_start DESC
			 LIMIT 100"
		);

		$run_url    = wp_nonce_url( admin_url( 'admin-post.php?action=glamlux_run_payroll' ), 'glamlux_run_payroll' );
		$export_url = wp_nonce_url( add_query_arg( 'export', 'csv', admin_url( 'admin.php?page=glamlux-payroll' ) ), 'glamlux_export_payroll' );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Payroll Processing', 'glamlux-core' ) . '</h1>';
		echo '<a href="' . esc_url( $run_url ) . '" class="page-title-action" onclick="return confirm(\'Calculate commissions for this month?\')">' . esc_html__( 'Run Payroll Calculation', 'glamlux-core' ) . '</a>';
		echo '<a href="' . esc_url( $export_url ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'glamlux-core' ) . '</a>';
		echo '<hr class="wp-header-end">';

		if ( isset( $_GET['done'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Payroll calculated and recorded successfully.', 'glamlux-core' ) . '</p></div>';
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Staff</th><th>Salon</th><th>Period</th><th>Services ($)</th><th>Commission ($)</th><th>Status</th></tr></thead><tbody>';

		if ( empty( $payroll_records ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No payroll periods found. Click "Run Payroll Calculation" to generate.', 'glamlux-core' ) . '</td></tr>';
		} else {
			foreach ( $payroll_records as $r ) {
				$badge_color = 'paid' === $r->status ? '#7ED321' : '#e2a000';
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s → %s</td><td>$%s</td><td>$%s</td><td><span style="color:%s;font-weight:600;">%s</span></td></tr>',
					esc_html( $r->staff_name ?? 'Unknown' ),
					esc_html( $r->salon_name ?? '—' ),
					esc_html( $r->period_start ),
					esc_html( $r->period_end ),
					esc_html( number_format( (float) $r->total_services, 2 ) ),
					esc_html( number_format( (float) $r->commission_earned, 2 ) ),
					esc_attr( $badge_color ),
					esc_html( ucfirst( $r->status ) )
				);
			}
		}

		echo '</tbody></table></div>';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Business Logic: Commission Calculation
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Calculate commissions for all active staff from completed appointments
	 * within the given period. Creates wp_gl_payroll records.
	 *
	 * @param int    $salon_id
	 * @param string $period_start  MySQL date string 'YYYY-MM-DD'
	 * @param string $period_end    MySQL date string 'YYYY-MM-DD'
	 * @return bool
	 */
	public function calculate_period( $salon_id, $period_start, $period_end ) {
		global $wpdb;

		// Get all completed appointments for this salon within the period,
		// grouped by staff member.
		$staff_earnings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.staff_id,
				        st.commission_rate,
				        COALESCE( SUM( a.amount ), 0 ) AS total_services
				 FROM {$wpdb->prefix}gl_appointments a
				 INNER JOIN {$wpdb->prefix}gl_staff st ON a.staff_id = st.id
				 WHERE a.salon_id = %d
				 AND a.status = 'completed'
				 AND DATE( a.appointment_time ) BETWEEN %s AND %s
				 GROUP BY a.staff_id",
				absint( $salon_id ),
				sanitize_text_field( $period_start ),
				sanitize_text_field( $period_end )
			)
		);

		if ( empty( $staff_earnings ) ) {
			return false;
		}

		foreach ( $staff_earnings as $record ) {
			$commission = round( (float) $record->total_services * ( (float) $record->commission_rate / 100 ), 2 );

			// Insert payroll record (skip if already exists for this period)
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}gl_payroll
					 WHERE staff_id = %d AND salon_id = %d AND period_start = %s AND period_end = %s LIMIT 1",
					absint( $record->staff_id ), absint( $salon_id ),
					$period_start, $period_end
				)
			);

			if ( ! $existing ) {
				$wpdb->insert(
					"{$wpdb->prefix}gl_payroll",
					array(
						'staff_id'         => absint( $record->staff_id ),
						'salon_id'         => absint( $salon_id ),
						'period_start'     => $period_start,
						'period_end'       => $period_end,
						'total_services'   => (float) $record->total_services,
						'commission_earned'=> $commission,
						'status'           => 'pending',
					)
				);
			}
		}

		return true;
	}

	/**
	 * Handle admin-post.php action to trigger payroll for current month.
	 */
	public function handle_run_payroll() {
		if ( ! check_admin_referer( 'glamlux_run_payroll' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'glamlux-core' ) );
		}
		if ( ! current_user_can( 'manage_glamlux_franchise' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'glamlux-core' ) );
		}

		global $wpdb;

		$period_start = date( 'Y-m-01' ); // First of this month
		$period_end   = date( 'Y-m-t' );  // Last of this month

		$salons = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}gl_salons WHERE is_active = 1" );
		foreach ( $salons as $salon_id ) {
			$this->calculate_period( $salon_id, $period_start, $period_end );
		}

		wp_redirect( admin_url( 'admin.php?page=glamlux-payroll&done=1' ) );
		exit;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// CSV Export
	// ─────────────────────────────────────────────────────────────────────────

	public function export_csv() {
		global $wpdb;

		$records = $wpdb->get_results(
			"SELECT p.*, u.display_name AS staff_name, s.name AS salon_name
			 FROM {$wpdb->prefix}gl_payroll p
			 LEFT JOIN {$wpdb->prefix}gl_staff st ON p.staff_id = st.id
			 LEFT JOIN {$wpdb->users} u ON st.wp_user_id = u.ID
			 LEFT JOIN {$wpdb->prefix}gl_salons s ON p.salon_id = s.id
			 ORDER BY p.period_start DESC"
		);

		$filename = 'glamlux-payroll-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Staff Name', 'Salon', 'Period Start', 'Period End', 'Total Services ($)', 'Commission Earned ($)', 'Status' ) );

		foreach ( $records as $r ) {
			fputcsv( $output, array(
				$r->staff_name ?? 'Unknown',
				$r->salon_name ?? '—',
				$r->period_start,
				$r->period_end,
				number_format( (float) $r->total_services, 2 ),
				number_format( (float) $r->commission_earned, 2 ),
				ucfirst( $r->status ),
			) );
		}

		fclose( $output );
	}
}
