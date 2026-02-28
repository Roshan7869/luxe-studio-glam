<?php
/**
 * Tie WooCommerce payments to GlamLux systems.
 */
class GlamLux_WC_Hooks {
	/**
	 * @var GlamLux_Service_Membership
	 */
	private $membership_service;

	public function __construct( GlamLux_Service_Membership $membership_service = null ) {
		$this->membership_service = $membership_service ?: new GlamLux_Service_Membership();
		add_action( 'woocommerce_order_status_completed', array( $this, 'process_order' ) );
	}

	public function process_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$membership_granted = $this->membership_service->handle_wc_completed_order( $order );
		if ( $membership_granted ) {
			require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-exotel-api.php';
			$exotel = new GlamLux_Exotel_API();
			$exotel->send_sms( $order->get_billing_phone(), 'Welcome to GlamLux2Lux! Your membership is active.' );
		}
	}
}
