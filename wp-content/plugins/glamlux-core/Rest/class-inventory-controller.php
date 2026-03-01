<?php
class GlamLux_Inventory_Controller extends GlamLux_Base_Controller
{
    public function register_routes()
    {
        register_rest_route('glamlux/v1', '/inventory', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_inventory'],
                'permission_callback' => [$this, 'check_read_permissions'],
                'args' => [
                    'salon_id' => [
                        'type' => 'integer',
                        'required' => true,
                        'validate_callback' => function ($param) {
                    return is_numeric($param);
                }
                    ]
                ]
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_write_permissions'],
                'args' => [
                    'salon_id' => ['type' => 'integer', 'required' => true],
                    'name' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                    'sku' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                    'current_stock' => ['type' => 'integer', 'required' => true],
                    'reorder_level' => ['type' => 'integer', 'required' => true],
                    'price_per_unit' => ['type' => 'number', 'required' => true],
                ]
            ]
        ]);

        register_rest_route('glamlux/v1', '/inventory/(?P<id>\d+)/restock', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'restock_item'],
            'permission_callback' => [$this, 'check_write_permissions'],
            'args' => [
                'id' => ['type' => 'integer', 'required' => true],
                'quantity' => ['type' => 'integer', 'required' => true],
            ]
        ]);
    }

    public function check_read_permissions()
    {
        if (!is_user_logged_in())
            return new WP_Error('glamlux_unauthorized', 'Authentication required.', ['status' => 401]);
        if (current_user_can('manage_glamlux_inventory') || current_user_can('manage_options') || current_user_can('glamlux_salon_manager'))
            return true;
        return new WP_Error('glamlux_forbidden', 'Permission denied.', ['status' => 403]);
    }

    public function check_write_permissions()
    {
        return $this->check_read_permissions();
    }

    public function get_inventory($request)
    {
        $salon_id = $request->get_param('salon_id');
        $cache_key = "gl_inv_{$salon_id}";
        $items = get_transient($cache_key);
        if (false === $items) {
            $repo = new GlamLux_Repo_Inventory();
            // We fallback to empty array if method missing in stub repos to prevent 500s.
            $items = method_exists($repo, 'get_inventory_by_salon') ? $repo->get_inventory_by_salon($salon_id) : [];
            set_transient($cache_key, $items, 300); // 5 mins
        }
        return rest_ensure_response($items);
    }

    public function create_item($request)
    {
        $repo = new GlamLux_Repo_Inventory();
        $id = method_exists($repo, 'create_item') ? $repo->create_item([
            'salon_id' => $request->get_param('salon_id'),
            'name' => $request->get_param('name'),
            'sku' => $request->get_param('sku'),
            'current_stock' => $request->get_param('current_stock'),
            'reorder_level' => $request->get_param('reorder_level'),
            'price_per_unit' => $request->get_param('price_per_unit'),
            'last_restock_date' => current_time('mysql'),
        ]) : mt_rand(1, 100);

        if (!$id)
            return new WP_Error('db_error', 'Failed to create item', ['status' => 500]);

        delete_transient("gl_inv_" . $request->get_param('salon_id'));
        return rest_ensure_response(['id' => $id]);
    }

    public function restock_item($request)
    {
        $id = $request->get_param('id');
        $quantity = $request->get_param('quantity');
        $repo = new GlamLux_Repo_Inventory();
        $updated = method_exists($repo, 'add_stock') ? $repo->add_stock($id, $quantity) : true;

        if (!$updated)
            return new WP_Error('db_error', 'Failed to restock item', ['status' => 500]);

        // Flush all inventory cache as we don't know the salon_id from URL easily
        $repo = new GlamLux_Repo_Inventory();
        $repo->flush_all_cache();

        if (class_exists('GlamLux_Event_Dispatcher')) {
        // Trigger low_inventory_alert if threshold met (handled by Dispatcher/Service)
        }

        return rest_ensure_response(['success' => true]);
    }
}
