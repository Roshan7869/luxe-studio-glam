<?php
/**
 * Seed exactly 39 visual enterprise demo records.
 *
 * Run:
 *   wp eval-file wp-content/plugins/glamlux-core/scripts/seed-visual-dataset-39.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run via WP-CLI: wp eval-file ...\n" );
}

global $wpdb;


function glamlux_seed39_get_or_create_user( string $username, string $email, string $display_name, string $role ): int {
	$user = get_user_by( 'login', $username );
	if ( $user ) {
		wp_update_user( array( 'ID' => $user->ID, 'display_name' => $display_name, 'role' => $role ) );
		return (int) $user->ID;
	}
	$user_id = wp_create_user( $username, wp_generate_password( 20, true, true ), $email );
	if ( is_wp_error( $user_id ) ) {
		echo 'User create failed for ' . $username . ': ' . $user_id->get_error_message() . "\n";
		return 0;
	}
	wp_update_user( array( 'ID' => (int) $user_id, 'display_name' => $display_name, 'role' => $role ) );
	return (int) $user_id;
}

$tables = array(
	'service_pricing' => $wpdb->prefix . 'gl_service_pricing',
	'service_logs' => $wpdb->prefix . 'gl_service_logs',
	'product_sales' => $wpdb->prefix . 'gl_product_sales',
	'leads' => $wpdb->prefix . 'gl_leads',
	'memberships' => $wpdb->prefix . 'gl_memberships',
	'salons' => $wpdb->prefix . 'gl_salons',
	'staff' => $wpdb->prefix . 'gl_staff',
	'financial_reports' => $wpdb->prefix . 'gl_financial_reports',
);

foreach ( $tables as $table ) {
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		echo "Missing table: {$table}\n";
		return;
	}
}

// 1) SERVICES (6)
$services = array(
	array( 'id' => 701, 'service_name' => 'Luxury Hair Coloring', 'category' => 'Hair', 'base_price' => 6500, 'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 702, 'service_name' => 'Royal Bridal Package', 'category' => 'Bridal', 'base_price' => 25000, 'image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
	array( 'id' => 703, 'service_name' => 'Diamond Facial Therapy', 'category' => 'Skin', 'base_price' => 4500, 'image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03' ),
	array( 'id' => 704, 'service_name' => 'Advanced Nail Extension', 'category' => 'Nails', 'base_price' => 3200, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371' ),
	array( 'id' => 705, 'service_name' => 'Premium Keratin Boost', 'category' => 'Hair', 'base_price' => 9000, 'image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3' ),
	array( 'id' => 706, 'service_name' => 'Anti-Aging Gold Facial', 'category' => 'Skin', 'base_price' => 6000, 'image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
);
foreach ( $services as $row ) {
	$wpdb->replace(
		$tables['service_pricing'],
		array(
			'id' => $row['id'],
			'service_name' => $row['service_name'],
			'category' => $row['category'],
			'base_price' => $row['base_price'],
			'custom_price' => null,
			'duration_minutes' => 60,
			'description' => 'Seeded visual demo service',
			'image_url' => $row['image_url'],
			'is_active' => 1,
			'service_id' => $row['id'],
			'franchise_id' => null,
		)
	);
}

// 2) SERVICE LOGS (6)
$service_logs = array(
	array( 'id' => 801, 'appointment_id' => 401, 'before_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9', 'after_image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
	array( 'id' => 802, 'appointment_id' => 402, 'before_image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3', 'after_image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371' ),
	array( 'id' => 803, 'appointment_id' => 403, 'before_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03', 'after_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
	array( 'id' => 804, 'appointment_id' => 404, 'before_image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371', 'after_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03' ),
	array( 'id' => 805, 'appointment_id' => 405, 'before_image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3', 'after_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 806, 'appointment_id' => 406, 'before_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b', 'after_image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
);
foreach ( $service_logs as $row ) {
	$wpdb->replace( $tables['service_logs'], $row + array( 'notes' => 'Before/After seeded visual sample' ) );
}

// 3) PRODUCT SALES (6)
$product_sales = array(
	array( 'id' => 901, 'salon_id' => 101, 'product_name' => "L'Oreal Professional Mask", 'total_amount' => 2200, 'product_image_url' => 'https://images.unsplash.com/photo-1585386959984-a41552231658' ),
	array( 'id' => 902, 'salon_id' => 102, 'product_name' => 'MAC Bridal Kit', 'total_amount' => 5800, 'product_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 903, 'salon_id' => 103, 'product_name' => 'Gold Facial Cream', 'total_amount' => 3200, 'product_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03' ),
	array( 'id' => 904, 'salon_id' => 101, 'product_name' => 'Keratin Shampoo', 'total_amount' => 1800, 'product_image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3' ),
	array( 'id' => 905, 'salon_id' => 102, 'product_name' => 'Luxury Nail Gel Set', 'total_amount' => 2600, 'product_image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371' ),
	array( 'id' => 906, 'salon_id' => 103, 'product_name' => 'Skin Glow Serum', 'total_amount' => 2900, 'product_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
);
foreach ( $product_sales as $row ) {
	$wpdb->replace(
		$tables['product_sales'],
		array(
			'id' => $row['id'],
			'salon_id' => $row['salon_id'],
			'client_id' => null,
			'wc_order_id' => 99000 + $row['id'],
			'product_name' => $row['product_name'],
			'product_image_url' => $row['product_image_url'],
			'total_amount' => $row['total_amount'],
			'sale_date' => '2026-02-15 12:00:00',
		)
	);
}

// 4) LEADS (6)
$leads = array(
	array( 'id' => 1001, 'name' => 'Riya Kapoor', 'source' => 'instagram', 'avatar_url' => 'https://randomuser.me/api/portraits/women/11.jpg', 'status' => 'new', 'email' => 'riya.kapoor@example.com', 'phone' => '9000001001' ),
	array( 'id' => 1002, 'name' => 'Nisha Jain', 'source' => 'website', 'avatar_url' => 'https://randomuser.me/api/portraits/women/21.jpg', 'status' => 'contacted', 'email' => 'nisha.jain@example.com', 'phone' => '9000001002' ),
	array( 'id' => 1003, 'name' => 'Tanya Verma', 'source' => 'referral', 'avatar_url' => 'https://randomuser.me/api/portraits/women/31.jpg', 'status' => 'converted', 'email' => 'tanya.verma@example.com', 'phone' => '9000001003' ),
	array( 'id' => 1004, 'name' => 'Pooja Mehra', 'source' => 'instagram', 'avatar_url' => 'https://randomuser.me/api/portraits/women/41.jpg', 'status' => 'new', 'email' => 'pooja.mehra@example.com', 'phone' => '9000001004' ),
	array( 'id' => 1005, 'name' => 'Alia Khan', 'source' => 'website', 'avatar_url' => 'https://randomuser.me/api/portraits/women/51.jpg', 'status' => 'contacted', 'email' => 'alia.khan@example.com', 'phone' => '9000001005' ),
	array( 'id' => 1006, 'name' => 'Sana Sheikh', 'source' => 'referral', 'avatar_url' => 'https://randomuser.me/api/portraits/women/61.jpg', 'status' => 'converted', 'email' => 'sana.sheikh@example.com', 'phone' => '9000001006' ),
);
foreach ( $leads as $row ) {
	$wpdb->replace(
		$tables['leads'],
		array(
			'id' => $row['id'],
			'name' => $row['name'],
			'email' => $row['email'],
			'phone' => $row['phone'],
			'state' => 'India',
			'interest_type' => 'franchise',
			'message' => 'Seeded visual CRM lead',
			'avatar_url' => $row['avatar_url'],
			'status' => $row['status'],
			'assigned_to' => null,
			'source' => $row['source'],
		)
	);
}

// 5) MEMBERSHIPS (6)
$memberships = array(
	array( 'id' => 1101, 'name' => 'Silver Glow Plan', 'duration_months' => 3, 'price' => 12000, 'banner_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 1102, 'name' => 'Gold Luxe Plan', 'duration_months' => 6, 'price' => 22000, 'banner_image_url' => 'https://images.unsplash.com/photo-1524253482453-aabd1fc54bc9' ),
	array( 'id' => 1103, 'name' => 'Platinum Royal Plan', 'duration_months' => 12, 'price' => 40000, 'banner_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03' ),
	array( 'id' => 1104, 'name' => 'Bridal Diamond Plan', 'duration_months' => 4, 'price' => 30000, 'banner_image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371' ),
	array( 'id' => 1105, 'name' => 'Premium Hair Care', 'duration_months' => 6, 'price' => 18000, 'banner_image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3' ),
	array( 'id' => 1106, 'name' => 'Skin Revival Plan', 'duration_months' => 3, 'price' => 15000, 'banner_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
);
foreach ( $memberships as $row ) {
	$wpdb->replace(
		$tables['memberships'],
		array(
			'id' => $row['id'],
			'name' => $row['name'],
			'tier_level' => 1,
			'price' => $row['price'],
			'duration_months' => $row['duration_months'],
			'benefits' => 'Seeded visual membership package',
			'banner_image_url' => $row['banner_image_url'],
		)
	);
}

// 6) NEW SALONS (3)
$salons = array(
	array( 'id' => 1201, 'franchise_id' => 1, 'name' => 'Bandra Elite Studio', 'interior_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
	array( 'id' => 1202, 'franchise_id' => 2, 'name' => 'South Delhi Luxe', 'interior_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 1203, 'franchise_id' => 3, 'name' => 'Koramangala Royal', 'interior_image_url' => 'https://images.unsplash.com/photo-1524253482453-aabd1fc54bc9' ),
);
foreach ( $salons as $row ) {
	$wpdb->replace(
		$tables['salons'],
		array(
			'id' => $row['id'],
			'franchise_id' => $row['franchise_id'],
			'name' => $row['name'],
			'address' => 'Seeded demo location',
			'is_active' => 1,
			'interior_image_url' => $row['interior_image_url'],
		)
	);
}

// 7) NEW STAFF (3)
$staff = array(
	array( 'id' => 1301, 'salon_id' => 1201, 'name' => 'Aarti Desai', 'job_role' => 'Makeup Artist', 'profile_image_url' => 'https://randomuser.me/api/portraits/women/72.jpg' ),
	array( 'id' => 1302, 'salon_id' => 1202, 'name' => 'Manisha Kapoor', 'job_role' => 'Hair Stylist', 'profile_image_url' => 'https://randomuser.me/api/portraits/women/82.jpg' ),
	array( 'id' => 1303, 'salon_id' => 1203, 'name' => 'Shruti Rao', 'job_role' => 'Skin Specialist', 'profile_image_url' => 'https://randomuser.me/api/portraits/women/92.jpg' ),
);
foreach ( $staff as $row ) {
	$wpdb->replace(
		$tables['staff'],
		array(
			'id' => $row['id'],
			'wp_user_id' => glamlux_seed39_get_or_create_user( 'gl_seed39_staff_' . $row['id'], 'gl_seed39_staff_' . $row['id'] . '@glamlux.local', $row['name'], 'glamlux_staff' ),
			'salon_id' => $row['salon_id'],
			'commission_rate' => 12.50,
			'specializations' => $row['job_role'],
			'job_role' => $row['job_role'],
			'profile_image_url' => $row['profile_image_url'],
			'is_active' => 1,
		)
	);
}

// 8) FINANCIAL REPORTS (3)
$reports = array(
	array( 'id' => 1401, 'franchise_id' => 1, 'report_month' => '2026-02', 'total_revenue' => 1250000, 'report_chart_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71' ),
	array( 'id' => 1402, 'franchise_id' => 2, 'report_month' => '2026-02', 'total_revenue' => 1680000, 'report_chart_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71' ),
	array( 'id' => 1403, 'franchise_id' => 3, 'report_month' => '2026-02', 'total_revenue' => 1430000, 'report_chart_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71' ),
);
foreach ( $reports as $row ) {
	$expenses = (float) $row['total_revenue'] * 0.58;
	$wpdb->replace(
		$tables['financial_reports'],
		array(
			'id' => $row['id'],
			'franchise_id' => $row['franchise_id'],
			'salon_id' => null,
			'report_month' => $row['report_month'],
			'total_revenue' => $row['total_revenue'],
			'total_expenses' => $expenses,
			'net_profit' => (float) $row['total_revenue'] - $expenses,
			'report_chart_image_url' => $row['report_chart_image_url'],
			'generated_at' => '2026-03-01 12:00:00',
		)
	);
}

echo "✅ Seeded exact 39 visual enterprise records (idempotent).\n";
