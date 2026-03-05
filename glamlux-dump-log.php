<?php
$log_path = dirname(__FILE__) . '/wp-content/debug.log';
if (file_exists($log_path)) {
    header('Content-Type: text/plain');
    $lines = file($log_path);
    if ($lines !== false) {
        $recent_lines = array_slice($lines, -100);
        foreach ($recent_lines as $line) {
            echo $line;
        }
    } else {
        echo "Could not read debug.log";
    }
} else {
    echo "debug.log not found at: " . $log_path;
}
