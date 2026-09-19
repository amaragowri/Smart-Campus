<?php

// Support environment variables (ideal for Render deployment) with fallback to default credentials
$host     = getenv('DB_HOST')     ?: (getenv('MYSQL_HOST')     ?: "sql206.byethost32.com");
$username = getenv('DB_USER')     ?: (getenv('MYSQL_USER')     ?: "b32_42851003");
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : "Pandu@kichu143");
$database = getenv('DB_NAME')     ?: (getenv('MYSQL_DATABASE') ?: "b32_42851003_smartcampus");
$port     = getenv('DB_PORT')     ? (int)getenv('DB_PORT')     : 3306;

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>