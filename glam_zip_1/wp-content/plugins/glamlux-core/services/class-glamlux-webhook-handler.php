<?php

/**
 * Handles incoming payment webhook events from Razorpay and Stripe.
 * Class dependency is intentionally un-type-hinted to avoid Fatal Errors
 * if the autoloader resolves the Repo class after this file is parsed.
 */
class GlamLux_Webhook_Handler
{
    private $gateways = [];
    private $dispatcher;
    private $repo;

    public function __construct($d = null, $repo = null)
    {
        $this->dispatcher = $d;
        if ($repo !== null) {
            $this->repo = $repo;
        }
        elseif (class_exists('GlamLux_Repo_Webhook')) {
            $this->repo = new GlamLux_Repo_Webhook();
        }
        else {
            $this->repo = null;
        }
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
        if (!$this->repo->log_webhook_event($gid, $txn_id, $et, $raw)) {
            status_header(200);
            exit("Duplicate generic webhook ignored");
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
