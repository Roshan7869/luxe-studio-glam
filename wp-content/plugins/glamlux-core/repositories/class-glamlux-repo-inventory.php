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

    /**
     * Concurrency-safe stock deduction.
     * Uses SELECT ... FOR UPDATE to lock the row during the transaction,
     * preventing race conditions from concurrent requests.
     *
     * @return bool|WP_Error True on success, WP_Error if insufficient stock.
     */
    public function deduct(int $inventory_id, int $qty = 1)
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');

        // Lock the row — other transactions wait until we COMMIT
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT quantity, product_name FROM {$wpdb->prefix}gl_inventory WHERE id = %d FOR UPDATE",
            $inventory_id
        ));

        if (!$current) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('not_found', 'Inventory item not found.');
        }

        if ((int)$current->quantity < $qty) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error(
                'insufficient_stock',
                sprintf('Only %d units of "%s" available (requested %d).', $current->quantity, $current->product_name, $qty)
                );
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}gl_inventory SET quantity = quantity - %d WHERE id = %d",
            $qty, $inventory_id
        ));

        $wpdb->query('COMMIT');
        return true;
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

    public function add_item(array $data)
    {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'gl_inventory',
            [
                'salon_id' => absint($data['salon_id']),
                'product_name' => sanitize_text_field($data['product_name']),
                'sku' => sanitize_text_field($data['sku'] ?? ''),
                'category' => sanitize_text_field($data['category'] ?? 'general'),
                'quantity' => absint($data['quantity'] ?? 0),
                'reorder_threshold' => absint($data['reorder_threshold'] ?? 5),
                'unit_cost' => floatval($data['unit_cost'] ?? 0),
                'price_per_unit' => floatval($data['price_per_unit'] ?? 0),
                'last_restocked' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%s']
        );

        if (!$result) {
            return new \WP_Error('db_error', 'Failed to add inventory item.');
        }

        return $wpdb->insert_id;
    }

    public function update_item(int $item_id, array $data): bool
    {
        global $wpdb;
        $update = [];
        $formats = [];

        $allowed = [
            'product_name' => '%s', 'sku' => '%s', 'category' => '%s',
            'quantity' => '%d', 'reorder_threshold' => '%d',
            'unit_cost' => '%f', 'price_per_unit' => '%f',
        ];

        foreach ($allowed as $col => $fmt) {
            if (isset($data[$col])) {
                $update[$col] = in_array($fmt, ['%d']) ? absint($data[$col]) :
                    (in_array($fmt, ['%f']) ? floatval($data[$col]) : sanitize_text_field($data[$col]));
                $formats[] = $fmt;
            }
        }

        if (empty($update))
            return false;

        return false !== $wpdb->update(
            $wpdb->prefix . 'gl_inventory',
            $update,
            ['id' => $item_id],
            $formats,
            ['%d']
        );
    }

    public function delete_item(int $item_id): bool
    {
        global $wpdb;
        return false !== $wpdb->delete(
            $wpdb->prefix . 'gl_inventory',
            ['id' => $item_id],
            ['%d']
        );
    }

    public function get_item_with_salon(int $item_id): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, s.name AS salon_name FROM {$wpdb->prefix}gl_inventory i
             LEFT JOIN {$wpdb->prefix}gl_salons s ON i.salon_id = s.id
             WHERE i.id = %d", $item_id
        ), ARRAY_A) ?: null;
    }

    public function flush_all_cache(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gl_inv_%'");
    }
}
