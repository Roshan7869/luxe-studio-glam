<?php
/**
 * GlamLux Service Service (Service Pricing & Catalogue)
 *
 * LAYER: Business Logic (Service Layer)
 * RULE:  Zero SQL. All data access delegates to GlamLux_Repo_Service.
 */
class GlamLux_Service_Service
{
    private GlamLux_Repo_Service $repo;

    public function __construct(GlamLux_Repo_Service $repo = null)
    {
        $this->repo = $repo ?: new GlamLux_Repo_Service();
    }

    /**
     * Get all global services with override count
     */
    public function get_all(): array
    {
        return $this->repo->get_all_global_services();
    }

    /**
     * Get a specific service by ID
     */
    public function get_by_id(int $id): ?array
    {
        return $this->repo->get_service_by_id($id);
    }

    /**
     * Get all franchises for pricing override UI
     */
    public function get_franchises(): array
    {
        return $this->repo->get_all_franchises();
    }

    /**
     * Get franchise-specific overrides for a service
     */
    public function get_overrides(int $service_id): array
    {
        return $this->repo->get_franchise_overrides($service_id);
    }

    /**
     * Create a new global service
     */
    public function create(array $data): int|WP_Error
    {
        $required = ['service_name', 'base_price', 'duration_minutes'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf('Field "%s" is required.', $field), ['status' => 400]);
            }
        }

        $insert = [
            'service_name' => sanitize_text_field($data['service_name']),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'base_price' => floatval($data['base_price']),
            'duration_minutes' => intval($data['duration_minutes']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
        ];

        $id = $this->repo->insert_service($insert);
        if (!$id) {
            return new WP_Error('db_error', 'Failed to create service.', ['status' => 500]);
        }

        return $id;
    }

    /**
     * Update an existing service
     */
    public function update(int $id, array $data): bool|WP_Error
    {
        $service = $this->repo->get_service_by_id($id);
        if (!$service) {
            return new WP_Error('not_found', 'Service not found.', ['status' => 404]);
        }

        $update = [];
        if (!empty($data['service_name'])) {
            $update['service_name'] = sanitize_text_field($data['service_name']);
        }
        if (isset($data['category'])) {
            $update['category'] = sanitize_text_field($data['category']);
        }
        if (isset($data['base_price'])) {
            $update['base_price'] = floatval($data['base_price']);
        }
        if (isset($data['duration_minutes'])) {
            $update['duration_minutes'] = intval($data['duration_minutes']);
        }
        if (isset($data['description'])) {
            $update['description'] = sanitize_textarea_field($data['description']);
        }

        if (empty($update)) {
            return new WP_Error('no_data', 'No valid fields provided.', ['status' => 400]);
        }

        return $this->repo->update_service($id, $update);
    }

    /**
     * Delete a service and all its overrides
     */
    public function delete(int $id): bool|WP_Error
    {
        $service = $this->repo->get_service_by_id($id);
        if (!$service) {
            return new WP_Error('not_found', 'Service not found.', ['status' => 404]);
        }

        return $this->repo->delete_service($id);
    }

    /**
     * Update franchise-specific price overrides for a service
     */
    public function update_franchise_overrides(int $service_id, array $overrides): bool|WP_Error
    {
        $service = $this->repo->get_service_by_id($service_id);
        if (!$service) {
            return new WP_Error('not_found', 'Service not found.', ['status' => 404]);
        }

        foreach ($overrides as $franchise_id => $custom_price) {
            $price = !empty($custom_price) ? floatval($custom_price) : null;
            if (!$this->repo->set_franchise_override($service_id, intval($franchise_id), $price)) {
                return new WP_Error('db_error', 'Failed to update franchise override.', ['status' => 500]);
            }
        }

        return true;
    }
}
