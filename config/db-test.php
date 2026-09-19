<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "sql206.byethost32.com";
$username = "b32_42851003";
$password = "Pandu@kichu143";
$database = "b32_42851003_smartcampus";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("❌ DATABASE CONNECTION FAILED: " . $conn->connect_error);
}

echo "✅ DATABASE CONNECTION SUCCESSFUL!";

?>