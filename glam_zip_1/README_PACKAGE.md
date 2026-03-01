# glam_zip_1 package

This folder contains a **lean, push-safe** package for running Luxe Studio Glam in local WordPress + WP-CLI workflows.

## Why this package was trimmed
The initial package included many default WordPress assets (fonts/images/screenshots from stock themes/plugins), which introduced binary files and made Git push/review harder.

This version keeps only the core items needed for audits/edits and removes binary-heavy defaults.

## Included folders
- `wp-content/plugins/glamlux-core/` (main custom business plugin)
- `wp-content/plugins/redis-cache/` (cache plugin source)
- `wp-content/themes/glamlux-theme/` (custom site theme)
- `wp-content/object-cache.php` and core `index.php` placeholders
- `nginx/` (web server config)
- `scripts/` (automation/audit helpers)
- `tests/` (validation suites)
- `wp/` (placeholder mount point)

## Included root files
- `docker-compose.yml`, `Dockerfile`, `install.sh`
- `wp-config-railway.php`, `nginx.conf`
- `composer.json`, `phpunit.xml.dist`, `phpstan.neon.dist`
- `README.md`

## Typical local workflow
1. Start stack: `docker compose up -d`
2. Enter WordPress container: `docker compose exec wordpress bash`
3. Run WP-CLI checks, for example:
   - `wp core version`
   - `wp plugin list`
   - `wp eval 'echo "audit ok";'`
