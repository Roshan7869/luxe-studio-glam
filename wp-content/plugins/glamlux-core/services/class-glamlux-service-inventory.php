<?php
class GlamLux_Service_Inventory
{
    private $repo;

    public function __construct(GlamLux_Repo_Inventory $repo = null)
    {
        $this->repo = $repo ?: new GlamLux_Repo_Inventory();
    }

    public function check_low_stock()
    {
        $low_stock = $this->repo->get_low_stock_items();

        if (empty($low_stock)) {
            return 0;
        }

        foreach ($low_stock as $item) {
            do_action('glamlux_low_inventory_alert', $item);
        }

        return count($low_stock);
    }
}
