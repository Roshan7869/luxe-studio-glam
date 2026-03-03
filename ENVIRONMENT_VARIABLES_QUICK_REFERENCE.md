# GlamLux Environment Variables - Quick Reference & Action Items

## 🔴 CRITICAL SECURITY ISSUE

### Hardcoded Credentials Found!

**File**: `scripts/remote-audit.js` (lines 5-6)
```javascript
const USERNAME = 'glamlux_admin';
const PASSWORD = 'GlamLux@2026#';
```

**Immediate Actions Required**:
1. ⚠️ **CHANGE PASSWORD NOW** in production WordPress
2. Remove credentials from source code - convert to env vars
3. Check git history for exposure timeline
4. Consider compromised; rotate all related passwords

**Fix Script** (proposed):
```javascript
const USERNAME = process.env.AUDIT_USER || '';
const PASSWORD = process.env.AUDIT_PASSWORD || '';

if (!USERNAME || !PASSWORD) {
    console.error('ERROR: AUDIT_USER and AUDIT_PASSWORD environment variables required.');
    process.exit(1);
}
```

---

## 📋 GlamLux-Specific Environment Variables

### GLAMLUX_JWT_SECRET
- **Purpose**: JWT authentication for mobile apps
- **Defined**: `wp-config-railway.php` (line 68)
- **Used In**: `glamlux-core/includes/class-glamlux-jwt-auth.php`
- **Fallback**: `wp_salt('auth')` ⚠️ (degraded security)
- **Status**: ✓ Documented in code; ✗ Missing from README
- **Action**: SET in Railway environment; ADD to deployment checklist

### MIGRATION_USER & MIGRATION_PASSWORD
- **Purpose**: Authenticate Node.js migration script to WordPress
- **Used**: `scripts/run-migration.js` (lines 5-6)
- **Defined**: Not defined anywhere - Runtime only
- **Fallback**: None - script exits if missing
- **Status**: ✗ Not documented
- **Action**: ADD to Redis.md; SET in deployment pipeline; Update .env.example

---

## 📊 Infrastructure Environment Variables Status

| Variable | Required? | Fallback | Documented? | Issue |
|----------|----------|---------|---|---|
| MYSQLHOST | Yes | mysql.railway.internal | ✓ | None |
| MYSQLUSER | Yes | root | ✓ | Should require env var |
| MYSQLPASSWORD | Yes | None | ✓ | Error if missing - good |
| MYSQLPORT | No | 3306 | ✓ | None |
| MYSQLDATABASE | No | railway | ✓ | None |
| REDISHOST | No | Empty (disabled) | ✓ | None |
| REDISPORT | No | 6379 | ✓ | None |
| REDIS_PASSWORD | No | None | ✓ | None |
| GLAMLUX_JWT_SECRET | Yes | wp_salt ('auth') | ⚠️ | Should require env var |
| SENTRY_DSN | No | None (disabled) | ⚠️ | Optional; considered |

---

## 🔧 Configuration File Locations

### WordPress Config
- **Production**: `wp-config-railway.php`
- **Local Sample**: `wp-config-local-sample.php`

### Plugin Init
- **Location**: `wp-content/plugins/glamlux-core/glamlux-core.php`
- **Defines**: `GLAMLUX_VERSION`, `GLAMLUX_PLUGIN_DIR`, etc.

### JWT Handling
- **Location**: `wp-content/plugins/glamlux-core/includes/class-glamlux-jwt-auth.php`
- **Purpose**: Validates `GLAMLUX_JWT_SECRET` constant

### Error Tracking
- **Location**: `wp-content/plugins/glamlux-core/Core/class-glamlux-sentry.php`
- **Uses**: `SENTRY_DSN` environment variable

---

## 📝 Documentation Gaps

### Missing Documents
- [ ] `ENVIRONMENT_CONFIGURATION.md` - Comprehensive reference
- [ ] `.env.example` - Template for all variables

### Files to Update
- [ ] `README.md` - Add GLAMLUX_JWT_SECRET to env vars section
- [ ] `docs/operational-documentation.md` - Environment variable guide
- [ ] `docs/deployment-checklist.md` - Pre-deployment verification steps

---

## ✅ Recommended Environment Variables Template

Create `.env.example` at repository root:

```env
# ========== GLAMLUX PLUGIN ==========
GLAMLUX_JWT_SECRET=your-super-secret-jwt-key-here

# ========== DATABASE (Railway) ==========
MYSQLHOST=mysql.railway.internal
MYSQLPORT=3306
MYSQLUSER=railway_user
MYSQLPASSWORD=your-secure-password
MYSQLDATABASE=railway

# ========== REDIS CACHE (Railway) ==========
REDISHOST=redis.railway.internal
REDISPORT=6379
REDIS_PASSWORD=your-redis-password

# ========== WORDPRESS AUTH KEYS/SALTS ==========
# Generate strong random values at https://api.wordpress.org/secret-key/1.1/salt/
AUTH_KEY=put-your-unique-phrase-here
SECURE_AUTH_KEY=put-your-unique-phrase-here
LOGGED_IN_KEY=put-your-unique-phrase-here
NONCE_KEY=put-your-unique-phrase-here
AUTH_SALT=put-your-unique-phrase-here
SECURE_AUTH_SALT=put-your-unique-phrase-here
LOGGED_IN_SALT=put-your-unique-phrase-here
NONCE_SALT=put-your-unique-phrase-here

# ========== ERROR TRACKING (optional) ==========
SENTRY_DSN=https://your-sentry-dsn@sentry.io/project-id

# ========== MIGRATION SCRIPTS ==========
MIGRATION_USER=glamlux_admin
MIGRATION_PASSWORD=your-secure-admin-password
MIGRATION_HOST=luxe-studio-glam-production.up.railway.app

# ========== WORDPRESS OPTIONS ==========
WP_ENVIRONMENT_TYPE=production
DISABLE_WP_CRON=true
```

---

## 🚀 Pre-Deployment Verification Checklist

```bash
# 1. Set all required environment variables
echo "GLAMLUX_JWT_SECRET: $GLAMLUX_JWT_SECRET" && test ! -z "$GLAMLUX_JWT_SECRET" && echo "✓" || echo "✗ MISSING"
echo "MIGRATION_USER: $MIGRATION_USER" && test ! -z "$MIGRATION_USER" && echo "✓" || echo "✗ MISSING"
echo "MIGRATION_PASSWORD: $MIGRATION_PASSWORD" && test ! -z "$MIGRATION_PASSWORD" && echo "✓" || echo "✗ MISSING"

# 2. Verify MySQL connectivity
wp db check

# 3. Verify Redis cache (if configured)
[ ! -z "$REDISHOST" ] && wp cache status || echo "Redis not configured"

# 4. Check plugin activation
wp plugin status glamlux-core

# 5. Test JWT secret is loaded
wp config get GLAMLUX_JWT_SECRET
```

---

## 📞 Related Documentation

Generated from comprehensive audit:
- Full report: `ENVIRONMENT_VARIABLES_AUDIT.md`
- Deployment guide: `README.md`
- Technical architecture: `docs/technical-documentation.md`
- Operational runbook: `docs/operational-documentation.md`

---

## Summary Statistics

- **Total Environment Variables**: 20+
- **GlamLux-Specific Variables**: 3 primary
- **Critical Issues Found**: 1 (hardcoded credentials)
- **Undocumented Variables**: 4
- **Documentation Coverage**: 60% (12 of 20 documented)

---

**Last Updated**: March 3, 2026
**Next Review**: After all CRITICAL items are resolved
