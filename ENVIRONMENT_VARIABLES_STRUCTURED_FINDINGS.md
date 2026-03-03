# GlamLux Environment Variables - Structured Findings

**Report Date**: March 3, 2026  
**Audit Scope**: Complete codebase environment variable inventory

---

## VARIABLE 1: GLAMLUX_JWT_SECRET

**Category**: Authentication / GlamLux Plugin

### Definition Location
- **File**: [wp-config-railway.php](wp-config-railway.php)
- **Lines**: 65-68
- **Code**:
  ```php
  if (!getenv('GLAMLUX_JWT_SECRET')) {
      error_log('[GlamLux] GLAMLUX_JWT_SECRET env var is not set. JWT will fall back to wp_salt() (lower security).');
  }
  define('GLAMLUX_JWT_SECRET', getenv('GLAMLUX_JWT_SECRET') ?: wp_salt('auth'));
  ```

### Usage Locations
| File | Line | Context |
|------|------|---------|
| [wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php](wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php#L58-L64) | 58-64 | HS256 JWT encoding/decoding for mobile app auth |
| [wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php](wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php#L9) | 9 | Documentation comment in class docblock |

### Fallback/Error Handling
- **Fallback Value**: `wp_salt('auth')`
- **Error Logging**: YES - logs `error_log('[GlamLux] GLAMLUX_JWT_SECRET env var is not set...')`
- **Security Impact**: ⚠️ MEDIUM - Fallback reduces token stability if WordPress salts change
- **Transient Warning**: Logs warning once per day via `set_transient('gl_jwt_secret_warning', true, DAY_IN_SECONDS)`

### Documentation Status
- ✓ Documented in [wp-config-railway.php](wp-config-railway.php) line 64 comment
- ✗ NOT in [README.md](README.md) environment variables section
- ✗ NOT in [docs/technical-documentation.md](docs/technical-documentation.md)
- ✗ NOT in [docs/operational-documentation.md](docs/operational-documentation.md)

### Recommendation
- ADD to [README.md](README.md) deployment guide
- REQUIRE in production (remove fallback or make fallback non-functional)
- ADD to pre-deployment checklist
- USE unique 64+ character string, not derived from other salts

---

## VARIABLE 2: MIGRATION_USER

**Category**: Infrastructure / Node.js Scripts

### Definition Location
- **No permanent definition** - Runtime environment variable only
- **File**: [scripts/run-migration.js](scripts/run-migration.js)
- **Lines**: 5, 9
- **Code**:
  ```javascript
  const USERNAME = process.env.MIGRATION_USER;
  
  if (!USERNAME || !PASSWORD) {
      console.error('ERROR: MIGRATION_USER and MIGRATION_PASSWORD environment variables must be set.');
      process.exit(1);
  }
  ```

### Usage Locations
| File | Line | Context |
|------|------|---------|
| [scripts/run-migration.js](scripts/run-migration.js#L5) | 5 | Read from process.env |
| [scripts/run-migration.js](scripts/run-migration.js#L28) | 28 | Used in login request: `querystring.stringify({ log: USERNAME, pwd: PASSWORD, ... })` |

### Fallback/Error Handling
- **Fallback Value**: NONE
- **Error Handling**: Process exits with code 1 if missing
- **Error Message**: `"ERROR: MIGRATION_USER and MIGRATION_PASSWORD environment variables must be set."`
- **Severity**: FATAL - script cannot run without both variables

### Documentation Status
- ✗ NOT documented anywhere
- ✗ NOT in [README.md](README.md)
- ✗ NOT in [docs/](docs/)
- ⚠️ Only inline error message in code

### Related Variables
- `MIGRATION_HOST` - Server hostname (line 4, default: `luxe-studio-glam-production.up.railway.app`)
- `MIGRATION_PASSWORD` - See next section

### Recommendation
- ADD to [README.md](README.md) migration/deployment section
- CREATE `.env.example` with placeholder
- UPDATE [docs/operational-documentation.md](docs/operational-documentation.md)
- ADD to Railway deployment environment setup

---

## VARIABLE 3: MIGRATION_PASSWORD

**Category**: Infrastructure / Node.js Scripts

### Definition Location
- **No permanent definition** - Runtime environment variable only
- **File**: [scripts/run-migration.js](scripts/run-migration.js)
- **Lines**: 6, 9
- **Code**:
  ```javascript
  const PASSWORD = process.env.MIGRATION_PASSWORD;
  
  if (!USERNAME || !PASSWORD) {
      console.error('ERROR: MIGRATION_USER and MIGRATION_PASSWORD environment variables must be set.');
      process.exit(1);
  }
  ```

### Usage Locations
| File | Line | Context |
|------|------|---------|
| [scripts/run-migration.js](scripts/run-migration.js#L6) | 6 | Read from process.env |
| [scripts/run-migration.js](scripts/run-migration.js#L28) | 28 | Used in login request: `querystring.stringify({ log: USERNAME, pwd: PASSWORD, ... })` |

### Fallback/Error Handling
- **Fallback Value**: NONE
- **Error Handling**: Process exits with code 1 if missing
- **Error Message**: `"ERROR: MIGRATION_USER and MIGRATION_PASSWORD environment variables must be set."`
- **Severity**: FATAL - script cannot run without both variables

### Documentation Status
- ✗ NOT documented anywhere
- ✗ NOT in [README.md](README.md)
- ✗ NOT in [docs/](docs/)
- ⚠️ Only inline error message in code

### CRITICAL SECURITY ISSUE ⚠️
**⚠️ WARNING**: Hardcoded credentials found in [scripts/remote-audit.js](scripts/remote-audit.js) (lines 5-6):
```javascript
const USERNAME = 'glamlux_admin';
const PASSWORD = 'GlamLux@2026#';
```
This is a CRITICAL security vulnerability - production credentials exposed in source code.

### Recommendation
- Same as MIGRATION_USER (ADD documentation, .env.example, etc.)
- **IMMEDIATE**: Change production admin password
- **IMMEDIATE**: Remove hardcoded credentials from [scripts/remote-audit.js](scripts/remote-audit.js)
- Add audit user credentials to environment variables instead

---

## UNDOCUMENTED INFRASTRUCTURE VARIABLES

### SENTRY_DSN
- **File**: [wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php](wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php#L13)
- **Line**: 13
- **Usage**: Error tracking via Sentry platform
- **Fallback**: None (Sentry disabled if missing)
- **Error Handling**: Early return if DSN not set
- **Status**: ⚠️ Optional; minimal documentation

### WP_REDIS_PREFIX & WP_REDIS_SELECTIVE_FLUSH
- **File**: [wp-content/object-cache.php](wp-content/object-cache.php#L234-L239)
- **Lines**: 234, 238
- **Usage**: Redis cache configuration overrides
- **Fallback**: Defaults used if env vars not set
- **Status**: ⚠️ Advanced; for cache optimization only

### HTTP_X_APP_USER
- **File**: [wp-content/object-cache.php](wp-content/object-cache.php#L249)
- **Line**: 249
- **Usage**: Legacy fallback for Redis prefix (used by some cloud providers)
- **Status**: ⚠️ Legacy; not recommended for new deployments

---

## OTHER WordPress/Standard Variables (DOCUMENTED)

These are well-documented and follow standard patterns:

### MySQL Variables
- `MYSQLHOST` ✓ Documented; fallback: `mysql.railway.internal`
- `MYSQLPORT` ✓ Documented; fallback: `3306`
- `MYSQLUSER` ✓ Documented; fallback: `root`
- `MYSQLPASSWORD` ✓ Documented; NO fallback (error if missing)
- `MYSQLDATABASE` ✓ Documented; fallback: `railway`

### Redis Variables
- `REDISHOST` ✓ Documented; fallback: empty (cache disabled)
- `REDISPORT` ✓ Documented; fallback: `6379`
- `REDIS_PASSWORD` ✓ Documented; optional

### WordPress Auth Keys/Salts (All fallbacks are hardcoded generic values)
- `AUTH_KEY` ✓ Documented; fallback: `X!kP9#mNz2Lq&v7YrTb@cJeWsAuFdGh0`
- `SECURE_AUTH_KEY` ✓ Documented; fallback: `D5jZnQ3!xHpRwS8oVcMtLa4UbEyKfIg6`
- `LOGGED_IN_KEY` ✓ Documented; fallback: `Bm1eN^7zXsYvTcP#QrLdA9wFkHuJgZ2!`
- `NONCE_KEY` ✓ Documented; fallback: `Wr6uSt!0BpXqN5JvDyCfEl3aIMGkHZ8#`
- `AUTH_SALT` ✓ Documented; fallback: `Jc4dP!2eR7sL0uVhXnBqWtKmZAyFgO9!`
- `SECURE_AUTH_SALT` ✓ Documented; fallback: `Gf8aZ#3iM5xQ2sLTdCwYnEbRuHpVkO1!`
- `LOGGED_IN_SALT` ✓ Documented; fallback: `Hn9kT!1rU6vM3bPeXcLfIwSzJyDQgA7#`
- `NONCE_SALT` ✓ Documented; fallback: `Py2wF#5jS8nK0qAeZbTxGcMrVuDHiOL4!`

⚠️ **Note**: Hardcoded fallbacks for auth keys/salts are NOT recommended for production.

---

## NAMING CONSISTENCY ANALYSIS

### Consistent Patterns ✓
- `GLAMLUX_*` = GlamLux-specific (e.g., `GLAMLUX_JWT_SECRET`)
- `WP_*` = WordPress core (e.g., `WP_CACHE`, `WP_REDIS_*`)
- `MYSQL*` = MySQL (Railway convention) (e.g., `MYSQLHOST`)
- `REDIS*` = Redis (Railway convention) (e.g., `REDISHOST`)

### Inconsistent Patterns ⚠️
- `MIGRATION_USER`, `MIGRATION_PASSWORD` - No prefix. Should be `GLAMLUX_MIGRATION_USER`, `GLAMLUX_MIGRATION_PASSWORD`
- `AUDIT_USER`, `AUDIT_PASSWORD` (implied from hardcoded credentials) - Should follow same pattern
- `AUTH_*`, `NONCE_*` - No `WP_` prefix (WordPress convention)

### Recommendation
Adopt strict naming:
- **All GlamLux variables**: `GLAMLUX_*` prefix
- **All script variables**: `GLAMLUX_*` prefix
- **Infrastructure**: Match Railway/service provider conventions
- **WordPress**: Maintain `WP_*` prefix for new variables

---

## HARDCODED SECRETS/CREDENTIALS FOUND

### 🔴 CRITICAL: [scripts/remote-audit.js](scripts/remote-audit.js) Lines 5-6

```javascript
const USERNAME = 'glamlux_admin';
const PASSWORD = 'GlamLux@2026#';
```

- **Risk Level**: 🔴 CRITICAL
- **Impact**: Production admin credentials exposed in source code
- **Accessible To**: Anyone with repo access (including git history)
- **Affected Service**: Production WordPress deployment on Railway
- **Timeline**: Credentials have been committed to git history

**Immediate Actions**:
1. CHANGE admin password in production NOW
2. Audit git history: `git log --all --source --oneline --grep="GlamLux@2026"`
3. Remove credentials from source code
4. Ask: "Who has accessed this credentials?"
5. Consider repository as compromised until remediation complete

**Fix Approach**:
```javascript
const USERNAME = process.env.AUDIT_USER || '';
const PASSWORD = process.env.AUDIT_PASSWORD || '';

if (!USERNAME || !PASSWORD) {
    console.error('ERROR: AUDIT_USER and AUDIT_PASSWORD environment variables required.');
    process.exit(1);
}
```

---

## DOCUMENTATION GAPS SUMMARY

### Files Missing Documentation
| Item | Should Be In | Currently In | Status |
|------|---|---|---|
| GLAMLUX_JWT_SECRET | README.md | wp-config-railway.php only | ✗ Missing |
| MIGRATION_USER | README.md, docs/ | Nowhere | ✗ Missing |
| MIGRATION_PASSWORD | README.md, docs/ | Nowhere | ✗ Missing |
| Certificate | .env.example | Not present | ✗ Missing |
| Env var checklist | docs/deployment-checklist.md | Not present | ✗ Missing |

### Proposed New Files
1. **`.env.example`** - Template for all environment variables
2. **`docs/ENVIRONMENT_CONFIGURATION.md`** - Comprehensive reference guide

---

## CONSISTENCY VIOLATIONS (Hardcoded Fallbacks)

| Variable | Hardcoded Fallback | Risk | Recommendation |
|----------|---|---|---|
| Auth/Salt keys | 8 hardcoded values | 🟡 MEDIUM | Should require env vars in production |
| GLAMLUX_JWT_SECRET | wp_salt('auth') | 🟡 MEDIUM | Should require env var |

These fallbacks reduce production security by allowing the application to start with weak/known secrets.

---

## SUMMARY STATISTICS

| Metric | Count |
|--------|-------|
| **Total Environment Variables Found** | 20+ |
| **GlamLux-Specific Variables** | 3 documented + 1 implied (audit) |
| **Infrastructure Variables** | 8 |
| **WordPress Core Variables** | 8 |
| **Documented in Code** | 16 |
| **Documented in README** | 8 |
| **Documented in docs/** | 3 |
| **Undocumented** | 4 |
| **NO Fallback** | 2 (MIGRATION_USER, MIGRATION_PASSWORD) |
| **Generic Fallback** | 8 (Auth/Salt keys) |
| **Critical Security Issues** | 1 (hardcoded credentials in remote-audit.js) |
| **Naming Inconsistencies** | 5 (inconsistent prefixes) |

---

## Cross-Reference Index

### Configuration Loading Chain
1. Environment variables set in Railway dashboard
2. Read by [wp-config-railway.php](wp-config-railway.php) via `getenv()`
3. Defined as WordPress constants (`define()`)
4. Used in plugins/themes via `defined()` checks
5. Logged if missing via `error_log()`

### Plugin Initialization
1. [glamlux-core.php](wp-content/plugins/glamlux-core/glamlux-core.php) - Entry point
2. [class-glamlux-jwt-auth.php](wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php) - Uses GLAMLUX_JWT_SECRET
3. [class-glamlux-sentry.php](wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php) - Uses SENTRY_DSN

### Script Locations
1. [scripts/run-migration.js](scripts/run-migration.js) - Uses MIGRATION_USER, MIGRATION_PASSWORD
2. [scripts/remote-audit.js](scripts/remote-audit.js) - ⚠️ Contains hardcoded credentials
3. [scripts/k6-load-test.js](scripts/k6-load-test.js) - Uses BASE_URL via k6 `__ENV`

---

**Report Generated**: March 3, 2026
**Review Recommended**: Immediately for CRITICAL items; within 1 week for HIGH priority
