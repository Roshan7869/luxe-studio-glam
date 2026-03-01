<?php
/**
 * Inventory Repository
 */
class GlamLux_Repo_Inventory
{

    public function get_by_salon(int $salon_id, bool $low_stock_only = false): array
    {
        global $wpdb;
        $where = $wpdb->prepare('salon_id = %d', $salon_id);
        if ($low_stock_only) {
            $where .= ' AND quantity <= reorder_threshold';
        }
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gl_inventory WHERE {$where} ORDER BY product_name ASC",
            ARRAY_A
        ) ?: array();
    }

    public function get_low_stock_alerts(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT i.*, s.name AS salon_name
			 FROM {$wpdb->prefix}gl_inventory i
			 INNER JOIN {$wpdb->prefix}gl_salons s ON i.salon_id = s.id
			 WHERE i.quantity <= i.reorder_threshold
			 ORDER BY i.quantity ASC",
            ARRAY_A
        ) ?: array();
    }

    public function deduct(int $inventory_id, int $qty = 1): bool
    {
        global $wpdb;
        return false !== $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}gl_inventory
			 SET quantity = GREATEST(quantity - %d, 0)
			 WHERE id = %d",
            $qty, $inventory_id
        ));
    }

    public function restock(int $inventory_id, int $qty, float $unit_cost = 0.00): bool
    {
        global $wpdb;
        return false !== $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}gl_inventory
			 SET quantity = quantity + %d,
			     unit_cost = CASE WHEN %f > 0 THEN %f ELSE unit_cost END,
			     last_restocked = %s
			 WHERE id = %d",
            $qty, $unit_cost, $unit_cost, current_time('mysql'), $inventory_id
        ));
    }

    public function get_list(array $filters = array()): array
    {
        global $wpdb;
        $where = '1=1';
        $args = array();
        if (!empty($filters['salon_id'])) {
            $where .= ' AND i.salon_id = %d';
            $args[] = (int)$filters['salon_id'];
        }
        if (!empty($filters['category'])) {
            $where .= ' AND i.category = %s';
            $args[] = $filters['category'];
        }
        $limit = (int)($filters['limit'] ?? 50);
        $offset = (int)($filters['offset'] ?? 0);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, s.name AS salon_name
			 FROM {$wpdb->prefix}gl_inventory i
			 LEFT JOIN {$wpdb->prefix}gl_salons s ON i.salon_id = s.id
			 WHERE {$where} ORDER BY i.product_name ASC LIMIT %d OFFSET %d",
            ...array_merge($args, array($limit, $offset))
        ), ARRAY_A) ?: array();
    }

    public function flush_all_cache(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gl_inv_%'");
    }
}
