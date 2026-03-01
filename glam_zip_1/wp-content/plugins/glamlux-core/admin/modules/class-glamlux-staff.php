<?php
/**
 * Handles CRUD for Staff members and commission setups.
 */
class GlamLux_Staff {

	/**
	 * Main output for the WP Admin "Staff" page under a Franchise Admin
	 */
	public function render_admin_page() {
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">Manage Staff</h1>';
		echo '<a href="#" class="page-title-action">Invite Staff Member</a>';
		echo '<hr class="wp-header-end">';
		
		echo '<div class="notice notice-info"><p>This module provides management for <strong>wp_gl_staff</strong>, including setting commission rates.</p></div>';

		// Placeholder for WP_List_Table
		echo '<table class="wp-list-table widefat fixed striped table-view-list">';
		echo '<thead><tr><th>Staff Name</th><th>Salon Location</th><th>Commission Rate (%)</th><th>Specializations</th><th>Actions</th></tr></thead>';
		echo '<tbody><tr><td colspan="5">No staff members found. <a href="#">Add a staff member.</a></td></tr></tbody>';
		echo '</table>';
		echo '</div>';
	}
}
