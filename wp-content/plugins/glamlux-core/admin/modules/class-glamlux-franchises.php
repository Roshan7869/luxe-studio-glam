<?php
/**
 * GlamLux Franchise Management Module
 *
 * Full WP_List_Table–powered CRUD admin interface for managing franchises.
 *
 * Phase 13 Deliverables:
 * - List all franchises with sortable columns
 * - Add / Edit / Delete franchise records
 * - Assign franchise admin (wp_user_id)
 * - Set territory state
 * - Toggle central price override
 * - get_franchise_revenue() wired to real SQL
 */
class GlamLux_Franchises {

	/** @var GlamLux_Reporting */
	private $reporting;

	public function __construct() {
		global $glamlux_reporting;
		$this->reporting = $glamlux_reporting instanceof GlamLux_Reporting
			? $glamlux_reporting
			: new GlamLux_Reporting();

		add_action( 'admin_post_glamlux_save_franchise',   array( $this, 'handle_save_franchise' ) );
		add_action( 'admin_post_glamlux_delete_franchise', array( $this, 'handle_delete_franchise' ) );
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
			$this->render_edit_form( absint( $_GET['franchise_id'] ?? 0 ) );
		} elseif ( 'add' === $action ) {
			$this->render_edit_form( 0 );
		} else {
			$this->render_list_table();
		}
	}

	/**
	 * Render the franchise list table.
	 */
	private function render_list_table() {
		global $wpdb;

		$franchises = $wpdb->get_results(
			"SELECT f.*, u.display_name AS manager_name
			 FROM {$wpdb->prefix}gl_franchises f
			 LEFT JOIN {$wpdb->users} u ON f.admin_id = u.ID
			 ORDER BY f.created_at DESC"
		);

		$add_url = admin_url( 'admin.php?page=glamlux-franchises&gl_action=add' );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Manage Franchises', 'glamlux-core' ) . '</h1>';
		echo '<a href="' . esc_url( $add_url ) . '" class="page-title-action">' . esc_html__( 'Add New', 'glamlux-core' ) . '</a>';
		echo '<hr class="wp-header-end">';

		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Franchise saved successfully.', 'glamlux-core' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Franchise deleted.', 'glamlux-core' ) . '</p></div>';
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>
			<th>' . esc_html__( 'ID', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Name', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Territory', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Manager', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Revenue', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Created', 'glamlux-core' ) . '</th>
			<th>' . esc_html__( 'Actions', 'glamlux-core' ) . '</th>
		</tr></thead>';
		echo '<tbody>';

		if ( empty( $franchises ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No franchises registered yet.', 'glamlux-core' ) . '</td></tr>';
		} else {
			foreach ( $franchises as $f ) {
				$revenue  = $this->reporting->get_franchise_revenue( $f->id );
				$edit_url = admin_url( 'admin.php?page=glamlux-franchises&gl_action=edit&franchise_id=' . $f->id );
				$del_url  = wp_nonce_url(
					admin_url( 'admin-post.php?action=glamlux_delete_franchise&franchise_id=' . $f->id ),
					'glamlux_delete_franchise_' . $f->id
				);
				printf(
					'<tr>
						<td>%d</td>
						<td><strong>%s</strong></td>
						<td>%s</td>
						<td>%s</td>
						<td>$%s</td>
						<td>%s</td>
						<td>
							<a href="%s">%s</a> |
							<a href="%s" onclick="return confirm(\'Are you sure?\');" style="color:#d63638;">%s</a>
						</td>
					</tr>',
					esc_html( $f->id ),
					esc_html( $f->name ),
					esc_html( $f->territory_state ?: '—' ),
					esc_html( $f->manager_name ?: '(unassigned)' ),
					esc_html( number_format( $revenue, 2 ) ),
					esc_html( $f->created_at ),
					esc_url( $edit_url ), esc_html__( 'Edit', 'glamlux-core' ),
					esc_url( $del_url ),  esc_html__( 'Delete', 'glamlux-core' )
				);
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render the Add/Edit form.
	 *
	 * @param int $franchise_id  0 = new, >0 = edit existing.
	 */
	private function render_edit_form( $franchise_id ) {
		global $wpdb;

		$franchise = null;
		if ( $franchise_id > 0 ) {
			$franchise = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gl_franchises WHERE id = %d LIMIT 1", $franchise_id )
			);
		}

		// Gather all WP users who could be assigned as managers
		$managers = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );

		// Gather Indian states for territory dropdown
		$states = array( 'Maharashtra', 'Delhi', 'Karnataka', 'Tamil Nadu', 'West Bengal',
			'Telangana', 'Gujarat', 'Rajasthan', 'Uttar Pradesh', 'Punjab',
			'Haryana', 'Kerala', 'Andhra Pradesh', 'Madhya Pradesh', 'Bihar' );

		$form_action = admin_url( 'admin-post.php' );
		$page_title  = $franchise_id > 0 ? __( 'Edit Franchise', 'glamlux-core' ) : __( 'Add New Franchise', 'glamlux-core' );
		$back_url    = admin_url( 'admin.php?page=glamlux-franchises' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( $page_title ) . '</h1>';
		echo '<a href="' . esc_url( $back_url ) . '">← ' . esc_html__( 'Back to list', 'glamlux-core' ) . '</a>';
		echo '<hr class="wp-header-end">';

		echo '<form method="POST" action="' . esc_url( $form_action ) . '" style="max-width:640px;margin-top:20px;">';
		wp_nonce_field( 'glamlux_save_franchise', '_glamlux_nonce' );
		echo '<input type="hidden" name="action" value="glamlux_save_franchise">';
		echo '<input type="hidden" name="franchise_id" value="' . esc_attr( $franchise_id ) . '">';

		echo '<table class="form-table">';

		// Name
		printf(
			'<tr><th><label for="gl_fname">%s</label></th>
			 <td><input type="text" id="gl_fname" name="gl_name" class="regular-text" required value="%s"></td></tr>',
			esc_html__( 'Franchise Name', 'glamlux-core' ),
			esc_attr( $franchise->name ?? '' )
		);

		// Location
		printf(
			'<tr><th><label for="gl_floc">%s</label></th>
			 <td><input type="text" id="gl_floc" name="gl_location" class="regular-text" value="%s"></td></tr>',
			esc_html__( 'Address / City', 'glamlux-core' ),
			esc_attr( $franchise->location ?? '' )
		);

		// Territory State
		echo '<tr><th><label for="gl_state">' . esc_html__( 'Territory State', 'glamlux-core' ) . '</label></th><td>';
		echo '<select id="gl_state" name="gl_territory_state" class="regular-text">';
		echo '<option value="">' . esc_html__( '— Select State —', 'glamlux-core' ) . '</option>';
		foreach ( $states as $state ) {
			$selected = ( ( $franchise->territory_state ?? '' ) === $state ) ? 'selected' : '';
			printf( '<option value="%s" %s>%s</option>', esc_attr( $state ), esc_attr( $selected ), esc_html( $state ) );
		}
		echo '</select></td></tr>';

		// Franchise Admin (Manager)
		echo '<tr><th><label for="gl_mgr">' . esc_html__( 'Franchise Manager (User)', 'glamlux-core' ) . '</label></th><td>';
		echo '<select id="gl_mgr" name="gl_admin_id" class="regular-text">';
		echo '<option value="0">' . esc_html__( '— Unassigned —', 'glamlux-core' ) . '</option>';
		foreach ( $managers as $mgr ) {
			$selected = ( (int) ( $franchise->admin_id ?? 0 ) === (int) $mgr->ID ) ? 'selected' : '';
			printf( '<option value="%d" %s>%s (#%d)</option>', esc_attr( $mgr->ID ), esc_attr( $selected ), esc_html( $mgr->display_name ), esc_attr( $mgr->ID ) );
		}
		echo '</select></td></tr>';

		// Central Price Override
		printf(
			'<tr><th><label for="gl_override">%s</label></th>
			 <td><input type="number" id="gl_override" name="gl_central_price_override" step="0.01" class="small-text" placeholder="Leave blank for none" value="%s">
			 <p class="description">%s</p></td></tr>',
			esc_html__( 'Central Price Override ($)', 'glamlux-core' ),
			esc_attr( $franchise->central_price_override ?? '' ),
			esc_html__( 'If set, this price overrides all service prices for this franchise.', 'glamlux-core' )
		);

		echo '</table>';
		submit_button( $franchise_id > 0 ? __( 'Update Franchise', 'glamlux-core' ) : __( 'Add Franchise', 'glamlux-core' ) );
		echo '</form></div>';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Form Handlers (admin-post)
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Save (Create or Update) a franchise record.
	 */
	public function handle_save_franchise() {
		if ( ! check_admin_referer( 'glamlux_save_franchise', '_glamlux_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'glamlux-core' ) );
		}
		if ( ! current_user_can( 'manage_glamlux_platform' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'glamlux-core' ) );
		}

		global $wpdb;

		$franchise_id = absint( $_POST['franchise_id'] ?? 0 );
		$data         = array(
			'name'                   => sanitize_text_field( $_POST['gl_name'] ?? '' ),
			'location'               => sanitize_text_field( $_POST['gl_location'] ?? '' ),
			'territory_state'        => sanitize_text_field( $_POST['gl_territory_state'] ?? '' ),
			'admin_id'               => absint( $_POST['gl_admin_id'] ?? 0 ),
			'central_price_override' => ! empty( $_POST['gl_central_price_override'] ) ? floatval( $_POST['gl_central_price_override'] ) : null,
		);

		if ( $franchise_id > 0 ) {
			$wpdb->update( "{$wpdb->prefix}gl_franchises", $data, array( 'id' => $franchise_id ) );
		} else {
			$wpdb->insert( "{$wpdb->prefix}gl_franchises", $data );
		}

		// Fire cache invalidation
		do_action( 'glamlux_salon_saved' );

		wp_redirect( admin_url( 'admin.php?page=glamlux-franchises&saved=1' ) );
		exit;
	}

	/**
	 * Delete a franchise record.
	 */
	public function handle_delete_franchise() {
		$franchise_id = absint( $_GET['franchise_id'] ?? 0 );

		if ( ! check_admin_referer( 'glamlux_delete_franchise_' . $franchise_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'glamlux-core' ) );
		}
		if ( ! current_user_can( 'manage_glamlux_platform' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'glamlux-core' ) );
		}

		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}gl_franchises", array( 'id' => $franchise_id ), array( '%d' ) );

		wp_redirect( admin_url( 'admin.php?page=glamlux-franchises&deleted=1' ) );
		exit;
	}

	/**
	 * Proxy method used by the Admin class display callback.
	 */
	public function get_franchise_revenue( $franchise_id ) {
		return $this->reporting->get_franchise_revenue( $franchise_id );
	}
}
