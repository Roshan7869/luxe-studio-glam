# GlamLux2Lux — Phase 16 Stress Validation Run Guide

## Prerequisites

| Tool        | Install Command                                          |
|-------------|----------------------------------------------------------|
| k6          | `winget install k6` (Windows) / `brew install k6` (Mac) |
| WP-CLI      | <https://wp-cli.org/#installing>                           |
| Docker      | <https://www.docker.com/products/docker-desktop>           |
| Lighthouse  | `npm install -g @lhci/cli`                               |

---

## Step 1 — Start the Stack

```bash
# From d:\Luxe_studio_ glam
docker compose up -d
```

Wait ~30s for WordPress and Redis to initialize.

---

## Step 2 — Seed Database (Scale Test Data)

```bash
# Default: 50 franchises, 5 salons each, 10,000 appointments
wp eval-file wp-content/plugins/glamlux-core/scripts/wp-cli-seeder.php

# Full Domino's-scale test (WARNING: will take 5–10 min on local machine)
wp eval-file wp-content/plugins/glamlux-core/scripts/wp-cli-seeder.php \
  --franchises=10000 --salons=5 --appointments=50000
```

The seeder will print EXPLAIN output after seeding. Check that all critical
queries show `type=ref` or `type=range` (indexed). `type=ALL` = full table scan = problem.

---

## Step 3 — Apply FK Constraints

```bash
wp eval-file wp-content/plugins/glamlux-core/scripts/migrate-add-foreign-keys.php
```

Expected output: `[OK]` for all 11 FK constraints.

---

## Step 4 — Run Lighthouse CI (Frontend Performance)

```bash
lhci autorun --config .lighthouserc.json
```

**Pass Criteria (Enterprise-Ready):**

| Metric                   | Target      |
|--------------------------|-------------|
| Performance Score        | ≥ 90        |
| First Contentful Paint   | < 2.0s      |
| Largest Contentful Paint | < 2.5s      |
| Total Blocking Time      | < 300ms     |
| Cumulative Layout Shift  | < 0.1       |
| Speed Index              | < 3.0s      |

---

## Step 5 — Run k6 Load Test

```bash
# Smoke test (quick, 30 seconds)
k6 run --env BASE_URL=http://localhost:8888 \
       --vus 20 --duration 30s \
       scripts/k6-load-test.js

# Full enterprise stress test (runs all 3 scenarios, ~5 minutes)
k6 run --env BASE_URL=http://localhost:8888 scripts/k6-load-test.js
```

**Pass Criteria:**

| Metric                              | Threshold   |
|-------------------------------------|-------------|
| p95 Booking Latency                 | < 1000ms    |
| p95 API Read Latency (GET /salons)  | < 300ms     |
| HTTP Error Rate                     | < 1%        |
| Booking Error Rate                  | < 1%        |

k6 will print **✓ PASS** or **✗ FAIL** for each threshold at the end.

---

## Step 6 — Redis Sanity Check

```bash
# Connect to Redis inside Docker
docker compose exec redis redis-cli

# Verify GlamLux transients are being cached
127.0.0.1:6379> KEYS glamlux_*
# Should show keys like glamlux_cached_salons, glamlux_cached_services, glamlux_rl_*
```

---

## Scoring Impact (Phase 16)

| Validation                    | Score Impact |
|-------------------------------|--------------|
| p95 booking < 1s @ 500 VUs    | +5           |
| API reads < 300ms sustained   | +3           |
| Lighthouse Perf ≥ 90          | +2           |
| FK constraints confirmed      | +2           |
| EXPLAIN shows indexed queries | +3           |

**Post Phase 16 estimated score: 100–108 / 110** 🏆
