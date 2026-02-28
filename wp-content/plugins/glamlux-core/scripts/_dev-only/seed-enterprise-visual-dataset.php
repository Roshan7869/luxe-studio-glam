<?php
/**
 * Enterprise Visual + Operational Dataset Seeder
 *
 * Run with:
 *   wp eval-file wp-content/plugins/glamlux-core/scripts/seed-enterprise-visual-dataset.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run via WP-CLI: wp eval-file ...\n" );
}

global $wpdb;

function glamlux_seed_get_or_create_user( string $username, string $email, string $display_name, string $role = 'glamlux_staff' ): int {
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

	wp_update_user(
		array(
			'ID' => (int) $user_id,
			'display_name' => $display_name,
			'role' => $role,
		)
	);

	return (int) $user_id;
}

$tables = array(
	'franchises' => $wpdb->prefix . 'gl_franchises',
	'salons' => $wpdb->prefix . 'gl_salons',
	'staff' => $wpdb->prefix . 'gl_staff',
	'clients' => $wpdb->prefix . 'gl_clients',
	'appointments' => $wpdb->prefix . 'gl_appointments',
	'payroll' => $wpdb->prefix . 'gl_payroll',
	'inventory' => $wpdb->prefix . 'gl_inventory',
	'service_pricing' => $wpdb->prefix . 'gl_service_pricing',
	'service_logs' => $wpdb->prefix . 'gl_service_logs',
	'product_sales' => $wpdb->prefix . 'gl_product_sales',
	'leads' => $wpdb->prefix . 'gl_leads',
	'memberships' => $wpdb->prefix . 'gl_memberships',
	'financial_reports' => $wpdb->prefix . 'gl_financial_reports',
);

$missing = array();
foreach ( $tables as $k => $tbl ) {
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) );
	if ( $exists !== $tbl ) {
		$missing[] = $tbl;
	}
}

if ( ! empty( $missing ) ) {
	echo 'Missing tables, activate/upgrade plugin first:' . "\n";
	foreach ( $missing as $tbl ) {
		echo '- ' . $tbl . "\n";
	}
	return;
}

$super_admin_id = glamlux_seed_get_or_create_user( 'glamlux_super_seed', 'super-seed@glamlux.local', 'GlamLux Seed Super Admin', 'glamlux_super_admin' );
if ( ! $super_admin_id ) {
	$super_admin_id = 1;
}

$franchises = array(
	array( 'id' => 1, 'name' => 'GlamLux Mumbai Elite', 'location' => 'Mumbai, Maharashtra', 'admin_id' => $super_admin_id, 'territory_state' => 'Maharashtra', 'central_price_override' => 0.00, 'created_at' => '2026-01-01 09:00:00' ),
	array( 'id' => 2, 'name' => 'GlamLux Delhi Luxe', 'location' => 'Delhi, India', 'admin_id' => $super_admin_id, 'territory_state' => 'Delhi', 'central_price_override' => 1.00, 'created_at' => '2026-01-03 09:00:00' ),
	array( 'id' => 3, 'name' => 'GlamLux Bangalore Prime', 'location' => 'Bangalore, Karnataka', 'admin_id' => $super_admin_id, 'territory_state' => 'Karnataka', 'central_price_override' => 0.00, 'created_at' => '2026-01-05 09:00:00' ),
);
foreach ( $franchises as $row ) {
	$wpdb->replace( $tables['franchises'], $row );
}

$salons = array(
	array( 'id' => 101, 'franchise_id' => 1, 'name' => 'Andheri Luxe Studio', 'address' => 'Andheri West, Mumbai', 'is_active' => 1, 'interior_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
	array( 'id' => 102, 'franchise_id' => 2, 'name' => 'Connaught Royal Studio', 'address' => 'Connaught Place, Delhi', 'is_active' => 1, 'interior_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 103, 'franchise_id' => 3, 'name' => 'Indiranagar Prime Studio', 'address' => 'Indiranagar, Bangalore', 'is_active' => 1, 'interior_image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
	array( 'id' => 1201, 'franchise_id' => 1, 'name' => 'Bandra Elite Studio', 'address' => 'Bandra, Mumbai', 'is_active' => 1, 'interior_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
	array( 'id' => 1202, 'franchise_id' => 2, 'name' => 'South Delhi Luxe', 'address' => 'South Delhi', 'is_active' => 1, 'interior_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 1203, 'franchise_id' => 3, 'name' => 'Koramangala Royal', 'address' => 'Koramangala, Bangalore', 'is_active' => 1, 'interior_image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
);
foreach ( $salons as $row ) {
	$wpdb->replace( $tables['salons'], $row );
}

$staff_seed = array(
	array( 'id' => 201, 'salon_id' => 101, 'name' => 'Aisha Khan', 'role' => 'Senior Stylist', 'commission_rate' => 15.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/women/12.jpg' ),
	array( 'id' => 202, 'salon_id' => 101, 'name' => 'Rohan Mehta', 'role' => 'Junior Stylist', 'commission_rate' => 10.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/men/22.jpg' ),
	array( 'id' => 203, 'salon_id' => 102, 'name' => 'Simran Kaur', 'role' => 'Senior Stylist', 'commission_rate' => 18.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/women/32.jpg' ),
	array( 'id' => 204, 'salon_id' => 102, 'name' => 'Arjun Verma', 'role' => 'Nail Specialist', 'commission_rate' => 12.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/men/42.jpg' ),
	array( 'id' => 205, 'salon_id' => 103, 'name' => 'Neha Reddy', 'role' => 'Hair Expert', 'commission_rate' => 16.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/women/52.jpg' ),
	array( 'id' => 206, 'salon_id' => 103, 'name' => 'Vikram Rao', 'role' => 'Skin Specialist', 'commission_rate' => 14.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/men/62.jpg' ),
	array( 'id' => 1301, 'salon_id' => 1201, 'name' => 'Aarti Desai', 'role' => 'Makeup Artist', 'commission_rate' => 17.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/women/72.jpg' ),
	array( 'id' => 1302, 'salon_id' => 1202, 'name' => 'Manisha Kapoor', 'role' => 'Hair Stylist', 'commission_rate' => 13.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/women/82.jpg' ),
	array( 'id' => 1303, 'salon_id' => 1203, 'name' => 'Shruti Rao', 'role' => 'Skin Specialist', 'commission_rate' => 15.00, 'profile_image_url' => 'https://randomuser.me/api/portraits/women/92.jpg' ),
);
foreach ( $staff_seed as $row ) {
	$username = 'gl_staff_' . $row['id'];
	$email = $username . '@glamlux.local';
	$wp_user_id = glamlux_seed_get_or_create_user( $username, $email, $row['name'], 'glamlux_staff' );
	$wpdb->replace(
		$tables['staff'],
		array(
			'id' => $row['id'],
			'wp_user_id' => $wp_user_id,
			'salon_id' => $row['salon_id'],
			'commission_rate' => $row['commission_rate'],
			'specializations' => $row['role'],
			'job_role' => $row['role'],
			'profile_image_url' => $row['profile_image_url'],
			'is_active' => 1,
		)
	);
}

$memberships = array(
	array( 'id' => 1101, 'name' => 'Silver Glow Plan', 'tier_level' => 1, 'price' => 12000.00, 'duration_months' => 3, 'benefits' => 'Quarterly skin regimen + priority booking.', 'banner_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 1102, 'name' => 'Gold Luxe Plan', 'tier_level' => 2, 'price' => 22000.00, 'duration_months' => 6, 'benefits' => 'Bi-weekly facial + product kit discounts.', 'banner_image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
	array( 'id' => 1103, 'name' => 'Platinum Royal Plan', 'tier_level' => 3, 'price' => 40000.00, 'duration_months' => 12, 'benefits' => 'Unlimited consultations + annual makeover.', 'banner_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03' ),
	array( 'id' => 1104, 'name' => 'Bridal Diamond Plan', 'tier_level' => 3, 'price' => 30000.00, 'duration_months' => 4, 'benefits' => 'Bridal calendar support + premium slots.', 'banner_image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371' ),
	array( 'id' => 1105, 'name' => 'Premium Hair Care', 'tier_level' => 2, 'price' => 18000.00, 'duration_months' => 6, 'benefits' => 'Hair diagnostics + keratin maintenance.', 'banner_image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3' ),
	array( 'id' => 1106, 'name' => 'Skin Revival Plan', 'tier_level' => 1, 'price' => 15000.00, 'duration_months' => 3, 'benefits' => 'Targeted anti-aging sessions.', 'banner_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
);
foreach ( $memberships as $row ) {
	$wpdb->replace( $tables['memberships'], $row );
}

$clients_seed = array(
	array( 'id' => 301, 'name' => 'Priya Sharma', 'phone' => '9876543210', 'email' => 'priya@mail.com', 'membership_id' => 1101, 'franchise_id' => 1 ),
	array( 'id' => 302, 'name' => 'Anjali Gupta', 'phone' => '9812345678', 'email' => 'anjali@mail.com', 'membership_id' => 1102, 'franchise_id' => 1 ),
	array( 'id' => 303, 'name' => 'Meera Singh', 'phone' => '9123456780', 'email' => 'meera@mail.com', 'membership_id' => 1103, 'franchise_id' => 2 ),
	array( 'id' => 304, 'name' => 'Kavya Nair', 'phone' => '9988776655', 'email' => 'kavya@mail.com', 'membership_id' => 1104, 'franchise_id' => 2 ),
	array( 'id' => 305, 'name' => 'Sneha Patel', 'phone' => '9765432109', 'email' => 'sneha@mail.com', 'membership_id' => 1105, 'franchise_id' => 3 ),
	array( 'id' => 306, 'name' => 'Isha Rao', 'phone' => '9654321098', 'email' => 'isha@mail.com', 'membership_id' => 1106, 'franchise_id' => 3 ),
);
foreach ( $clients_seed as $row ) {
	$username = 'gl_client_' . $row['id'];
	$wp_user_id = glamlux_seed_get_or_create_user( $username, $row['email'], $row['name'], 'glamlux_client' );
	$wpdb->replace(
		$tables['clients'],
		array(
			'id' => $row['id'],
			'wp_user_id' => $wp_user_id,
			'membership_id' => $row['membership_id'],
			'membership_expiry' => '2026-12-31 23:59:59',
			'total_spent' => 0.00,
			'notes' => 'Franchise ' . $row['franchise_id'] . ' seeded demo client | Phone: ' . $row['phone'],
		)
	);
}

$appointments = array(
	array( 'id' => 401, 'client_id' => 301, 'salon_id' => 101, 'staff_id' => 201, 'service_name' => 'Hair Spa', 'appointment_time' => '2026-02-01 10:00:00', 'duration_minutes' => 60, 'status' => 'completed', 'amount' => 2500.00, 'payment_status' => 'paid' ),
	array( 'id' => 402, 'client_id' => 302, 'salon_id' => 101, 'staff_id' => 202, 'service_name' => 'Haircut', 'appointment_time' => '2026-02-02 11:00:00', 'duration_minutes' => 45, 'status' => 'completed', 'amount' => 1200.00, 'payment_status' => 'paid' ),
	array( 'id' => 403, 'client_id' => 303, 'salon_id' => 102, 'staff_id' => 203, 'service_name' => 'Bridal Makeup', 'appointment_time' => '2026-02-03 12:00:00', 'duration_minutes' => 120, 'status' => 'completed', 'amount' => 15000.00, 'payment_status' => 'paid' ),
	array( 'id' => 404, 'client_id' => 304, 'salon_id' => 102, 'staff_id' => 204, 'service_name' => 'Nail Art', 'appointment_time' => '2026-02-04 14:00:00', 'duration_minutes' => 60, 'status' => 'completed', 'amount' => 1800.00, 'payment_status' => 'paid' ),
	array( 'id' => 405, 'client_id' => 305, 'salon_id' => 103, 'staff_id' => 205, 'service_name' => 'Keratin', 'appointment_time' => '2026-02-05 16:00:00', 'duration_minutes' => 90, 'status' => 'completed', 'amount' => 8000.00, 'payment_status' => 'paid' ),
	array( 'id' => 406, 'client_id' => 306, 'salon_id' => 103, 'staff_id' => 206, 'service_name' => 'Facial', 'appointment_time' => '2026-02-06 17:00:00', 'duration_minutes' => 60, 'status' => 'completed', 'amount' => 3500.00, 'payment_status' => 'paid' ),
);
foreach ( $appointments as $row ) {
	$wpdb->replace( $tables['appointments'], $row );
}

$service_catalog = array(
	array( 'id' => 701, 'service_name' => 'Luxury Hair Coloring', 'category' => 'Hair', 'base_price' => 6500.00, 'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 702, 'service_name' => 'Royal Bridal Package', 'category' => 'Bridal', 'base_price' => 25000.00, 'image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
	array( 'id' => 703, 'service_name' => 'Diamond Facial Therapy', 'category' => 'Skin', 'base_price' => 4500.00, 'image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03' ),
	array( 'id' => 704, 'service_name' => 'Advanced Nail Extension', 'category' => 'Nails', 'base_price' => 3200.00, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371' ),
	array( 'id' => 705, 'service_name' => 'Premium Keratin Boost', 'category' => 'Hair', 'base_price' => 9000.00, 'image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3' ),
	array( 'id' => 706, 'service_name' => 'Anti-Aging Gold Facial', 'category' => 'Skin', 'base_price' => 6000.00, 'image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
);
foreach ( $service_catalog as $row ) {
	$wpdb->replace(
		$tables['service_pricing'],
		array(
			'id' => $row['id'],
			'service_name' => $row['service_name'],
			'category' => $row['category'],
			'base_price' => $row['base_price'],
			'custom_price' => null,
			'duration_minutes' => 60,
			'description' => 'Seeded enterprise demo catalogue item.',
			'image_url' => $row['image_url'],
			'is_active' => 1,
			'service_id' => $row['id'],
			'franchise_id' => null,
		)
	);
}

$service_logs = array(
	array( 'id' => 801, 'appointment_id' => 401, 'before_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9', 'after_image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
	array( 'id' => 802, 'appointment_id' => 402, 'before_image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3', 'after_image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371' ),
	array( 'id' => 803, 'appointment_id' => 403, 'before_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03', 'after_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
	array( 'id' => 804, 'appointment_id' => 404, 'before_image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371', 'after_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03' ),
	array( 'id' => 805, 'appointment_id' => 405, 'before_image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3', 'after_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 806, 'appointment_id' => 406, 'before_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b', 'after_image_url' => 'https://images.unsplash.com/photo-1524253482453-3fed8d2fe12b' ),
);
foreach ( $service_logs as $row ) {
	$wpdb->replace( $tables['service_logs'], $row + array( 'notes' => 'Seeded before/after visual log.' ) );
}

$product_sales = array(
	array( 'id' => 901, 'salon_id' => 101, 'product_name' => "L'Oreal Professional Mask", 'total_amount' => 2200.00, 'product_image_url' => 'https://images.unsplash.com/photo-1585386959984-a41552231658' ),
	array( 'id' => 902, 'salon_id' => 102, 'product_name' => 'MAC Bridal Kit', 'total_amount' => 5800.00, 'product_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9' ),
	array( 'id' => 903, 'salon_id' => 103, 'product_name' => 'Gold Facial Cream', 'total_amount' => 3200.00, 'product_image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03' ),
	array( 'id' => 904, 'salon_id' => 101, 'product_name' => 'Keratin Shampoo', 'total_amount' => 1800.00, 'product_image_url' => 'https://images.unsplash.com/photo-1582095133179-bfd08e2fc6b3' ),
	array( 'id' => 905, 'salon_id' => 102, 'product_name' => 'Luxury Nail Gel Set', 'total_amount' => 2600.00, 'product_image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371' ),
	array( 'id' => 906, 'salon_id' => 103, 'product_name' => 'Skin Glow Serum', 'total_amount' => 2900.00, 'product_image_url' => 'https://images.unsplash.com/photo-1596178065887-1198b6148b2b' ),
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

$leads = array(
	array( 'id' => 1001, 'name' => 'Riya Kapoor', 'source' => 'Instagram', 'avatar_url' => 'https://randomuser.me/api/portraits/women/11.jpg', 'status' => 'new', 'email' => 'riya.kapoor@example.com', 'phone' => '9000001001' ),
	array( 'id' => 1002, 'name' => 'Nisha Jain', 'source' => 'Website', 'avatar_url' => 'https://randomuser.me/api/portraits/women/21.jpg', 'status' => 'contacted', 'email' => 'nisha.jain@example.com', 'phone' => '9000001002' ),
	array( 'id' => 1003, 'name' => 'Tanya Verma', 'source' => 'Referral', 'avatar_url' => 'https://randomuser.me/api/portraits/women/31.jpg', 'status' => 'converted', 'email' => 'tanya.verma@example.com', 'phone' => '9000001003' ),
	array( 'id' => 1004, 'name' => 'Pooja Mehra', 'source' => 'Instagram', 'avatar_url' => 'https://randomuser.me/api/portraits/women/41.jpg', 'status' => 'new', 'email' => 'pooja.mehra@example.com', 'phone' => '9000001004' ),
	array( 'id' => 1005, 'name' => 'Alia Khan', 'source' => 'Website', 'avatar_url' => 'https://randomuser.me/api/portraits/women/51.jpg', 'status' => 'contacted', 'email' => 'alia.khan@example.com', 'phone' => '9000001005' ),
	array( 'id' => 1006, 'name' => 'Sana Sheikh', 'source' => 'Referral', 'avatar_url' => 'https://randomuser.me/api/portraits/women/61.jpg', 'status' => 'converted', 'email' => 'sana.sheikh@example.com', 'phone' => '9000001006' ),
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
			'message' => 'Enterprise seeded CRM lead.',
			'avatar_url' => $row['avatar_url'],
			'status' => $row['status'],
			'assigned_to' => null,
			'source' => strtolower( $row['source'] ),
			'created_at' => '2026-02-10 10:00:00',
		)
	);
}

$payroll = array(
	array( 'id' => 501, 'staff_id' => 201, 'salon_id' => 101, 'period_start' => '2026-02-01', 'period_end' => '2026-02-28', 'total_services' => 2500.00, 'commission_earned' => 375.00, 'status' => 'processed', 'processed_at' => '2026-03-01 10:00:00' ),
	array( 'id' => 502, 'staff_id' => 203, 'salon_id' => 102, 'period_start' => '2026-02-01', 'period_end' => '2026-02-28', 'total_services' => 15000.00, 'commission_earned' => 2700.00, 'status' => 'processed', 'processed_at' => '2026-03-01 10:00:00' ),
	array( 'id' => 503, 'staff_id' => 205, 'salon_id' => 103, 'period_start' => '2026-02-01', 'period_end' => '2026-02-28', 'total_services' => 8000.00, 'commission_earned' => 1280.00, 'status' => 'processed', 'processed_at' => '2026-03-01 10:00:00' ),
);
foreach ( $payroll as $row ) {
	$wpdb->replace( $tables['payroll'], $row );
}

$inventory = array(
	array( 'id' => 601, 'salon_id' => 101, 'product_name' => "L'Oreal Keratin Serum", 'category' => 'Hair', 'quantity' => 25, 'reorder_threshold' => 5, 'unit_cost' => 850.00, 'last_restocked' => '2026-02-10 09:00:00' ),
	array( 'id' => 602, 'salon_id' => 102, 'product_name' => 'MAC Bridal Kit', 'category' => 'Makeup', 'quantity' => 10, 'reorder_threshold' => 3, 'unit_cost' => 2200.00, 'last_restocked' => '2026-02-10 09:00:00' ),
	array( 'id' => 603, 'salon_id' => 103, 'product_name' => 'VLCC Facial Cream', 'category' => 'Skin', 'quantity' => 18, 'reorder_threshold' => 4, 'unit_cost' => 740.00, 'last_restocked' => '2026-02-10 09:00:00' ),
);
foreach ( $inventory as $row ) {
	$wpdb->replace( $tables['inventory'], $row );
}

$financial_reports = array(
	array( 'id' => 1401, 'franchise_id' => 1, 'salon_id' => null, 'report_month' => '2026-02', 'total_revenue' => 1250000.00, 'total_expenses' => 710000.00, 'net_profit' => 540000.00, 'report_chart_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71', 'generated_at' => '2026-03-01 12:00:00' ),
	array( 'id' => 1402, 'franchise_id' => 2, 'salon_id' => null, 'report_month' => '2026-02', 'total_revenue' => 1680000.00, 'total_expenses' => 940000.00, 'net_profit' => 740000.00, 'report_chart_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71', 'generated_at' => '2026-03-01 12:00:00' ),
	array( 'id' => 1403, 'franchise_id' => 3, 'salon_id' => null, 'report_month' => '2026-02', 'total_revenue' => 1430000.00, 'total_expenses' => 810000.00, 'net_profit' => 620000.00, 'report_chart_image_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71', 'generated_at' => '2026-03-01 12:00:00' ),
);
foreach ( $financial_reports as $row ) {
	$wpdb->replace( $tables['financial_reports'], $row );
}

echo "✅ Enterprise dataset seeded successfully.\n";
echo "Franchises: 3 | Salons: 6 | Staff: 9 | Clients: 6 | Appointments: 6\n";
echo "Services: 6 | Service Logs: 6 | Product Sales: 6 | Leads: 6 | Memberships: 6\n";
echo "Payroll: 3 | Inventory: 3 | Financial Reports: 3\n";
