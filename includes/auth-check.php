<?php
/**
 * SmartCampus Authentication & Authorization Guard
 */

require_once __DIR__ . "/../config/config.php";

/**
 * Enforce authentication and optional role restrictions
 *
 * @param array|string $allowed_roles Specific role or array of allowed roles
 * @param string|null $redirect_url Optional custom redirect URL
 */
function require_auth($allowed_roles = [], $redirect_url = null) {
    if (!isset($_SESSION["user_id"])) {
        if (empty($redirect_url)) {
            $redirect_url = "../auth/student-login.php";
        }
        
        // If request expects JSON, return 401 Unauthorized
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            json_response(["success" => false, "message" => "Unauthorized access. Please log in."], 401);
        }
        
        header("Location: " . $redirect_url);
        exit();
    }

    if (!empty($allowed_roles)) {
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }

        $current_role = $_SESSION["role"] ?? "";

        if (!in_array($current_role, $allowed_roles, true)) {
            if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                json_response(["success" => false, "message" => "Forbidden: You do not have permission to access this resource."], 403);
            }

            // Redirect to their respective dashboard or generic unauthorized page
            $role_dashboards = [
                "STUDENT" => "../student/dashboard.php",
                "FACULTY" => "../faculty/dashboard.php",
                "ADMIN"   => "../admin/dashboard.php",
                "EXAM"    => "../exam/dashboard.php",
                "OFFICE"  => "../office/dashboard.php",
                "SAC"     => "../sac/dashboard.php"
            ];

            $target = $role_dashboards[$current_role] ?? "../index.php";
            header("Location: " . $target);
            exit();
        }
    }
}

// Backward compatibility: If directly included, ensure the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/student-login.php");
    exit();
}