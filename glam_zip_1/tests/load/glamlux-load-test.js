/**
 * ══════════════════════════════════════════════════════════════════════════════
 * GlamLux2Lux — k6 Load Test Suite
 * Phase 6: Validation & Benchmarking Framework
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * SCENARIOS:
 *   1. scenario_booking     — 100 concurrent bookings stress test
 *   2. scenario_api_reads   — 500 REST API reads/minute
 *   3. scenario_dashboard   — Admin dashboard KPI page under load
 *
 * TARGETS:
 *   Booking P95:  < 800ms
 *   REST read P95: < 300ms
 *   Error rate:   < 1%
 *   Deadlocks:    0
 *
 * USAGE:
 *   npm install -g k6         (or use Docker: docker run grafana/k6)
 *   k6 run tests/load/glamlux-load-test.js
 *   k6 run --out json=results/k6-report.json tests/load/glamlux-load-test.js
 *
 * AGAINST LOCAL DOCKER:
 *   k6 run --env BASE_URL=http://localhost:8888 tests/load/glamlux-load-test.js
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// ─── Custom Metrics ───────────────────────────────────────────────────────────
const bookingErrors = new Rate('booking_error_rate');
const bookingDuration = new Trend('booking_duration_ms', true);
const apiReadDuration = new Trend('api_read_duration_ms', true);
const deadlockCount = new Counter('deadlock_count');

// ─── Config ───────────────────────────────────────────────────────────────────
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8888';
const WP_NONCE = __ENV.WP_NONCE || 'test_nonce_replace_me';   // wp_create_nonce('glamlux_booking_nonce')
const AUTH_COOKIE = __ENV.AUTH_COOKIE || '';                        // Optional: logged-in session cookie

// ─── Test Options ─────────────────────────────────────────────────────────────
export const options = {
    scenarios: {

        /**
         * SCENARIO 1: 100 concurrent bookings surge
         * Simulates flash-sale / event booking spike
         */
        scenario_booking: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '20s', target: 20 },   // Ramp up
                { duration: '30s', target: 100 },   // Peak: 100 concurrent
                { duration: '20s', target: 0 },   // Ramp down
            ],
            gracefulRampDown: '10s',
            exec: 'bookingScenario',
            tags: { scenario: 'booking_concurrency' },
        },

        /**
         * SCENARIO 2: 500 REST API reads/minute
         * Simulates dashboard polling, salon listings, service catalog
         */
        scenario_api_reads: {
            executor: 'constant-arrival-rate',
            rate: 500,
            timeUnit: '1m',
            duration: '2m',
            preAllocatedVUs: 30,
            maxVUs: 80,
            exec: 'apiReadScenario',
            tags: { scenario: 'api_reads' },
            startTime: '30s',   // Let booking scenario start first
        },

        /**
         * SCENARIO 3: Admin dashboard pressure
         * Ensures KPI cache works under concurrent admin users
         */
        scenario_dashboard: {
            executor: 'constant-vus',
            vus: 5,
            duration: '1m',
            exec: 'dashboardScenario',
            tags: { scenario: 'admin_dashboard' },
            startTime: '10s',
        },

    },

    thresholds: {
        // ── Critical Thresholds (fail build if exceeded) ───────────────────────
        'booking_duration_ms{scenario:booking_concurrency}': ['p(95)<800'],   // 95% bookings < 800ms
        'api_read_duration_ms{scenario:api_reads}': ['p(95)<300'],   // 95% reads   < 300ms
        'booking_error_rate': ['rate<0.01'],    // < 1% error rate
        'deadlock_count': ['count==0'],     // Zero deadlocks allowed

        // ── Warning Thresholds ─────────────────────────────────────────────────
        'http_req_failed': ['rate<0.02'],    // < 2% HTTP failures
        'http_req_duration{scenario:api_reads}': ['p(50)<150'],    // Median read < 150ms
    },
};

// ─── Scenario 1: Concurrent Booking ──────────────────────────────────────────
export function bookingScenario() {
    group('Booking Flow', function () {
        const payload = {
            action: 'glamlux_book_appointment',
            nonce: WP_NONCE,
            salon_id: String(Math.floor(Math.random() * 5) + 1),
            service_id: String(Math.floor(Math.random() * 10) + 1),
            appointment_time: getRandomFutureSlot(),
            notes: 'Load test booking',
        };

        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded',
            ...(AUTH_COOKIE ? { 'Cookie': AUTH_COOKIE } : {}),
        };

        const start = Date.now();
        const res = http.post(`${BASE_URL}/wp-admin/admin-ajax.php`, payload, { headers, tags: { name: 'AJAX_Booking' } });
        const duration = Date.now() - start;

        bookingDuration.add(duration);

        const success = check(res, {
            'status 200': (r) => r.status === 200,
            'no WP error': (r) => !r.body.includes('"success":false'),
            'booking_id returned': (r) => r.body.includes('appointment_id'),
            'no PHP fatal': (r) => !r.body.includes('Fatal error'),
            'response < 800ms': (r) => duration < 800,
        });

        bookingErrors.add(!success);

        // Detect deadlock in response
        if (res.body.includes('Deadlock') || res.body.includes('Lock wait timeout')) {
            deadlockCount.add(1);
            console.error(`Deadlock detected! VU ${__VU}, iter ${__ITER}`);
        }

        sleep(Math.random() * 0.5 + 0.1); // 100–600ms think time
    });
}

// ─── Scenario 2: API Read Flood ───────────────────────────────────────────────
export function apiReadScenario() {
    const endpoints = [
        '/wp-json/glamlux/v1/salons',
        '/wp-json/glamlux/v1/services',
        '/wp-json/glamlux/v1/salons/1',
        '/wp-json/glamlux/v1/reports/kpi',
        '/wp-json/glamlux/v1/staff',
        '/wp-json/glamlux/v1/bookings/mine',
    ];

    const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];

    group('REST API Reads', function () {
        const headers = {
            'Accept': 'application/json',
            ...(AUTH_COOKIE ? { 'Cookie': AUTH_COOKIE } : {}),
        };

        const start = Date.now();
        const res = http.get(`${BASE_URL}${endpoint}`, { headers, tags: { name: 'REST_Read' } });
        const duration = Date.now() - start;

        apiReadDuration.add(duration);

        check(res, {
            'status 200 or 401': (r) => [200, 401, 403].includes(r.status),
            'JSON content-type': (r) => (r.headers['Content-Type'] || '').includes('application/json'),
            'response < 300ms': (r) => duration < 300,
            'no PHP warning': (r) => !r.body.includes('<b>Warning</b>'),
        });

        // Verify REST Cache headers are present in Demo Mode
        check(res, {
            'cache-control present': (r) => r.headers['Cache-Control'] !== undefined,
        });

        sleep(0.1);
    });
}

// ─── Scenario 3: Admin Dashboard ─────────────────────────────────────────────
export function dashboardScenario() {
    group('Admin Dashboard', function () {
        // Admin panel requires auth — skip if no cookie provided
        if (!AUTH_COOKIE) {
            sleep(1);
            return;
        }

        const start = Date.now();
        const res = http.get(`${BASE_URL}/wp-admin/admin.php?page=glamlux-reporting`, {
            headers: { 'Cookie': AUTH_COOKIE },
            tags: { name: 'Admin_Dashboard' },
        });
        const duration = Date.now() - start;

        check(res, {
            'admin page loads': (r) => r.status === 200,
            'KPI cards rendered': (r) => r.body.includes('Total Revenue'),
            'no fatal error': (r) => !r.body.includes('Fatal error'),
            'dashboard < 1000ms': (r) => duration < 1000,
        });

        sleep(2); // Admin users poll slowly
    });
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Generate a random future appointment time (today + 1 to 30 days, 9:00–18:00).
 */
function getRandomFutureSlot() {
    const daysAhead = Math.floor(Math.random() * 30) + 1;
    const hour = Math.floor(Math.random() * 9) + 9;  // 09:00–17:00
    const minute = [0, 30][Math.floor(Math.random() * 2)];

    const d = new Date();
    d.setDate(d.getDate() + daysAhead);
    d.setHours(hour, minute, 0, 0);

    return d.toISOString().slice(0, 16).replace('T', ' ');
}
