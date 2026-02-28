<?php
class GlamLux_Service_Membership {
	private $repo;
	public function __construct(GlamLux_Repo_Membership $repo = null) { $this->repo = $repo ?: new GlamLux_Repo_Membership(); }
	public function grant($cid, $mid, $src="manual", $oid=0) {
		$tier = $this->repo->get_tier($mid); if (!$tier) return false;
		$expiry = date("Y-m-d H:i:s", strtotime("+".((int)$tier->duration_months)." months"));
		$this->repo->update_client_membership($cid, $mid, $expiry);
		$this->repo->insert_purchase(["client_id"=>$cid,"membership_id"=>$mid,"source"=>$src,"wc_order_id"=>$oid,"granted_at"=>current_time("mysql"),"expires_at"=>$expiry,"status"=>"active"]);
		do_action("glamlux_membership_granted", $cid, $mid, $expiry); return true;
	}
	public function revoke($cid) { return $this->repo->update_client_membership($cid, null, null); }
	public function renew($cid, $mid, $src="manual", $oid=0) {
		$tier = $this->repo->get_tier($mid); if (!$tier) return false;
		$cl = $this->repo->get_client($cid);
		$base = ($cl && $cl->membership_expiry && strtotime($cl->membership_expiry) > time()) ? strtotime($cl->membership_expiry) : time();
		$expiry = date("Y-m-d H:i:s", strtotime("+".((int)$tier->duration_months)." months", $base));
		$this->repo->update_client_membership($cid, $mid, $expiry);
		$this->repo->insert_purchase(["client_id"=>$cid,"membership_id"=>$mid,"source"=>$src,"wc_order_id"=>$oid,"granted_at"=>current_time("mysql"),"expires_at"=>$expiry,"status"=>"renewed"]);
		return true;
	}
	public function process_expired() {
		$expired = $this->repo->get_expired_clients();
		foreach ($expired as $c) {
			$this->repo->update_client_membership($c->cid, null, null);
			if ($c->user_email) wp_mail($c->user_email, "GlamLux Membership Expired", "Dear ".$c->display_name.", your membership expired.");
		}
		return count($expired);
	}
	public function send_renewal_reminders($days=5) {
		$target = date("Y-m-d", strtotime("+$days days"));
		$rows = $this->repo->get_expiring_clients($target);
		$cnt = 0;
		foreach ($rows as $r) {
			if ($r->user_email) { wp_mail($r->user_email,"GlamLux ".$r->tname." expires in $days day(s)", "Renew!"); $cnt++; }
		}
		return $cnt;
	}
	public function get_tier($id) { return $this->repo->get_tier($id); }
	public function get_all_tiers() { return $this->repo->get_all_tiers(); }
	public function create_tier($d) {
		return $this->repo->insert_tier(["name"=>sanitize_text_field($d["name"]),"description"=>sanitize_textarea_field($d["description"]??""),"price"=>(float)($d["price"]??0),"duration_months"=>(int)($d["duration_months"]??12),"discount_percent"=>(float)($d["discount_percent"]??0),"wc_product_id"=>(int)($d["wc_product_id"]??0),"is_active"=>1]);
	}
	public function update_tier($id,$d) {
		return $this->repo->update_tier($id, ["name"=>sanitize_text_field($d["name"]),"description"=>sanitize_textarea_field($d["description"]??""),"price"=>(float)($d["price"]??0),"duration_months"=>(int)($d["duration_months"]??12),"discount_percent"=>(float)($d["discount_percent"]??0),"wc_product_id"=>(int)($d["wc_product_id"]??0),"is_active"=>(int)($d["is_active"]??1)]);
	}
	public function delete_tier($id) { if ($this->repo->has_members($id)) return false; return $this->repo->delete_tier($id); }
	public function get_revenue_by_tier($f,$t) { return $this->repo->get_revenue($f,$t); }
	public function get_active_member_count() { return $this->repo->get_active_count(); }
	public function register_wc_hooks() { if (!class_exists("WooCommerce")) return; add_action("woocommerce_order_status_completed", [$this,"on_wc_order_completed"]); }
	public function on_wc_order_completed($oid) {
		$order=wc_get_order($oid); if(!$order) return;
		foreach ($order->get_items() as $item) {
			$tier = $this->repo->get_tier_by_wc_product($item->get_product_id()); if (!$tier) continue;
			$cl = $this->repo->get_client_by_user_id($order->get_customer_id()); if ($cl) $this->grant($cl->id, $tier->id, "woocommerce", $oid);
		}
	}
}