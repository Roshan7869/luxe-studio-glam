# How to Import GlamLux into Local WP

Follow these steps to migrate the site from the current workspace to Local WP (Flywheel).

## Step 1: Export the Database

You need a SQL dump of your current database. If you have WP-CLI installed on your environment, run:

```bash
wp db export glam-local.sql
```

Otherwise, use PHPMyAdmin or the Railway dashboard to export your `railway` database as a `.sql` file and place it in the root of this directory (`d:\Luxe_studio_ glam`).

## Step 2: Prepare the Package

Local WP imports sites most easily from a `.zip` archive.

1. Select the `wp-content` folder and your `glam-local.sql` file.
2. (Optional) Include the `wp-config-local-sample.php` if you want a reference.
3. Zip these items together.

## Step 3: Import into Local WP

1. Open the **Local** application.
2. Drag and drop your `.zip` file into the Local window.
3. Follow the import wizard:
   - Select a site name.
   - Choose the "Preferred" or "Custom" environment (matches PHP 8.1+ and MySQL 8.0+).

## Step 4: Seed Enterprise Data (Optional)

If you want to start with a fresh set of salon and staff data:

1. Log in to your local WordPress admin.
2. Add `?seed_now=1` to the URL (e.g., `http://glamlux.local/wp-admin/?seed_now=1`).
3. The plugin will automatically populate the database with the enterprise dataset.

---
**Note:** The `glamlux-core` plugin is pre-configured to handle local environments automatically.
