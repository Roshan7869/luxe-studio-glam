<?php
/**
 * Message Queue Service for Background Job Processing
 *
 * Supports AWS SQS with database fallback for distributed job processing.
 * Handles email, notifications, PDF generation, and custom background jobs.
 *
 * @package GlamLux
 * @subpackage Services
 * @since 7.3
 */

class GlamLux_Message_Queue
{
    private $queue_type = 'database'; // 'sqs' or 'database'
    private $sqs_client = null;
    private $queue_url = null;

    public function __construct()
    {
        $this->initialize_queue();
    }

    /**
     * Initialize queue (SQS or database)
     */
    private function initialize_queue()
    {
        if (defined('GLAMLUX_AWS_SQS_URL') && GLAMLUX_AWS_SQS_URL) {
            try {
                // Try AWS SDK if available
                if (class_exists('Aws\Sqs\SqsClient')) {
                    $this->sqs_client = new \Aws\Sqs\SqsClient([
                        'version' => 'latest',
                        'region' => defined('GLAMLUX_AWS_REGION') ? GLAMLUX_AWS_REGION : 'us-east-1'
                    ]);
                    $this->queue_url = GLAMLUX_AWS_SQS_URL;
                    $this->queue_type = 'sqs';
                    glamlux_log('AWS SQS queue initialized');
                }
            } catch (Exception $e) {
                glamlux_log_error('AWS SQS initialization failed, using database queue: ' . $e->getMessage());
            }
        }

        if ($this->queue_type === 'database') {
            glamlux_log('Database message queue initialized');
        }
    }

    /**
     * Queue a job for background processing
     */
    public function queue_job($job_type, $payload = [], $priority = 'normal', $delay = 0)
    {
        global $wpdb;

        try {
            $job_id = wp_generate_uuid4();

            if ($this->queue_type === 'sqs') {
                return $this->queue_job_sqs($job_id, $job_type, $payload, $priority, $delay);
            } else {
                return $this->queue_job_database($job_id, $job_type, $payload, $priority, $delay);
            }
        } catch (Exception $e) {
            glamlux_log_error('Job queue error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue job in AWS SQS
     */
    private function queue_job_sqs($job_id, $job_type, $payload, $priority, $delay)
    {
        try {
            $message_body = json_encode([
                'job_id' => $job_id,
                'job_type' => $job_type,
                'payload' => $payload,
                'priority' => $priority,
                'created_at' => current_time('mysql'),
                'retry_count' => 0
            ]);

            $this->sqs_client->sendMessage([
                'QueueUrl' => $this->queue_url,
                'MessageBody' => $message_body,
                'DelaySeconds' => $delay,
                'MessageAttributes' => [
                    'job_type' => [
                        'DataType' => 'String',
                        'StringValue' => $job_type
                    ],
                    'priority' => [
                        'DataType' => 'String',
                        'StringValue' => $priority
                    ]
                ]
            ]);

            glamlux_log("Job queued (SQS): {$job_type}");
            return $job_id;
        } catch (Exception $e) {
            glamlux_log_error("SQS job queue failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue job in database
     */
    private function queue_job_database($job_id, $job_type, $payload, $priority, $delay)
    {
        global $wpdb;

        $priority_value = [
            'critical' => 1,
            'high' => 5,
            'normal' => 10,
            'low' => 20
        ][$priority] ?? 10;

        $execute_at = $delay > 0
            ? date('Y-m-d H:i:s', time() + $delay)
            : current_time('mysql');

        $result = $wpdb->insert(
            $wpdb->prefix . 'gl_job_queue',
            [
                'job_id' => $job_id,
                'job_type' => $job_type,
                'payload' => wp_json_encode($payload),
                'priority' => $priority_value,
                'status' => 'pending',
                'execute_at' => $execute_at,
                'created_at' => current_time('mysql'),
                'retry_count' => 0
            ]
        );

        if (!$result) {
            throw new Exception('Database insert failed');
        }

        glamlux_log("Job queued (DB): {$job_type}");
        return $job_id;
    }

    /**
     * Process queued jobs (called by WP-Cron)
     */
    public static function process_queue($limit = 25)
    {
        global $wpdb;

        try {
            $jobs = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}gl_job_queue
                 WHERE status = 'pending' AND execute_at <= NOW()
                 ORDER BY priority ASC, created_at ASC
                 LIMIT $limit"
            );

            if (empty($jobs)) {
                return ['processed' => 0, 'failed' => 0];
            }

            $processed = 0;
            $failed = 0;

            foreach ($jobs as $job) {
                if (self::execute_job($job)) {
                    $processed++;
                } else {
                    $failed++;
                }
            }

            glamlux_log("Job queue processed: {$processed} succeeded, {$failed} failed");
            return ['processed' => $processed, 'failed' => $failed];
        } catch (Exception $e) {
            glamlux_log_error('Job queue processing error: ' . $e->getMessage());
            return ['processed' => 0, 'failed' => 0];
        }
    }

    /**
     * Execute a single job
     */
    private static function execute_job($job)
    {
        global $wpdb;

        try {
            $payload = json_decode($job->payload, true);

            // Route to appropriate handler
            switch ($job->job_type) {
                case 'send_email':
                    self::handle_send_email($payload);
                    break;
                case 'send_notification':
                    self::handle_send_notification($payload);
                    break;
                case 'generate_pdf':
                    self::handle_generate_pdf($payload);
                    break;
                case 'generate_report':
                    self::handle_generate_report($payload);
                    break;
                default:
                    do_action('glamlux_job_' . $job->job_type, $payload);
            }

            // Mark as processed
            $wpdb->update(
                $wpdb->prefix . 'gl_job_queue',
                [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ],
                ['job_id' => $job->job_id]
            );

            glamlux_log("Job completed: {$job->job_type}");
            return true;
        } catch (Exception $e) {
            // Retry logic
            if ($job->retry_count < 3) {
                $wpdb->update(
                    $wpdb->prefix . 'gl_job_queue',
                    [
                        'retry_count' => $job->retry_count + 1,
                        'status' => 'pending',
                        'execute_at' => date('Y-m-d H:i:s', time() + (300 * pow(2, $job->retry_count))) // Exponential backoff
                    ],
                    ['job_id' => $job->job_id]
                );
            } else {
                $wpdb->update(
                    $wpdb->prefix . 'gl_job_queue',
                    [
                        'status' => 'failed',
                        'error_message' => substr($e->getMessage(), 0, 255),
                        'failed_at' => current_time('mysql')
                    ],
                    ['job_id' => $job->job_id]
                );
            }

            glamlux_log_error("Job failed: {$job->job_type} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle email job
     */
    private static function handle_send_email($payload)
    {
        $to = $payload['to'] ?? null;
        $subject = $payload['subject'] ?? null;
        $message = $payload['message'] ?? null;

        if (!$to || !$subject || !$message) {
            throw new Exception('Missing email parameters');
        }

        wp_mail($to, $subject, $message);
    }

    /**
     * Handle notification job
     */
    private static function handle_send_notification($payload)
    {
        $user_id = $payload['user_id'] ?? null;
        $title = $payload['title'] ?? null;
        $body = $payload['body'] ?? null;

        if (!$user_id || !$title || !$body) {
            throw new Exception('Missing notification parameters');
        }

        require_once GLAMLUX_PLUGIN_DIR . 'services/class-glamlux-firebase-messaging.php';
        $fcm = new GlamLux_Firebase_Messaging();
        $fcm->send_to_user($user_id, $title, $body);
    }

    /**
     * Handle PDF generation job
     */
    private static function handle_generate_pdf($payload)
    {
        $report_id = $payload['report_id'] ?? null;
        $report_type = $payload['report_type'] ?? null;

        if (!$report_id || !$report_type) {
            throw new Exception('Missing PDF parameters');
        }

        // Implement PDF generation logic
        do_action('glamlux_generate_pdf', $report_id, $report_type);
    }

    /**
     * Handle report generation job
     */
    private static function handle_generate_report($payload)
    {
        $user_id = $payload['user_id'] ?? null;
        $report_type = $payload['report_type'] ?? null;

        if (!$user_id || !$report_type) {
            throw new Exception('Missing report parameters');
        }

        // Implement report generation logic
        do_action('glamlux_generate_report', $user_id, $report_type);
    }

    /**
     * Get queue statistics
     */
    public static function get_stats()
    {
        global $wpdb;

        return [
            'pending' => (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gl_job_queue WHERE status = 'pending'"
            ),
            'processing' => (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gl_job_queue WHERE status = 'processing'"
            ),
            'completed' => (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gl_job_queue WHERE status = 'completed'"
            ),
            'failed' => (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}gl_job_queue WHERE status = 'failed'"
            )
        ];
    }

    /**
     * Cleanup old completed/failed jobs
     */
    public static function cleanup_old_jobs($days = 30)
    {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}gl_job_queue
                 WHERE status IN ('completed', 'failed')
                 AND created_at < %s",
                $cutoff_date
            )
        );
    }
}

/**
 * Global message queue instance
 */
function glamlux_queue()
{
    static $queue = null;
    if ($queue === null) {
        $queue = new GlamLux_Message_Queue();
    }
    return $queue;
}
