<?php
/**
 * GlamLux Service Repository (Service Pricing)
 *
 * LAYER: Repository (Data Access Layer)
 * RULE:  The ONLY place where SQL is allowed for service pricing operations.
 */
class GlamLux_Repo_Service
{
    /**
     * Fetch all global services (no franchise_id set)
     */
    public function get_all_global_services(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT sp.*, COUNT(spo.id) AS override_count
             FROM {$wpdb->prefix}gl_service_pricing sp
             LEFT JOIN {$wpdb->prefix}gl_service_pricing spo ON spo.service_id = sp.id AND spo.franchise_id IS NOT NULL
             WHERE sp.franchise_id IS NULL
             GROUP BY sp.id
             ORDER BY sp.service_name ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get a specific service by ID
     */
    public function get_service_by_id(int $id): ?array
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gl_service_pricing WHERE id = %d AND franchise_id IS NULL LIMIT 1",
                $id
            ),
            ARRAY_A
        ) ?: null;
    }

    /**
     * Get all franchises
     */
    public function get_all_franchises(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}gl_franchises ORDER BY name ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get franchise-specific pricing overrides for a service
     */
    public function get_franchise_overrides(int $service_id): array
    {
        global $wpdb;

        $overrides = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gl_service_pricing WHERE service_id = %d AND franchise_id IS NOT NULL",
                $service_id
            ),
            ARRAY_A
        ) ?: [];

        $result = [];
        foreach ($overrides as $o) {
            $result[$o['franchise_id']] = $o['custom_price'];
        }
        return $result;
    }

    /**
     * Insert a new global service
     */
    public function insert_service(array $data): int
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'gl_service_pricing', $data);
        return $wpdb->insert_id;
    }

    /**
     * Update an existing service
     */
    public function update_service(int $id, array $data): bool
    {
        global $wpdb;

        return false !== $wpdb->update(
            $wpdb->prefix . 'gl_service_pricing',
            $data,
            ['id' => $id]
        );
    }

    /**
     * Delete a service
     */
    public function delete_service(int $id): bool
    {
        global $wpdb;

        // Delete all overrides first
        $wpdb->delete($wpdb->prefix . 'gl_service_pricing', ['service_id' => $id]);
        
        // Then delete the global service
        return false !== $wpdb->delete(
            $wpdb->prefix . 'gl_service_pricing',
            ['id' => $id]
        );
    }

    /**
     * Set or update a franchise-specific price override
     */
    public function set_franchise_override(int $service_id, int $franchise_id, ?float $custom_price): bool
    {
        global $wpdb;

        if ($custom_price === null) {
            // Delete the override
            return false !== $wpdb->delete(
                $wpdb->prefix . 'gl_service_pricing',
                ['service_id' => $service_id, 'franchise_id' => $franchise_id]
            );
        }

        // Check if override exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gl_service_pricing WHERE service_id = %d AND franchise_id = %d LIMIT 1",
            $service_id, $franchise_id
        ));

        if ($exists) {
            return false !== $wpdb->update(
                $wpdb->prefix . 'gl_service_pricing',
                ['custom_price' => $custom_price],
                ['service_id' => $service_id, 'franchise_id' => $franchise_id]
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'gl_service_pricing',
                [
                    'service_id' => $service_id,
                    'franchise_id' => $franchise_id,
                    'custom_price' => $custom_price,
                ]
            );
            return $wpdb->insert_id > 0;
        }
    }
}
