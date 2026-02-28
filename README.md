# 💇‍♀️ Luxe Studio Glam - B2B Franchise Management System

**Luxe Studio Glam** is a robust, highly-customized WordPress architecture designed for real-time B2B franchise operations, staff management, and client appointment scheduling.

This platform bypasses traditional WordPress bottlenecks by utilizing a custom relational database schema, ensuring scalability for multi-salon and horizontal franchise setups.

---

## 🏗️ System Architecture

Built on a layered tech stack, the system is designed to handle high transaction volumes efficiently:

- **Infrastructure**: Dockerized environment tailored for VPS/PaaS (Railway), utilizing Nginx/Apache, PHP 8.2+, MySQL 8+, and Redis Object Cache.
- **Core Framework**: WordPress provides the underlying routing, robust authentication mechanism, and role-based access control.
- **Business Logic Layer**: The proprietary `glamlux-core` plugin handles all domain-specific features, data relation, and API integrations.

---

## 🗄️ Database & Schema

To achieve enterprise-grade performance, `glamlux-core` provisions **10 custom relational tables** upon activation, avoiding standard `wp_postmeta` slowdowns:

1. `wp_gl_franchises` - Multi-tenant franchise configuration
2. `wp_gl_salons` - Physical salon locations & capacity
3. `wp_gl_staff` - Employee profiles, roles, and availability
4. `wp_gl_memberships` - Tiered subscription data
5. `wp_gl_clients` - Centralized customer repository
6. `wp_gl_appointments` - Real-time scheduling & conflict prevention
7. `wp_gl_payroll` - Commission & salary tracking
8. `wp_gl_product_sales` - Granular POS inventory logs
9. `wp_gl_service_logs` - Historical service performance
10. `wp_gl_financial_reports` - Aggregated financial analytics

---

## 🧩 Plugin Architecture (`glamlux-core`)

The heart of the system is the bespoke core plugin:

- **Activation (`GlamLux_Activator`)**: Ensures database structural integrity via strict `dbDelta` routines during initialization.
- **Access Control (`GlamLux_Admin`)**: Implements rigid, scoped access to the dashboard using 4 distinct custom roles:
  - `glamlux_super_admin` (Global master control)
  - `glamlux_franchise_admin` (Regional/Salon control)
  - `glamlux_staff` (Schedule & client viewing)
  - `glamlux_client` (Booking & membership portal)
- **Integrations (`GlamLux_WC_Hooks`)**: Seamlessly connects WooCommerce transactions to instantly provision `wp_gl_memberships`.
- **Communications**: Dispatch automated SMS notifications via the Exotel API integration (`GlamLux_Exotel_API`).

---

## ⚡ Real-Time Operations

- **AJAX Endpoints**: Registered natively to allow dynamic, real-time schedule conflict-checking against `wp_gl_appointments` before a client confirms checkout.
- **Async Processing**: Leverages WP-Cron (`class-glamlux-cron.php`) to handle background tasks like purging expired memberships or processing renewals without blocking the main UI thread.

---

## 🚀 Deployment (Railway)

The repository is configured for modern, ephemeral container deployment via **Railway**.

### Prerequisites (Railway Plugins)

- MySQL 8.0+
- Redis

### Quick Start

1. Create a **New Project** in Railway and deploy from this GitHub repository.
2. The provided `Dockerfile` uses the `wordpress:apache` image to serve the application and install necessary PHP extensions (PDO MySQL, Redis, GD, etc).
3. Add the **MySQL** and **Redis** plugins to your Railway environment.
4. Configure the following Environment Variables in the WordPress service:

```env
WORDPRESS_DB_HOST=<railway_mysql_host>
WORDPRESS_DB_USER=<user>
WORDPRESS_DB_PASSWORD=<password>
WORDPRESS_DB_NAME=<db_name>

DISABLE_WP_CRON=true
WP_ENVIRONMENT_TYPE=production

WP_REDIS_HOST=<railway_redis_host>
WP_REDIS_PORT=6379
```


### Enterprise Demo Dataset Seeder

To load the full visual + operational demo dataset (services, before/after logs, leads, memberships, salons, staff, payroll, inventory, and reports), run:

```bash
wp eval-file wp-content/plugins/glamlux-core/scripts/seed-enterprise-visual-dataset.php
```

This seeder is idempotent (uses `REPLACE`) and designed for staging/demo refresh workflows.


For the exact **39-record visual dataset pack** (6 services, 6 logs, 6 product sales, 6 leads, 6 memberships, 3 salons, 3 staff, 3 financial reports), run:

```bash
wp eval-file wp-content/plugins/glamlux-core/scripts/seed-visual-dataset-39.php
```

### Important Production Notes

- **Storage**: Railway provides *ephemeral* storage. For persistent media uploads (`wp-content/uploads`), you **must** configure an S3 bucket or Cloudflare R2 via an offload plugin.
- **Cron Jobs**: Since system cron is disabled (`DISABLE_WP_CRON=true`), create a Railway Scheduled Job that pings `wp cron event run --due-now` every 5 minutes.
- **Object Cache**: Ensure the Redis Object Cache plugin is enabled after deployment for optimal database read speeds.

---
*Generated for Luxe Studio Glam Franchise Operations.*
