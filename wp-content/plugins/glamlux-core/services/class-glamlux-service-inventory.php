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

        return $this->repo->add_item($data);
    }

    /**
     * Update an existing inventory item.
     */
    public function update_item(int $item_id, array $data): bool
    {
        return $this->repo->update_item($item_id, $data);
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

        if (is_wp_error($result)) {
            return false;
        }

        // Trigger low stock check after deduction
        $item = $this->repo->get_item_with_salon($item_id);

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
        return $this->repo->delete_item($item_id);
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
