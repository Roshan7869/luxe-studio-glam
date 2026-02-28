<?php
class GlamLux_Webhook_Handler
{
    private $gateways = [];
    private $dispatcher;
    public function __construct($d = null)
    {
        $this->dispatcher = $d;
    }
    public function get_gateway_id()
    {
        return "";
    }
    public function register_gateway($gw)
    {
        $this->gateways[$gw->get_gateway_id()] = $gw;
    }
    public function handle($gid, $raw, $sig, $sec)
    {
        if (!isset($this->gateways[$gid])) {
            status_header(400);
            exit("Unknown gateway");
        }
        if (!$this->gateways[$gid]->verify_webhook($raw, $sig, $sec)) {
            status_header(403);
            exit("Bad sig");
        }
        $ev = json_decode($raw, true);
        $et = $ev["event"] ?? $ev["type"] ?? "";
        $txn_id = $ev['id'] ?? $ev['data']['id'] ?? $ev['payload']['payment']['entity']['id'] ?? uniqid('tx_');

        // Phase 4: Idempotency Enforcement (prevent duplicate processing)
        global $wpdb;
        $table = $wpdb->prefix . 'gl_webhook_events';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE gateway = %s AND transaction_id = %s", $gid, $txn_id));
            if ($exists) {
                status_header(200);
                exit("Duplicate generic webhook ignored");
            }
            $wpdb->insert($table, [
                'gateway' => $gid,
                'transaction_id' => $txn_id,
                'event_type' => $et,
                'payload' => $raw,
                'created_at' => current_time('mysql')
            ]);
        }

        if (str_contains($et, "payment.captured") || str_contains($et, "charge.succeeded")) {
            $p = ["appointment_id" => $ev["payload"]["payment"]["entity"]["notes"]["appointment_id"] ?? 0, "amount" => $ev["payload"]["payment"]["entity"]["amount"] ?? 0];
            if ($this->dispatcher)
                $this->dispatcher->dispatch("payment_completed", $p);
            do_action("glamlux_event_payment_completed", $p);
        }
        status_header(200);
        echo "OK";
        exit;
    }
}
