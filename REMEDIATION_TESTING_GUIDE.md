# GlamLux Remediation — Quick Testing Guide

## Phase 1: Architecture Validation

### Test: Services Admin Module
```bash
# Verify services admin loads without SQL errors
- Navigate to wp-admin/admin.php?page=glamlux-services
- Create new service: Should use GlamLux_Service_Service
- Edit service: Should not see any raw wpdb queries
- Verify franchise overrides load correctly
```

---

## Phase 2: Concurrency Testing

### Test 1: Booking Race Condition Prevention
```javascript
// Simulate concurrent booking requests on same staff/time slot
// Step 1: Send 50 simultaneous booking requests for 10:00-11:00 on same staff
// Expected: Only 1 booking succeeds; others fail with "slot already taken"
// Verify: No duplicate bookings created despite concurrent requests

// Terminal:
npm run k6 -- scripts/k6-load-test.js --vus 50 --duration 2m
```

### Test 2: Payroll Batch Idempotency
```php
// Manual test
$payroll = new GlamLux_Service_Payroll();

// Run batch
$result1 = $payroll->run_monthly_batch('2026-03-01', '2026-03-31', 1);
echo $result1['status']; // "ok", payslips = 50

// Simulate cache flush
delete_transient('payroll_2026-03-01_2026-03-31_1');

// Run again immediately
$result2 = $payroll->run_monthly_batch('2026-03-01', '2026-03-31', 1);
echo $result2['status']; // "already_ran" (DB check prevents duplicate)
```

### Test 3: Lead Audit Trail Atomicity
```php
// Simulate update_status with forced followup insert failure
// Expected: Both status update AND followup insert rollback
// Verify: DB shows no orphaned status changes without audit trail
```

---

## Phase 3: Multi-Tenant Isolation

### Test 1: Staff List Filtering (CRITICAL)
```text
SETUP:
- Create two franchises: "Franchise A" and "Franchise B"
- Create 5 staff in Franchise A, 5 staff in Franchise B
- Create users "admin_a" and "admin_b" as franchise admins for each

TEST:
1. Log in as admin_a (Franchise A manager)
   - Navigate to Manage Staff
   - Verify: Only 5 staff from Franchise A visible
   - Verify: Zero staff from Franchise B visible
   - Verify: Cannot see competitors' commission rates

2. Log in as admin_b (Franchise B manager)
   - Navigate to Manage Staff
   - Verify: Only 5 staff from Franchise B visible
   - Verify: Different staff list than admin_a

3. Log in as Super Admin
   - Verify: All 10 staff visible
   - Verify: Can see all franchises' data
```

### Test 2: Staff Deactivation Role Removal
```php
// BEFORE deactivation:
$user = get_user_by('ID', 42);
echo in_array('glamlux_staff', $user->roles) ? 'YES' : 'NO'; // YES

// Deactivate staff
$staff_service = new GlamLux_Service_Staff();
$staff_service->deactivate(5);

// AFTER deactivation:
$user = get_user_by('ID', 42);
echo in_array('glamlux_staff', $user->roles) ? 'YES' : 'NO'; // NO

// Try login as deactivated user
// Expected: "Insufficient permissions" error
```

---

## Phase 4: Frontend Error Handling

### Test 1: Booking Form Error Response (MANUAL)
```html
<!-- Open developer console, then: -->

// Force API to return 400
fetch('/wp-json/glamlux/v1/book', {
    method: 'POST',
    body: JSON.stringify({ invalid_data: true })
})
.then(r => r.ok ? r.json() : Promise.reject(r))
.catch(e => console.log('ERROR HANDLER FIRED:', e))

<!-- Expected: See red error toast, NOT green success -->
```

### Test 2: Franchise Application Form (MANUAL)
```html
<!-- Open page-franchise-apply.php, inspect network: -->

1. Fill form with valid data
2. Observer Network tab
3. Force API to return 5XX error
4. Expected: Red error toast appears
5. Verify: Form NOT marked as submitted
```

---

## Phase 5: Redis Infrastructure

### Test: Memory Pressure Eviction
```bash
# Monitor Redis memory usage
redis-cli INFO memory

# Fill cache with large transients
for i in {1..1000}; do
  wp transient set "test_$i" "large_data_$(date)" 3600
done

# Monitor while memory approaches 256MB
redis-cli INFO memory

# Expected: Automatic LRU eviction kicks in
#   - Memory stays under 256MB
#   - Oldest/least-used transients evicted first
#   - WordPress continues functioning normally
```

---

## Quick Sanity Checks (5 min)

```bash
# 1. Check plugin loads without errors
php -l glamlux-core/glamlux-core.php

# 2. Verify new classes are loaded
grep -r "class GlamLux_Service_Service" wp-content/plugins/glamlux-core/

# 3. Check database transaction support
mysql -e "SELECT 'START TRANSACTION; COMMIT;' | mysql -u user -p db"

# 4. Verify Redis config applied
grep "WP_REDIS_MAXMEMORY_POLICY" wp-config-railway.php
```

---

## Rollback Plan (if needed)

```bash
# Revert files to previous version
git checkout HEAD -- \
  repositories/class-glamlux-repo-service.php \
  services/class-glamlux-service-service.php \
  admin/modules/class-glamlux-services-admin.php \
  services/class-glamlux-service-payroll.php \
  services/class-glamlux-service-lead.php \
  services/class-glamlux-service-staff.php \
  repositories/class-glamlux-repo-staff.php \
  repositories/class-glamlux-repo-appointment.php \
  admin/modules/class-glamlux-staff.php \
  themes/glamlux-theme/footer.php \
  themes/glamlux-theme/page-franchise-apply.php \
  wp-config-railway.php \
  glamlux-core.php

# Clear caches
wp cache flush
redis-cli FLUSHDB
```

---

## Success Criteria

- ✅ No SQL queries executed outside Service/Repository layers
- ✅ Concurrent booking attempts properly serialized
- ✅ Payroll batches cannot be duplicated even after cache flush
- ✅ Franchise admins cannot see competitor staff data
- ✅ Deactivated staff lose WordPress role and dashboard access
- ✅ Frontend shows error toasts for failed API calls
- ✅ Redis continues operating under memory pressure
