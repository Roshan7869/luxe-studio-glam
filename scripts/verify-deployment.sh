#!/bin/bash
# GlamLux Deployment Verification Script - Phase 8
# Runs automatically after deployment to verify site health

BASE_URL=${1:-"https://luxe-studio-glam-production.up.railway.app"}
WP_ADMIN_URL="${BASE_URL}/wp-admin/site-health.php"
API_INVENTORY="${BASE_URL}/wp-json/glamlux/v1/inventory"
API_MEMBERSHIPS="${BASE_URL}/wp-json/glamlux/v1/memberships"

echo "========================================="
echo " GlamLux Deployment Verification"
echo " Target: $BASE_URL"
echo "========================================="

check_endpoint() {
    ENDPOINT=$1
    NAME=$2
    EXPECTED_CODE=$3

    echo -n "Checking $NAME... "
    # We use -o /dev/null to hide output, -s for silent, -w to get http code
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$ENDPOINT")
    
    # 200, 401 (Unauthorized but reachable), or 403 are usually "alive" for APIs
    if [ "$HTTP_CODE" == "$EXPECTED_CODE" ] || [ "$HTTP_CODE" == "401" ] || [ "$HTTP_CODE" == "403" ]; then
        echo "✅ OK (HTTP $HTTP_CODE)"
        return 0
    else
        echo "❌ FAILED (HTTP $HTTP_CODE)"
        return 1
    fi
}

# 1. Check REST Endpoints
check_endpoint "$API_INVENTORY" "Inventory API" "200"
check_endpoint "$API_MEMBERSHIPS" "Memberships API" "200"

# 2. Check Site Health (Requires auth, so 302 redirect to login is expected/healthy)
check_endpoint "$WP_ADMIN_URL" "Site Health Page" "302"

# 3. Check health status endpoint (added in Phase 8)
check_endpoint "${BASE_URL}/glamlux-health.php" "GlamLux Health Probe" "200"

# 4. Check Plugin Version using WP-CLI if running locally in container
if command -v wp &> /dev/null; then
    echo -n "Checking Plugin Version... "
    VERSION=$(wp plugin get glamlux-core --field=version --allow-root 2>/dev/null)
    if [[ "$VERSION" == 3.* ]]; then
        echo "✅ OK (v$VERSION)"
    else
        echo "❌ FAILED (v$VERSION)"
    fi
fi

echo "========================================="
echo " Verification Complete"
echo "========================================="
