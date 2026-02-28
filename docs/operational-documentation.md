# GlamLux2Lux Operational Documentation

## 1. Backup Policy

- Maintain daily automated backups of the entire `.sql` database, with specific focus on custom `wp_gl_*` tables.
- Media uploads should be synced to an offsite S3-compatible service weekly.

## 2. Security Policy

- The `GlamLux_Admin` explicitly checks against `manage_glamlux_platform` and `manage_glamlux_franchise` capabilities; ensure no standard WP plugins interfere with the capabilities array.
- Database access requests for Analytics tools should operate purely as `READ-ONLY` replicas.

## 3. Maintenance Plan

- Updating WordPress Core is safe, as the plugin runs outside the procedural core.
- When expanding `dbDelta` structure in future versions, testing must occur on a staging VPS to prevent locking production tables.

## 4. Performance Checklist

- Confirm Redis Object Caching is enabled on the server.
- Confirm Exotel SMS failure logs are rotating correctly inside the `debug.log`.
