<?php
/**
 * GlamLux System Mode Manager
 *
 * LAYER: Middleware / Infrastructure Governance
 * RULE:  This controls the global system mode state (Standard, Demo, Enterprise, Sim).
 *        It MUST NOT contain business logic.
 */
class GlamLux_System_Mode
{

    const OPTION_KEY = 'glamlux_system_mode';

    private static $state = null;

    /**
     * Get the current active state array.
     */
    public static function get_state(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }

        $default_state = [
            'active_mode' => 'standard', // standard | demo | enterprise | simulation
            'expires_at' => 0, // 0 = never
            'last_changed_by' => 0,
        ];

        self::$state = get_option(self::OPTION_KEY, $default_state);

        // Auto-revert if TTL expired
        if (self::$state['expires_at'] > 0 && time() > self::$state['expires_at']) {
            self::revert_to_standard();
        }

        return self::$state;
    }

    /**
     * Update the system mode safely.
     */
    public static function set_mode(string $mode, int $ttl_hours = 0): bool|WP_Error
    {
        $allowed_modes = ['standard', 'demo', 'enterprise', 'simulation'];

        if (!in_array($mode, $allowed_modes, true)) {
            return new WP_Error('invalid_mode', 'Attempted to set an invalid system mode.');
        }

        // Security: Capability Lock
        if (!current_user_can('manage_options') || !current_user_can('edit_plugins')) {
            return new WP_Error('forbidden', 'Insufficient permissions to change system mode.');
        }

        // Security: Environment Guard
        if ($mode !== 'standard' && wp_get_environment_type() === 'production') {
            if (!defined('GLAMLUX_ALLOW_MODE_OVERRIDE') || !GLAMLUX_ALLOW_MODE_OVERRIDE) {
                return new WP_Error('forbidden', 'System overrides are blocked in production environments.');
            }
        }

        $current_state = self::get_state();
        $new_state = [
            'active_mode' => $mode,
            'expires_at' => $ttl_hours > 0 ? time() + ($ttl_hours * 3600) : 0,
            'last_changed_by' => get_current_user_id(),
        ];

        // Log the audit change if the table exists (Phase 4 support)
        self::log_audit($current_state['active_mode'], $mode);

        self::$state = $new_state;
        update_option(self::OPTION_KEY, $new_state);

        return true;
    }

    /**
     * Forced safety fallback to standard mode.
     */
    public static function revert_to_standard(): void
    {
        self::$state = [
            'active_mode' => 'standard',
            'expires_at' => 0,
            'last_changed_by' => 0,
        ];
        update_option(self::OPTION_KEY, self::$state);
    }

    // ─────────────────────────────────────────────────────────────────
    // State Checkers
    // ─────────────────────────────────────────────────────────────────

    public static function is_demo(): bool
    {
        return self::get_state()['active_mode'] === 'demo';
    }

    public static function is_enterprise(): bool
    {
        return self::get_state()['active_mode'] === 'enterprise';
    }

    public static function is_simulation(): bool
    {
        return self::get_state()['active_mode'] === 'simulation';
    }

    // ─────────────────────────────────────────────────────────────────
    // Hooks & Helpers
    // ─────────────────────────────────────────────────────────────────

    public static function init(): void
    {
        // Render sticky admin notice if we are not in standard mode
        add_action('admin_notices', [self::class , 'render_admin_notice']);
    }

    public static function render_admin_notice(): void
    {
        $state = self::get_state();
        if ($state['active_mode'] === 'standard')
            return;

        $labels = [
            'demo' => '🟣 DEMO MODE ACTIVE — Performance simulated. Live crons and emails halted.',
            'enterprise' => '🟢 ENTERPRISE MODE ACTIVE — Advanced telemetry enabled.',
            'simulation' => '🟠 SIMULATION MODE ACTIVE — Infrastructure bounds testing.',
        ];

        $color = [
            'demo' => '#9333EA',
            'enterprise' => '#059669',
            'simulation' => '#EA580C',
        ];

        $msg = $labels[$state['active_mode']] ?? 'UNKNOWN OVERRIDE';
        $bg = $color[$state['active_mode']] ?? '#333';

        echo "<div style='background: {$bg}; color: white; padding: 12px; font-weight: bold; text-align: center; margin: 10px 20px 0 2px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid rgba(255,255,255,0.5);'>
				⚠️ GLAMLUX SYSTEM OVERRIDE: {$msg}
			  </div>";
    }

    private static function log_audit(string $old_mode, string $new_mode): void
    {
        if (class_exists('GlamLux_Repo_System_Mode')) {
            GlamLux_Repo_System_Mode::log_audit(
                get_current_user_id(),
                $old_mode,
                $new_mode,
                sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
            );
        }
    }
}
