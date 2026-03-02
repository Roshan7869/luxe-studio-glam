/**
 * GlamLux2Lux — Enterprise k6 Load Test
 *
 * Simulates:
 *   • 50 concurrent bookings   (POST /glamlux/v1/book)
 *   • 100 concurrent REST reads (GET  /glamlux/v1/salons, /services, /health)
 *   • 20 concurrent CRM inserts (POST /glamlux/v1/leads)
 *
 * Thresholds:
 *   • Booking P95 < 800ms
 *   • Read P95   < 300ms
 *   • CRM P95    < 800ms
 *   • Error rate < 1%
 *   • No slow queries > 500ms (enforced via check)
 *
 * Usage:
 *   k6 run --env BASE_URL=http://localhost:80 scripts/k6-load-test.js
 */
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// ── Custom Metrics ──────────────────────────────────────────────────
const bookingDuration = new Trend('booking_duration', true);
const readDuration = new Trend('read_duration', true);
const crmDuration = new Trend('crm_duration', true);
const errorRate = new Rate('custom_errors');

// ── Config ──────────────────────────────────────────────────────────
const BASE_URL = __ENV.BASE_URL || 'https://luxe-studio-glam-production.up.railway.app';
const WP_NONCE = __ENV.WP_NONCE || '';   // Set via --env WP_NONCE=<nonce> for auth'd routes

const HEADERS_JSON = {
    'Content-Type': 'application/json',
};
if (WP_NONCE) {
    HEADERS_JSON['X-WP-Nonce'] = WP_NONCE;
}

// ── Scenarios ───────────────────────────────────────────────────────
export const options = {
    scenarios: {
        // Scenario 1: 100 concurrent REST reads
        rest_reads: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '10s', target: 100 },   // Ramp to 100 VUs
                { duration: '30s', target: 100 },   // Hold at 100 VUs
                { duration: '10s', target: 0 },      // Ramp down
            ],
            exec: 'readScenario',
            tags: { scenario: 'rest_reads' },
        },

        // Scenario 2: 50 concurrent bookings
        concurrent_bookings: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '10s', target: 50 },
                { duration: '30s', target: 50 },
                { duration: '10s', target: 0 },
            ],
            exec: 'bookingScenario',
            tags: { scenario: 'concurrent_bookings' },
            startTime: '5s', // Slight delay so reads start first
        },

        // Scenario 3: 20 concurrent CRM inserts
        crm_inserts: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '10s', target: 20 },
                { duration: '30s', target: 20 },
                { duration: '10s', target: 0 },
            ],
            exec: 'crmScenario',
            tags: { scenario: 'crm_inserts' },
            startTime: '5s',
        },
    },

    thresholds: {
        // Global
        http_req_failed: ['rate<0.01'],

        // Read P95 < 300ms
        'read_duration': ['p(95)<300'],

        // Booking P95 < 800ms
        'booking_duration': ['p(95)<800'],

        // CRM P95 < 800ms
        'crm_duration': ['p(95)<800'],

        // No single request > 500ms (slow query proxy)
        'http_req_duration{scenario:rest_reads}': ['max<500'],
    },
};

// ── Helpers ─────────────────────────────────────────────────────────
function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function randomDate() {
    const d = new Date();
    d.setDate(d.getDate() + randomInt(1, 30));
    const h = randomInt(10, 18);
    const m = Math.random() > 0.5 ? '00' : '30';
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${h}:${m}:00`;
}

// ── Scenario 1: REST Reads (100 VUs) ────────────────────────────────
export function readScenario() {
    group('REST Reads', function () {
        const endpoints = [
            { url: `${BASE_URL}/wp-json/glamlux/v1/salons`, name: 'List Salons' },
            { url: `${BASE_URL}/wp-json/glamlux/v1/services`, name: 'List Services' },
            { url: `${BASE_URL}/wp-json/glamlux/v1/health`, name: 'Health Check' },
            { url: `${BASE_URL}/`, name: 'Homepage' },
        ];

        const target = endpoints[randomInt(0, endpoints.length - 1)];
        const res = http.get(target.url, { tags: { name: target.name } });

        readDuration.add(res.timings.duration);

        const ok = check(res, {
            'status is 200': (r) => r.status === 200,
            'response time < 300ms': (r) => r.timings.duration < 300,
            'no slow query (< 500ms)': (r) => r.timings.duration < 500,
        });
        if (!ok) errorRate.add(1);
        else errorRate.add(0);

        sleep(Math.random() * 1 + 0.5); // 0.5–1.5s
    });
}

// ── Scenario 2: Concurrent Bookings (50 VUs) ────────────────────────
export function bookingScenario() {
    group('Concurrent Bookings', function () {
        const payload = JSON.stringify({
            salon_id: randomInt(1, 5),
            service_id: randomInt(1, 10),
            appointment_time: randomDate(),
            notes: `k6 load test booking VU-${__VU} iter-${__ITER}`,
        });

        const res = http.post(
            `${BASE_URL}/wp-json/glamlux/v1/book`,
            payload,
            {
                headers: HEADERS_JSON,
                tags: { name: 'Create Booking' },
            }
        );

        bookingDuration.add(res.timings.duration);

        const ok = check(res, {
            // 200 = success, 401 = auth required (expected w/o nonce), 409 = slot taken (expected under load)
            'booking accepted or expected rejection': (r) =>
                r.status === 200 || r.status === 401 || r.status === 409,
            'booking P95 < 800ms': (r) => r.timings.duration < 800,
        });
        if (!ok) errorRate.add(1);
        else errorRate.add(0);

        sleep(Math.random() * 2 + 1); // 1–3s
    });
}

// ── Scenario 3: CRM Lead Inserts (20 VUs) ───────────────────────────
export function crmScenario() {
    group('CRM Inserts', function () {
        const payload = JSON.stringify({
            name: `Load Test User ${__VU}-${__ITER}`,
            email: `loadtest_${__VU}_${__ITER}_${Date.now()}@k6test.dev`,
            phone: `+91${randomInt(7000000000, 9999999999)}`,
            state: ['Maharashtra', 'Karnataka', 'Delhi', 'Tamil Nadu', 'Gujarat'][randomInt(0, 4)],
            interest_type: 'franchise',
            message: `k6 load test CRM insert VU-${__VU} iter-${__ITER}`,
            source: 'k6_load_test',
        });

        const res = http.post(
            `${BASE_URL}/wp-json/glamlux/v1/leads`,
            payload,
            {
                headers: HEADERS_JSON,
                tags: { name: 'Capture Lead' },
            }
        );

        crmDuration.add(res.timings.duration);

        const ok = check(res, {
            // 200 = success, 201 = created, 409 = duplicate (expected)
            'CRM insert accepted or dup': (r) =>
                r.status === 200 || r.status === 201 || r.status === 409,
            'CRM P95 < 800ms': (r) => r.timings.duration < 800,
        });
        if (!ok) errorRate.add(1);
        else errorRate.add(0);

        sleep(Math.random() * 2 + 1); // 1–3s
    });
}
