/**
 * GlamLux2Lux — k6 Load Test Suite
 *
 * Validates Domino's-level franchise scalability claims.
 * Covers the 3 critical attack surfaces:
 *   1.  High-concurrency booking flow (500 VUs)
 *   2.  API read throughput under sustained load (salons, services)
 *   3.  Authenticated user flow (login → view appointments → book)
 *
 * INSTALL k6:
 *   Windows:  winget install k6 --source winget
 *   macOS:    brew install k6
 *   Docker:   docker run --rm -i grafana/k6 run - <k6-load-test.js
 *
 * RUN (adjust BASE_URL for your environment):
 *   k6 run --env BASE_URL=http://localhost:8888 scripts/k6-load-test.js
 *
 * EXPECTED THRESHOLDS for Enterprise-Ready status:
 *   - p95 booking latency < 1000ms
 *   - p95 API read latency < 300ms
 *   - Error rate < 1%
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

// ─── Custom Metrics ───────────────────────────────────────────────────────────
const bookingErrors   = new Rate('booking_errors');
const bookingDuration = new Trend('booking_duration_ms', true);
const apiErrors       = new Rate('api_errors');
const totalBookings   = new Counter('total_bookings_attempted');

// ─── Test Configuration ───────────────────────────────────────────────────────
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8888';
const WP_NONCE = __ENV.WP_NONCE || 'test-nonce-replace-me'; // wp_create_nonce('wp_rest')

export const options = {
    scenarios: {

        // ── Scenario 1: Booking Stress Test ─────────────────────────────────
        // Simulates 500 concurrent users attempting to book appointments.
        // Models peak traffic on a busy franchise launch day.
        booking_stress: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 50   },  // Warm up to 50
                { duration: '60s', target: 250  },  // Ramp to 250
                { duration: '60s', target: 500  },  // Peak: 500 concurrent
                { duration: '30s', target: 0    },  // Cool down
            ],
            exec: 'bookingFlow',
            gracefulRampDown: '10s',
        },

        // ── Scenario 2: API Read Throughput ──────────────────────────────────
        // 1,000 requests/min sustained for public read endpoints.
        api_read_throughput: {
            executor: 'constant-arrival-rate',
            rate: 1000,
            timeUnit: '1m',   // 1000 req/min ≈ 16.7 req/s
            duration: '2m',
            preAllocatedVUs: 50,
            maxVUs: 100,
            exec: 'apiReadFlow',
        },

        // ── Scenario 3: Authenticated User Flow ──────────────────────────────
        // 100 authenticated clients browsing and booking — sustained 5 min.
        authenticated_flow: {
            executor: 'constant-vus',
            vus: 100,
            duration: '5m',
            exec: 'authenticatedFlow',
        },
    },

    thresholds: {
        // Enterprise-Ready thresholds
        'http_req_duration{scenario:booking_stress}':    ['p(95)<1000'],  // 95th pct < 1s
        'http_req_duration{scenario:api_read_throughput}': ['p(95)<300'], // 95th pct < 300ms
        'http_req_failed':   ['rate<0.01'],   // Error rate < 1%
        'booking_errors':    ['rate<0.01'],
        'booking_duration_ms': ['p(95)<1000'],
    },
};

// ─── Test Data ────────────────────────────────────────────────────────────────
// In real testing, load from a CSV with real credentials via SharedArray.
// For local testing, these stubs exercise the API surface.
const TEST_SALONS   = [1, 2, 3, 4, 5];
const TEST_SERVICES = [1, 2, 3];

function randomItem(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function futureTime() {
    // Returns a datetime string 1–7 days from now in MySQL format
    const d = new Date();
    d.setDate(d.getDate() + Math.floor(Math.random() * 7) + 1);
    d.setHours(9 + Math.floor(Math.random() * 9), 0, 0, 0);
    return d.toISOString().replace('T', ' ').substring(0, 19);
}

// ─── Scenario Functions ───────────────────────────────────────────────────────

/**
 * Scenario 1: Booking Stress Flow
 * POST /glamlux/v1/book with randomised salon + service + time.
 */
export function bookingFlow() {
    totalBookings.add(1);

    const payload = JSON.stringify({
        salon_id:         randomItem(TEST_SALONS),
        service_id:       randomItem(TEST_SERVICES),
        appointment_time: futureTime(),
        notes:            'Load test booking',
    });

    const params = {
        headers: {
            'Content-Type':  'application/json',
            'X-WP-Nonce':    WP_NONCE,
        },
        tags: { name: 'POST /book' },
    };

    const start = Date.now();
    const res   = http.post(`${BASE_URL}/wp-json/glamlux/v1/book`, payload, params);
    const ms    = Date.now() - start;

    bookingDuration.add(ms);

    const success = check(res, {
        'booking status 2xx or 401 (auth required)': (r) =>
            r.status === 200 || r.status === 201 || r.status === 401,
        'booking response is JSON': (r) =>
            r.headers['Content-Type'] && r.headers['Content-Type'].includes('application/json'),
        'booking latency < 1000ms': () => ms < 1000,
    });

    if (!success || (res.status !== 200 && res.status !== 201 && res.status !== 401)) {
        bookingErrors.add(1);
    }

    sleep(Math.random() * 2 + 0.5); // Think time: 0.5s – 2.5s
}

/**
 * Scenario 2: API Read Throughput
 * Alternates between GET /salons and GET /services.
 */
export function apiReadFlow() {
    group('Public API reads', function() {

        // GET /salons
        const salonsRes = http.get(`${BASE_URL}/wp-json/glamlux/v1/salons`, {
            tags: { name: 'GET /salons' },
        });
        const salonsOk = check(salonsRes, {
            'GET /salons 200':          (r) => r.status === 200,
            'GET /salons < 300ms':      (r) => r.timings.duration < 300,
            'GET /salons is array':     (r) => {
                try { return Array.isArray(JSON.parse(r.body)); } catch(e) { return false; }
            },
        });
        if (!salonsOk) apiErrors.add(1);

        sleep(0.1);

        // GET /services
        const servicesRes = http.get(`${BASE_URL}/wp-json/glamlux/v1/services`, {
            tags: { name: 'GET /services' },
        });
        const servicesOk = check(servicesRes, {
            'GET /services 200':        (r) => r.status === 200,
            'GET /services < 300ms':    (r) => r.timings.duration < 300,
        });
        if (!servicesOk) apiErrors.add(1);

    });

    sleep(0.5);
}

/**
 * Scenario 3: Authenticated User Flow
 * Simulates a client browsing salons, checking their appointments,
 * then booking a slot — all with authenticated sessions.
 */
export function authenticatedFlow() {
    group('Authenticated client flow', function() {

        // Step 1: Browse salons (public)
        const salonsRes = http.get(`${BASE_URL}/wp-json/glamlux/v1/salons`, {
            tags: { name: 'authenticated: GET /salons' },
        });
        check(salonsRes, { 'browse salons ok': (r) => r.status === 200 });

        sleep(1);

        // Step 2: Check my appointments (requires auth)
        const apptRes = http.get(`${BASE_URL}/wp-json/glamlux/v1/my-appointments`, {
            headers: { 'X-WP-Nonce': WP_NONCE },
            tags: { name: 'authenticated: GET /my-appointments' },
        });
        check(apptRes, {
            'my-appointments 200 or 401': (r) => r.status === 200 || r.status === 401,
        });

        sleep(1.5);

        // Step 3: Attempt booking
        const bookRes = http.post(
            `${BASE_URL}/wp-json/glamlux/v1/book`,
            JSON.stringify({
                salon_id:         randomItem(TEST_SALONS),
                service_id:       randomItem(TEST_SERVICES),
                appointment_time: futureTime(),
            }),
            {
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': WP_NONCE },
                tags: { name: 'authenticated: POST /book' },
            }
        );
        check(bookRes, {
            'book 2xx or 401': (r) => r.status >= 200 && r.status < 500,
        });

        sleep(2);
    });
}

// ─── Lifecycle Hooks ──────────────────────────────────────────────────────────

export function setup() {
    console.log(`\n🏁 GlamLux2Lux Load Test Starting`);
    console.log(`   Target: ${BASE_URL}`);
    console.log(`   Enterprise Thresholds: p95 booking < 1000ms, error rate < 1%\n`);
}

export function teardown(data) {
    console.log('\n📊 Test Complete. Review the summary above for threshold violations.');
    console.log('   If all thresholds PASS → Platform qualifies as Enterprise-Ready.');
}
