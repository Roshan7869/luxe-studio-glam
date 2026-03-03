# GlamLux Environment Variables Audit Report

**Generated:** March 3, 2026  
**Scope:** Complete GlamLux codebase environment variable inventory

---

## Executive Summary

This audit identifies **3 documented GlamLux-specific environment variables**, **8+ standard WordPress/infrastructure variables**, and **1 critical security issue** (hardcoded credentials found in audit script).

### Key Findings:
- ⚠️ **CRITICAL**: Hardcoded production credentials in `scripts/remote-audit.js`
- ⚠️ **SECURITY**: `GLAMLUX_JWT_SECRET` falls back to `wp_salt('auth')` with warning logs
- ✓ **DOCUMENTED**: Environment variables in `wp-config-railway.php` with fallback values
- ⚠️ **UNDOCUMENTED**: Several infrastructure variables lack clear guidance

---

## 1. GLAMLUX_JWT_SECRET

### Variable Name
`GLAMLUX_JWT_SECRET`

### Purpose
JWT authentication secret for headless mobile app authentication (HS256 encoding)

### Definition Locations

| File | Line | Context |
|------|------|---------|
| [wp-config-railway.php](wp-config-railway.php#L65-L68) | 65-68 | Primary definition with error logging |
| [wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php](wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php#L9) | 9 | Documentation comment |

### Usage Locations

| File | Line | Usage Type | Details |
|------|------|-----------|---------|
| [wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php](wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php#L58-L64) | 58-64 | Constant check & secret retrieval | `defined('GLAMLUX_JWT_SECRET')` check followed by fallback to `wp_salt('auth')` |

### Fallback / Error Handling

```php
// From wp-config-railway.php (lines 65-68)
if (!getenv('GLAMLUX_JWT_SECRET')) {
    error_log('[GlamLux] GLAMLUX_JWT_SECRET env var is not set. JWT will fall back to wp_salt() (lower security).');
}
define('GLAMLUX_JWT_SECRET', getenv('GLAMLUX_JWT_SECRET') ?: wp_salt('auth'));

// From class-glamlux-jwt-auth.php (lines 58-65)
if (defined('GLAMLUX_JWT_SECRET')) {
    return GLAMLUX_JWT_SECRET;
}
if (!get_transient('gl_jwt_secret_warning')) {
    glamlux_log_error('SECURITY WARNING: GLAMLUX_JWT_SECRET not defined in wp-config.php! 
                       Falling back to wp_salt(). Token stability may vary if salt changes.');
    set_transient('gl_jwt_secret_warning', true, DAY_IN_SECONDS);
}
return wp_salt('auth');
```

**Risk**: Fallback is security-degraded; if WordPress salts change, existing JWT tokens become invalid.

### Documentation Status

| Location | Status | Details |
|----------|--------|---------|
| wp-config-railway.php | ✓ Documented | Line 64 comment explains purpose |
| README.md | ✗ Not documented | Missing from environment variables list |
| docs/technical-documentation.md | ✗ Not documented | Not mentioned |
| docs/project-file-hierarchy.md | ✗ Not documented | Not mentioned |

### Recommendations

- ✓ **MUST SET** in production Railway environment
- Add to [README.md](README.md) environment variables section
- Consider requiring (no fallback) in production environments
- Add to deployment checklist/operational documentation

---

## 2. MIGRATION_USER & MIGRATION_PASSWORD

### Variables
- `MIGRATION_USER` - Username for migration script authentication
- `MIGRATION_PASSWORD` - Password for migration script authentication

### Purpose
Authenticate WP-CLI migration script ([scripts/run-migration.js](scripts/run-migration.js)) to trigger plugin activation/deactivation via HTTP login

### Definition Locations

None - these are NOT defined anywhere in configuration files. They're expected to be set at **runtime only** via Node.js environment.

### Usage Locations

| File | Line | Usage Type | Details |
|------|------|-----------|---------|
| [scripts/run-migration.js](scripts/run-migration.js#L5-L9) | 5-9 | `process.env` extraction | Direct read from process environment with validation |

### Code Reference
```javascript
const USERNAME = process.env.MIGRATION_USER;
const PASSWORD = process.env.MIGRATION_PASSWORD;

if (!USERNAME || !PASSWORD) {
    console.error('ERROR: MIGRATION_USER and MIGRATION_PASSWORD environment variables must be set.');
    process.exit(1);
}
```

### Fallback / Error Handling

| Scenario | Behavior |
|----------|----------|
| Missing `MIGRATION_USER` | Error logged, process exits with code 1 |
| Missing `MIGRATION_PASSWORD` | Error logged, process exits with code 1 |
| Invalid/wrong credentials | HTTP 302/401 response; nonce extraction fails; deactivation skips |

### Additional Environment Variable Used

| Variable | Purpose | Location |
|----------|---------|----------|
| `MIGRATION_HOST` | Migration server hostname | [scripts/run-migration.js](scripts/run-migration.js#L4) line 4 |
| **Default** | `luxe-studio-glam-production.up.railway.app` | Fallback if not set |

### Documentation Status

| Location | Status | Details |
|----------|--------|---------|
| wp-config files | ✗ N/A | Not applicable (JS runtime only) |
| README.md | ✗ Not documented | Missing |
| docs/technical-documentation.md | ✗ Not documented | Not mentioned |
| scripts/run-migration.js | ✓ Code comment | Inline error message only |

### Recommendations

- Add to [README.md](README.md) deployment/migration section
- Document in [docs/operational-documentation.md](docs/operational-documentation.md)
- Consider adding `.env.example` at root for Node scripts
- Add more descriptive error messages (e.g., "Migration admin credentials invalid")

---

## 3. Standard Infrastructure Environment Variables

### Documented Variables

| Variable | Defined In | Usage | Fallback | Status |
|----------|-----------|-------|----------|--------|
| **MYSQLHOST** | [wp-config-railway.php](wp-config-railway.php#L5) | DB_HOST | `mysql.railway.internal` | ✓ Documented |
| **MYSQLPORT** | [wp-config-railway.php](wp-config-railway.php#L5) | DB_PORT | `3306` | ✓ Documented |
| **MYSQLUSER** | [wp-config-railway.php](wp-config-railway.php#L6) | DB_USER | `root` | ✓ Documented |
| **MYSQLPASSWORD** | [wp-config-railway.php](wp-config-railway.php#L8-L11) | DB_PASSWORD | None (error logged) | ⚠️ **No fallback** |
| **MYSQLDATABASE** | [wp-config-railway.php](wp-config-railway.php#L12) | DB_NAME | `railway` | ✓ Documented |
| **REDISHOST** | [wp-config-railway.php](wp-config-railway.php#L43) | WP_REDIS_HOST | Empty string (cache disabled) | ✓ Documented |
| **REDISPORT** | [wp-config-railway.php](wp-config-railway.php#L48) | WP_REDIS_PORT | `6379` | ✓ Documented |
| **REDIS_PASSWORD** | [wp-config-railway.php](wp-config-railway.php#L49) | WP_REDIS_PASSWORD | None (optional) | ✓ Documented |
| **SENTRY_DSN** | [wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php](wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php#L13) | Sentry error tracking | None (Sentry disabled if absent) | ⚠️ Partially documented |

### WordPress Authentication Keys/Salts

All defined in [wp-config-railway.php](wp-config-railway.php#L18-L25):
- `AUTH_KEY`
- `SECURE_AUTH_KEY`
- `LOGGED_IN_KEY`
- `NONCE_KEY`
- `AUTH_SALT`
- `SECURE_AUTH_SALT`
- `LOGGED_IN_SALT`
- `NONCE_SALT`

**Status**: Each has documented hardcoded fallback (not ideal for production)

### Additional WordPress Variables

| Variable | Defined In | Purpose |
|----------|-----------|---------|
| `WP_ENVIRONMENT_TYPE` | README.md section | Environment type (development/staging/production) |
| `DISABLE_WP_CRON` | [wp-config-railway.php](wp-config-railway.php#L62) | Disable WordPress cron; rely on system cron |
| `WP_REDIS_PREFIX` | [wp-content/object-cache.php](wp-content/object-cache.php#L234-L235) | Redis cache key prefix (via getenv) |
| `WP_REDIS_SELECTIVE_FLUSH` | [wp-content/object-cache.php](wp-content/object-cache.php#L238-L239) | Selective cache flushing |

---

## 4. Undocumented Environment Variables

### Variables Found But Not Clearly Documented

| Variable | Found In | Purpose | Status |
|----------|----------|---------|--------|
| `SENTRY_DSN` | [class-glamlux-sentry.php](wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php#L13) | Error tracking via Sentry | ⚠️ Optional; no error if missing |
| `WP_REDIS_PREFIX` | [object-cache.php](wp-content/object-cache.php#L234-L235) | Redis key namespace | ⚠️ Optional override |
| `WP_REDIS_SELECTIVE_FLUSH` | [object-cache.php](wp-content/object-cache.php#L238-L239) | Cache flush scope | ⚠️ Optional override |
| `HTTP_X_APP_USER` | [object-cache.php](wp-content/object-cache.php#L249) | App user context for Redis prefix | ⚠️ Legacy/fallback mechanism |

### Recommendation

Add a dedicated [docs/ENVIRONMENT_CONFIGURATION.md](docs/ENVIRONMENT_CONFIGURATION.md) file documenting all variables with examples.

---

## 5. CRITICAL SECURITY ISSUES FOUND

### ⚠️ CRITICAL: Hardcoded Credentials in Audit Script

**File**: [scripts/remote-audit.js](scripts/remote-audit.js)  
**Lines**: 5-6  

```javascript
const USERNAME = 'glamlux_admin';
const PASSWORD = 'GlamLux@2026#';
```

**Severity**: 🔴 **CRITICAL**

**Impact**:
- Production WordPress admin credentials exposed in source code
- Accessible to anyone with repository access
- Used in automated audit/monitoring scripts

**Affected Scope**:
- Compromises production Railway deployment
- Allows unauthorized admin access
- Password likely used elsewhere

### Recommended Actions (IMMEDIATE)

1. **Change Password Immediately** in production WordPress
2. **Remove hardcoded credentials** from script:
   ```javascript
   const USERNAME = process.env.AUDIT_USER || '';
   const PASSWORD = process.env.AUDIT_PASSWORD || '';
   if (!USERNAME || !PASSWORD) {
       console.error('ERROR: AUDIT_USER and AUDIT_PASSWORD environment variables required.');
       process.exit(1);
   }
   ```
3. **Set via Railway environment variables** instead
4. **Audit git history** to determine who accessed the credentials
5. **Rotate all admin passwords** as precaution
6. **Add to .gitignore** any files containing credentials

---

## 6. Environment Variable Naming Consistency

### Observations

#### ✓ Consistent Patterns

| Prefix | Pattern | Examples |
|--------|---------|----------|
| `GLAMLUX_` | GlamLux-specific | `GLAMLUX_JWT_SECRET` |
| `WP_` | WordPress core | `WP_CACHE`, `WP_REDIS_*` |
| `MYSQL*` | MySQL (Railway) | `MYSQLHOST`, `MYSQLUSER` |
| `REDIS*` | Redis (Railway) | `REDISHOST`, `REDISPORT` |

#### ⚠️ Inconsistent Patterns

| Variable | Inconsistency | Recommendation |
|----------|---|---|
| `AUTH_KEY`, etc. | No prefix (WP auth keys mixed) | Consider `WP_AUTH_KEY` prefix for clarity |
| `MIGRATION_USER` | No standard prefix | Consider `GLAMLUX_MIGRATION_USER` |
| `MIGRATION_PASSWORD` | No standard prefix | Consider `GLAMLUX_MIGRATION_PASSWORD` |
| `AUDIT_USER` (implied) | Missing | Should be `GLAMLUX_AUDIT_USER` |
| `AUDIT_PASSWORD` (implied) | Hardcoded instead of env var | Should be `GLAMLUX_AUDIT_PASSWORD` |

### Recommendation

Adopt strict naming convention:
- **GlamLux-specific**: `GLAMLUX_*`
- **Infrastructure**: `MYSQL*`, `REDIS*`, `SENTRY_*` (match railway naming)
- **WordPress core**: `WP_*` or `WORDPRESS_*`

---

## 7. Missing Documentation & Best Practices

### Documentation Gaps

| Document | Missing Content |
|----------|---|
| [README.md](README.md) | `GLAMLUX_JWT_SECRET`, `MIGRATION_USER`, `MIGRATION_PASSWORD` |
| [docs/operational-documentation.md](docs/operational-documentation.md) | Complete environment variable reference |
| [docs/technical-documentation.md](docs/technical-documentation.md) | Security/credential management guidance |
| `.env.example` | Not present; should contain template for all vars |

### Additions Needed

1. **[docs/ENVIRONMENT_CONFIGURATION.md](docs/ENVIRONMENT_CONFIGURATION.md)** (NEW)
   - Comprehensive variable listing
   - Required vs. optional distinction
   - Example values and fallbacks
   - Security warnings

2. **.env.example** (NEW)
   ```env
   # ==== Database (Railway) ====
   MYSQLHOST=
   MYSQLPORT=3306
   MYSQLUSER=
   MYSQLPASSWORD=
   MYSQLDATABASE=
   
   # ==== Redis (Railway) ====
   REDISHOST=
   REDISPORT=6379
   REDIS_PASSWORD=
   
   # ==== GlamLux Plugin ====
   GLAMLUX_JWT_SECRET=
   
   # ==== WordPress Auth Keys ====
   AUTH_KEY=
   SECURE_AUTH_KEY=
   LOGGED_IN_KEY=
   NONCE_KEY=
   AUTH_SALT=
   SECURE_AUTH_SALT=
   LOGGED_IN_SALT=
   NONCE_SALT=
   
   # ==== Monitoring ====
   SENTRY_DSN=
   
   # ==== Migration (Node.js Scripts) ====
   MIGRATION_USER=
   MIGRATION_PASSWORD=
   MIGRATION_HOST=luxe-studio-glam-production.up.railway.app
   ```

3. **Update deployment checklist** in Railway/CI-CD pipeline

---

## 8. Summary Table: All Environment Variables

| # | Variable | Type | Defined In | Fallback | Error Handling | Documented | Risk Level |
|---|----------|------|-----------|----------|---|---|---|
| 1 | `GLAMLUX_JWT_SECRET` | GlamLux | wp-config-railway.php | wp_salt('auth') | Logged warning | ⚠️ Partial | 🟡 Medium |
| 2 | `MIGRATION_USER` | Script | None (runtime) | None | Exit code 1 | ✗ No | 🟡 Medium |
| 3 | `MIGRATION_PASSWORD` | Script | None (runtime) | None | Exit code 1 | ✗ No | 🔴 Critical* |
| 4 | `MYSQLHOST` | Infra | wp-config-railway.php | mysql.railway.internal | — | ✓ Yes | 🟢 Low |
| 5 | `MYSQLPORT` | Infra | wp-config-railway.php | 3306 | — | ✓ Yes | 🟢 Low |
| 6 | `MYSQLUSER` | Infra | wp-config-railway.php | root | — | ✓ Yes | 🟢 Low |
| 7 | `MYSQLPASSWORD` | Infra | wp-config-railway.php | None | Logged error | ✓ Yes | 🟡 Medium |
| 8 | `MYSQLDATABASE` | Infra | wp-config-railway.php | railway | — | ✓ Yes | 🟢 Low |
| 9 | `REDISHOST` | Infra | wp-config-railway.php | Empty (disabled) | — | ✓ Yes | 🟢 Low |
| 10 | `REDISPORT` | Infra | wp-config-railway.php | 6379 | — | ✓ Yes | 🟢 Low |
| 11 | `REDIS_PASSWORD` | Infra | wp-config-railway.php | None (optional) | — | ✓ Yes | 🟢 Low |
| 12 | `SENTRY_DSN` | Infra | class-glamlux-sentry.php | None (disabled) | Early return | ⚠️ Partial | 🟢 Low |
| 13 | `AUTH_KEY` | WP Core | wp-config-railway.php | Hardcoded | — | ✓ Yes | 🟡 Medium |
| 14 | `SECURE_AUTH_KEY` | WP Core | wp-config-railway.php | Hardcoded | — | ✓ Yes | 🟡 Medium |
| 15 | `LOGGED_IN_KEY` | WP Core | wp-config-railway.php | Hardcoded | — | ✓ Yes | 🟡 Medium |
| 16 | `NONCE_KEY` | WP Core | wp-config-railway.php | Hardcoded | — | ✓ Yes | 🟡 Medium |
| 17 | `AUTH_SALT` | WP Core | wp-config-railway.php | Hardcoded | — | ✓ Yes | 🟡 Medium |
| 18 | `SECURE_AUTH_SALT` | WP Core | wp-config-railway.php | Hardcoded | — | ✓ Yes | 🟡 Medium |
| 19 | `LOGGED_IN_SALT` | WP Core | wp-config-railway.php | Hardcoded | — | ✓ Yes | 🟡 Medium |
| 20 | `NONCE_SALT` | WP Core | wp-config-railway.php | Hardcoded | — | ✓ Yes | 🟡 Medium |

* **Note**: Risk is hardcoded in audit script (remote-audit.js), not the variable itself.

---

## 9. Recommendations & Action Items

### 🔴 CRITICAL (Do Immediately)

- [ ] **Remove hardcoded credentials** from [scripts/remote-audit.js](scripts/remote-audit.js)
- [ ] **Change production admin password** (glamlux_admin)
- [ ] **Audit git history** for credential exposure
- [ ] **Rotate all WordPress auth keys/salts** if repo was public

### 🟡 HIGH (Before Next Deployment)

- [ ] **Set GLAMLUX_JWT_SECRET** in Railway environment
- [ ] **Set MIGRATION_USER and MIGRATION_PASSWORD** for Node scripts
- [ ] **Ensure MYSQLPASSWORD is set** (currently has no fallback)
- [ ] **Create .env.example** file at repository root
- [ ] **Document all variables** in README or new ENVIRONMENT_CONFIGURATION.md

### 🟢 MEDIUM (Best Practices)

- [ ] Standardize environment variable naming (adopt GLAMLUX_ prefix consistently)
- [ ] Add to deployment checklist/runbook
- [ ] Consider secrets manager (Railway Secrets, HashiCorp Vault) for production
- [ ] Remove hardcoded auth key fallbacks; require environment variables
- [ ] Add pre-commit hook to detect secrets

### 🔵 LOW (Future Improvements)

- [ ] Add environment variable validation script
- [ ] Create dashboard/admin page showing loaded configuration
- [ ] Implement feature flags via environment variables
- [ ] Consider .env file support for local development

---

## 10. Testing Recommendations

### Environment Variables Checklist

```bash
# 1. Verify GlamLux-specific variables
wp config get GLAMLUX_JWT_SECRET
wp config get GLORIFIED_ENVIRONMENT

# 2. Check database connectivity
wp db check

# 3. Verify cache connectivity (if enabled)
wp cache status

# 4. Test JWT authentication
curl -X POST https://mysite.com/glamlux/v1/auth \
  -H "Authorization: Bearer $TEST_JWT" \
  -d '{"user_id":1}'

# 5. Verify Sentry (if configured)
wp error_log "Test Sentry error"

# 6. Test migration script
MIGRATION_USER=testuser MIGRATION_PASSWORD=testpass node scripts/run-migration.js
```

---

## 11. Reference Files

### Configuration Files
- [wp-config-railway.php](wp-config-railway.php) - Primary configuration
- [wp-config-local-sample.php](wp-config-local-sample.php) - Local development template
- [README.md](README.md) - Deployment guide

### Plugin Files
- [wp-content/plugins/glamlux-core/glamlux-core.php](wp-content/plugins/glamlux-core/glamlux-core.php) - Plugin initialization
- [wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php](wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php) - JWT handling
- [wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php](wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php) - Error tracking

### Script Files
- [scripts/run-migration.js](scripts/run-migration.js) - Migration script
- [scripts/remote-audit.js](scripts/remote-audit.js) - ⚠️ CONTAINS HARDCODED CREDENTIALS

### Documentation
- [docs/technical-documentation.md](docs/technical-documentation.md)
- [docs/project-file-hierarchy.md](docs/project-file-hierarchy.md)
- [docs/operational-documentation.md](docs/operational-documentation.md)

---

## End of Report
