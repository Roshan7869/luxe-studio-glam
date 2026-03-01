<?php
class GlamLux_Event_Dispatcher
{
    private $listeners = [];
    private $core_map = [];
    public function register_core_listeners()
    {
        $this->core_map = [
            'appointment_completed' => [['GlamLux_Service_Commission', 'handle_appointment_completed'], ['GlamLux_Service_Revenue', 'handle_appointment_completed']],
            'payment_completed' => [['GlamLux_Service_Commission', 'handle_appointment_completed'], ['GlamLux_Service_Revenue', 'handle_payment_completed'], ['GlamLux_Event_Listeners', 'on_payment_captured']], // Map webhook to our captured listener
            'appointment_created' => [['GlamLux_Event_Listeners', 'on_appointment_created']],
            'membership_granted' => [['GlamLux_Event_Listeners', 'on_membership_granted']],
            'payment_captured' => [['GlamLux_Event_Listeners', 'on_payment_captured']],
            'low_inventory_alert' => [['GlamLux_Event_Listeners', 'on_low_inventory']],
        ];
    }
    public function dispatch($event, $payload = [])
    {
        $all = array_merge($this->listeners[$event] ?? [], $this->core_map[$event] ?? []);
        foreach ($all as $cb) {
            try {
                if (is_array($cb) && is_string($cb[0]) && class_exists($cb[0]))
                    call_user_func([$cb[0], $cb[1]], $payload);
                elseif (is_callable($cb))
                    call_user_func($cb, $payload);
            }
            catch (Throwable $e) {
                error_log("[EventDispatcher] " . $e->getMessage());
            }
        }
        do_action('glamlux_event_' . $event, $payload);
    }
    public function on($event, $listener)
    {
        $this->listeners[$event][] = $listener;
    }
}
