# GlamLux WP-CLI Health Check Runbook

## Command

Run from the WordPress container or host where WP-CLI is available:

```bash
wp glamlux health
```

## What this validates

The `wp glamlux health` command validates the following gates:

1. **Required DB schema for operations monitoring**
   - Required tables exist.
   - Required columns exist.
   - Required indexes exist.
   - `gl_memberships` contains either `status` or `is_active` for active membership checks.

2. **Operations summary endpoint contract**
   - `GET /wp-json/glamlux/v1/operations/summary` responds with HTTP `200`.
   - Root shape keys and nested `database` / `operations` keys exist.
   - Expected field types are validated.

3. **`operations.active_memberships` output type**
   - Ensures `operations.active_memberships` is an integer.

4. **SQL warning/error guardrail during check**
   - Requires `WP_DEBUG_LOG` to be enabled and readable.
   - Fails if new SQL/database warnings are written to debug log while the check runs.

## Expected output

### Success

```text
Success: GlamLux health check passed.
```

### Failure

On failure, command exits non-zero and prints all failing checks, for example:

```text
Error: GlamLux health check failed:
- Missing required table: wp_gl_staff
- Missing required column: wp_gl_appointments.status
- /wp-json/glamlux/v1/operations/summary returned HTTP 500
- operations.active_memberships is not an integer.
- SQL warnings/errors detected in debug log while running health check.
```

## CI / staging gate behavior

The GitHub Actions workflow includes **WP-CLI GlamLux Health Gate** as a required job before downstream checks. The job:

1. Starts Docker stack.
2. Enables `WP_DEBUG` and `WP_DEBUG_LOG`.
3. Runs `wp glamlux health --allow-root` inside `glamlux_wp`.
4. Fails the pipeline if any health condition fails.

## Remediation paths

### Missing table / column / index

- Run/verify plugin activation migrations:
  - Re-activate `glamlux-core` plugin.
  - Verify `glamlux_db_version` and migration scripts.
- For index drift, apply the relevant migration script (for example `scripts/migrate-v4-indexes.php`) using WP-CLI eval-file if needed.

### Endpoint contract failures

- Confirm `GlamLux_Operations_Controller` route registration.
- Confirm current user context has required capabilities (`administrator`, `manage_options`, or GlamLux platform/franchise management capabilities).
- Validate operations summary payload keys in `GlamLux_Service_Operations::get_operations_summary()`.

### `active_memberships` type failures

- Verify `gl_memberships` schema includes `status` or `is_active`.
- Validate no plugin customization is casting output to string/float.

### SQL warnings in debug log

- Inspect `wp-content/debug.log` for:
  - unknown column/table,
  - bad SQL syntax,
  - WordPress database errors.
- Fix offending query and rerun:

```bash
wp glamlux health
```

Only promote to staging/production once the command passes cleanly.
