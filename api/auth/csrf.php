<?php
/**
 * SmartCampus CSRF Token Provider API
 */

require_once __DIR__ . "/../../config/config.php";

$token = generate_csrf_token();

json_response([
    "success" => true,
    "csrf_token" => $token
]);
