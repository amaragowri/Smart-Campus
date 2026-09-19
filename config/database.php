<?php

// Support environment variables (ideal for Render deployment) with fallback to default credentials
$host     = getenv('DB_HOST')     ?: (getenv('MYSQL_HOST')     ?: "sql206.byethost32.com");
$username = getenv('DB_USER')     ?: (getenv('MYSQL_USER')     ?: "b32_42851003");
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : "Pandu@kichu143");
$database = getenv('DB_NAME')     ?: (getenv('MYSQL_DATABASE') ?: "b32_42851003_smartcampus");
$port     = getenv('DB_PORT')     ? (int)getenv('DB_PORT')     : 3306;

mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
$connected = @$conn->real_connect($host, $username, $password, $database, $port);

if (!$connected || $conn->connect_error) {
    // If request expects JSON API, return JSON error
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "success" => false, 
            "message" => "Database connection failed: " . ($conn->connect_error ?: "Connection timed out")
        ]);
        exit();
    }
    die("Database connection failed: " . ($conn->connect_error ?: "Connection timed out"));
}

$conn->set_charset("utf8mb4");

?>