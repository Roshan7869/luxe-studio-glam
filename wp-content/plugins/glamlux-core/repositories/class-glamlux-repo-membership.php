<?php
class GlamLux_Repo_Membership {
	public function get_tier($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_memberships WHERE id=%d", $id)) ?: null; }
	public function get_all_tiers() { global $wpdb; return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gl_memberships ORDER BY price ASC", ARRAY_A) ?: []; }
	public function get_tier_by_wc_product($pid) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_memberships WHERE wc_product_id=%d LIMIT 1", $pid)); }
	public function get_client($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_clients WHERE id=%d", $id)); }
	public function get_client_by_user_id($uid) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}gl_clients WHERE wp_user_id=%d LIMIT 1", $uid)); }
	public function update_client_membership($id, $mid, $expiry) { global $wpdb; return false !== $wpdb->update($wpdb->prefix."gl_clients",["membership_id"=>$mid,"membership_expiry"=>$expiry],["id"=>$id]); }
	public function insert_purchase($data) { global $wpdb; return $wpdb->insert($wpdb->prefix."gl_membership_purchases", $data, ["%d","%d","%s","%d","%s","%s","%s"]); }
	public function get_expired_clients() { global $wpdb; return $wpdb->get_results("SELECT c.id AS cid,u.user_email,u.display_name FROM {$wpdb->prefix}gl_clients c LEFT JOIN {$wpdb->users} u ON c.wp_user_id=u.ID WHERE c.membership_id IS NOT NULL AND c.membership_expiry IS NOT NULL AND c.membership_expiry<NOW()"); }
	public function get_expiring_clients($target_date) { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT c.id,u.user_email,u.display_name,m.name AS tname FROM {$wpdb->prefix}gl_clients c LEFT JOIN {$wpdb->users} u ON c.wp_user_id=u.ID LEFT JOIN {$wpdb->prefix}gl_memberships m ON c.membership_id=m.id WHERE DATE(c.membership_expiry)=%s", $target_date)); }
	public function insert_tier($d) { global $wpdb; $r = $wpdb->insert($wpdb->prefix."gl_memberships",$d); return $r ? $wpdb->insert_id : false; }
	public function update_tier($id, $d) { global $wpdb; return false !== $wpdb->update($wpdb->prefix."gl_memberships",$d,["id"=>$id]); }
	public function has_members($tier_id) { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}gl_clients WHERE membership_id=%d", $tier_id)) > 0; }
	public function delete_tier($id) { global $wpdb; return false !== $wpdb->delete($wpdb->prefix."gl_memberships",["id"=>$id]); }
	public function get_revenue($f,$t) { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT m.id AS membership_id,m.name AS tier_name,m.price,COUNT(p.id) AS sales_count,SUM(m.price) AS total_revenue FROM {$wpdb->prefix}gl_membership_purchases p INNER JOIN {$wpdb->prefix}gl_memberships m ON p.membership_id=m.id WHERE DATE(p.granted_at) BETWEEN %s AND %s GROUP BY m.id ORDER BY total_revenue DESC",$f,$t),ARRAY_A)?:[]; }
	public function get_active_count() { global $wpdb; return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gl_clients WHERE membership_id IS NOT NULL AND membership_expiry>NOW()"); }
}