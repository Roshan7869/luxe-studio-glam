# 📁 GlamLux2Lux Project File Hierarchy

This document explains the tree-like structure of the `glamlux-core` plugin, detailing the specific role and responsibility of each directory and critical file. The architecture strictly adheres to Domain-Driven Design (DDD) and Low-Level Design (LLD) principles, ensuring that each operation is managed securely and predictably.

---

## 🌳 Directory Tree Overview

```text
glamlux-core/
├── glamlux-core.php
├── Core/
├── Rest/
├── services/
├── repositories/
├── admin/
│   └── modules/
├── includes/
└── scripts/
```

---

## 📄 Root Files

### `glamlux-core.php`

- **Role:** The main plugin bootstrap file.
- **Responsibility:**
  - Defines global plugin constants (`GLAMLUX_VERSION`, `GLAMLUX_PLUGIN_DIR`).
  - Sequentially loads all infrastructure files, Repositories, Services, and REST Controllers in the exact order required to satisfy dependencies.
  - Registers the global `GlamLux_Logger` and handles plugin activation/deactivation hooks.
  - Acts as the entry point connecting WordPress to the GlamLux Application Layer.

---

## 📂 `Core/` — Infrastructure & Events

Contains the fundamental building blocks of the application that are not specific to any one business domain but provide necessary systems.

- **`class-activator.php` / `class-deactivator.php`**
  - **Role:** Lifecycle hooks.
  - **Responsibility:** Creates database tables on installation, registers custom capabilities to WordPress roles (`glamlux_franchise_admin`, `glamlux_staff`), and sets up initial data.
- **`class-event-dispatcher.php`**
  - **Role:** The Event Bus.
  - **Responsibility:** Decouples modules. Instead of the Booking system directly calling Payroll when an appointment is completed, it triggers an event `glamlux_appointment_completed`. The Event Dispatcher catches this and alerts any listening systems.
- **`class-glamlux-logger.php`**
  - **Role:** Enterprise structured logger.
  - **Responsibility:** Splits logs into isolated files (e.g., `logs/glamlux-payment.log`, `logs/glamlux-cron.log`) by severity (Info, Warning, Error), protecting the standard WordPress debug log.

---

## 📂 `Rest/` — The Presentation Layer (API)

Contains **Thin Controllers**. No business logic or database queries exist here.

- **`class-rest-manager.php`**
  - **Role:** API Router.
  - **Responsibility:** Initializes and registers all controllers with WordPress.
- **`class-base-controller.php`**
  - **Role:** Abstract Controller.
  - **Responsibility:** Provides shared security methods (e.g., `require_logged_in`, `require_staff_or_admin`) used by all child controllers to instantly secure routes.
- **`class-[domain]-controller.php` (e.g., `class-booking-controller.php`, `class-gdpr-controller.php`)**
  - **Role:** API Endpoint Handlers.
  - **Responsibility:**
    1. Receive incoming HTTP requests (`GET`, `POST`, `DELETE`).
    2. Extract parameters (e.g., `user_id`, `service_id`).
    3. Pass parameters directly to the **Service Layer**.
    4. Format the final output as a standardized JSON response.

---

## 📂 `services/` — The Application (Business) Layer

The "brain" of the operation. Contains **Services**. No database queries (`$wpdb`) exist here—only business rules.

- **`class-glamlux-service-[domain].php` (e.g., `class-glamlux-service-booking.php`, `class-glamlux-service-payroll.php`)**
  - **Role:** Business Logic Orchestrator.
  - **Responsibility:**
    - Example (Booking): Ensures the requested time slot is valid, checks if the staff member is available, handles the booking transaction, and requests the Repository to save the record.
    - Example (Payroll): Reads attendance and commission data, calculates payouts, and instructs the Repository to create payslips.
- **`class-glamlux-payment-[gateway].php` (e.g., `class-glamlux-payment-razorpay.php`)**
  - **Role:** Payment Integration.
  - **Responsibility:** Implements the `PaymentGatewayInterface` to standardize interactions with external financial APIs securely.

---

## 📂 `repositories/` — The Data Access Layer

Contains **Repositories**. This is the **ONLY** place in the entire system where queries (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) to the database (`$wpdb`) are permitted.

- **`class-glamlux-repo-[domain].php` (e.g., `class-glamlux-repo-appointment.php`, `class-glamlux-repo-franchise.php`)**
  - **Role:** Database Wrapper.
  - **Responsibility:** Executes raw SQL efficiently. Protects the application from SQL injection using `$wpdb->prepare()`. Exposes clean methods like `get_active_salons()` to the Service Layer so that Services do not need to understand SQL structure.

---

## 📂 `admin/` — WordPress Dashboard Integration

Manages the user interface components displayed inside the WordPress Admin Panel for super admins and franchise owners.

- **`class-glamlux-admin.php`**
  - **Role:** Admin Menu Builder.
  - **Responsibility:** Registers the left-hand sidebar menus (e.g., "GlamLux2Lux", "Appointments", "Payroll") based on user permissions.
- **`modules/class-glamlux-[feature].php`**
  - **Role:** UI Renderers.
  - **Responsibility:** Creates the HTML tables, forms, and views seen inside the admin area. They interact with Services to fetch data for display.

---

## 📂 `includes/` — Global Glue & Native WP Integration

Classes that bridge the custom app with native WordPress and WooCommerce features.

- **`class-glamlux-content-manager.php`**
  - **Role:** Governance Engine.
  - **Responsibility:** Registers Custom Post Types (`gl_announcement`, `gl_offer`), handles the "Franchise Assignment" sidebar meta box, and strictly scopes franchise owners to edit only their assigned content using native WP hooks (`save_post`, `pre_get_posts`).
- **`class-glamlux-cron.php`**
  - **Role:** Background Task Scheduler.
  - **Responsibility:** Maps WP-Cron timers (`daily`, `monthly`) to Domain Services. Example: Every month, it asks `GlamLux_Service_Payroll` to automatically execute the payroll batch calculation.
- **`class-glamlux-wc-hooks.php`**
  - **Role:** WooCommerce Bridge.
  - **Responsibility:** Listens for WooCommerce checkout completions and links product purchases to internal entities (like activating a Membership).

---

## 📂 `scripts/` — Maintenance & Migrations

One-off tools and database administration scripts not loaded during normal application execution.

- **`migrate-v[X]-[feature].php` (e.g., `migrate-v4-indexes.php`)**
  - **Role:** Schema Evolver.
  - **Responsibility:** Safely alters database tables over time (e.g., adding foreign keys, indexing columns like `appointment_id` or `client_id` for massive speed improvements) without losing user data.

---

## 🔄 How an Operation is Managed (Example: Booking an Appointment via REST)

1. **Request Arrives:** The mobile app sends a `POST` request to `/glamlux/v1/bookings`.
2. **REST Controller (`class-booking-controller.php`):**
   - Uses `require_staff_or_admin` base logic to verify the user JWT.
   - Extracts `service_id` and `appointment_time` and passes them to `GlamLux_Service_Booking->book_appointment_via_api()`.
3. **Domain Service (`class-glamlux-service-booking.php`):**
   - Contains the core business rules. It checks if the slot is free.
   - Tells the Repository layer to save the data.
4. **Repository (`class-glamlux-repo-appointment.php`):**
   - Takes the data, prepares a secure SQL `INSERT` statement, and commits it into the `wp_gl_appointments` table. Returns the new ID.
5. **Event Dispatcher (`class-event-dispatcher.php`):**
   - The Service triggers `glamlux_appointment_created`. Anything listening (like Notification rules) runs independently.
6. **Response Returns:**
   - The REST Controller replies with a clean `{"success": true, "appointment_id": 123}` JSON payload back to the mobile app.

This pattern absolutely guarantees that bug tracing is instantaneous, performance is optimized, and scaling the system later requires zero rewrites of the core engine.
