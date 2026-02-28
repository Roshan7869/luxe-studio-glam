# 🔎 Enterprise WordPress Full-System Integration Audit Prompt

Use this prompt with AI code scanners, internal architecture reviews, DevOps audits, and recurring release readiness checks.

---

## PROMPT (copy/paste exactly)

You are acting as a **Senior Enterprise WordPress Architect and Platform Auditor**.

Your job is to execute a **cross-layer integration and operability audit** for this WordPress platform.

Evaluate each layer independently, then evaluate interaction quality across layers.

### Context
- Platform type: enterprise franchise operations on WordPress.
- Core plugin: `wp-content/plugins/glamlux-core`.
- Active custom theme: `wp-content/themes/glamlux-theme`.
- Architecture intent: Controller → Service → Repository → DB.
- Output must be deterministic, risk-ranked, and remediation-ready.

---

## 1) FRONTEND LAYER INSPECTION

Inspect:
- Theme templates and partials (`header.php`, `footer.php`, `front-page.php`, `functions.php`, `style.css`)
- JS runtime behavior (scroll engine, observers, modal handlers, fetch calls)
- CSS performance and animation strategy
- Asset loading / render-blocking patterns
- REST/AJAX consumption discipline
- Admin-configurable behavior usage (`get_theme_mod`, options, localized config)

Validate:
- No direct DB queries in frontend templates/scripts
- Dynamic data loaded via REST/localized config only
- No duplicate/conflicting animation or scroll engines
- No conflicting smooth-scroll strategies (CSS smooth + JS engine collision)
- No blocking third-party scripts without fallback strategy
- Animations use GPU-safe properties (`transform`, `opacity`) for hot paths
- Nonce usage is correct where auth endpoints are called

Check:
- Hardcoded data that should be admin-controlled
- Feature toggles controllable via WP Admin
- Cache behavior compatibility with frontend state
- Accessibility regressions (reduced-motion compatibility)

Output:
- `Frontend Integration Score (0-10)`
- `Top 5 frontend risks`
- `Action plan with file-level remediation`

---

## 2) BACKEND SERVICE LAYER INSPECTION

Inspect:
- `services/`, `repositories/`, `Rest/`, `includes/`, `admin/`, `Core/`
- REST controllers and AJAX handlers
- Cron handlers and event dispatcher
- Middleware/system-mode implementation

Validate:
- No `$wpdb` usage outside repository layer unless explicitly justified and documented
- No business logic inside REST/AJAX controllers
- No direct SQL in admin modules for domain workflows (prefer service/repository)
- DB writes use prepared statements
- Cron jobs delegate to services (no fat cron handlers)
- Event dispatch has no circular loops or recursive cascade hazards
- Heavy operations abstracted and cache-aware

Check:
- Transactional safety for money/booking workflows
- Concurrency safety for booking slots and webhook handling
- Feature-flag coupling and environment mode isolation

Output:
- `Backend Compliance Score (0-10)`
- `Violations grouped by architecture rule`
- `Refactor priority roadmap`

---

## 3) DATA LAYER & DATABASE ROBUSTNESS AUDIT

Inspect:
- Schema creation/migrations (`Core/class-activator.php`, migration scripts)
- Index coverage for hot paths
- FK/index consistency
- Query patterns in repositories
- Transient/object-cache strategy and fallbacks

Validate:
- Hot-path queries avoid anti-index patterns where possible
- FK and frequently filtered columns are indexed
- Monetary values use DECIMAL discipline
- Structured data not hidden in uncontrolled TEXT blobs unless justified
- No avoidable full-table scans on hot endpoints
- EXPLAIN plan evidence for top query paths
- Migration scripts are idempotent and safe on re-run

Check:
- Redis/object cache compatibility and graceful fallback behavior
- Webhook idempotency and deduplication strategy
- Cache invalidation discipline

Output:
- `Data Layer Robustness Score (0-10)`
- `Index/query remediation table`
- `Migration safety verdict`

---

## 4) WORDPRESS ADMIN OPERABILITY AUDIT

Inspect:
- Admin menu registration and module discoverability
- Settings pages and capability boundaries
- Nonce handling and form security
- Dashboard/reporting operability
- System mode controls and feature toggles

Validate:
- Core business operations are manageable from admin UI
- No critical operation is CLI-only without admin equivalent
- No manual DB edits required for normal operations
- No orphaned cron/event processes
- Capability-protected toggles for dangerous actions

Check:
- Franchise settings operability
- Payroll management operability
- Membership management operability
- Webhook/payment config operability
- Safe system-mode toggling and rollback

Output:
- `Admin Manageability Score (0-10)`
- `UI/UX operability gaps`
- `Admin-first remediation list`

---

## 5) CROSS-LAYER INTERACTION INSPECTION

Trace and validate these paths:
- Frontend → REST → Service → Repository → DB
- Admin → Service → Repository → DB
- Cron → Service → Repository
- Webhook → Validation → Service/Repository
- Middleware → filters/hooks → domain isolation

Validate:
- No repository bypass from controllers/admin
- No REST direct-to-DB shortcuts
- No admin module embedding business logic that bypasses services
- No race hazards between cron and live API writes

Simulate (conceptually or via tests):
- 100 concurrent booking attempts
- Cron execution during live bookings
- Duplicate webhook retries
- Redis/cache outage fallback
- Mode switch (demo/live) during active traffic

Output:
- `Interaction Stability Score (0-10)`
- `Race-condition matrix`
- `Isolation/contract violations`

---

## 6) SECURITY & STABILITY VALIDATION

Check:
- `permission_callback` exists for every REST route
- Input sanitization + output escaping discipline
- Nonce verification for admin state-changing actions
- Capability checks for sensitive operations
- SQL injection protections
- Idempotency protection for externally triggered writes
- Background task lock/duplication controls

Output:
- Risk classification by severity: `P0 / P1 / P2`
- `Exploitability + blast radius`
- `Immediate hardening steps`

---

## 7) ENTERPRISE SCALABILITY ASSESSMENT

Evaluate:
- Horizontal scalability constraints
- Shared-state assumptions across nodes
- Cron behavior in multi-node deployments
- Webhook replay safety
- Async-readiness and queue migration path
- Cache dependency and failure mode behavior

Output:
- `Infrastructure Maturity Score (0-10)`
- `Concurrency Readiness Score (0-10)`
- `Async Readiness Score (0-10)`
- `Scale blockers`

---

## 8) FINAL OUTPUT FORMAT (MANDATORY)

Return exactly:

1. `Overall Score: X/10`
2. `Layer Breakdown`
   - Frontend:
   - Backend:
   - Data:
   - Admin:
   - Security:
   - Concurrency:
3. `Critical Issues (P0)`
4. `High Risk Issues (P1)`
5. `Medium Improvements (P2)`
6. `Low Optimizations`
7. `90-Day Remediation Roadmap`
8. `Final Verdict`
   - Demo Ready? (Yes/No)
   - Enterprise Ready? (Yes/No)
   - Multi-Franchise Ready? (Yes/No)

Additional requirements:
- Include file-path evidence for each major finding.
- Include concrete remediation steps and owner role (Backend, Frontend, DevOps, QA).
- Flag any architecture drift from intended Controller→Service→Repository layering.
- Do not provide generic advice; every conclusion must map to observed implementation evidence.

---

## Optional execution helpers (for human reviewers)

Run these before auditing:

```bash
php -l wp-content/plugins/glamlux-core/glamlux-core.php
php -l wp-content/themes/glamlux-theme/header.php
php -l wp-content/themes/glamlux-theme/footer.php
rg -n "register_rest_route\(|permission_callback|\$wpdb|wp_nonce|current_user_can|prepare\(" wp-content/plugins/glamlux-core
rg -n "add_action\(|add_filter\(|do_action\(|apply_filters\(" wp-content/plugins/glamlux-core
```

Use EXPLAIN for known hot queries in repository classes and include query-plan findings in the Data section.
