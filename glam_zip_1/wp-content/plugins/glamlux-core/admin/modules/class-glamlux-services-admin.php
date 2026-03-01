<?php
/**
 * GlamLux Services Admin Module
 *
 * Global service catalogue management + franchise-specific pricing overrides.
 *
 * Phase 13 Deliverable:
 * - List all services from wp_gl_service_pricing
 * - Add/Edit/Delete global services
 * - Set per-franchise custom prices
 */
class GlamLux_Services_Admin {

	public function __construct() {
		add_action( 'admin_post_glamlux_save_service',   array( $this, 'handle_save_service' ) );
		add_action( 'admin_post_glamlux_delete_service', array( $this, 'handle_delete_service' ) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Admin Page Render
	// ─────────────────────────────────────────────────────────────────────────

	public function render_admin_page() {

		if ( ! current_user_can( 'manage_glamlux_platform' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access Denied.', 'glamlux-core' ) );
		}

		$action = sanitize_text_field( $_GET['gl_action'] ?? '' );

		if ( 'edit' === $action ) {
			$this->render_edit_form( absint( $_GET['service_id'] ?? 0 ) );
		} elseif ( 'add' === $action ) {
			$this->render_edit_form( 0 );
		} else {
			$this->render_list_table();
		}
	}

	private function render_list_table() {
		global $wpdb;

		// Fetch services with optional franchise-specific override count
		$services = $wpdb->get_results(
			"SELECT sp.*, COUNT(spo.id) AS override_count
			 FROM {$wpdb->prefix}gl_service_pricing sp
			 LEFT JOIN {$wpdb->prefix}gl_service_pricing spo ON spo.service_id = sp.id AND spo.franchise_id IS NOT NULL
			 WHERE sp.franchise_id IS NULL
			 GROUP BY sp.id
			 ORDER BY sp.service_name ASC"
		);

		$add_url = admin_url( 'admin.php?page=glamlux-services&gl_action=add' );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Services & Pricing', 'glamlux-core' ) . '</h1>';
		echo '<a href="' . esc_url( $add_url ) . '" class="page-title-action">' . esc_html__( 'Add New Service', 'glamlux-core' ) . '</a>';
		echo '<hr class="wp-header-end">';

		if ( isset( $_GET['saved'] ) )   echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Service saved.', 'glamlux-core' ) . '</p></div>';
		if ( isset( $_GET['deleted'] ) ) echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Service deleted.', 'glamlux-core' ) . '</p></div>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>
			<th>' . esc_html__( 'ID', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Service Name', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Category', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Base Price', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Duration (min)', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Franchise Overrides', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Actions', 'glamlux-core' ) . '</th>
		</tr></thead><tbody>';

		if ( empty( $services ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No services found. Add your first service.', 'glamlux-core' ) . '</td></tr>';
		} else {
			foreach ( $services as $svc ) {
				$edit_url = admin_url( 'admin.php?page=glamlux-services&gl_action=edit&service_id=' . $svc->id );
				$del_url  = wp_nonce_url( admin_url( 'admin-post.php?action=glamlux_delete_service&service_id=' . $svc->id ), 'glamlux_delete_service_' . $svc->id );
				printf(
					'<tr>
						<td>%d</td>
						<td><strong>%s</strong></td>
						<td>%s</td>
						<td>$%s</td>
						<td>%s min</td>
						<td>%d override(s)</td>
						<td><a href="%s">Edit</a> | <a href="%s" onclick="return confirm(\'Delete this service?\');" style="color:#d63638;">Delete</a></td>
					</tr>',
					esc_html( $svc->id ),
					esc_html( $svc->service_name ),
					esc_html( $svc->category ?? '—' ),
					esc_html( number_format( (float) $svc->base_price, 2 ) ),
					esc_html( $svc->duration_minutes ?? '—' ),
					esc_html( $svc->override_count ),
					esc_url( $edit_url ),
					esc_url( $del_url )
				);
			}
		}

		echo '</tbody></table></div>';
	}

	private function render_edit_form( $service_id ) {
		global $wpdb;

		$svc = null;
		if ( $service_id > 0 ) {
			$svc = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gl_service_pricing WHERE id = %d AND franchise_id IS NULL LIMIT 1", $service_id )
			);
		}

		// Get all franchises for the override section
		$franchises = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}gl_franchises ORDER BY name ASC" );

		// Get existing franchise overrides for this service
		$overrides = $service_id > 0 ? $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gl_service_pricing WHERE service_id = %d AND franchise_id IS NOT NULL", $service_id )
		) : array();

		$overrides_by_franchise = array();
		foreach ( $overrides as $o ) {
			$overrides_by_franchise[ $o->franchise_id ] = $o->custom_price;
		}

		$form_action = admin_url( 'admin-post.php' );
		$page_title  = $service_id > 0 ? __( 'Edit Service', 'glamlux-core' ) : __( 'Add New Service', 'glamlux-core' );
		$back_url    = admin_url( 'admin.php?page=glamlux-services' );
		$categories  = array( 'Skin Care', 'Hair', 'Makeup', 'Nails', 'Wellness', 'Bridal', 'Other' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $page_title ) . '</h1>';
		echo '<a href="' . esc_url( $back_url ) . '">← ' . esc_html__( 'Back to list', 'glamlux-core' ) . '</a>';
		echo '<hr class="wp-header-end">';

		echo '<form method="POST" action="' . esc_url( $form_action ) . '" style="max-width:680px;margin-top:20px;">';
		wp_nonce_field( 'glamlux_save_service', '_glamlux_nonce' );
		echo '<input type="hidden" name="action" value="glamlux_save_service">';
		echo '<input type="hidden" name="service_id" value="' . esc_attr( $service_id ) . '">';

		echo '<table class="form-table">';

		// Service Name
		printf( '<tr><th>%s</th><td><input type="text" name="gl_service_name" class="regular-text" required value="%s"></td></tr>',
			esc_html__( 'Service Name', 'glamlux-core' ), esc_attr( $svc->service_name ?? '' ) );

		// Category
		echo '<tr><th>' . esc_html__( 'Category', 'glamlux-core' ) . '</th><td><select name="gl_category" class="regular-text">';
		foreach ( $categories as $cat ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $cat ), selected( ( $svc->category ?? '' ), $cat, false ), esc_html( $cat ) );
		}
		echo '</select></td></tr>';

		// Base Price
		printf( '<tr><th>%s</th><td><input type="number" name="gl_base_price" step="0.01" class="small-text" required value="%s"></td></tr>',
			esc_html__( 'Base Price ($)', 'glamlux-core' ), esc_attr( $svc->base_price ?? '' ) );

		// Duration
		printf( '<tr><th>%s</th><td><input type="number" name="gl_duration" class="small-text" value="%s"> min</td></tr>',
			esc_html__( 'Duration (minutes)', 'glamlux-core' ), esc_attr( $svc->duration_minutes ?? '60' ) );

		// Description
		printf( '<tr><th>%s</th><td><textarea name="gl_description" class="large-text" rows="3">%s</textarea></td></tr>',
			esc_html__( 'Description', 'glamlux-core' ), esc_textarea( $svc->description ?? '' ) );

		echo '</table>';

		// Franchise-Level Price Overrides
		if ( ! empty( $franchises ) && $service_id > 0 ) {
			echo '<h3>' . esc_html__( 'Franchise-Specific Price Overrides', 'glamlux-core' ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'Leave blank to use the base price for that franchise.', 'glamlux-core' ) . '</p>';
			echo '<table class="widefat" style="max-width:480px;">';
			echo '<thead><tr><th>' . esc_html__( 'Franchise', 'glamlux-core' ) . '</th><th>' . esc_html__( 'Custom Price ($)', 'glamlux-core' ) . '</th></tr></thead><tbody>';
			foreach ( $franchises as $fran ) {
				$override_val = $overrides_by_franchise[ $fran->id ] ?? '';
				printf(
					'<tr><td>%s</td><td><input type="number" step="0.01" class="small-text" name="gl_overrides[%d]" value="%s" placeholder="Base price"></td></tr>',
					esc_html( $fran->name ),
					esc_attr( $fran->id ),
					esc_attr( $override_val )
				);
			}
			echo '</tbody></table>';
		}

		submit_button( $service_id > 0 ? __( 'Update Service', 'glamlux-core' ) : __( 'Add Service', 'glamlux-core' ) );
		echo '</form></div>';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Form Handlers
	// ─────────────────────────────────────────────────────────────────────────

	public function handle_save_service() {
		if ( ! check_admin_referer( 'glamlux_save_service', '_glamlux_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'glamlux-core' ) );
		}
		if ( ! current_user_can( 'manage_glamlux_platform' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'glamlux-core' ) );
		}

		global $wpdb;

		$service_id = absint( $_POST['service_id'] ?? 0 );
		$data       = array(
			'service_name'     => sanitize_text_field( $_POST['gl_service_name'] ?? '' ),
			'category'         => sanitize_text_field( $_POST['gl_category'] ?? '' ),
			'base_price'       => floatval( $_POST['gl_base_price'] ?? 0 ),
			'duration_minutes' => absint( $_POST['gl_duration'] ?? 60 ),
			'description'      => sanitize_textarea_field( $_POST['gl_description'] ?? '' ),
			'franchise_id'     => null, // Global service
			'custom_price'     => null,
		);

		if ( $service_id > 0 ) {
			$wpdb->update( "{$wpdb->prefix}gl_service_pricing", $data, array( 'id' => $service_id ) );
		} else {
			$wpdb->insert( "{$wpdb->prefix}gl_service_pricing", $data );
			$service_id = $wpdb->insert_id;
		}

		// Save franchise-specific overrides
		$overrides = $_POST['gl_overrides'] ?? array();
		foreach ( $overrides as $franchise_id => $custom_price ) {
			$franchise_id = absint( $franchise_id );
			$custom_price = strlen( trim( $custom_price ) ) > 0 ? floatval( $custom_price ) : null;

			// Check if override row exists
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}gl_service_pricing WHERE service_id = %d AND franchise_id = %d LIMIT 1",
					$service_id, $franchise_id
				)
			);

			if ( null === $custom_price ) {
				// Remove override if field was cleared
				if ( $existing ) {
					$wpdb->delete( "{$wpdb->prefix}gl_service_pricing", array( 'id' => $existing ), array( '%d' ) );
				}
			} elseif ( $existing ) {
				$wpdb->update( "{$wpdb->prefix}gl_service_pricing", array( 'custom_price' => $custom_price ), array( 'id' => $existing ) );
			} else {
				$wpdb->insert( "{$wpdb->prefix}gl_service_pricing", array(
					'service_id'   => $service_id,
					'franchise_id' => $franchise_id,
					'custom_price' => $custom_price,
				) );
			}
		}

		// Invalidate API cache
		do_action( 'glamlux_service_saved' );

		wp_redirect( admin_url( 'admin.php?page=glamlux-services&saved=1' ) );
		exit;
	}

	public function handle_delete_service() {
		$service_id = absint( $_GET['service_id'] ?? 0 );
		if ( ! check_admin_referer( 'glamlux_delete_service_' . $service_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'glamlux-core' ) );
		}
		if ( ! current_user_can( 'manage_glamlux_platform' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'glamlux-core' ) );
		}

		global $wpdb;
		// Also delete all overrides for this service
		$wpdb->delete( "{$wpdb->prefix}gl_service_pricing", array( 'service_id' => $service_id ), array( '%d' ) );
		$wpdb->delete( "{$wpdb->prefix}gl_service_pricing", array( 'id' => $service_id ), array( '%d' ) );

		do_action( 'glamlux_service_saved' );

		wp_redirect( admin_url( 'admin.php?page=glamlux-services&deleted=1' ) );
		exit;
	}
}
