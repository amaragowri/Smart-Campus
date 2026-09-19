<?php
/**
 * SmartCampus Health Check Endpoint for Render & Monitoring
 */

require_once __DIR__ . '/config/config.php';

$db_status = "disconnected";
$db_error = null;

// Test Database Connection with a short timeout to prevent slow responses
try {
    $host     = getenv('DB_HOST')     ?: (getenv('MYSQL_HOST')     ?: "sql206.byethost32.com");
    $username = getenv('DB_USER')     ?: (getenv('MYSQL_USER')     ?: "b32_42851003");
    $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : "Pandu@kichu143");
    $database = getenv('DB_NAME')     ?: (getenv('MYSQL_DATABASE') ?: "b32_42851003_smartcampus");
    $port     = getenv('DB_PORT')     ? (int)getenv('DB_PORT')     : 3306;

    mysqli_report(MYSQLI_REPORT_OFF);
    $test_conn = mysqli_init();
    $test_conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    $connected = @$test_conn->real_connect($host, $username, $password, $database, $port);

    if ($connected && !$test_conn->connect_error) {
        $db_status = "connected";
        $test_conn->close();
    } else {
        $db_status = "unreachable";
        $db_error = $test_conn->connect_error ?: "Connection timed out";
    }
} catch (Throwable $e) {
    $db_status = "error";
    $db_error = $e->getMessage();
}

$response = [
    "status" => "healthy",
    "timestamp" => date("c"),
    "environment" => getenv("APP_ENV") ?: "production",
    "php_version" => PHP_VERSION,
    "database" => [
        "status" => $db_status
    ]
];

if ($db_error && getenv("APP_ENV") === "development") {
    $response["database"]["error"] = $db_error;
}

json_response($response, 200);
