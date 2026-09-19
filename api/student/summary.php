<?php
/**
 * SmartCampus Student Summary API (for real-time dashboard refresh)
 */

require_once __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../includes/auth-check.php";
require_once __DIR__ . "/../../config/database.php";

require_auth("STUDENT");

$user_id = $_SESSION["user_id"];

// Fetch student profile
$stmt = $conn->prepare("SELECT student_id, roll_number, full_name, branch, semester, section FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    json_response(["success" => false, "message" => "Student record not found"], 404);
}

$student_id = $student["student_id"];

// Calculate Attendance Stats
$att_stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_classes,
        SUM(CASE WHEN UPPER(status) = 'PRESENT' THEN 1 ELSE 0 END) AS present_classes
    FROM attendance
    WHERE student_id = ?
");
$att_stmt->bind_param("i", $student_id);
$att_stmt->execute();
$att_data = $att_stmt->get_result()->fetch_assoc();
$att_stmt->close();

$total_classes = (int)($att_data["total_classes"] ?? 0);
$present_classes = (int)($att_data["present_classes"] ?? 0);
$attendance_pct = ($total_classes > 0) ? round(($present_classes / $total_classes) * 100, 1) : 0;

json_response([
    "success" => true,
    "student" => [
        "roll_number"    => $student["roll_number"],
        "full_name"      => $student["full_name"],
        "branch"         => $student["branch"],
        "semester"       => $student["semester"],
        "section"        => $student["section"]
    ],
    "attendance" => [
        "total"          => $total_classes,
        "present"        => $present_classes,
        "percentage"     => $attendance_pct,
        "is_shortage"    => ($attendance_pct < 75)
    ]
]);
