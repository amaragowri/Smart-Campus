<?php
/**
 * SmartCampus Secure Logout Handler
 */

require_once __DIR__ . "/../config/config.php";

$role = $_SESSION["role"] ?? "STUDENT";

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Map role to respective login portal
$role_logins = [
    "STUDENT" => "student-login.php",
    "FACULTY" => "faculty-login.php",
    "ADMIN"   => "admin-login.php",
    "EXAM"    => "exam-login.php",
    "OFFICE"  => "office-login.php",
    "SAC"     => "sac-login.php"
];

$redirect = $role_logins[$role] ?? "student-login.php";

// If requested via JSON API / Fetch
if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    json_response([
        "success" => true,
        "message" => "Logged out successfully",
        "redirect" => $redirect
    ]);
}

header("Location: " . $redirect);
exit();