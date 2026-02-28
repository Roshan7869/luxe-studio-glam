# GlamLux2Lux User Documentation

## 1. Super Admin Guide

- **Accessing Dashboard**: Login via `/wp-admin`. The "GlamLux Platform" menu offers an aggregate view of all franchises.
- **Reporting**: Drill down into global revenue across `wp_gl_financial_reports`.

## 2. Franchise Admin Guide

- **Managing Salons**: Your interface is scoped strictly to "My Salon." You can add new service pricing.
- **Staff Management**: Add staff members to your Salon via the "Manage Staff" submenu. Adjust their commission rates.
- **Payroll**: Generate period-based payroll using the automated calculator which inspects `wp_gl_appointments` and applies the staff's specific base rate.

## 3. Staff Guide

- **Appointments Viewer**: Log in to view your daily schedule dynamically fetched from `wp_gl_appointments`.

## 4. Client Guide

- **Booking**: Select from the tailored menu. Real-time availability checks ensure no staff double bookings.
- **Memberships**: Purchase via WooCommerce. Automatically activates special pricing and perks within the system. SMS confirmations will arrive via Exotel upon successful payment.
