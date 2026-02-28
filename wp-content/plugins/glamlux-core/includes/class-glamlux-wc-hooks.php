<?php
/**
 * Tie WooCommerce payments to GlamLux systems.
 */
class GlamLux_WC_Hooks {

	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'process_order' ) );
	}

	public function process_order( $order_id ) {
		$order = wc_get_order( $order_id );
		$user_id = $order->get_user_id();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();
			
			// Detect if product is a membership
			$is_membership = get_post_meta( $product_id, '_glamlux_is_membership', true );
			if ( $is_membership ) {
				$membership_tier = get_post_meta( $product_id, '_glamlux_membership_tier', true );
				
				// Apply membership to client
				require_once GLAMLUX_PLUGIN_DIR . 'admin/modules/class-glamlux-memberships.php';
				$memberships = new GlamLux_Memberships();
				$memberships->grant_membership( $user_id, $membership_tier );

				// Send SMS
				require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-exotel-api.php';
				$exotel = new GlamLux_Exotel_API();
				$exotel->send_sms( $order->get_billing_phone(), "Welcome to GlamLux2Lux! Your membership is active." );
			}
		}
	}
}
