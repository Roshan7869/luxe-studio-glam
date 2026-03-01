<?php
class GlamLux_Service_Membership {
	private $repo;

	public function __construct( GlamLux_Repo_Membership $repo = null ) {
		$this->repo = $repo ?: new GlamLux_Repo_Membership();
	}

	public function grant( $cid, $mid, $src = 'manual', $oid = 0 ) {
		$tier = $this->repo->get_tier( $mid );
		if ( ! $tier ) {
			return false;
		}

		if ( 'woocommerce' === $src && $oid > 0 && $this->repo->has_purchase_for_order( $cid, $mid, $oid ) ) {
			return false;
		}

		$expiry = date( 'Y-m-d H:i:s', strtotime( '+' . ( (int) $tier->duration_months ) . ' months' ) );
		$this->repo->update_client_membership( $cid, $mid, $expiry );
		$this->repo->insert_purchase(
			array(
				'client_id'     => $cid,
				'membership_id' => $mid,
				'source'        => $src,
				'wc_order_id'   => $oid,
				'granted_at'    => current_time( 'mysql' ),
				'expires_at'    => $expiry,
				'status'        => 'active',
			)
		);
		do_action( 'glamlux_membership_granted', $cid, $mid, $expiry );

		return true;
	}

	public function revoke( $cid ) {
		return $this->repo->update_client_membership( $cid, null, null );
	}

	public function renew( $cid, $mid, $src = 'manual', $oid = 0 ) {
		$tier = $this->repo->get_tier( $mid );
		if ( ! $tier ) {
			return false;
		}

		$cl     = $this->repo->get_client( $cid );
		$base   = ( $cl && $cl->membership_expiry && strtotime( $cl->membership_expiry ) > time() ) ? strtotime( $cl->membership_expiry ) : time();
		$expiry = date( 'Y-m-d H:i:s', strtotime( '+' . ( (int) $tier->duration_months ) . ' months', $base ) );
		$this->repo->update_client_membership( $cid, $mid, $expiry );
		$this->repo->insert_purchase(
			array(
				'client_id'     => $cid,
				'membership_id' => $mid,
				'source'        => $src,
				'wc_order_id'   => $oid,
				'granted_at'    => current_time( 'mysql' ),
				'expires_at'    => $expiry,
				'status'        => 'renewed',
			)
		);

		return true;
	}

	public function process_expired() {
		$expired = $this->repo->get_expired_clients();
		foreach ( $expired as $c ) {
			$this->repo->update_client_membership( $c->cid, null, null );
			if ( $c->user_email ) {
				wp_mail( $c->user_email, 'GlamLux Membership Expired', 'Dear ' . $c->display_name . ', your membership expired.' );
			}
		}

		return count( $expired );
	}

	public function send_renewal_reminders( $days = 5 ) {
		$target = date( 'Y-m-d', strtotime( "+$days days" ) );
		$rows   = $this->repo->get_expiring_clients( $target );
		$cnt    = 0;
		foreach ( $rows as $r ) {
			if ( $r->user_email ) {
				wp_mail( $r->user_email, "GlamLux {$r->tname} expires in $days day(s)", 'Renew!' );
				++$cnt;
			}
		}

		return $cnt;
	}

	public function get_tier( $id ) {
		return $this->repo->get_tier( $id );
	}

	public function get_all_tiers() {
		return $this->repo->get_all_tiers();
	}

	public function create_tier( $d ) {
		return $this->repo->insert_tier(
			array(
				'name'             => sanitize_text_field( $d['name'] ),
				'description'      => sanitize_textarea_field( $d['description'] ?? '' ),
				'price'            => (float) ( $d['price'] ?? 0 ),
				'duration_months'  => (int) ( $d['duration_months'] ?? 12 ),
				'discount_percent' => (float) ( $d['discount_percent'] ?? 0 ),
				'wc_product_id'    => (int) ( $d['wc_product_id'] ?? 0 ),
				'is_active'        => 1,
			)
		);
	}

	public function update_tier( $id, $d ) {
		return $this->repo->update_tier(
			$id,
			array(
				'name'             => sanitize_text_field( $d['name'] ),
				'description'      => sanitize_textarea_field( $d['description'] ?? '' ),
				'price'            => (float) ( $d['price'] ?? 0 ),
				'duration_months'  => (int) ( $d['duration_months'] ?? 12 ),
				'discount_percent' => (float) ( $d['discount_percent'] ?? 0 ),
				'wc_product_id'    => (int) ( $d['wc_product_id'] ?? 0 ),
				'is_active'        => (int) ( $d['is_active'] ?? 1 ),
			)
		);
	}

	public function delete_tier( $id ) {
		if ( $this->repo->has_members( $id ) ) {
			return false;
		}

		return $this->repo->delete_tier( $id );
	}

	public function get_revenue_by_tier( $f, $t ) {
		return $this->repo->get_revenue( $f, $t );
	}

	public function get_active_member_count() {
		return $this->repo->get_active_count();
	}

	/**
	 * @deprecated Membership WC registration now lives in GlamLux_WC_Hooks.
	 */
	public function register_wc_hooks() {
		_doing_it_wrong( __METHOD__, 'WooCommerce hooks are registered via GlamLux_WC_Hooks.', '3.0.1' );
	}

	/**
	 * Backward-compatible wrapper for prior service hook callbacks.
	 */
	public function on_wc_order_completed( $oid ) {
		$this->handle_wc_completed_order( $oid );
	}

	/**
	 * Canonical WooCommerce membership grant + purchase logging path.
	 *
	 * @param int|WC_Order $order_or_id Order id or order object.
	 *
	 * @return bool True when one or more memberships were granted.
	 */
	public function handle_wc_completed_order( $order_or_id ) {
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
		if ( ! $order ) {
			return false;
		}

		$order_id = (int) $order->get_id();
		if ( ! $this->acquire_order_lock( $order_id ) ) {
			return false;
		}

		try {
			if ( $this->is_order_processed( $order_id ) ) {
				return false;
			}

			$client = $this->repo->get_client_by_user_id( $order->get_customer_id() );
			if ( ! $client ) {
				return false;
			}

			$granted = false;
			foreach ( $order->get_items() as $item ) {
				$tier = $this->repo->get_tier_by_wc_product( $item->get_product_id() );
				if ( ! $tier ) {
					continue;
				}

				if ( $this->grant( $client->id, $tier->id, 'woocommerce', $order_id ) ) {
					$granted = true;
				}
			}

			if ( $granted ) {
				$this->mark_order_processed( $order_id );
			}

			return $granted;
		} finally {
			$this->release_order_lock( $order_id );
		}
	}

	private function acquire_order_lock( $order_id ) {
		return add_post_meta( $order_id, '_glamlux_membership_processing_lock', time(), true );
	}

	private function release_order_lock( $order_id ) {
		delete_post_meta( $order_id, '_glamlux_membership_processing_lock' );
	}

	private function is_order_processed( $order_id ) {
		return 'yes' === get_post_meta( $order_id, '_glamlux_membership_processed', true );
	}

	private function mark_order_processed( $order_id ) {
		update_post_meta( $order_id, '_glamlux_membership_processed', 'yes' );
	}
}
