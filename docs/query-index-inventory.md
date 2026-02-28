# Query + Index Inventory (Operations, Reporting, Payroll, Booking)

This inventory captures high-frequency read paths and the composite indexes required to keep them index-friendly in staging/production.

## Operations service

- `SELECT COUNT(id) FROM wp_gl_appointments WHERE DATE(appointment_time)=CURDATE()`
- `SELECT COUNT(id) FROM wp_gl_appointments WHERE status IN ('pending','scheduled')`
- `SELECT COUNT(id) FROM wp_gl_leads WHERE status IN ('new','open','follow_up')`

## Reporting service

- Revenue rollups by salon/franchise with completed appointments.
- Monthly revenue trend:
  `WHERE status='completed' AND appointment_time >= DATE_SUB(NOW(), INTERVAL ? MONTH)`

## Payroll service

- Commission scan by staff + period window:
  `WHERE staff_id=? AND period_start>=? AND period_end<=?`
- Pending liability:
  `WHERE status='pending' [AND salon_id=?]`
- Payroll list filter:
  `WHERE p.salon_id=? AND p.status=? ORDER BY p.id DESC LIMIT 200`

## Booking service

- Slot availability:
  `WHERE staff_id=? AND appointment_time=? AND status NOT IN ('cancelled','refunded')`
- Client appointment history:
  `WHERE client_id=? ORDER BY appointment_time DESC LIMIT 50`

## Composite index baseline

- `gl_appointments (salon_id, appointment_time, status)`
- `gl_clients (membership_id, membership_expiry)`
- `gl_payroll (staff_id, period_start, period_end, status)`
- `gl_leads (status, assigned_to, created_at)`

These are enforced in migration version 6 with `SHOW INDEX` guards.
