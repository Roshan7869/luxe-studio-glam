-- Add composite indexes for optimal lookup performance when querying tables by joined fields

-- For staff fetching on the homepage/team page (needs salon_id and active status)
ALTER TABLE wp_gl_staff ADD INDEX IF NOT EXISTS idx_salon_active (salon_id, is_active);

-- For retrieving before/after pictures keyed by appointments
ALTER TABLE wp_gl_service_logs ADD INDEX IF NOT EXISTS idx_appointment (appointment_id);

-- For admin financial stats tracking when generating monthly analytics
ALTER TABLE wp_gl_product_sales ADD INDEX IF NOT EXISTS idx_salon_date (salon_id, sale_date);

-- For membership tier listing filtering out inactive legacy tiers
ALTER TABLE wp_gl_memberships ADD INDEX IF NOT EXISTS idx_active_tier (is_active, tier_level);
