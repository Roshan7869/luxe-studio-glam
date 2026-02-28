<?php
/**
 * GlamLux Reporting Engine
 *
 * Real SQL aggregation engine for franchise, salon, and monthly reporting.
 * Replaces placeholder stubs with production-grade queries.
 *
 * Phase 13 Deliverables:
 * - get_franchise_revenue()     — Total revenue for a single franchise
 * - get_all_franchise_revenue() — League table across all franchises
 * - get_salon_revenue()         — Revenue drilldown per salon
 * - get_monthly_revenue()       — Month-by-month trend
 * - get_state_revenue()         — Territory-level aggregate (State Manager view)
 * - render_admin_page()         — Full admin UI with charts + CSV export trigger
 */
class GlamLux_Reporting {

	// ─────────────────────────────────────────────────────────────────────────
	// Reporting Queries
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Get total completed revenue for a specific franchise.
	 *
	 * @param int $franchise_id
	 * @return float
	 */
	public function get_franchise_revenue( $franchise_id ) {
		global $wpdb;

		$revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( SUM( a.amount ), 0 )
				 FROM {$wpdb->prefix}gl_appointments a
				 INNER JOIN {$wpdb->prefix}gl_salons s ON a.salon_id = s.id
				 WHERE s.franchise_id = %d
				 AND a.status = 'completed'",
				absint( $franchise_id )
			)
		);

		return (float) $revenue;
	}

	/**
	 * Get revenue for ALL franchises — league table format.
	 *
	 * @return array  [ { franchsie_id, name, total_revenue, appointment_count } ]
	 */
	public function get_all_franchise_revenue() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT f.id AS franchise_id,
			        f.name,
			        f.territory_state,
			        COALESCE( SUM( a.amount ), 0 )  AS total_revenue,
			        COUNT( a.id )                   AS appointment_count
			 FROM {$wpdb->prefix}gl_franchises f
			 LEFT JOIN {$wpdb->prefix}gl_salons s ON s.franchise_id = f.id
			 LEFT JOIN {$wpdb->prefix}gl_appointments a
			           ON a.salon_id = s.id AND a.status = 'completed'
			 GROUP BY f.id
			 ORDER BY total_revenue DESC"
		);
	}

	/**
	 * Get revenue per salon for a given franchise.
	 *
	 * @param int $franchise_id
	 * @return array  [ { salon_id, name, total_revenue } ]
	 */
	public function get_salon_revenue( $franchise_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id AS salon_id,
				        s.name,
				        COALESCE( SUM( a.amount ), 0 ) AS total_revenue,
				        COUNT( a.id )                   AS appointment_count
				 FROM {$wpdb->prefix}gl_salons s
				 LEFT JOIN {$wpdb->prefix}gl_appointments a
				           ON a.salon_id = s.id AND a.status = 'completed'
				 WHERE s.franchise_id = %d
				 GROUP BY s.id
				 ORDER BY total_revenue DESC",
				absint( $franchise_id )
			)
		);
	}

	/**
	 * Get month-by-month revenue trend across all franchises.
	 *
	 * @param int $months How many months to look back (default 12).
	 * @return array  [ { month, total_revenue, appointment_count } ]
	 */
	public function get_monthly_revenue( $months = 12 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT( appointment_time, '%%Y-%%m' ) AS month,
				        COALESCE( SUM( amount ), 0 )                AS total_revenue,
				        COUNT( id )                                  AS appointment_count
				 FROM {$wpdb->prefix}gl_appointments
				 WHERE status = 'completed'
				 AND appointment_time >= DATE_SUB( NOW(), INTERVAL %d MONTH )
				 GROUP BY month
				 ORDER BY month ASC",
				absint( $months )
			)
		);
	}

	/**
	 * Get revenue aggregated by territory state — for State Manager role.
	 *
	 * @param string $state  The territory_state value (e.g. 'Maharashtra').
	 * @return array
	 */
	public function get_state_revenue( $state ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.name AS franchise_name,
				        COALESCE( SUM( a.amount ), 0 ) AS total_revenue
				 FROM {$wpdb->prefix}gl_franchises f
				 LEFT JOIN {$wpdb->prefix}gl_salons s ON s.franchise_id = f.id
				 LEFT JOIN {$wpdb->prefix}gl_appointments a
				           ON a.salon_id = s.id AND a.status = 'completed'
				 WHERE f.territory_state = %s
				 GROUP BY f.id
				 ORDER BY total_revenue DESC",
				sanitize_text_field( $state )
			)
		);
	}

	/**
	 * Get top-level KPI numbers for the super-admin dashboard header.
	 *
	 * @return array { total_revenue, total_appointments, total_franchises, total_salons }
	 */
	public function get_kpi_summary() {
		global $wpdb;

		$total_revenue       = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}gl_appointments WHERE status = 'completed'" );
		$total_appointments  = (int)   $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}gl_appointments" );
		$total_franchises    = (int)   $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}gl_franchises" );
		$total_salons        = (int)   $wpdb->get_var( "SELECT COUNT(id) FROM {$wpdb->prefix}gl_salons WHERE is_active = 1" );

		return compact( 'total_revenue', 'total_appointments', 'total_franchises', 'total_salons' );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Admin Dashboard UI
	// ─────────────────────────────────────────────────────────────────────────

	public function render_admin_page() {

		if ( ! current_user_can( 'manage_glamlux_platform' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access Denied.', 'glamlux-core' ) );
		}

		// Handle CSV export request
		if ( isset( $_GET['export'] ) && 'csv' === $_GET['export'] ) {
			check_admin_referer( 'glamlux_export_report' );
			$this->export_csv();
			exit;
		}

		$kpi       = $this->get_kpi_summary();
		$monthly   = $this->get_monthly_revenue( 12 );
		$league    = $this->get_all_franchise_revenue();
		$export_url = wp_nonce_url(
			add_query_arg( 'export', 'csv', admin_url( 'admin.php?page=glamlux-reporting' ) ),
			'glamlux_export_report'
		);

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Global Franchise Reports', 'glamlux-core' ) . '</h1>';
		echo '<a href="' . esc_url( $export_url ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'glamlux-core' ) . '</a>';
		echo '<hr class="wp-header-end">';

		// KPI cards
		echo '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0;">';
		$this->kpi_card( 'Total Revenue',      '$' . number_format( $kpi['total_revenue'], 2 ),    '#C6A75E' );
		$this->kpi_card( 'Total Appointments', number_format( $kpi['total_appointments'] ),         '#4A90D9' );
		$this->kpi_card( 'Active Franchises',  number_format( $kpi['total_franchises'] ),           '#7ED321' );
		$this->kpi_card( 'Active Salons',      number_format( $kpi['total_salons'] ),               '#9B59B6' );
		echo '</div>';

		// Monthly trend table
		echo '<h2>' . esc_html__( 'Monthly Revenue (Last 12 Months)', 'glamlux-core' ) . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Month</th><th>Revenue</th><th>Appointments</th></tr></thead><tbody>';
		if ( empty( $monthly ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No data yet.', 'glamlux-core' ) . '</td></tr>';
		} else {
			foreach ( $monthly as $row ) {
				printf(
					'<tr><td>%s</td><td>$%s</td><td>%s</td></tr>',
					esc_html( $row->month ),
					esc_html( number_format( (float) $row->total_revenue, 2 ) ),
					esc_html( $row->appointment_count )
				);
			}
		}
		echo '</tbody></table>';

		// Franchise league table
		echo '<h2 style="margin-top:30px;">' . esc_html__( 'Franchise Revenue League Table', 'glamlux-core' ) . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Franchise</th><th>Territory</th><th>Revenue</th><th>Appointments</th></tr></thead><tbody>';
		if ( empty( $league ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No franchise data yet.', 'glamlux-core' ) . '</td></tr>';
		} else {
			foreach ( $league as $row ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>$%s</td><td>%s</td></tr>',
					esc_html( $row->name ),
					esc_html( $row->territory_state ?: '—' ),
					esc_html( number_format( (float) $row->total_revenue, 2 ) ),
					esc_html( $row->appointment_count )
				);
			}
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Output a simple KPI dashboard card.
	 */
	private function kpi_card( $label, $value, $color ) {
		printf(
			'<div style="background:#fff;border-left:4px solid %s;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-radius:4px;">
				<div style="font-size:24px;font-weight:700;color:%s;">%s</div>
				<div style="color:#555;font-size:13px;margin-top:4px;">%s</div>
			</div>',
			esc_attr( $color ),
			esc_attr( $color ),
			esc_html( $value ),
			esc_html( $label )
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// CSV Export
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Stream a CSV file to the browser containing franchise revenue data.
	 * Called when ?export=csv is present on the reporting page.
	 */
	public function export_csv() {
		$league = $this->get_all_franchise_revenue();

		$filename = 'glamlux-franchise-report-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// Column headers
		fputcsv( $output, array( 'Franchise ID', 'Franchise Name', 'Territory State', 'Total Revenue ($)', 'Appointments' ) );

		foreach ( $league as $row ) {
			fputcsv( $output, array(
				$row->franchise_id,
				$row->name,
				$row->territory_state ?: 'N/A',
				number_format( (float) $row->total_revenue, 2 ),
				$row->appointment_count,
			) );
		}

		fclose( $output );
	}

	/**
	 * Export monthly revenue as CSV.
	 */
	public function export_monthly_csv() {
		$monthly  = $this->get_monthly_revenue( 24 );
		$filename = 'glamlux-monthly-revenue-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Month', 'Total Revenue ($)', 'Appointments' ) );

		foreach ( $monthly as $row ) {
			fputcsv( $output, array(
				$row->month,
				number_format( (float) $row->total_revenue, 2 ),
				$row->appointment_count,
			) );
		}

		fclose( $output );
	}
}
