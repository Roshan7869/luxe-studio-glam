<?php
/**
 * Async Notification Dispatcher — Sprint A
 *
 * Queues email/SMS jobs for background processing via WP-Cron instead of
 * blocking the main HTTP request. Each job is stored as a transient.
 *
 * LAYER: Infrastructure
 * RULE:  Never call wp_mail() or Exotel directly from event listeners.
 */
class GlamLux_Async_Dispatcher
{
    const QUEUE_OPTION = 'glamlux_notification_queue';
    const CRON_HOOK = 'glamlux_process_notification_queue';

    /**
     * Enqueue a notification job for background processing.
     *
     * @param string $type   'email' or 'sms'
     * @param array  $data   Job payload (to, subject, body, phone, message, etc.)
     */
    public static function enqueue(string $type, array $data): void
    {
        $job = [
            'id' => wp_generate_uuid4(),
            'type' => $type,
            'data' => $data,
            'created_at' => current_time('mysql'),
            'attempts' => 0,
        ];

        $queue = get_option(self::QUEUE_OPTION, []);
        $queue[] = $job;
        update_option(self::QUEUE_OPTION, $queue, false); // autoload = false

        // Ensure cron is scheduled (idempotent)
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'every_minute', self::CRON_HOOK);
        }
    }

    /**
     * Enqueue an email notification.
     */
    public static function enqueue_email(string $to, string $subject, string $body, array $headers = []): void
    {
        if (empty($headers)) {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
        }
        self::enqueue('email', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'headers' => $headers,
        ]);
    }

    /**
     * Enqueue an SMS notification.
     */
    public static function enqueue_sms(string $phone, string $message): void
    {
        if (empty($phone))
            return;
        self::enqueue('sms', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }

    /**
     * Enqueue a push notification (FCM format).
     */
    public static function enqueue_push(int $user_id, string $title, string $body, array $data = []): void
    {
        if (empty($user_id))
            return;

        self::enqueue('push', [
            'user_id' => $user_id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    /**
     * Process all queued notifications (called by WP-Cron).
     * Max 20 jobs per run to prevent timeout.
     */
    public static function process_queue(): void
    {
        $queue = get_option(self::QUEUE_OPTION, []);
        if (empty($queue))
            return;

        $max_per_run = 20;
        $processed = 0;
        $failed = [];

        foreach ($queue as $job) {
            if ($processed >= $max_per_run) {
                $failed[] = $job; // re-queue for next run
                continue;
            }

            try {
                if ($job['type'] === 'email') {
                    $result = wp_mail(
                        $job['data']['to'],
                        $job['data']['subject'],
                        $job['data']['body'],
                        $job['data']['headers'] ?? []
                    );
                    if (!$result) {
                        throw new \RuntimeException('wp_mail returned false');
                    }
                }
                elseif ($job['type'] === 'sms') {
                    require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-exotel-api.php';
                    $sms = new GlamLux_Exotel_API();
                    $sms->send_sms($job['data']['phone'], $job['data']['message']);
                }
                elseif ($job['type'] === 'push') {
                    require_once GLAMLUX_PLUGIN_DIR . 'includes/class-glamlux-fcm-api.php';
                    $fcm = new GlamLux_FCM_API();
                    $device_token = get_user_meta($job['data']['user_id'], 'glamlux_fcm_token', true);
                    if ($device_token) {
                        $fcm->send_push($device_token, $job['data']['title'], $job['data']['body'], $job['data']['data']);
                    }
                }
                $processed++;
            }
            catch (\Throwable $e) {
                $job['attempts'] = ($job['attempts'] ?? 0) + 1;
                if ($job['attempts'] < 3) {
                    $failed[] = $job; // retry up to 3 times
                }
                glamlux_log_error('Async dispatch failed', [
                    'job_id' => $job['id'],
                    'type' => $job['type'],
                    'attempt' => $job['attempts'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Save remaining/failed jobs back to queue
        update_option(self::QUEUE_OPTION, $failed, false);

        if ($processed > 0) {
            $count_failed = count($failed);
            glamlux_log_error("Async dispatcher: processed {$processed} jobs, {$count_failed} remaining", [
                'processed' => $processed,
                'remaining' => $count_failed,
            ]);
        }
    }
}
