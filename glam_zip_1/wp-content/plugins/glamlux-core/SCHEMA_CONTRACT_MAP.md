# GlamLux Repository Schema Contract Map

This map documents each table contract currently used by classes in `repositories/` and the canonical table columns expected by those queries.

## Repository → table map

| Repository | Tables used |
|---|---|
| `class-glamlux-repo-appointment.php` | `gl_appointments`, `gl_clients`, `gl_salons`, `gl_service_logs`, `gl_staff` |
| `class-glamlux-repo-attendance.php` | `gl_attendance`, `gl_shifts` |
| `class-glamlux-repo-franchise.php` | `gl_franchises`, `gl_salons`, `gl_service_pricing`, `gl_appointments` |
| `class-glamlux-repo-gdpr.php` | `gl_clients`, `gl_appointments` |
| `class-glamlux-repo-inventory.php` | `gl_inventory`, `gl_salons` |
| `class-glamlux-repo-lead.php` | `gl_leads`, `gl_followups` |
| `class-glamlux-repo-membership.php` | `gl_memberships`, `gl_membership_purchases`, `gl_clients` |
| `class-glamlux-repo-payroll.php` | `gl_attendance`, `gl_payroll`, `gl_staff`, `gl_appointments` |
| `class-glamlux-repo-revenue.php` | `gl_metrics_cache`, `gl_appointments`, `gl_clients` |
| `class-glamlux-repo-territory.php` | `gl_franchises`, `gl_salons`, `gl_appointments` |

## Canonical contracts for alignment work

### `gl_shifts`
Canonical contract (used by attendance logic):
- `staff_id`, `salon_id`, `shift_date`
- `start_time`, `end_time`, `status`

Decision: repository queries now use `start_time/end_time/status` to match canonical schema.

### Payroll/staff contract
Canonical contract for payroll/staff integration:
- `gl_staff.base_salary`
- `gl_payroll.total_pay`
- `gl_payroll.appointment_id`
- `gl_payroll.paid_at`

Decision: migration guards ensure these columns exist with safe defaults.

## Migration guard policy

Added activation-time, idempotent guards that only add missing columns:
- `gl_staff.base_salary decimal(10,2) DEFAULT '0.00' NOT NULL`
- `gl_payroll.appointment_id bigint(20) DEFAULT NULL`
- `gl_payroll.total_pay decimal(10,2) DEFAULT '0.00' NOT NULL`
- `gl_payroll.paid_at datetime DEFAULT NULL`
- `gl_shifts.start_time time DEFAULT NULL`
- `gl_shifts.end_time time DEFAULT NULL`
- `gl_shifts.status varchar(50) DEFAULT 'scheduled' NOT NULL`

These guards are intended to keep existing installs compatible while preserving expected repository/service behavior.
