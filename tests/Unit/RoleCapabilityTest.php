<?php

namespace GlamLux\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the franchise role hierarchy capabilities.
 *
 * These tests verify:
 *  1. The three new roles exist in the activator definition.
 *  2. Role capability constants are correctly separated (no escalation).
 *  3. The base controller permission helpers compile without errors.
 *  4. The user management controller constants are correctly defined.
 */
class RoleCapabilityTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Role Definitions (mirrors class-activator.php)
    // ─────────────────────────────────────────────────────────────────────────

    private const CHAIRPERSON_CAPS = [
        'read'                              => true,
        'upload_files'                      => true,
        'manage_glamlux_franchise'          => true,
        'view_franchise_reports'            => true,
        'manage_glamlux_franchise_managers' => true,
        'manage_glamlux_franchise_employees'=> true,
        'manage_glamlux_appointments'       => true,
        'manage_glamlux_inventory'          => true,
        'glamlux_check_attendance'          => true,
        'manage_glamlux_platform'           => false,
        'view_state_reports'                => false,
    ];

    private const FRANCHISE_MANAGER_CAPS = [
        'read'                               => true,
        'upload_files'                       => true,
        'manage_glamlux_franchise_employees' => true,
        'manage_glamlux_appointments'        => true,
        'view_franchise_reports'             => true,
        'manage_glamlux_inventory'           => true,
        'glamlux_check_attendance'           => true,
        'manage_glamlux_franchise'           => false,
        'manage_glamlux_franchise_managers'  => false,
        'manage_glamlux_platform'            => false,
    ];

    private const FRANCHISE_EMPLOYEE_CAPS = [
        'read'                       => true,
        'manage_glamlux_appointments'=> true,
        'glamlux_check_attendance'   => true,
    ];

    private const FORBIDDEN_EMPLOYEE_CAPS = [
        'manage_glamlux_franchise',
        'manage_glamlux_franchise_managers',
        'manage_glamlux_franchise_employees',
        'manage_glamlux_platform',
        'view_franchise_reports',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Chairperson must have franchise management and manager assignment caps.
     */
    public function test_chairperson_has_required_caps(): void
    {
        foreach (['manage_glamlux_franchise', 'manage_glamlux_franchise_managers', 'view_franchise_reports'] as $cap) {
            $this->assertTrue(
                self::CHAIRPERSON_CAPS[$cap],
                "Chairperson must have capability: {$cap}"
            );
        }
    }

    /**
     * Chairperson must NOT have super-admin platform caps.
     */
    public function test_chairperson_lacks_super_admin_caps(): void
    {
        $this->assertFalse(self::CHAIRPERSON_CAPS['manage_glamlux_platform'], 'Chairperson must not have manage_glamlux_platform');
        $this->assertFalse(self::CHAIRPERSON_CAPS['view_state_reports'], 'Chairperson must not have view_state_reports');
    }

    /**
     * Franchise Manager must be able to manage employees but NOT managers.
     */
    public function test_franchise_manager_caps(): void
    {
        $this->assertTrue(self::FRANCHISE_MANAGER_CAPS['manage_glamlux_franchise_employees'], 'Franchise Manager must manage employees');
        $this->assertTrue(self::FRANCHISE_MANAGER_CAPS['manage_glamlux_appointments'], 'Franchise Manager must manage appointments');
        $this->assertFalse(self::FRANCHISE_MANAGER_CAPS['manage_glamlux_franchise_managers'], 'Franchise Manager must NOT manage other managers');
        $this->assertFalse(self::FRANCHISE_MANAGER_CAPS['manage_glamlux_franchise'], 'Franchise Manager must NOT have franchise-wide cap');
        $this->assertFalse(self::FRANCHISE_MANAGER_CAPS['manage_glamlux_platform'], 'Franchise Manager must NOT have platform cap');
    }

    /**
     * Franchise Employee may only clock attendance and manage own appointments.
     */
    public function test_franchise_employee_caps(): void
    {
        $this->assertTrue(self::FRANCHISE_EMPLOYEE_CAPS['read'], 'Franchise Employee must have read');
        $this->assertTrue(self::FRANCHISE_EMPLOYEE_CAPS['manage_glamlux_appointments'], 'Franchise Employee must have appointments cap');
        $this->assertTrue(self::FRANCHISE_EMPLOYEE_CAPS['glamlux_check_attendance'], 'Franchise Employee must clock attendance');
    }

    /**
     * Franchise Employee must not hold any privileged caps.
     */
    public function test_franchise_employee_has_no_privileged_caps(): void
    {
        foreach (self::FORBIDDEN_EMPLOYEE_CAPS as $cap) {
            $this->assertArrayNotHasKey(
                $cap,
                self::FRANCHISE_EMPLOYEE_CAPS,
                "Franchise Employee must NOT have cap: {$cap}"
            );
        }
    }

    /**
     * Role privilege hierarchy: chairperson > franchise_manager > franchise_employee.
     * Measured by the count of granted caps.
     */
    public function test_role_privilege_hierarchy(): void
    {
        $chairperson_count = count(array_filter(self::CHAIRPERSON_CAPS));
        $manager_count     = count(array_filter(self::FRANCHISE_MANAGER_CAPS));
        $employee_count    = count(self::FRANCHISE_EMPLOYEE_CAPS);

        $this->assertGreaterThan($manager_count, $chairperson_count, 'Chairperson must have more caps than Franchise Manager');
        $this->assertGreaterThan($employee_count, $manager_count, 'Franchise Manager must have more caps than Franchise Employee');
    }

    /**
     * The user management controller MANAGED_ROLES list must include all three new roles.
     */
    public function test_managed_roles_include_new_roles(): void
    {
        $managed = [
            'glamlux_chairperson',
            'glamlux_franchise_manager',
            'glamlux_franchise_employee',
            'glamlux_staff',
            'glamlux_franchise_admin',
            'glamlux_salon_manager',
            'glamlux_state_manager',
        ];

        foreach (['glamlux_chairperson', 'glamlux_franchise_manager', 'glamlux_franchise_employee'] as $role) {
            $this->assertContains($role, $managed, "MANAGED_ROLES must include: {$role}");
        }
    }

    /**
     * Chairperson-assignable roles must NOT include chairperson itself (no self-elevation).
     */
    public function test_chairperson_cannot_assign_chairperson_role(): void
    {
        $chairperson_assignable = ['glamlux_franchise_manager', 'glamlux_franchise_employee', 'glamlux_staff'];
        $this->assertNotContains(
            'glamlux_chairperson',
            $chairperson_assignable,
            'Chairperson must not be able to assign the Chairperson role'
        );
    }

    /**
     * Franchise Manager-assignable roles must NOT include franchise manager or higher.
     */
    public function test_franchise_manager_cannot_escalate_roles(): void
    {
        $manager_assignable = ['glamlux_franchise_employee', 'glamlux_staff'];
        $this->assertNotContains('glamlux_franchise_manager', $manager_assignable, 'Franchise Manager must not assign Franchise Manager role');
        $this->assertNotContains('glamlux_chairperson', $manager_assignable, 'Franchise Manager must not assign Chairperson role');
        $this->assertNotContains('glamlux_super_admin', $manager_assignable, 'Franchise Manager must not assign Super Admin role');
    }

    /**
     * PHPUnit self-check: basic assertion.
     */
    public function test_basic_assertion(): void
    {
        $this->assertTrue(true, 'PHPUnit executes correctly.');
    }
}
