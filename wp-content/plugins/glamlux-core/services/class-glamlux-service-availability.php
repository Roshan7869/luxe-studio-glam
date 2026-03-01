<?php
/**
 * Phase 5 Centralized Availability Engine
 * Consolidates duration and overlap logic specifically to single endpoints
 * so REST, AJAX, and internal modules consume the exact same validation state.
 */
class GlamLux_Service_Availability
{
    private $appointmentRepo;
    private $staffRepo;

    public function __construct(GlamLux_Repo_Appointment $appointmentRepo = null, GlamLux_Repo_Staff $staffRepo = null)
    {
        $this->appointmentRepo = $appointmentRepo ?: new GlamLux_Repo_Appointment();
        $this->staffRepo = $staffRepo ?: new GlamLux_Repo_Staff();
    }

    /**
     * Determines if a specific salon has AT LEAST ONE staff member available
     * for a requested time block.
     * 
     * @param int $salon_id 
     * @param string $start_time (MySQL datetime)
     * @param int $duration_minutes Defaults to 30.
     * @return bool
     */
    public function is_salon_slot_available(int $salon_id, string $start_time, int $duration_minutes = 30): bool
    {
        $end_time = date('Y-m-d H:i:s', strtotime($start_time) + ($duration_minutes * 60));

        $staff_members = $this->staffRepo->get_all(['salon_id' => $salon_id, 'status' => 'active']);

        if (empty($staff_members)) {
            return false;
        }

        // Return true the instant we find ONE staff member without overlap.
        foreach ($staff_members as $staff) {
            if (!$this->appointmentRepo->has_time_overlap($staff['id'], $start_time, $end_time)) {
                return true;
            }
        }

        // All active staff have an overlap intersection.
        return false;
    }
}
