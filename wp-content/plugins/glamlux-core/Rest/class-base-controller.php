<?php
class GlamLux_Base_Controller {
	public function require_logged_in() {
		if (!is_user_logged_in()) return new WP_Error('glamlux_unauthorized', 'You must be logged in.', ['status'=>401]);
		return true;
	}
	public function require_client_role() {
		if (!is_user_logged_in()) return new WP_Error('glamlux_unauthorized', 'Authentication required.', ['status'=>401]);
		$allowed = ['glamlux_client', 'glamlux_staff', 'glamlux_franchise_admin', 'glamlux_super_admin', 'administrator'];
		$user = wp_get_current_user();
		foreach ($allowed as $role) { if (in_array($role, (array)$user->roles, true)) return true; }
		return new WP_Error('glamlux_forbidden', 'Permission denied.', ['status'=>403]);
	}
	public function require_staff_or_admin() {
		if (!is_user_logged_in()) return new WP_Error('glamlux_unauthorized', 'Authentication required.', ['status'=>401]);
		if (current_user_can('glamlux_manage_appointments') || current_user_can('manage_options')) return true;
		return new WP_Error('glamlux_forbidden', 'Insufficient permissions.', ['status'=>403]);
	}
	public function require_franchise_admin() {
		return current_user_can('manage_options') || current_user_can('glamlux_state_manager') || current_user_can('glamlux_franchise_admin');
	}
	public function require_salon_manager() {
		return current_user_can('manage_options') || current_user_can('glamlux_state_manager') || current_user_can('glamlux_franchise_admin') || current_user_can('glamlux_salon_manager');
	}
}