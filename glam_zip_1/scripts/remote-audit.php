<?php

$site_url = 'https://luxe-studio-glam-production.up.railway.app';
$username = 'glamlux_admin';
$password = 'GlamLux@2026#';

echo "Logging into remotely deployed WordPress enterprise portal at $site_url...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $site_url . '/wp-login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'log' => $username,
    'pwd' => $password,
    'wp-submit' => 'Log In',
    'redirect_to' => $site_url . '/wp-admin/',
    'testcookie' => 1
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
curl_setopt($ch, CURLOPT_HEADER, true);
$login_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($http_code >= 400) {
    echo "Login failed. HTTP Code: $http_code\n";
    exit(1);
}

// Fetch nonce
curl_setopt($ch, CURLOPT_URL, $site_url . '/wp-admin/');
curl_setopt($ch, CURLOPT_POST, false);
$admin_page = curl_exec($ch);

// Search for wp.api.nonce
$nonce = '';
if (preg_match('/var wpApiSettings = {"root":".*?","nonce":"([a-z0-9]+)","versionString":".*?"};/', $admin_page, $matches)) {
    $nonce = $matches[1];
}

echo "Authenticating REST API requests... Nonce: " . ($nonce ?: 'Not found (fallback to cookie)') . "\n";

// Now request the operations summary via REST API
curl_setopt($ch, CURLOPT_URL, $site_url . '/wp-json/glamlux/v1/operations/summary');
if ($nonce) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-WP-Nonce: ' . $nonce
    ]);
}

$api_response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($api_response, $header_size);
$api_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);
@unlink(__DIR__ . '/cookie.txt');

echo "Operations API Returned $api_code:\n";
echo json_encode(json_decode($body), JSON_PRETTY_PRINT);
echo "\n";
