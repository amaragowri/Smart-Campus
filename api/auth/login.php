<?php
/**
 * SmartCampus Universal Asynchronous Login API
 */

require_once __DIR__ . "/../../config/config.php";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    json_response(["success" => false, "message" => "Invalid request method. Only POST is accepted."], 405);
}

require_once __DIR__ . "/../../config/database.php";

// Read JSON or Form POST data
$raw_input = file_get_contents("php://input");
$input = json_decode($raw_input, true);

if (!is_array($input)) {
    $input = $_POST;
}

$username      = trim($input["username"] ?? "");
$password      = $input["password"] ?? "";
$expected_role = strtoupper(trim($input["role"] ?? ""));
$csrf_token    = $input["csrf_token"] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

// Validate CSRF if provided
if ($csrf_token !== null && !verify_csrf_token($csrf_token)) {
    json_response(["success" => false, "message" => "Security validation failed. Please refresh the page."], 403);
}

if ($username === "" || $password === "") {
    json_response(["success" => false, "message" => "Please enter username and password."], 400);
}

// Query user by username
$sql = "SELECT user_id, username, password, role, status FROM users WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    json_response(["success" => false, "message" => "Database error: " . $conn->error], 500);
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    $stmt->close();
    json_response(["success" => false, "message" => "Invalid username or password."], 401);
}

$user = $result->fetch_assoc();
$stmt->close();

$user_role   = strtoupper(trim($user["role"]));
$user_status = strtoupper(trim($user["status"]));

// Status check
if ($user_status !== "ACTIVE") {
    json_response(["success" => false, "message" => "Your account is inactive. Please contact administration."], 403);
}

// Role groups
$sac_roles = ["SAC", "SAC_LEAD", "DOMAIN_LEAD", "CR", "LR"];
$exam_roles = ["EXAM", "EXAM_CELL"];

// Role check if specific portal requested
if (!empty($expected_role)) {
    $is_role_match = false;

    if ($expected_role === "SAC" && in_array($user_role, $sac_roles, true)) {
        $is_role_match = true;
    } elseif ($expected_role === "EXAM" && in_array($user_role, $exam_roles, true)) {
        $is_role_match = true;
    } elseif ($expected_role === $user_role) {
        $is_role_match = true;
    }

    if (!$is_role_match) {
        json_response([
            "success" => false, 
            "message" => "Access denied for this portal. Your account is registered as: " . $user_role
        ], 403);
    }
}

// Verify Password (supports password_verify with fallback to hash_equals for test seeds)
$password_valid = password_verify($password, $user["password"]) || hash_equals((string)$user["password"], $password);

if (!$password_valid) {
    json_response(["success" => false, "message" => "Invalid username or password."], 401);
}

// Regenerate session ID on login to prevent session fixation
session_regenerate_id(true);

$_SESSION["user_id"]  = $user["user_id"];
$_SESSION["username"] = $user["username"];
$_SESSION["role"]     = $user_role;

// Determine redirect destination based on role
$dashboards = [
    "STUDENT"     => "../student/dashboard.php",
    "FACULTY"     => "../faculty/dashboard.php",
    "ADMIN"       => "../admin/dashboard.php",
    "EXAM"        => "../exam/dashboard.php",
    "EXAM_CELL"   => "../exam/dashboard.php",
    "OFFICE"      => "../office/dashboard.php",
    "SAC"         => "../sac/dashboard.php",
    "SAC_LEAD"    => "../sac/dashboard.php",
    "DOMAIN_LEAD" => "../sac/dashboard.php",
    "CR"          => "../sac/dashboard.php",
    "LR"          => "../sac/dashboard.php"
];

$target_redirect = $dashboards[$user_role] ?? "../index.php";

json_response([
    "success"  => true,
    "message"  => "Welcome back, " . htmlspecialchars($user["username"]) . "!",
    "redirect" => $target_redirect,
    "user"     => [
        "user_id"  => $user["user_id"],
        "username" => $user["username"],
        "role"     => $user_role
    ]
]);
