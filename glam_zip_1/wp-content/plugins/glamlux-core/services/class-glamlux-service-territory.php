<?php
class GlamLux_Service_Territory {
	private $repo;
	public function __construct(GlamLux_Repo_Territory $repo = null) { $this->repo = $repo ?: new GlamLux_Repo_Territory(); }
	public function has_territory_conflict(string $state, ?int $exclude = null): bool { return $this->repo->count_franchises_in_state($state, $exclude) > 0; }
	public function get_franchise_by_state(string $state): ?object { return $this->repo->get_franchise_by_state($state); }
	public function get_territory_map(): array { return $this->repo->get_territory_map(); }
	public function get_revenue_by_territory(string $f, string $t): array { return $this->repo->get_revenue_by_territory($f, $t); }
	public function auto_assign_by_territory(string $state): ?int { $id = $this->repo->get_admin_id_by_territory($state); return $id ? (int)$id : null; }
}