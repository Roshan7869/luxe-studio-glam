<?php
/**
 * Inventory Service — Sprint 4 (Full CRUD)
 *
 * Wraps GlamLux_Repo_Inventory with validation, sanitization, and event dispatch.
 */
class GlamLux_Service_Inventory
{
    private $repo;

    public function __construct(GlamLux_Repo_Inventory $repo = null)
    {
        $this->repo = $repo ?: new GlamLux_Repo_Inventory();
    }

    /**
     * Get all inventory items for a salon.
     */
    public function get_items(int $salon_id, bool $low_stock_only = false): array
    {
        return $this->repo->get_by_salon($salon_id, $low_stock_only);
    }

    /**
     * Add a new inventory item for a salon.
     */
    public function add_item(array $data)
    {
        $required = ['salon_id', 'product_name'];
        foreach ($required as $key) {
            if (empty($data[$key])) {
                return new \WP_Error('missing_field', "Missing required field: {$key}");
            }
        }

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

    /**
     * Update an existing inventory item.
     */
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

    /**
     * Restock an item by adding quantity.
     */
    public function restock(int $item_id, int $qty, float $unit_cost = 0.00): bool
    {
        if ($qty <= 0)
            return false;
        return $this->repo->restock($item_id, $qty, $unit_cost);
    }

    /**
     * Deduct stock from an item.
     */
    public function deduct(int $item_id, int $qty = 1): bool
    {
        if ($qty <= 0)
            return false;
        $result = $this->repo->deduct($item_id, $qty);

        // Trigger low stock check after deduction
        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, s.name AS salon_name FROM {$wpdb->prefix}gl_inventory i
             LEFT JOIN {$wpdb->prefix}gl_salons s ON i.salon_id = s.id
             WHERE i.id = %d", $item_id
        ), ARRAY_A);

        if ($item && (int)$item['quantity'] <= (int)$item['reorder_threshold']) {
            do_action('glamlux_low_inventory_alert', $item);
        }

        return $result;
    }

    /**
     * Delete an inventory item.
     */
    public function delete_item(int $item_id): bool
    {
        global $wpdb;
        return false !== $wpdb->delete(
            $wpdb->prefix . 'gl_inventory',
        ['id' => $item_id],
        ['%d']
        );
    }

    /**
     * Check and alert on low stock items.
     */
    public function check_low_stock()
    {
        $low_stock = $this->repo->get_low_stock_alerts();

        if (empty($low_stock)) {
            return 0;
        }

        foreach ($low_stock as $item) {
            do_action('glamlux_low_inventory_alert', $item);
        }

        return count($low_stock);
    }
}
