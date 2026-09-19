<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../auth/admin-login.php");
    exit();
}

require_once "../config/database.php";


// =========================
// BASIC COUNTS
// =========================

function getCount($conn, $table) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }
    return 0;
}

$total_students = getCount($conn, "students");
$total_faculty = getCount($conn, "faculty");
$total_users = getCount($conn, "users");
$total_subjects = getCount($conn, "subjects");
$total_exams = getCount($conn, "exam_details");
$total_projects = getCount($conn, "projects");


// =========================
// ATTENDANCE SUMMARY
// =========================

$attendance_total = 0;
$attendance_present = 0;
$attendance_absent = 0;

$attendance_query = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(UPPER(status) = 'PRESENT') AS present,
        SUM(UPPER(status) = 'ABSENT') AS absent
    FROM attendance
");

if ($attendance_query) {
    $attendance_data = $attendance_query->fetch_assoc();

    $attendance_total = (int)($attendance_data['total'] ?? 0);
    $attendance_present = (int)($attendance_data['present'] ?? 0);
    $attendance_absent = (int)($attendance_data['absent'] ?? 0);
}

$attendance_percentage = $attendance_total > 0
    ? round(($attendance_present / $attendance_total) * 100, 2)
    : 0;


// =========================
// FEES SUMMARY
// =========================

$total_fee = 0;
$total_paid = 0;
$total_pending = 0;

$fees_query = $conn->query("
    SELECT
        COALESCE(SUM(amount),0) AS total_fee,
        COALESCE(SUM(paid_amount),0) AS total_paid
    FROM fees
");

if ($fees_query) {

    $fees_data = $fees_query->fetch_assoc();

    $total_fee = (float)$fees_data['total_fee'];
    $total_paid = (float)$fees_data['total_paid'];
}

$total_pending = $total_fee - $total_paid;


// =========================
// DEPARTMENT SUMMARY
// =========================

$department_data = [];

$department_query = $conn->query("
    SELECT
        department,
        COUNT(*) AS total
    FROM students
    GROUP BY department
    ORDER BY total DESC
");

if ($department_query) {

    while ($row = $department_query->fetch_assoc()) {
        $department_data[] = $row;
    }
}


// =========================
// YEAR-WISE STUDENTS
// =========================

$year_data = [];

$year_query = $conn->query("
    SELECT
        year,
        COUNT(*) AS total
    FROM students
    GROUP BY year
    ORDER BY year
");

if ($year_query) {

    while ($row = $year_query->fetch_assoc()) {
        $year_data[] = $row;
    }
}


// =========================
// PROJECT STATUS
// =========================

$project_data = [];

$project_query = $conn->query("
    SELECT
        status,
        COUNT(*) AS total
    FROM projects
    GROUP BY status
");

if ($project_query) {

    while ($row = $project_query->fetch_assoc()) {
        $project_data[] = $row;
    }
}


// =========================
// EXAM SUMMARY
// =========================

$exam_data = [];

$exam_query = $conn->query("
    SELECT
        exam_type,
        COUNT(*) AS total
    FROM exam_details
    GROUP BY exam_type
    ORDER BY total DESC
");

if ($exam_query) {

    while ($row = $exam_query->fetch_assoc()) {
        $exam_data[] = $row;
    }
}


// =========================
// RECENT USERS
// =========================

$recent_users = [];

$user_query = $conn->query("
    SELECT
        user_id,
        username,
        role,
        status,
        created_at
    FROM users
    ORDER BY user_id DESC
    LIMIT 8
");

if ($user_query) {

    while ($row = $user_query->fetch_assoc()) {
        $recent_users[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin Reports | SmartCampus</title>

<link rel="stylesheet"
      href="../assets/css/admin.css">

</head>


<body class="admin-reports-page">


<!-- ================= SIDEBAR ================= -->

<aside class="admin-sidebar">

    <div class="admin-brand">

        <div class="admin-brand-icon">
            SC
        </div>

        <div>
            <strong>SmartCampus</strong>
            <span>Admin Panel</span>
        </div>

    </div>


    <nav class="admin-nav">

        <a href="dashboard.php">
            <span>📊</span>
            Dashboard
        </a>

        <a href="users.php">
            <span>👥</span>
            Users
        </a>

        <a href="students.php">
            <span>🎓</span>
            Students
        </a>

        <a href="faculty.php">
            <span>👨‍🏫</span>
            Faculty
        </a>

        <a href="subjects.php">
            <span>📚</span>
            Subjects
        </a>

        <a href="reports.php"
           class="active">
            <span>📈</span>
            Reports
        </a>

    </nav>


    <div class="admin-sidebar-bottom">

        <button id="themeToggle"
                class="admin-theme-btn">
            🌙 Dark Mode
        </button>

        <a href="../auth/logout.php"
           class="admin-logout">
            🚪 Logout
        </a>

    </div>

</aside>


<!-- ================= MAIN ================= -->

<main class="admin-main">


<header class="admin-topbar">

    <div>

        <h1>Reports & Analytics</h1>

        <p>
            View overall SmartCampus academic and administrative statistics.
        </p>

    </div>


    <div class="admin-profile">

        <div class="admin-avatar">
            A
        </div>

        <div>

            <strong>Administrator</strong>
            <span>ADMIN</span>

        </div>

    </div>

</header>


<!-- ================= REPORT ACTIONS ================= -->

<section class="admin-report-actions">

    <div>

        <h2>System Overview</h2>

        <p>
            A consolidated view of important campus statistics.
        </p>

    </div>


    <button onclick="window.print()"
            class="admin-primary-btn">

        🖨️ Print Report

    </button>

</section>


<!-- ================= MAIN STATS ================= -->

<section class="admin-user-stats">


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            🎓
        </div>

        <div>

            <span>Total Students</span>

            <strong>
                <?= $total_students ?>
            </strong>

        </div>

    </div>


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            👨‍🏫
        </div>

        <div>

            <span>Total Faculty</span>

            <strong>
                <?= $total_faculty ?>
            </strong>

        </div>

    </div>


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            📚
        </div>

        <div>

            <span>Total Subjects</span>

            <strong>
                <?= $total_subjects ?>
            </strong>

        </div>

    </div>


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            📝
        </div>

        <div>

            <span>Total Exams</span>

            <strong>
                <?= $total_exams ?>
            </strong>

        </div>

    </div>


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            🚀
        </div>

        <div>

            <span>Total Projects</span>

            <strong>
                <?= $total_projects ?>
            </strong>

        </div>

    </div>


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            👥
        </div>

        <div>

            <span>System Users</span>

            <strong>
                <?= $total_users ?>
            </strong>

        </div>

    </div>

</section>


<!-- ================= ATTENDANCE + FEES ================= -->

<section class="admin-report-grid">


    <!-- ATTENDANCE -->

    <div class="admin-panel report-card">

        <div class="admin-panel-header">

            <div>

                <h2>Attendance Overview</h2>

                <p>
                    Overall attendance across recorded classes.
                </p>

            </div>

            <div class="report-big-number">
                <?= $attendance_percentage ?>%
            </div>

        </div>


        <div class="report-progress">

            <div
                class="report-progress-fill"
                style="width: <?= min(100, $attendance_percentage) ?>%;">
            </div>

        </div>


        <div class="report-mini-stats">

            <div>

                <span>Total Records</span>

                <strong>
                    <?= $attendance_total ?>
                </strong>

            </div>


            <div>

                <span>Present</span>

                <strong>
                    <?= $attendance_present ?>
                </strong>

            </div>


            <div>

                <span>Absent</span>

                <strong>
                    <?= $attendance_absent ?>
                </strong>

            </div>

        </div>

    </div>


    <!-- FEES -->

    <div class="admin-panel report-card">

        <div class="admin-panel-header">

            <div>

                <h2>Fee Collection</h2>

                <p>
                    Overall fee collection status.
                </p>

            </div>

            <div class="report-big-number">

                ₹<?= number_format($total_paid, 0) ?>

            </div>

        </div>


        <div class="fee-report-row">

            <span>Total Fee</span>

            <strong>
                ₹<?= number_format($total_fee, 2) ?>
            </strong>

        </div>


        <div class="fee-report-row">

            <span>Collected</span>

            <strong>
                ₹<?= number_format($total_paid, 2) ?>
            </strong>

        </div>


        <div class="fee-report-row pending">

            <span>Pending</span>

            <strong>
                ₹<?= number_format(max(0, $total_pending), 2) ?>
            </strong>

        </div>

    </div>

</section>


<!-- ================= DEPARTMENT + YEAR ================= -->

<section class="admin-report-grid">


    <!-- DEPARTMENT -->

    <div class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>Students by Department</h2>

                <p>
                    Department-wise student distribution.
                </p>

            </div>

        </div>


        <?php if (!empty($department_data)): ?>

            <div class="report-list">

                <?php foreach ($department_data as $dept): ?>

                    <div class="report-list-item">

                        <div>

                            <strong>
                                <?= htmlspecialchars($dept['department']) ?>
                            </strong>

                            <span>
                                Students
                            </span>

                        </div>


                        <b>
                            <?= $dept['total'] ?>
                        </b>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="report-empty">
                No department data available.
            </div>

        <?php endif; ?>

    </div>


    <!-- YEAR -->

    <div class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>Students by Year</h2>

                <p>
                    Year-wise student distribution.
                </p>

            </div>

        </div>


        <?php if (!empty($year_data)): ?>

            <div class="report-list">

                <?php foreach ($year_data as $yr): ?>

                    <div class="report-list-item">

                        <div>

                            <strong>
                                Year <?= htmlspecialchars($yr['year']) ?>
                            </strong>

                            <span>
                                Students
                            </span>

                        </div>


                        <b>
                            <?= $yr['total'] ?>
                        </b>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="report-empty">
                No year data available.
            </div>

        <?php endif; ?>

    </div>

</section>


<!-- ================= PROJECT + EXAM ================= -->

<section class="admin-report-grid">


    <!-- PROJECT STATUS -->

    <div class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>Project Status</h2>

                <p>
                    Student project distribution by status.
                </p>

            </div>

        </div>


        <?php if (!empty($project_data)): ?>

            <div class="report-list">

                <?php foreach ($project_data as $project): ?>

                    <div class="report-list-item">

                        <div>

                            <strong>
                                <?= htmlspecialchars(
                                    $project['status']
                                ) ?>
                            </strong>

                            <span>
                                Projects
                            </span>

                        </div>


                        <b>
                            <?= $project['total'] ?>
                        </b>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="report-empty">
                No project data available.
            </div>

        <?php endif; ?>

    </div>


    <!-- EXAM TYPES -->

    <div class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>Exams by Type</h2>

                <p>
                    Exam distribution by examination type.
                </p>

            </div>

        </div>


        <?php if (!empty($exam_data)): ?>

            <div class="report-list">

                <?php foreach ($exam_data as $exam): ?>

                    <div class="report-list-item">

                        <div>

                            <strong>
                                <?= htmlspecialchars(
                                    $exam['exam_type']
                                ) ?>
                            </strong>

                            <span>
                                Examinations
                            </span>

                        </div>


                        <b>
                            <?= $exam['total'] ?>
                        </b>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="report-empty">
                No examination data available.
            </div>

        <?php endif; ?>

    </div>

</section>


<!-- ================= RECENT USERS ================= -->

<section class="admin-panel">

    <div class="admin-panel-header">

        <div>

            <h2>Recent System Users</h2>

            <p>
                Recently created accounts in SmartCampus.
            </p>

        </div>

    </div>


    <div class="admin-table-wrapper">

        <table class="admin-user-table">

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>

                </tr>

            </thead>


            <tbody>

            <?php if (!empty($recent_users)): ?>

                <?php foreach ($recent_users as $user): ?>

                    <tr>

                        <td>
                            #<?= $user['user_id'] ?>
                        </td>


                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $user['username']
                                ) ?>
                            </strong>

                        </td>


                        <td>

                            <span class="role-badge">

                                <?= htmlspecialchars(
                                    $user['role']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <span class="status-badge
                                <?= strtolower($user['status']) ?>">

                                <?= htmlspecialchars(
                                    $user['status']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= !empty($user['created_at'])
                                ? date(
                                    "d M Y",
                                    strtotime($user['created_at'])
                                )
                                : "-"
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td colspan="5"
                        class="no-users">

                        No users found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


</main>


<script>

/* =========================
   THEME
========================= */

const themeToggle =
    document.getElementById("themeToggle");


if (localStorage.getItem("smartcampus-theme") === "dark") {

    document.body.classList.add("dark-mode");

    themeToggle.innerHTML =
        "☀️ Light Mode";
}


themeToggle.addEventListener("click", function () {

    document.body.classList.toggle("dark-mode");


    if (document.body.classList.contains("dark-mode")) {

        localStorage.setItem(
            "smartcampus-theme",
            "dark"
        );

        themeToggle.innerHTML =
            "☀️ Light Mode";

    } else {

        localStorage.setItem(
            "smartcampus-theme",
            "light"
        );

        themeToggle.innerHTML =
            "🌙 Dark Mode";

    }

});

</script>


</body>
</html>