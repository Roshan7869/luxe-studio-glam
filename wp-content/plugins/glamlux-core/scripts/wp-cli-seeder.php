<?php
/**
 * GlamLux2Lux — WP-CLI Data Seeder
 *
 * Injects realistic test data to validate database index performance at scale.
 *
 * USAGE:
 *   wp eval-file wp-content/plugins/glamlux-core/scripts/wp-cli-seeder.php
 *
 * OPTIONAL FLAGS:
 *   --franchises=50
 *   --salons-per-franchise=4
 *   --staff-per-salon=6
 *   --appointments-per-day=18
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

$defaults = array(
	'franchises'             => 50,
	'salons-per-franchise'   => 4,
	'staff-per-salon'        => 6,
	'appointments-per-day'   => 18,
	'seed-days'              => 30,
);

$config = $defaults;

if ( defined( 'WP_CLI' ) ) {
	$assoc_args = WP_CLI::get_runner()->assoc_args ?? array();
	foreach ( $defaults as $key => $default_value ) {
		if ( isset( $assoc_args[ $key ] ) ) {
			$config[ $key ] = (int) $assoc_args[ $key ];
		}
	}
	// Backward-compatible aliases.
	if ( isset( $assoc_args['salons'] ) ) {
		$config['salons-per-franchise'] = (int) $assoc_args['salons'];
	}
	if ( isset( $assoc_args['appointments'] ) ) {
		$config['appointments-per-day'] = max( 1, (int) round( ( (int) $assoc_args['appointments'] ) / max( 1, $config['seed-days'] ) ) );
	}
}

$target_franchises    = max( 1, (int) $config['franchises'] );
$salons_per_franchise = max( 1, (int) $config['salons-per-franchise'] );
$staff_per_salon      = max( 1, (int) $config['staff-per-salon'] );
$appointments_per_day = max( 1, (int) $config['appointments-per-day'] );
$seed_days            = max( 1, (int) $config['seed-days'] );
$batch_size           = 500;

$states      = array( 'Maharashtra', 'Delhi', 'Karnataka', 'Tamil Nadu', 'Gujarat', 'Rajasthan', 'Kerala' );
$salon_names = array( 'Luxe Studio', 'GlamHub', 'Prestige Beauty', 'Velvet Touch', 'Aura Salon' );
$services    = array( 'Hair Cut', 'Hair Color', 'Facial', 'Manicure', 'Pedicure', 'Keratin Treatment' );
$statuses    = array( 'pending', 'confirmed', 'completed', 'cancelled' );
$weights     = array( 10, 25, 55, 10 );

$inserted = array(
	'franchises'   => 0,
	'salons'       => 0,
	'staff'        => 0,
	'clients'      => 0,
	'appointments' => 0,
);

$distribution = array(
	'salons_by_franchise'      => array(),
	'staff_by_salon'           => array(),
	'clients_by_salon'         => array(),
	'appts_per_salon_per_day'  => array(),
);

echo str_repeat( '─', 70 ) . "\n";
echo "GlamLux2Lux Stress Seeder\n";
echo str_repeat( '─', 70 ) . "\n";
echo "  Franchises:                 {$target_franchises}\n";
echo "  Baseline salons/franchise:  {$salons_per_franchise}\n";
echo "  Baseline staff/salon:       {$staff_per_salon}\n";
echo "  Baseline appts/salon/day:   {$appointments_per_day}\n";
echo "  Seed window (days):         {$seed_days}\n";
echo str_repeat( '─', 70 ) . "\n\n";

function glamlux_weighted_random( $items, $weights ) {
	$total = array_sum( $weights );
	$rand = mt_rand( 1, $total );
	$cumulative = 0;
	foreach ( $items as $i => $item ) {
		$cumulative += $weights[ $i ];
		if ( $rand <= $cumulative ) {
			return $item;
		}
	}
	return end( $items );
}

function glamlux_distributed_count( $baseline, $min_factor, $max_factor, $floor = 1 ) {
	$factor = mt_rand( (int) ( $min_factor * 100 ), (int) ( $max_factor * 100 ) ) / 100;
	return max( $floor, (int) round( $baseline * $factor ) );
}

function fake_city() {
	static $cities = array(
		'Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Chennai',
		'Pune', 'Kolkata', 'Jaipur', 'Ahmedabad', 'Surat',
		'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Bhopal',
	);
	return $cities[ array_rand( $cities ) ];
}

$start = microtime( true );
echo "[1/6] Seeding {$target_franchises} franchises...\n";

$max_franchise_id_before = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$wpdb->prefix}gl_franchises" );
$franchise_batch = array();

for ( $i = 0; $i < $target_franchises; $i++ ) {
	$state = $states[ array_rand( $states ) ];
	$franchise_batch[] = $wpdb->prepare(
		'(%s, %s, %d, %s, %f, NOW())',
		'Franchise ' . strtoupper( wp_generate_password( 6, false, false ) ),
		fake_city() . ', ' . $state,
		1,
		$state,
		round( mt_rand( 500, 4500 ) / 10, 2 )
	);

	if ( count( $franchise_batch ) >= $batch_size ) {
		$wpdb->query(
			"INSERT INTO {$wpdb->prefix}gl_franchises (name, location, admin_id, territory_state, central_price_override, created_at) VALUES " . implode( ',', $franchise_batch )
		);
		$franchise_batch = array();
		echo '.';
	}
}
if ( ! empty( $franchise_batch ) ) {
	$wpdb->query(
		"INSERT INTO {$wpdb->prefix}gl_franchises (name, location, admin_id, territory_state, central_price_override, created_at) VALUES " . implode( ',', $franchise_batch )
	);
}

$franchise_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}gl_franchises WHERE id > %d ORDER BY id ASC", $max_franchise_id_before ) );
$inserted['franchises'] = count( $franchise_ids );

echo "\n      ✓ {$inserted['franchises']} franchises inserted. (" . round( microtime( true ) - $start, 2 ) . "s)\n\n";

$start = microtime( true );
echo "[2/6] Seeding salons with franchise-level distribution...\n";

$max_salon_id_before = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$wpdb->prefix}gl_salons" );
$salon_batch = array();

foreach ( $franchise_ids as $franchise_id ) {
	$salon_count = glamlux_distributed_count( $salons_per_franchise, 0.6, 1.4, 1 );
	$distribution['salons_by_franchise'][ $franchise_id ] = $salon_count;

	for ( $s = 0; $s < $salon_count; $s++ ) {
		$salon_batch[] = $wpdb->prepare(
			'(%d, %s, %s, 1, NOW())',
			$franchise_id,
			$salon_names[ array_rand( $salon_names ) ] . ' #' . mt_rand( 1, 999 ),
			fake_city() . ', India'
		);

		if ( count( $salon_batch ) >= $batch_size ) {
			$wpdb->query(
				"INSERT INTO {$wpdb->prefix}gl_salons (franchise_id, name, address, is_active, created_at) VALUES " . implode( ',', $salon_batch )
			);
			$salon_batch = array();
			echo '.';
		}
	}
}
if ( ! empty( $salon_batch ) ) {
	$wpdb->query(
		"INSERT INTO {$wpdb->prefix}gl_salons (franchise_id, name, address, is_active, created_at) VALUES " . implode( ',', $salon_batch )
	);
}

$salons = $wpdb->get_results(
	$wpdb->prepare( "SELECT id, franchise_id FROM {$wpdb->prefix}gl_salons WHERE id > %d ORDER BY id ASC", $max_salon_id_before ),
	ARRAY_A
);
$inserted['salons'] = count( $salons );

echo "\n      ✓ {$inserted['salons']} salons inserted. (" . round( microtime( true ) - $start, 2 ) . "s)\n\n";

$start = microtime( true );
echo "[3/6] Seeding staff per salon...\n";

$max_staff_id_before = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$wpdb->prefix}gl_staff" );
$staff_batch = array();

foreach ( $salons as $salon ) {
	$staff_count = glamlux_distributed_count( $staff_per_salon, 0.7, 1.5, 2 );
	$distribution['staff_by_salon'][ $salon['id'] ] = $staff_count;

	for ( $i = 0; $i < $staff_count; $i++ ) {
		$staff_batch[] = $wpdb->prepare(
			'(%d, %d, %f, %s, %s, NULL, 1)',
			1,
			$salon['id'],
			round( mt_rand( 5, 20 ) + ( mt_rand( 0, 99 ) / 100 ), 2 ),
			wp_json_encode( array( $services[ array_rand( $services ) ], $services[ array_rand( $services ) ] ) ),
			( mt_rand( 1, 100 ) <= 20 ) ? 'Senior Stylist' : 'Stylist'
		);

		if ( count( $staff_batch ) >= $batch_size ) {
			$wpdb->query(
				"INSERT INTO {$wpdb->prefix}gl_staff (wp_user_id, salon_id, commission_rate, specializations, job_role, profile_image_url, is_active) VALUES " . implode( ',', $staff_batch )
			);
			$staff_batch = array();
			echo '.';
		}
	}
}
if ( ! empty( $staff_batch ) ) {
	$wpdb->query(
		"INSERT INTO {$wpdb->prefix}gl_staff (wp_user_id, salon_id, commission_rate, specializations, job_role, profile_image_url, is_active) VALUES " . implode( ',', $staff_batch )
	);
}

$staff_rows = $wpdb->get_results(
	$wpdb->prepare( "SELECT id, salon_id FROM {$wpdb->prefix}gl_staff WHERE id > %d ORDER BY id ASC", $max_staff_id_before ),
	ARRAY_A
);
$inserted['staff'] = count( $staff_rows );

echo "\n      ✓ {$inserted['staff']} staff inserted. (" . round( microtime( true ) - $start, 2 ) . "s)\n\n";

$start = microtime( true );
echo "[4/6] Seeding clients with salon demand distribution...\n";

$max_client_id_before = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$wpdb->prefix}gl_clients" );
$client_batch = array();
$target_clients = 0;

foreach ( $salons as $salon ) {
	$staff_count = $distribution['staff_by_salon'][ $salon['id'] ] ?? $staff_per_salon;
	$client_count = max( 30, glamlux_distributed_count( $staff_count * 25, 0.8, 1.3, 30 ) );
	$distribution['clients_by_salon'][ $salon['id'] ] = $client_count;
	$target_clients += $client_count;

	for ( $i = 0; $i < $client_count; $i++ ) {
		$client_batch[] = $wpdb->prepare(
			'(%d, NULL, NULL, %f, %s)',
			1,
			round( mt_rand( 0, 250000 ) / 100, 2 ),
			'VIP preference: ' . ( mt_rand( 0, 1 ) ? 'hair' : 'skin' )
		);

		if ( count( $client_batch ) >= $batch_size ) {
			$wpdb->query(
				"INSERT INTO {$wpdb->prefix}gl_clients (wp_user_id, membership_id, membership_expiry, total_spent, notes) VALUES " . implode( ',', $client_batch )
			);
			$client_batch = array();
			echo '.';
		}
	}
}
if ( ! empty( $client_batch ) ) {
	$wpdb->query(
		"INSERT INTO {$wpdb->prefix}gl_clients (wp_user_id, membership_id, membership_expiry, total_spent, notes) VALUES " . implode( ',', $client_batch )
	);
}

$client_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}gl_clients WHERE id > %d ORDER BY id ASC", $max_client_id_before ) );
$inserted['clients'] = count( $client_ids );

echo "\n      ✓ {$inserted['clients']} clients inserted (target {$target_clients}). (" . round( microtime( true ) - $start, 2 ) . "s)\n\n";

$start = microtime( true );
echo "[5/6] Seeding appointments over {$seed_days} days...\n";

$staff_by_salon = array();
foreach ( $staff_rows as $staff ) {
	$staff_by_salon[ $staff['salon_id'] ][] = (int) $staff['id'];
}

$appointment_batch = array();
$seeded_appointments = 0;
$window_start = strtotime( '-' . $seed_days . ' days' );

foreach ( $salons as $salon ) {
	$salon_id = (int) $salon['id'];
	$staff_ids = $staff_by_salon[ $salon_id ] ?? array();
	if ( empty( $staff_ids ) ) {
		continue;
	}

	$distribution['appts_per_salon_per_day'][ $salon_id ] = glamlux_distributed_count( $appointments_per_day, 0.7, 1.4, 3 );
	$client_pool_size = $distribution['clients_by_salon'][ $salon_id ] ?? 50;
	$client_pool = array_slice( $client_ids, 0, max( 1, $client_pool_size ) );
	shuffle( $client_pool );

	for ( $day = 0; $day < $seed_days; $day++ ) {
		$daily_count = glamlux_distributed_count( $distribution['appts_per_salon_per_day'][ $salon_id ], 0.8, 1.2, 1 );
		for ( $slot = 0; $slot < $daily_count; $slot++ ) {
			$status = glamlux_weighted_random( $statuses, $weights );
			$amount = ( 'cancelled' === $status ) ? 0 : round( mt_rand( 700, 6500 ) / 10, 2 );
			$duration = array( 30, 45, 60, 75, 90 )[ array_rand( array( 30, 45, 60, 75, 90 ) ) ];
			$appointment_time = date( 'Y-m-d H:i:s', $window_start + ( $day * DAY_IN_SECONDS ) + mt_rand( 36000, 72000 ) );

			$appointment_batch[] = $wpdb->prepare(
				'(%d, %d, %d, %s, %s, %d, %s, %f, %s)',
				$client_pool[ array_rand( $client_pool ) ],
				$salon_id,
				$staff_ids[ array_rand( $staff_ids ) ],
				$services[ array_rand( $services ) ],
				$appointment_time,
				$duration,
				$status,
				$amount,
				( 'completed' === $status ) ? 'paid' : 'pending'
			);
			$seeded_appointments++;

			if ( count( $appointment_batch ) >= $batch_size ) {
				$wpdb->query(
					"INSERT INTO {$wpdb->prefix}gl_appointments (client_id, salon_id, staff_id, service_name, appointment_time, duration_minutes, status, amount, payment_status) VALUES " . implode( ',', $appointment_batch )
				);
				$appointment_batch = array();
				echo '.';
			}
		}
	}
}
if ( ! empty( $appointment_batch ) ) {
	$wpdb->query(
		"INSERT INTO {$wpdb->prefix}gl_appointments (client_id, salon_id, staff_id, service_name, appointment_time, duration_minutes, status, amount, payment_status) VALUES " . implode( ',', $appointment_batch )
	);
}

$inserted['appointments'] = $seeded_appointments;
echo "\n      ✓ {$inserted['appointments']} appointments inserted. (" . round( microtime( true ) - $start, 2 ) . "s)\n\n";

echo "[6/6] Integrity checks (row counts + null/FK sanity)...\n";

$checks = array(
	'franchises inserted (row delta)' =>
		$wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_franchises WHERE id > {$max_franchise_id_before}" ) == $inserted['franchises'],
	'salons inserted (row delta)' =>
		$wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_salons WHERE id > {$max_salon_id_before}" ) == $inserted['salons'],
	'staff inserted (row delta)' =>
		$wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_staff WHERE id > {$max_staff_id_before}" ) == $inserted['staff'],
	'clients inserted (row delta)' =>
		$wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_clients WHERE id > {$max_client_id_before}" ) == $inserted['clients'],
	'appointments inserted (row delta)' =>
		$wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_appointments WHERE appointment_time >= DATE_SUB(NOW(), INTERVAL {$seed_days} DAY)" ) >= $inserted['appointments'],
	'salons with NULL franchise_id' =>
		0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_salons WHERE franchise_id IS NULL" ),
	'staff with NULL salon_id/wp_user_id' =>
		0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_staff WHERE salon_id IS NULL OR wp_user_id IS NULL" ),
	'appointments with NULL core fields' =>
		0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_appointments WHERE client_id IS NULL OR salon_id IS NULL OR staff_id IS NULL OR appointment_time IS NULL" ),
	'orphan salons (missing franchise)' =>
		0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_salons s LEFT JOIN {$wpdb->prefix}gl_franchises f ON f.id = s.franchise_id WHERE f.id IS NULL" ),
	'orphan staff (missing salon)' =>
		0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gl_staff st LEFT JOIN {$wpdb->prefix}gl_salons s ON s.id = st.salon_id WHERE s.id IS NULL" ),
	'orphan appointments (missing client/salon/staff)' =>
		0 === (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}gl_appointments a
			 LEFT JOIN {$wpdb->prefix}gl_clients c ON c.id = a.client_id
			 LEFT JOIN {$wpdb->prefix}gl_salons s ON s.id = a.salon_id
			 LEFT JOIN {$wpdb->prefix}gl_staff st ON st.id = a.staff_id
			 WHERE c.id IS NULL OR s.id IS NULL OR st.id IS NULL"
		),
);

$failed_checks = 0;
foreach ( $checks as $label => $passed ) {
	if ( $passed ) {
		echo "      ✓ {$label}\n";
	} else {
		echo "      ⚠ {$label}\n";
		$failed_checks++;
	}
}

echo "\n" . str_repeat( '─', 70 ) . "\n";
echo "Seeder complete.\n";
echo 'Inserted totals: ' . wp_json_encode( $inserted ) . "\n";
echo 'Integrity checks: ' . ( 0 === $failed_checks ? 'PASS' : "WARN ({$failed_checks} failed)" ) . "\n";
echo str_repeat( '─', 70 ) . "\n";
