<?php
/**
 * GlamLux2Lux — WP-CLI Data Seeder
 *
 * Injects realistic test data to validate database index performance at scale.
 *
 * USAGE (run from project root with WordPress running):
 *   wp eval-file wp-content/plugins/glamlux-core/scripts/wp-cli-seeder.php
 *
 * CUSTOMISE volume:
 *   wp eval-file ... --franchises=100 --salons-per-franchise=5 --appointments=50000
 *
 * Phase 16 Validation targets:
 *   - 10,000 franchise records → admin list loads in < 2s
 *   - 50,000 appointment records → reporting queries run in < 200ms
 *   - Index performance visible via EXPLAIN on key JOIN paths
 *
 * WARNING: This INSERTS real data into your database.
 *          Run ONLY on a local or staging database, never production.
 */

if ( ! defined( 'ABSPATH' ) ) {
	$wp_root = dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );
	if ( file_exists( $wp_root . '/wp-load.php' ) ) {
		require_once $wp_root . '/wp-load.php';
	} else {
		die( "Could not locate wp-load.php. Run via: wp eval-file <path>\n" );
	}
}

global $wpdb;

// ─── Config from CLI args ────────────────────────────────────────────────────
$target_franchises  = (int) ( $_SERVER['argv_franchises']  ?? 50   );
$salons_per         = (int) ( $_SERVER['argv_salons']      ?? 5    );
$target_appts       = (int) ( $_SERVER['argv_appointments'] ?? 10000 );
$batch_size         = 500; // Insert batch size to avoid memory overflow

// Respect CLI args passed via --runtime-args if using WP-CLI
if ( defined( 'WP_CLI' ) ) {
	$assoc_args = WP_CLI::get_runner()->arguments ?? array();
	$target_franchises = (int) ( $assoc_args['franchises']    ?? $target_franchises );
	$salons_per        = (int) ( $assoc_args['salons']         ?? $salons_per );
	$target_appts      = (int) ( $assoc_args['appointments']   ?? $target_appts );
}

echo str_repeat( '─', 65 ) . "\n";
echo "GlamLux2Lux Stress Seeder\n";
echo str_repeat( '─', 65 ) . "\n";
echo "  Franchises to seed:    {$target_franchises}\n";
echo "  Salons per franchise:  {$salons_per}\n";
echo "  Appointments:          {$target_appts}\n";
echo str_repeat( '─', 65 ) . "\n\n";

$states       = array( 'Maharashtra', 'Delhi', 'Karnataka', 'Tamil Nadu', 'Gujarat', 'Rajasthan', 'Kerala' );
$salon_names  = array( 'Luxe Studio', 'GlamHub', 'Prestige Beauty', 'Velvet Touch', 'Aura Salon' );
$services     = array( 1, 2, 3 ); // Assumes at least 3 services exist
$statuses     = array( 'pending', 'confirmed', 'completed', 'cancelled' );
$weights      = array( 10, 25, 55, 10 ); // Distribution of appointment statuses

function glamlux_weighted_random( $items, $weights ) {
	$total = array_sum( $weights );
	$rand  = mt_rand( 1, $total );
	$cumulative = 0;
	foreach ( $items as $i => $item ) {
		$cumulative += $weights[ $i ];
		if ( $rand <= $cumulative ) return $item;
	}
	return end( $items );
}

$franchise_ids = array();
$salon_ids     = array();
$staff_id      = 1; // Reference to a placeholder staff

// ─── STEP 1: Seed Franchises ─────────────────────────────────────────────────

$start = microtime( true );
echo "[1/4] Seeding {$target_franchises} franchises...\n";

$existing_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_franchises" );
echo "      (Existing: {$existing_count})\n";

$batch = array();
for ( $i = 0; $i < $target_franchises; $i++ ) {
	$state = $states[ array_rand( $states ) ];
	$batch[] = $wpdb->prepare(
		"(%s, %s, %d, %s, %f, NOW())",
		'Franchise_' . uniqid(),
		fake_city() . ', ' . $state,
		1,
		$state,
		round( mt_rand( 0, 1 ) === 1 ? mt_rand( 500, 5000 ) / 10 : 0, 2 )
	);

	if ( count( $batch ) >= $batch_size ) {
		$wpdb->query(
			"INSERT INTO {$wpdb->prefix}gl_franchises (name, location, admin_id, territory_state, central_price_override, created_at) VALUES "
			. implode( ',', $batch )
		);
		$batch = array();
		echo '.';
	}
}
if ( ! empty( $batch ) ) {
	$wpdb->query(
		"INSERT INTO {$wpdb->prefix}gl_franchises (name, location, admin_id, territory_state, central_price_override, created_at) VALUES "
		. implode( ',', $batch )
	);
}

$franchise_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}gl_franchises ORDER BY id ASC LIMIT " . ( $existing_count + $target_franchises ) );
echo "\n      ✓ " . count( $franchise_ids ) . " total franchise records. (" . round( microtime(true) - $start, 2 ) . "s)\n\n";

// ─── STEP 2: Seed Salons ──────────────────────────────────────────────────────

$start = microtime( true );
$total_salons = count( $franchise_ids ) * $salons_per;
echo "[2/4] Seeding ~{$total_salons} salons ({$salons_per} per franchise)...\n";

$batch = array();
foreach ( $franchise_ids as $fid ) {
	for ( $s = 0; $s < $salons_per; $s++ ) {
		$batch[] = $wpdb->prepare(
			"(%d, %s, %s, 1, NOW())",
			$fid,
			$salon_names[ array_rand( $salon_names ) ] . ' #' . mt_rand( 1, 999 ),
			fake_city()
		);

		if ( count( $batch ) >= $batch_size ) {
			$wpdb->query(
				"INSERT INTO {$wpdb->prefix}gl_salons (franchise_id, name, location, is_active, created_at) VALUES "
				. implode( ',', $batch )
			);
			$batch = array();
			echo '.';
		}
	}
}
if ( ! empty( $batch ) ) {
	$wpdb->query(
		"INSERT INTO {$wpdb->prefix}gl_salons (franchise_id, name, location, is_active, created_at) VALUES "
		. implode( ',', $batch )
	);
}

$salon_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}gl_salons ORDER BY id DESC LIMIT {$total_salons}" );
echo "\n      ✓ " . count( $salon_ids ) . " salon records. (" . round( microtime(true) - $start, 2 ) . "s)\n\n";

// ─── STEP 3: Seed Clients (for FK integrity) ──────────────────────────────────

$start = microtime( true );
$client_count = min( 500, $target_appts / 10 ); // 1 client per 10 appointments, max 500
echo "[3/4] Seeding {$client_count} test clients...\n";

$batch = array();
for ( $c = 0; $c < $client_count; $c++ ) {
	$batch[] = $wpdb->prepare(
		"(%d, %s, NULL, NULL, NOW())",
		1, // Map all to wp user 1 (admin)
		'+91' . mt_rand( 7000000000, 9999999999 )
	);
	if ( count( $batch ) >= $batch_size ) {
		$wpdb->query(
			"INSERT IGNORE INTO {$wpdb->prefix}gl_clients (wp_user_id, phone, membership_id, membership_expiry, created_at) VALUES "
			. implode( ',', $batch )
		);
		$batch = array();
	}
}
if ( ! empty( $batch ) ) {
	$wpdb->query(
		"INSERT IGNORE INTO {$wpdb->prefix}gl_clients (wp_user_id, phone, membership_id, membership_expiry, created_at) VALUES "
		. implode( ',', $batch )
	);
}
$client_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}gl_clients ORDER BY id DESC LIMIT {$client_count}" );
echo "      ✓ " . count( $client_ids ) . " client records. (" . round( microtime(true) - $start, 2 ) . "s)\n\n";

// ─── STEP 4: Seed Appointments ───────────────────────────────────────────────

$start = microtime( true );
echo "[4/4] Seeding {$target_appts} appointments...\n";

$batch      = array();
$seeded     = 0;

while ( $seeded < $target_appts ) {
	$salon_id   = $salon_ids[ array_rand( $salon_ids ) ];
	$client_id  = $client_ids[ array_rand( $client_ids ) ];
	$service_id = $services[ array_rand( $services ) ];
	$status     = glamlux_weighted_random( $statuses, $weights );
	$amount     = $status === 'cancelled' ? 0 : round( mt_rand( 500, 5000 ) / 10, 2 );

	// Random date: up to 2 years in either direction (historical + upcoming)
	$offset = mt_rand( -730, 90 );
	$appt_time = date( 'Y-m-d H:i:s', strtotime( "{$offset} days" ) + mt_rand( 32400, 64800 ) );

	$batch[] = $wpdb->prepare(
		"(%d, %d, %d, NULL, %s, %s, %f, NOW())",
		$salon_id,
		$client_id,
		$service_id,
		$appt_time,
		$status,
		$amount
	);

	$seeded++;

	if ( count( $batch ) >= $batch_size ) {
		$wpdb->query(
			"INSERT INTO {$wpdb->prefix}gl_appointments (salon_id, client_id, service_id, staff_id, appointment_time, status, amount, created_at) VALUES "
			. implode( ',', $batch )
		);
		$batch = array();
		echo '.';
	}
}
if ( ! empty( $batch ) ) {
	$wpdb->query(
		"INSERT INTO {$wpdb->prefix}gl_appointments (salon_id, client_id, service_id, staff_id, appointment_time, status, amount, created_at) VALUES "
		. implode( ',', $batch )
	);
}
echo "\n      ✓ {$seeded} appointment records. (" . round( microtime(true) - $start, 2 ) . "s)\n\n";

// ─── STEP 5: Run EXPLAIN on critical queries ─────────────────────────────────

echo str_repeat( '─', 65 ) . "\n";
echo "EXPLAIN ANALYSIS — Critical Query Performance\n";
echo str_repeat( '─', 65 ) . "\n\n";

$explain_queries = array(
	'Revenue by franchise (reporting engine)' =>
		"SELECT f.id, SUM(a.amount) as revenue
		 FROM {$wpdb->prefix}gl_franchises f
		 LEFT JOIN {$wpdb->prefix}gl_salons s ON s.franchise_id = f.id
		 LEFT JOIN {$wpdb->prefix}gl_appointments a ON a.salon_id = s.id AND a.status = 'completed'
		 GROUP BY f.id LIMIT 10",

	'Monthly revenue trend' =>
		"SELECT DATE_FORMAT(appointment_time, '%Y-%m') as month, SUM(amount)
		 FROM {$wpdb->prefix}gl_appointments
		 WHERE status = 'completed'
		 AND appointment_time >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
		 GROUP BY month",

	'Booking conflict check' =>
		"SELECT id FROM {$wpdb->prefix}gl_appointments
		 WHERE salon_id = 1 AND staff_id = 1
		 AND appointment_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)
		 AND status != 'cancelled'",
);

foreach ( $explain_queries as $label => $sql ) {
	$rows    = $wpdb->get_results( "EXPLAIN " . $sql, ARRAY_A );
	$type    = $rows[0]['type']    ?? '?';
	$key     = $rows[0]['key']     ?? 'NONE';
	$rows_est = $rows[0]['rows']   ?? '?';
	$extra   = $rows[0]['Extra']   ?? '';
	$ok      = in_array( $type, array( 'ref', 'eq_ref', 'range', 'index' ), true ) && $key !== 'NONE';

	printf(
		"  [%s] %s\n       type=%s, key=%s, est_rows=%s %s\n\n",
		$ok ? '✓ INDEXED' : '⚠ WARNING',
		$label,
		$type,
		$key,
		$rows_est,
		$extra ? "($extra)" : ''
	);
}

echo str_repeat( '─', 65 ) . "\n";
echo "Seeder complete. Database is ready for stress validation.\n";
echo "Next: run  k6 run scripts/k6-load-test.js\n";
echo str_repeat( '─', 65 ) . "\n";

// ─── Helper: fake city name ──────────────────────────────────────────────────
function fake_city() {
	static $cities = array(
		'Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Chennai',
		'Pune', 'Kolkata', 'Jaipur', 'Ahmedabad', 'Surat',
		'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Bhopal',
	);
	return $cities[ array_rand( $cities ) ];
}
