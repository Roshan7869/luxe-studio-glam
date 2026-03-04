# 🧪 COMPREHENSIVE TESTING GUIDE - Phase 1 & 2

**Focus**: Local testing, issue verification, operational management validation  
**Time**: 2-3 hours  
**Goal**: Ensure all Phase 1 fixes work and Phase 2 foundation is solid

---

## 📋 PRE-TESTING CHECKLIST

- [ ] Git changes committed
- [ ] Docker Docker installed and running
- [ ] 4GB+ RAM available
- [ ] Ports 80, 3306, 6379 available
- [ ] PHP CLI working locally (optional)
- [ ] Browser DevTools available (F12)

---

## 🚀 STEP 1: START LOCAL ENVIRONMENT (10 minutes)

### 1.1 Start Docker Services

```bash
cd /path/to/luxe-studio-glam
docker-compose down    # Clean slate
docker-compose up -d   # Start all services
```

### 1.2 Verify Services Running

```bash
docker-compose ps

# Output should show 4 services:
# ✅ glamlux_nginx    - Port 80
# ✅ glamlux_wordpress - PHP 8.2
# ✅ glamlux_db       - MySQL 8
# ✅ glamlux_redis    - Cache server
```

### 1.3 Wait for Full Initialization

```bash
# Wait 2-3 minutes for services to fully start
sleep 180

# Check if WordPress is initialized
curl -s http://localhost/wp-admin/admin-ajax.php -H "Content-Type: application/json"
# Should not return error
```

---

## ✅ STEP 2: TEST PHASE 1 FIXES (20 minutes)

### Test 2.1: Homepage (No HTTP 500)

**Test**: Homepage should load without errors

```bash
# Test 1: HTTP status code
curl -I http://localhost

# Expected: HTTP 200 OK
# ❌ Failed if: HTTP 500, 502, 503

# Test 2: Check homepage content
curl -s http://localhost | grep -i "glamlux"
# Should contain "GlamLux" or "glamlux"

# Test 3: No PHP errors in response
curl -s http://localhost | grep -i "fatal error"
# Should return nothing
```

**Browser Test**:
1. Open `http://localhost`
2. Should load without errors
3. Should show GlamLux homepage
4. No "500 Error" message

---

### Test 2.2: Responsive CSS Loaded

**Test**: CSS framework should be loaded

```bash
# Check if responsive.css is linked in header
curl -s http://localhost | grep "responsive.css"

# Should find:
# <link rel="stylesheet" href="/wp-content/themes/glamlux-theme/responsive.css">
```

**Browser Test**:
1. Open `http://localhost`
2. Press F12 (DevTools)
3. Go to "Sources" or "Network" tab
4. Find `responsive.css`
5. Should show status 200 (loaded)

---

### Test 2.3: Mobile Meta Tags

**Test**: Mobile meta tags should be present

```bash
# Check viewport meta tag
curl -s http://localhost | grep "viewport"

# Should show:
# <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

# Check Apple mobile web app
curl -s http://localhost | grep "apple-mobile-web-app"

# Should show multiple meta tags
```

---

### Test 2.4: Responsive Breakpoints

**Browser Test** (Most Important):

1. **Open Developer Tools**: Press F12
2. **Enable Device Emulation**: Click device icon (top-left corner)
3. **Test Each Breakpoint**:

#### Mobile (375px) - iPhone 12
```
✓ Single column layout
✓ Large buttons (easy to tap)
✓ Hamburger menu visible
✓ No horizontal scroll
✓ Text readable
```

#### Tablet (768px) - iPad
```
✓ 2-3 column layout
✓ Medium buttons
✓ Balanced spacing
✓ Landscape orientation works
```

#### Desktop (1366px) - Laptop
```
✓ 4-column grid
✓ Generous spacing
✓ Full navigation visible
✓ Hover effects work
```

**Test Script**:
```javascript
// Paste in browser console (F12)
console.log('Screen sizes:');
console.log('Current: ' + window.innerWidth + 'px × ' + window.innerHeight + 'px');
console.log('Mobile (< 576px): ' + (window.innerWidth < 576 ? '✓' : '✗'));
console.log('Tablet (576-992px): ' + (window.innerWidth >= 576 && window.innerWidth < 992 ? '✓' : '✗'));
console.log('Desktop (> 992px): ' + (window.innerWidth >= 992 ? '✓' : '✗'));
```

---

### Test 2.5: Cache Keys (Multisite)

**Test**: Cache keys should use blog ID

```bash
# Check front-page.php cache key usage
grep -n "get_current_blog_id()" wp-content/themes/glamlux-theme/front-page.php

# Should show on lines:
# 15: $cache_key = 'glamlux_fp_services_db_blog_' . get_current_blog_id();
# 40: $cache_key_salons = 'glamlux_fp_salons_db_blog_' . get_current_blog_id();
# (and more)
```

---

## 🏥 STEP 3: TEST PHASE 2 OPERATIONS (15 minutes)

### Test 3.1: Health Check Endpoint

**Test**: Health endpoint should return status

```bash
# Test 1: Quick health check
curl -s http://localhost/wp-json/glamlux/v1/health | jq .

# Expected response:
{
  "success": true,
  "status": "healthy",
  "timestamp": "2026-03-03T14:00:00Z",
  "checks": {
    "database": {
      "status": "ok",
      "response_time_ms": 12.5,
      "host": "db",
      "database": "glamlux_local"
    },
    "redis": {
      "status": "ok",
      "response_time_ms": 2.1
    },
    ...
  }
}

# Test 2: Check specific components
curl -s http://localhost/wp-json/glamlux/v1/health | jq '.checks.database.status'
# Should output: "ok"

curl -s http://localhost/wp-json/glamlux/v1/health | jq '.checks.redis.status'
# Should output: "ok"
```

**Expected Checks**:
```
✓ database:  ok
✓ redis:     ok
✓ plugin:    active
✓ schema:    valid
```

---

### Test 3.2: Database Connection

**Test**: Database should be responding

```bash
# Test from inside container
docker-compose exec wordpress wp db check

# Expected output:
# Success: WordPress database is accessible.
```

---

### Test 3.3: Redis Cache

**Test**: Redis should be connected

```bash
# Test Redis connection
docker-compose exec redis redis-cli ping

# Expected output:
# PONG

# Check Redis memory
docker-compose exec redis redis-cli INFO memory | head -20
```

---

### Test 3.4: API Endpoints

**Test**: Basic API endpoints should work

```bash
# Test 1: List Salons (public)
curl -s http://localhost/wp-json/glamlux/v1/salons | jq '.data | length'
# Should return a number (even if 0)

# Test 2: Check API structure
curl -s http://localhost/wp-json/glamlux/v1/salons | jq 'keys'
# Should include: ["data", "success", "pagination"]

# Test 3: Check pagination
curl -s http://localhost/wp-json/glamlux/v1/salons | jq '.pagination'
# Should show: page, per_page, total, pages
```

---

## 📊 STEP 4: PERFORMANCE TESTING (15 minutes)

### Test 4.1: Page Load Time

```bash
# Test using curl with timing
curl -o /dev/null -s -w 'Time taken: %{time_total}s\n' http://localhost

# Expected: < 2 seconds for localhost
# Good: < 1 second
# Excellent: < 500ms
```

### Test 4.2: Lighthouse Audit

```bash
# Install if not already
npm install -g @lhci/cli@latest

# Run audit
lhci autorun --config=.lighthouserc.json

# Check results
cat lh-report.json | jq '.categories'

# Expected scores:
# performance: 70+
# accessibility: 90+
# best-practices: 90+
# seo: 90+
```

### Test 4.3: Network Performance

**Browser Test**:
1. Open DevTools (F12)
2. Go to "Network" tab
3. Refresh page (Ctrl+R)
4. Check:
   - Total requests: < 100
   - Total size: < 5 MB
   - Load time: < 2 seconds
   - CSS files: responsive.css loads first

---

## 🐛 STEP 5: ERROR HANDLING (10 minutes)

### Test 5.1: Check Error Logs

```bash
# View PHP error log
docker-compose exec wordpress cat /var/www/html/wp-content/debug.log

# Should show no critical errors related to Phase 1 changes

# Clear log for clean testing
docker-compose exec wordpress echo "" > /var/www/html/wp-content/debug.log
```

### Test 5.2: Database Errors

```bash
# Check MySQL error log (if slow query log enabled)
docker-compose exec db tail /var/log/mysql/error.log

# Should show no critical errors
```

### Test 5.3: Browser Console

**Browser Test**:
1. Open DevTools (F12)
2. Go to "Console" tab
3. Refresh page
4. Should show: No red errors
5. Warnings are OK (informational)

---

## 📱 STEP 6: REAL DEVICE TESTING (Optional, 20 minutes)

### Test 6.1: Real Mobile Phone

**Prerequisites**:
- Find your machine's IP: `ipconfig getifaddr en0` (Mac) or `hostname -I` (Linux)
- Update Nginx config to allow your IP
- Phone on same network

**Test**:
```
1. On phone, open: http://{your-ip}:80
2. Homepage should load
3. Test navigation (hamburger menu)
4. Verify no 500 errors
5. Test booking flow
```

### Test 6.2: Real Tablet

Same as phone, but test landscape orientation:
```
1. Open in landscape
2. Layout should adapt
3. All content readable
4. Touch buttons work
```

---

## ✅ FINAL CHECKLIST

### Phase 1 Verification
- [ ] Homepage loads (HTTP 200)
- [ ] No "500 Error" messages
- [ ] responsive.css loads
- [ ] Mobile meta tags present
- [ ] Cache keys use blog ID
- [ ] Breakpoints work (mobile/tablet/desktop)
- [ ] No PHP errors in logs
- [ ] Browser console has no red errors

### Phase 2 Verification
- [ ] Health endpoint returns 200
- [ ] All health checks pass (db, redis, plugin)
- [ ] API endpoints respond
- [ ] Database connected
- [ ] Redis connected
- [ ] Page load time < 2 seconds
- [ ] Lighthouse score > 70
- [ ] No critical errors in logs

### Overall
- [ ] All tests passed
- [ ] Documentation read
- [ ] Team briefed
- [ ] Ready for production

---

## 🐛 TROUBLESHOOTING

### Homepage Returns 500
```bash
# Check logs
docker-compose logs wordpress | grep -i error

# Check front-page.php
grep -n "get_current_blog_id()" wp-content/themes/glamlux-theme/front-page.php

# Verify cache keys fix applied
```

### CSS Not Loading
```bash
# Check if file exists
ls -la wp-content/themes/glamlux-theme/responsive.css

# Check header.php for link
grep responsive.css wp-content/themes/glamlux-theme/header.php

# Clear browser cache (Ctrl+Shift+R)
```

### API Returns 404
```bash
# Check REST API is enabled
curl http://localhost/wp-json/

# Should show WordPress REST API response

# Check plugin is active
docker-compose exec wordpress wp plugin list | grep glamlux-core
```

### Database Connection Failed
```bash
# Check DB is running
docker-compose ps | grep db

# Restart DB
docker-compose restart db
sleep 30  # Wait for DB to start

# Test connection
docker-compose exec wordpress wp db check
```

### Redis Not Responding
```bash
# Check Redis is running
docker-compose exec redis redis-cli ping

# Check Redis config
docker-compose exec redis redis-cli CONFIG GET maxmemory

# Clear Redis if memory full
docker-compose exec redis redis-cli FLUSHALL
```

---

## 📊 TEST REPORT TEMPLATE

```
═══════════════════════════════════════════════════
  LUXE STUDIO GLAM - TESTING REPORT
═══════════════════════════════════════════════════

Date: 2026-03-03
Tester: [Your Name]
Environment: Local Docker

PHASE 1 RESULTS
───────────────────────────────────────────────────
Homepage (No 500):            [ PASS / FAIL ]
Responsive CSS Loaded:        [ PASS / FAIL ]
Mobile Meta Tags:             [ PASS / FAIL ]
Cache Keys (Multisite):       [ PASS / FAIL ]
Breakpoints (Mobile/Tablet):  [ PASS / FAIL ]
Error Logs:                   [ PASS / FAIL ]

PHASE 2 RESULTS
───────────────────────────────────────────────────
Health Endpoint:              [ PASS / FAIL ]
Database Check:               [ PASS / FAIL ]
Redis Check:                  [ PASS / FAIL ]
API Endpoints:                [ PASS / FAIL ]
Page Load Time:               [ PASS / FAIL ]
Lighthouse Score:             [ PASS / FAIL ]

SUMMARY
───────────────────────────────────────────────────
Total Tests: 12
Passed: __
Failed: __

Critical Issues: __
High Priority: __
Low Priority: __

Overall Status: [ APPROVED / NEEDS FIXES ]

Approved By: _________________
═══════════════════════════════════════════════════
```

---

## 🎉 SUCCESS CRITERIA

✅ **All Phase 1 tests pass**  
✅ **All Phase 2 foundation tests pass**  
✅ **No critical errors in logs**  
✅ **Performance acceptable**  
✅ **Documentation verified**  
✅ **Ready for production deployment**

---

**Begin testing now!** Follow each test sequentially and document results.

Questions? Check the relevant documentation:
- Phase 1: PHASE_1_COMPLETION_SUMMARY.md
- Phase 2: PHASE_2_OPERATIONS_ENHANCEMENT.md
- API: API_DOCUMENTATION.md
- Setup: LOCAL_DEVELOPMENT_SETUP.md
