# 🏗 GLAMLUX2LUX — ENTERPRISE TECHNICAL ARCHITECTURE

## 1️⃣ HIGH-LEVEL ARCHITECTURE MODEL

We will use a **Modular Monolith Architecture** inside WordPress.

**Why?**

- Faster development
- Easier maintenance
- Clear domain separation
- Scalable to microservices later

### 🧱 SYSTEM LAYERS

```text
┌──────────────────────────────────────┐
│           Presentation Layer         │
│  (Theme + Dashboards + REST Client)  │
└──────────────────────────────────────┘
┌──────────────────────────────────────┐
│         Application Layer            │
│  glamlux-franchise-core Plugin       │
│  - CRM Module                        │
│  - EMS Module                        │
│  - Franchise Module                  │
│  - Inventory Module                  │
│  - Payment Module                    │
│  - Reporting Module                  │
│  - Auth & RBAC                       │
└──────────────────────────────────────┘
┌──────────────────────────────────────┐
│            Domain Layer              │
│  Business Logic + Services           │
│  CommissionService                   │
│  BookingService                      │
│  RevenueService                      │
│  PayrollService                      │
└──────────────────────────────────────┘
┌──────────────────────────────────────┐
│              Data Layer              │
│  Custom MySQL Tables + Redis Cache   │
└──────────────────────────────────────┘
┌──────────────────────────────────────┐
│           Infrastructure Layer       │
│  VPS / Nginx / Redis / CDN / S3      │
└──────────────────────────────────────┘
```

---

## 2️⃣ PLUGIN ARCHITECTURE STRUCTURE

Your core system must be one enterprise plugin: `wp-content/plugins/glamlux-franchise-core/`

### Folder Structure

```text
glamlux-franchise-core/
│
├── glamlux-franchise-core.php
│
├── /core
│   ├── loader.php
│   ├── activator.php
│   ├── deactivator.php
│
├── /modules
│   ├── crm/
│   ├── ems/
│   ├── franchise/
│   ├── inventory/
│   ├── payments/
│   ├── reporting/
│   ├── bookings/
│
├── /services
│   ├── CommissionService.php
│   ├── BookingService.php
│   ├── RevenueService.php
│   ├── PayrollService.php
│   ├── TerritoryService.php
│
├── /repositories
│   ├── LeadRepository.php
│   ├── FranchiseRepository.php
│   ├── AppointmentRepository.php
│
├── /rest
│   ├── routes.php
│   ├── controllers/
│
├── /admin
│   ├── dashboards/
│   ├── list-tables/
│
└── /helpers
```

This keeps business logic separate, DB logic separate, UI separate, and REST separate. This is enterprise discipline.

For a deeply detailed file-by-file breakdown mapping every actual PHP file and operation in the system, refer to the **[Project File Hierarchy](project-file-hierarchy.md)** document.

---

## 3️⃣ DOMAIN-DRIVEN MODULE DESIGN

Each module should be isolated.

### CRM MODULE

- **Responsibilities:** Lead capture, Assignment logic, Follow-up engine, Conversion tracking
- **Core Service:** `LeadService`
- **Rules:** Auto-assign by territory, Log every status change, Track conversion funnel

### EMS MODULE

- **Core Services:** `AttendanceService`, `CommissionService`, `PayrollService`
- **Logic:** When Appointment → Completed: Fetch commission %, Calculate payout, Store payroll record, Trigger notification.

### FRANCHISE MODULE

- **Core Services:** `FranchiseService`, `TerritoryService`, `PricingService`
- **Rules:** Territory conflict detection, Central override logic, Franchise-level service pricing, Revenue aggregation.

### PAYMENT MODULE

We isolate payment logic. Never mix payment logic inside booking controller. Use event-driven approach.

- **Core Services:** `PaymentGatewayInterface`, `RazorpayService`, `StripeService`, `WebhookHandler`

---

## 4️⃣ EVENT-DRIVEN FLOW (CRITICAL)

Instead of tightly coupled logic, we use domain events. This prevents messy code dependencies.

**Example:**

- `AppointmentCompletedEvent`
- **Listeners:** `CommissionService`, `InventoryService`, `RevenueService`, `NotificationService`

---

## 5️⃣ DATA ARCHITECTURE

All core business tables are custom. **NEVER use `wp_posts` for operational data.**

### Database Grouping

- **Franchise Domain:** `wp_gl_franchises`, `wp_gl_salons`, `wp_gl_territories`
- **CRM Domain:** `wp_gl_leads`, `wp_gl_clients`, `wp_gl_followups`
- **Operations Domain:** `wp_gl_appointments`, `wp_gl_payroll`, `wp_gl_commissions`, `wp_gl_inventory`
- **Analytics Domain:** `wp_gl_financial_reports`, `wp_gl_metrics_cache`

### Redis Usage

- **Cache:** Salon list, Services, Monthly reports, Dashboard metrics
- **Never cache transactional writes.**

---

## 6️⃣ ROLE & ACCESS ARCHITECTURE

We implement RBAC with capability mapping. Each role maps to allowed modules, API routes, and dashboard views.

- Use `current_user_can()` AND enforce at REST level. Never rely only on UI restriction.

---

## 7️⃣ REST API ARCHITECTURE

All major modules expose REST endpoints (`/glamlux/v1/leads`, `/glamlux/v1/bookings`, `/glamlux/v1/payroll`, `/glamlux/v1/reports`).

Each route must:

1. Validate nonce/JWT
2. Validate capability
3. Sanitize input
4. Use service layer

---

## 8️⃣ PAYMENT FLOW ARCHITECTURE

**Booking Flow:**

```text
Frontend → Create appointment (pending) → Redirect to Razorpay → Webhook → Verify signature → Mark appointment paid → Trigger AppointmentCompletedEvent
```

**Royalty Flow:**

```text
Monthly cron → Calculate % revenue → Generate invoice → Auto-charge via stored payment token → Update ledger
```

---

## 9️⃣ INFRASTRUCTURE ARCHITECTURE

**Recommended Production Stack:**

- DigitalOcean 4GB VPS
- Nginx + PHP-FPM
- Redis
- MySQL 8
- Cloudflare CDN
- S3 backups

---

## 🔐 SECURITY ARCHITECTURE

Must include:

- Prepared SQL statements
- Nonce verification
- REST rate limiting
- Webhook signature verification
- Daily backups
- Role isolation
- GDPR export/delete

---

## 🔄 SCALING STRATEGY

- **Short-Term:** Modular Monolith (WordPress)
- **Mid-Term:** Extract Reporting Engine → Microservice, Payment Engine → External service, CRM → Dedicated CRM SaaS layer
- **Long-Term:** Move to Headless WordPress, React frontend, Dedicated API server

---

## 📊 PERFORMANCE TARGETS

- **Database queries:** < 200ms
- **API response:** < 300ms
- **Dashboard load:** < 2s
- **Booking transaction:** < 800ms
- **5,000 concurrent users sustained**

---

## 🚀 DEPLOYMENT TOPOLOGY

```text
Client
 ↓
Cloudflare CDN
 ↓
Nginx Reverse Proxy
 ↓
PHP-FPM
 ↓
WordPress Core + Plugin
 ↓
MySQL
 ↓
Redis
 ↓
S3 Backups
```

---

## 🧠 WHY THIS WORKS

Because business logic is isolated, data is separated from WP core, operations are event-driven, Redis optimizes read speeds, API endpoints are structured, roles are hardened, payments are decoupled, and the infrastructure is inherently scalable.

This is not a template-based WordPress site. This is a **Franchise Operating Platform**.

**We now have:**

- Modular architecture
- Clean domain separation
- Scalable data layer
- Enterprise-ready RBAC
- Payment orchestration
- Event-driven business engine
- Clear microservice migration path

This is how you build a system that scales like Domino's.
