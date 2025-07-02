<?php
session_start();

// Debug session variables
header('Content-Type: text/plain');

echo "=== Session Variables ===\n";
if (!empty($_SESSION)) {
    foreach ($_SESSION as $key => $value) {
        echo "\n$key: " . print_r($value, true);
    }
} else {
    echo "\nNo session variables set\n";
}

// Debug cookies
echo "\n\n=== Cookies ===\n";
if (!empty($_COOKIE)) {
    foreach ($_COOKIE as $key => $value) {
        echo "\n$key: $value";
    }
} else {
    echo "\nNo cookies set\n";
}

// Debug PHP session settings
echo "\n\n=== PHP Session Settings ===\n";
$settings = session_get_cookie_params();
foreach ($settings as $key => $value) {
    echo "\n$key: $value";
}

// Debug session status
echo "\n\n=== Session Status ===\n";
$status = session_status();
switch ($status) {
    case PHP_SESSION_DISABLED:
        echo "\nSession is disabled";
        break;
    case PHP_SESSION_NONE:
        echo "\nNo session is active";
        break;
    case PHP_SESSION_ACTIVE:
        echo "\nSession is active";
        break;
}
