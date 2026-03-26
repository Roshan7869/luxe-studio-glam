<?php
/**
 * GlamLux Security Headers Manager
 * 
 * Implements comprehensive security headers for production environments:
 * - HTTPS Strict Transport Security (HSTS)
 * - Content Security Policy (CSP)
 * - X-Frame-Options (Clickjacking protection)
 * - X-Content-Type-Options (MIME sniffing prevention)
 * - X-XSS-Protection (Legacy XSS protection)
 * - Referrer-Policy
 * - Permissions-Policy
 * 
 * PHASE 0: Security Hardening
 */

class GlamLux_Security_Headers {
    
    /**
     * Initialize security headers
     */
    public static function init() {
        add_action('send_headers', [__CLASS__, 'add_security_headers']);
        add_action('rest_api_init', [__CLASS__, 'register_csp_report_endpoint']);
    }
    
    /**
     * Add security headers to all responses
     */
    public static function add_security_headers() {
        // Only enforce in production
        if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'production') {
            return;
        }
        
        // Prevent MIME type sniffing
        // Tells browsers: "trust the Content-Type header, don't guess the MIME type"
        header('X-Content-Type-Options: nosniff');
        
        // Clickjacking protection
        // Prevents page from being embedded in iframes on other domains
        header('X-Frame-Options: SAMEORIGIN');
        
        // Legacy XSS protection (modern browsers use CSP instead)
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy: send referrer only for same-origin, cross-origin gets none
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions policy (formerly Feature-Policy)
        // Disable unused APIs: geolocation, microphone, camera, payment
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        
        // Content Security Policy (CSP)
        self::add_content_security_policy();
        
        // Note: HSTS header is already set in wp-config-railway.php
    }
    
    /**
     * Content Security Policy (CSP) Implementation
     * 
     * CSP prevents XSS attacks by restricting where scripts/styles/fonts can load from
     */
    private static function add_content_security_policy() {
        $home_url = home_url();
        $site_url = site_url();
        
        // Build CSP directives
        $csp_directives = [
            // Default source: only from same origin
            "default-src 'self'",
            
            // Scripts: self, unsafe-inline (WordPress uses inline scripts), Firebase, Google Tag Manager
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' " .
            "https://cdn.firebase.com " .
            "https://www.googletagmanager.com " .
            "https://www.google-analytics.com",
            
            // Styles: self, unsafe-inline (WordPress uses inline styles), Google Fonts
            "style-src 'self' 'unsafe-inline' " .
            "https://fonts.googleapis.com",
            
            // Fonts: self, Google Fonts
            "font-src 'self' " .
            "https://fonts.gstatic.com " .
            "https://fonts.googleapis.com",
            
            // Images: self, data URIs, any https domain
            "img-src 'self' data: https: blob:",
            
            // Media (audio/video): self only
            "media-src 'self'",
            
            // Connect (AJAX, WebSocket, fetch): self + external APIs
            "connect-src 'self' " .
            "https://fcm.googleapis.com " .
            "https://api.exotel.com " .
            "https://www.google-analytics.com",
            
            // Form submissions: self only
            "form-action 'self'",
            
            // Frame ancestors: prevent embedding in iframes
            "frame-ancestors 'none'",
            
            // CSP violation reporting endpoint
            "report-uri /wp-json/glamlux/v1/csp-report",
        ];
        
        // In development, add some debugging directives
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Allow more lenient policies for debugging
            $csp_directives[] = "script-src-elem 'unsafe-inline'";
        }
        
        // Combine directives
        $csp = implode('; ', $csp_directives);
        
        // Set CSP header (report-only for testing, enforcement for production)
        // Report-only mode: violations reported but not enforced
        header('Content-Security-Policy-Report-Only: ' . $csp);
        
        // In production, also enforce the CSP header
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
            header('Content-Security-Policy: ' . $csp);
        }
    }
    
    /**
     * Register CSP violation reporting endpoint
     * 
     * CSP violations are reported here for monitoring and analysis
     */
    public static function register_csp_report_endpoint() {
        register_rest_route('glamlux/v1', '/csp-report', [
            'methods' => ['POST', 'OPTIONS'],
            'callback' => [__CLASS__, 'handle_csp_report'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Handle incoming CSP violation reports
     * 
     * Logs CSP violations for security monitoring
     */
    public static function handle_csp_report($request) {
        $body = $request->get_body();
        $data = json_decode($body, true);
        
        if (empty($data['csp-report'])) {
            return new WP_REST_Response(['status' => 'invalid'], 400);
        }
        
        $report = $data['csp-report'];
        
        // Log CSP violation
        $violation_log = [
            'document_uri' => $report['document-uri'] ?? 'unknown',
            'violated_directive' => $report['violated-directive'] ?? 'unknown',
            'effective_directive' => $report['effective-directive'] ?? 'unknown',
            'original_policy' => $report['original-policy'] ?? 'unknown',
            'blocked_uri' => $report['blocked-uri'] ?? 'unknown',
            'source_file' => $report['source-file'] ?? 'unknown',
            'line_number' => $report['line-number'] ?? 'unknown',
            'column_number' => $report['column-number'] ?? 'unknown',
            'status_code' => $report['status-code'] ?? 'unknown',
            'disposition' => $report['disposition'] ?? 'unknown',
        ];
        
        // Log to error log
        glamlux_log_error('CSP Violation', $violation_log);
        
        // Could also send to external monitoring service (e.g., Sentry)
        if (function_exists('sentry_captureMessage')) {
            \Sentry\captureMessage(
                'CSP Violation: ' . $report['violated-directive'] ?? 'unknown',
                'warning',
                ['extra' => $violation_log]
            );
        }
        
        return new WP_REST_Response(['status' => 'received'], 204);
    }
}

// Initialize security headers
GlamLux_Security_Headers::init();
