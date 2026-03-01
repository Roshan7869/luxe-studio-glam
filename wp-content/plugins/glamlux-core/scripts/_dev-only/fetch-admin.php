<?php
/**
 * Force Reset Admin Password
 * Run with: https://luxe-studio-glam-production.up.railway.app/wp-content/plugins/glamlux-core/scripts/_dev-only/fetch-admin.php
 */
require_once('../../../../../wp-load.php');

if (!isset($_GET['secret']) || $_GET['secret'] !== 'force321') {
    exit('Unauthorized');
}

$admins = get_users(['role' => 'administrator']);
if (empty($admins)) {
    echo "No administrators found!<br>";
    exit;
}

$admin = $admins[0];
$new_pass = wp_generate_password(16, false);
wp_set_password($new_pass, $admin->ID);

echo "<h2>Admin Credentials Reset</h2>";
echo "<strong>Username:</strong> " . esc_html($admin->user_login) . "<br>";
echo "<strong>Email:</strong> " . esc_html($admin->user_email) . "<br>";
echo "<strong>New Password:</strong> <code style='background:#eee;padding:4px'>" . esc_html($new_pass) . "</code><br>";
echo "<br><a href='" . admin_url() . "'>Go to Login</a>";
