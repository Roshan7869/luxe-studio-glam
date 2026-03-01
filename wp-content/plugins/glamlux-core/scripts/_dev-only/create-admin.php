<?php
/**
 * Force Create Admin User
 * Run with: https://luxe-studio-glam-production.up.railway.app/wp-content/plugins/glamlux-core/scripts/_dev-only/create-admin.php
 */
require_once('../../../../../wp-load.php');

if (!isset($_GET['secret']) || $_GET['secret'] !== 'force321') {
    exit('Unauthorized');
}

$username = 'glamlux_admin';
$password = 'GlamLux@2026!';
$email = 'admin@glamlux.local';

$user = get_user_by('login', $username);
if (!$user) {
    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        exit("Failed to create user: " . $user_id->get_error_message());
    }
    $user = get_user_by('id', $user_id);
}
else {
    wp_set_password($password, $user->ID);
}

$user->set_role('administrator');

echo "<h2>Admin Credentials Created!</h2>";
echo "<strong>Username:</strong> " . esc_html($username) . "<br>";
echo "<strong>Password:</strong> <code style='background:#eee;padding:4px'>" . esc_html($password) . "</code><br>";
echo "<br><a href='" . admin_url() . "'>Go to Login</a>";
