# GlamLux Integration - Action Items & Fixes

**Generated:** March 3, 2026  
**Priority Level:** High (Security + Data Integrity)

---

## 🔴 SECURITY ISSUES - Fix Immediately

### Issue #1: Hardcoded Credentials in Migration Scripts

**Severity:** CRITICAL - Production credentials exposed in code

#### File 1: `glam_zip_1/scripts/run-migration.js`

**Current Code (Lines 5-6):**
```javascript
const USERNAME = 'glamlux_admin';
const PASSWORD = 'GlamLux@2026#';
```

**Fix:**
```javascript
const USERNAME = process.env.MIGRATION_USER || 'glamlux_admin';
const PASSWORD = process.env.MIGRATION_PASSWORD;

// Add validation
if (!PASSWORD) {
    console.error('ERROR: MIGRATION_PASSWORD environment variable is not set');
    process.exit(1);
}
```

#### File 2: `glam_zip_1/scripts/remote-audit.js`

**Current Code (Lines 5-6):**
```javascript
const USERNAME = 'glamlux_admin';
const PASSWORD = 'GlamLux@2026#';
```

**Fix:**
```javascript
const USERNAME = process.env.AUDIT_USER || 'glamlux_admin';
const PASSWORD = process.env.AUDIT_PASSWORD;

// Add validation
if (!PASSWORD) {
    console.error('ERROR: AUDIT_PASSWORD environment variable is not set');
    process.exit(1);
}
```

#### File 3: `glam_zip_1/scripts/remote-audit.php`

**Current Code (Lines 4-5):**
```php
$username = 'glamlux_admin';
$password = 'GlamLux@2026#';
```

**Fix:**
```php
$username = getenv('AUDIT_USER') ?: 'glamlux_admin';
$password = getenv('AUDIT_PASSWORD');

// Add validation
if (!$password) {
    echo "ERROR: AUDIT_PASSWORD environment variable is not set\n";
    exit(1);
}
```

---

## 🟠 DATA INTEGRITY ISSUES - Fix Before Multi-Site Deployment

### Issue #2: Inconsistent Transient Key Namespacing

**Severity:** HIGH - Causes cache collision in multi-site environments

#### Problem Areas:

All transient keys that LACK blog_id can cause data between sites to collide. The following fixes are required:

---

### Fix #1: `wp-content/plugins/glamlux-core/services/class-glamlux-service-staff.php`

**Location:** Lines 117-138 (update() and deactivate() methods)

**Current Code:**
```php
// Lines 117-118 (update method)
delete_transient('gl_api_staff_profiles_0');
delete_transient('gl_api_staff_profiles_' . $staff['salon_id']);

// Lines 137-138 (deactivate method)
delete_transient('gl_api_staff_profiles_0');
delete_transient('gl_api_staff_profiles_' . $staff['salon_id']);
```

**Fixed Code:**
```php
// Lines 117-118 (update method)
delete_transient('gl_api_staff_profiles_0_blog_' . get_current_blog_id());
delete_transient('gl_api_staff_profiles_' . (int)$staff['salon_id'] . '_blog_' . get_current_blog_id());

// Lines 137-138 (deactivate method)
delete_transient('gl_api_staff_profiles_0_blog_' . get_current_blog_id());
delete_transient('gl_api_staff_profiles_' . $staff['salon_id'] . '_blog_' . get_current_blog_id());
```

---

### Fix #2: `wp-content/plugins/glamlux-core/Rest/class-glamlux-data-controller.php`

**Location:** Lines 146, 165 (get_staff_profiles method)

**Current Code:**
```php
// Line 146
$cached = get_transient('gl_api_staff_profiles_' . $salon);
// ...
// Line 165
set_transient('gl_api_staff_profiles_' . $salon, $rows, 15 * MINUTE_IN_SECONDS);
```

**Fixed Code:**
```php
// Line 146
$cached = get_transient('gl_api_staff_profiles_' . $salon . '_blog_' . get_current_blog_id());
// ...
// Line 165
set_transient('gl_api_staff_profiles_' . $salon . '_blog_' . get_current_blog_id(), $rows, 15 * MINUTE_IN_SECONDS);
```

**Also Required:** Lines 77, 89 (get_memberships method)

**Current Code:**
```php
// Line 77
$cached = get_transient('gl_api_memberships');
// ...
// Line 89
set_transient('gl_api_memberships', $rows, 15 * MINUTE_IN_SECONDS);
```

**Fixed Code:**
```php
// Line 77
$cached = get_transient('gl_api_memberships_blog_' . get_current_blog_id());
// ...
// Line 89
set_transient('gl_api_memberships_blog_' . get_current_blog_id(), $rows, 15 * MINUTE_IN_SECONDS);
```

---

### Fix #3: `wp-content/plugins/glamlux-core/Rest/class-membership-controller.php`

**Location:** Lines 32, 36 (get_tiers method)

**Current Code:**
```php
// Line 32
$cache = get_transient('gl_mem_tiers');
if (false === $cache) {
    $repo = new GlamLux_Repo_Membership();
    $cache = method_exists($repo, 'get_active_tiers') ? $repo->get_active_tiers() : [];
    // Line 36
    set_transient('gl_mem_tiers', $cache, HOUR_IN_SECONDS);
}
```

**Fixed Code:**
```php
// Line 32
$cache_key = 'gl_mem_tiers_blog_' . get_current_blog_id();
$cache = get_transient($cache_key);
if (false === $cache) {
    $repo = new GlamLux_Repo_Membership();
    $cache = method_exists($repo, 'get_active_tiers') ? $repo->get_active_tiers() : [];
    // Line 36 (updated)
    set_transient($cache_key, $cache, HOUR_IN_SECONDS);
}
```

---

### Fix #4: `wp-content/themes/glamlux-theme/front-page.php`

**Location:** Lines 40, 51, 56, 68, 73, 84, 89, 100

**Current Code (Salons section, lines 40-51):**
```php
$salons_raw = get_transient('glamlux_fp_salons_db');
if (false === $salons_raw) {
    // ... database query
    set_transient('glamlux_fp_salons_db', $salons_raw, 15 * MINUTE_IN_SECONDS);
}
```

**Fixed Code:**
```php
$salons_cache_key = 'glamlux_fp_salons_db_blog_' . get_current_blog_id();
$salons_raw = get_transient($salons_cache_key);
if (false === $salons_raw) {
    // ... database query
    set_transient($salons_cache_key, $salons_raw, 15 * MINUTE_IN_SECONDS);
}
```

**Apply Same Fix To:**
- Staff section (lines 56, 68): `glamlux_fp_staff_db` → `glamlux_fp_staff_db_blog_{blog_id}`
- Memberships section (lines 73, 84): `glamlux_fp_memberships_db` → `glamlux_fp_memberships_db_blog_{blog_id}`
- Franchises section (lines 89, 100): `glamlux_fp_franchises_db` → `glamlux_fp_franchises_db_blog_{blog_id}`

---

### Fix #5: `wp-content/themes/glamlux-theme/page-portfolio.php`

**Location:** Line 10

**Current Code:**
```php
$logs = get_transient('glamlux_page_portfolio');
if (false === $logs) {
    // ... database query
    set_transient('glamlux_page_portfolio', $logs, 15 * MINUTE_IN_SECONDS);
}
```

**Fixed Code:**
```php
$portfolio_key = 'glamlux_page_portfolio_blog_' . get_current_blog_id();
$logs = get_transient($portfolio_key);
if (false === $logs) {
    // ... database query
    set_transient($portfolio_key, $logs, 15 * MINUTE_IN_SECONDS);
}
```

---

## 📋 Implementation Checklist

### Phase 1: Security (Complete First)
- [ ] Update `run-migration.js` to use env variables
- [ ] Update `remote-audit.js` to use env variables
- [ ] Update `remote-audit.php` to use env variables
- [ ] Test scripts with environment variables set
- [ ] Verify scripts fail gracefully when env vars missing
- [ ] Remove hardcoded credentials from repository history (consider git-secrets)

### Phase 2: Data Integrity (Complete Before Multi-Site)
- [ ] Fix `class-glamlux-service-staff.php` lines 117-138
- [ ] Fix `class-glamlux-data-controller.php` lines 77, 89, 146, 165
- [ ] Fix `class-membership-controller.php` lines 32, 36
- [ ] Fix `front-page.php` lines 40-100 (5 locations)
- [ ] Fix `page-portfolio.php` line 10
- [ ] Test transient isolation in multi-site environment
- [ ] Verify cache operates independently per site

### Phase 3: Verification
- [ ] Run integration verification again
- [ ] Test each fixed component manually
- [ ] Monitor logs for any transient collisions
- [ ] Performance test to ensure no degradation
- [ ] Multi-site integration test

---

## Environment Variables to Set (Railway/Production)

Add these to your Railway environment variables:

```bash
# Migration script credentials
MIGRATION_USER=glamlux_admin
MIGRATION_PASSWORD=<secure-password>

# Audit script credentials
AUDIT_USER=glamlux_admin
AUDIT_PASSWORD=<secure-password>

# JWT Secret (already configured)
GLAMLUX_JWT_SECRET=<secure-jwt-secret>
```

---

## Testing After Fixes

### Test Transient Isolation (Multi-Site)
```php
// Site 1
set_transient('gl_api_staff_profiles_1_blog_1', ['site1_data']);

// Site 2 (blog_id = 2)
set_transient('gl_api_staff_profiles_1_blog_2', ['site2_data']);

// Verify isolation
get_transient('gl_api_staff_profiles_1_blog_1'); // Should return ['site1_data']
get_transient('gl_api_staff_profiles_1_blog_2'); // Should return ['site2_data']
```

### Test Script Error Handling
```bash
# Should fail with error message
MIGRATION_PASSWORD="" node run-migration.js

# Should work correctly
MIGRATION_USER=admin MIGRATION_PASSWORD=secure node run-migration.js
```

---

## Verification After Fixes

Re-run integration verification and expect:
- ✅ All 8 VERIFIED items remain verified
- ✅ 2 WARNING items resolved
- ✅ 0 CRITICAL items
- ✅ Full compliance with multi-site standards

---

*Status: Ready for Implementation*  
*Estimated Effort: 1-2 hours*  
*Testing Time: 30 minutes*
