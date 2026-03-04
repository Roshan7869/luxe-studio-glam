#!/bin/bash
# Smoke Tests - Verify deployment health
# Usage: bash scripts/smoke-tests.sh [domain]

set -e

DOMAIN="${1:-https://glamlux.railway.app}"
PASSED=0
FAILED=0

echo "🧪 Running Smoke Tests"
echo "====================="
echo "Target: $DOMAIN"
echo ""

# Test 1: Health endpoint
echo "Test 1: Health Endpoint"
echo "  Checking $DOMAIN/wp-json/glamlux/v1/health..."
RESPONSE=$(curl -s -w "\n%{http_code}" "$DOMAIN/wp-json/glamlux/v1/health" || echo "")
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
    STATUS=$(echo "$BODY" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    if [ "$STATUS" = "healthy" ] || [ "$STATUS" = "degraded" ]; then
        echo "  ✅ Health check passed (HTTP $HTTP_CODE)"
        ((PASSED++))
    else
        echo "  ❌ Health check failed - invalid status"
        ((FAILED++))
    fi
else
    echo "  ❌ Health check failed (HTTP $HTTP_CODE)"
    ((FAILED++))
fi

# Test 2: Homepage
echo ""
echo "Test 2: Homepage"
echo "  Checking $DOMAIN..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$DOMAIN")
if [ "$HTTP_CODE" = "200" ]; then
    echo "  ✅ Homepage OK (HTTP $HTTP_CODE)"
    ((PASSED++))
else
    echo "  ❌ Homepage failed (HTTP $HTTP_CODE)"
    ((FAILED++))
fi

# Test 3: API Endpoint (salons)
echo ""
echo "Test 3: API Endpoint"
echo "  Checking $DOMAIN/wp-json/glamlux/v1/salons..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$DOMAIN/wp-json/glamlux/v1/salons")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
    echo "  ✅ API OK (HTTP $HTTP_CODE)"
    ((PASSED++))
else
    echo "  ❌ API failed (HTTP $HTTP_CODE)"
    ((FAILED++))
fi

# Test 4: REST API with auth
echo ""
echo "Test 4: Event Queue Endpoint"
echo "  Checking $DOMAIN/wp-json/glamlux/v1/events/stats..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Authorization: Bearer test-token" \
    "$DOMAIN/wp-json/glamlux/v1/events/stats")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
    echo "  ✅ Event queue endpoint OK (HTTP $HTTP_CODE)"
    ((PASSED++))
else
    echo "  ❌ Event queue endpoint failed (HTTP $HTTP_CODE)"
    ((FAILED++))
fi

# Test 5: Cache endpoint
echo ""
echo "Test 5: Cache Endpoint"
echo "  Checking $DOMAIN/wp-json/glamlux/v1/cache/stats..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Authorization: Bearer test-token" \
    "$DOMAIN/wp-json/glamlux/v1/cache/stats")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
    echo "  ✅ Cache endpoint OK (HTTP $HTTP_CODE)"
    ((PASSED++))
else
    echo "  ❌ Cache endpoint failed (HTTP $HTTP_CODE)"
    ((FAILED++))
fi

# Test 6: Response headers
echo ""
echo "Test 6: Security Headers"
echo "  Checking security headers..."
RESPONSE=$(curl -s -i "$DOMAIN" | grep -i "strict-transport")
if echo "$RESPONSE" | grep -q "strict-transport-security"; then
    echo "  ✅ HSTS header present"
    ((PASSED++))
else
    echo "  ⚠️  HSTS header missing (optional)"
fi

# Test 7: Performance
echo ""
echo "Test 7: Response Time"
echo "  Measuring response time..."
START_TIME=$(date +%s%N)
curl -s -o /dev/null "$DOMAIN/wp-json/glamlux/v1/health"
END_TIME=$(date +%s%N)
DURATION=$((($END_TIME - $START_TIME) / 1000000))

if [ $DURATION -lt 2000 ]; then
    echo "  ✅ Response time OK (${DURATION}ms)"
    ((PASSED++))
else
    echo "  ⚠️  Slow response time (${DURATION}ms)"
fi

# Test 8: Database check
echo ""
echo "Test 8: Database Tables"
if command -v wp &> /dev/null; then
    echo "  Checking database tables..."
    TABLE_COUNT=$(wp db query "SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name LIKE 'wp_gl_%';" 2>/dev/null || echo "0")
    if [ "$TABLE_COUNT" -ge 4 ]; then
        echo "  ✅ Database tables OK ($TABLE_COUNT tables)"
        ((PASSED++))
    else
        echo "  ❌ Database tables missing"
        ((FAILED++))
    fi
else
    echo "  ⚠️  WP-CLI not available (skipped)"
fi

# Results
echo ""
echo "=============================="
echo "Test Results"
echo "=============================="
echo "✅ Passed: $PASSED"
echo "❌ Failed: $FAILED"

if [ $FAILED -eq 0 ]; then
    echo ""
    echo "✅ All smoke tests passed!"
    exit 0
else
    echo ""
    echo "❌ Some tests failed!"
    exit 1
fi
