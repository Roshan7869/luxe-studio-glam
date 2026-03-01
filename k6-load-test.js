import http from 'k6/http';
import { check, sleep } from 'k6';

// 1. Configure the load test parameters
export const options = {
    stages: [
        { duration: '10s', target: 50 },  // Ramp-up to 50 concurrent virtual users
        { duration: '30s', target: 50 },  // Hold at 50 VUs for 30 seconds
        { duration: '10s', target: 0 },   // Ramp-down to 0 VUs
    ],
    thresholds: {
        // 95% of requests should complete within 500ms
        http_req_duration: ['p(95)<500'],
        // Less than 1% of requests can fail
        http_req_failed: ['rate<0.01'],
    },
};

// 2. Define the target domain
const BASE_URL = 'https://luxe-studio-glam.up.railway.app';

// 3. Define the VU execution lifecycle
export default function () {
    // Setup URLs to hit
    const endpoints = [
        { url: `${BASE_URL}/`, tags: { name: 'Homepage' } },
        { url: `${BASE_URL}/wp-json/glamlux/v1/health`, tags: { name: 'Health Endpoint' } },
        { url: `${BASE_URL}/wp-json/wp/v2/posts`, tags: { name: 'Standard WP API' } },
    ];

    // Pick a random endpoint to simulate varied real-world usage
    const target = endpoints[Math.floor(Math.random() * endpoints.length)];

    // Execute GET request
    const res = http.get(target.url, { tags: target.tags });

    // Validate the response
    check(res, {
        'status is 200': (r) => r.status === 200,
        'transaction time < 1000ms': (r) => r.timings.duration < 1000,
    });

    // Small delay to simulate human read time
    sleep(Math.random() * 2 + 1); // sleep 1-3 seconds
}
