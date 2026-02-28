<?php

class GlamLux_Operations_Controller extends GlamLux_Base_Controller {

	public function register_routes() {
		register_rest_route(
			'glamlux/v1',
			'/operations/summary',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_summary' ),
				'permission_callback' => array( $this, 'can_view_operations' ),
			)
		);
	}

	public function can_view_operations() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'glamlux_unauthorized', 'Authentication required.', array( 'status' => 401 ) );
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_glamlux_platform' ) || current_user_can( 'manage_glamlux_franchise' ) ) {
			return true;
		}

		return new WP_Error( 'glamlux_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
	}

	public function get_summary() {
		global $glamlux_operations_service;

		if ( ! is_object( $glamlux_operations_service ) ) {
			$glamlux_operations_service = new GlamLux_Service_Operations();
		}

		return rest_ensure_response( $glamlux_operations_service->get_operations_summary() );
	}
}
