# GlamLux2Lux Technical Documentation

## 1. System Architecture

GlamLux2Lux utilizes a custom layered architecture built on top of WordPress to facilitate scalable franchise operations.

- **Layer 1: Infrastructure**: Designed for VPS Hosting, Nginx, PHP 8.2+, MySQL 8+, Redis Object Cache.
- **Layer 2: Core Platform**: WordPress serves as the application framework (Authentication, Routing, Roles).
- **Layer 3: Business Logic**: The custom `glamlux-core` plugin handles all domain-specific behavior.

## 2. Database Schema

To bypass standard WordPress meta-table bottlenecks, `glamlux-core` provisions 10 custom relational tables upon activation:

- `wp_gl_franchises`
- `wp_gl_salons`
- `wp_gl_staff`
- `wp_gl_memberships`
- `wp_gl_clients`
- `wp_gl_appointments`
- `wp_gl_payroll`
- `wp_gl_product_sales`
- `wp_gl_service_logs`
- `wp_gl_financial_reports`

## 3. Plugin Architecture

- **Activation Hook** (`GlamLux_Activator`): Defines robust `dbDelta` routines for structural integrity.
- **Admin Interface Layer** (`GlamLux_Admin`): Scopes access to operational modules based on 4 distinct custom roles (`glamlux_super_admin`, `glamlux_franchise_admin`, `glamlux_staff`, `glamlux_client`).
- **Integration Layer**: `GlamLux_WC_Hooks` securely captures successful WooCommerce transactions to provision `wp_gl_memberships` and dispatch SMS via `GlamLux_Exotel_API`.

## 4. Real-time Implementations

- **AJAX Endpoints**: Registered inside `class-glamlux-ajax.php`, allowing dynamic conflict-checking against `wp_gl_appointments` before checkout.
- **Async Processes**: `class-glamlux-cron.php` utilizes WP-Cron to purge or renew memberships natively.
