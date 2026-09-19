<?php

session_start();

require_once "../config/database.php";

/* =========================
   ADMIN ACCESS CHECK
========================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "ADMIN"
) {
    header("Location: ../auth/admin-login.php");
    exit();
}


/* =========================
   ADMIN DETAILS
========================= */

$admin_name = $_SESSION["username"] ?? "Administrator";


/* =========================
   DASHBOARD STATISTICS
========================= */

$student_count = 0;
$faculty_count = 0;
$user_count = 0;
$subject_count = 0;
$exam_count = 0;
$pending_fee_count = 0;
$notification_count = 0;


/* Students */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM students"
);

if ($result) {
    $student_count =
        (int)$result->fetch_assoc()["total"];
}


/* Faculty */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM faculty"
);

if ($result) {
    $faculty_count =
        (int)$result->fetch_assoc()["total"];
}


/* Users */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM users"
);

if ($result) {
    $user_count =
        (int)$result->fetch_assoc()["total"];
}


/* Subjects */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM subjects"
);

if ($result) {
    $subject_count =
        (int)$result->fetch_assoc()["total"];
}


/* Exams */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM exam_details"
);

if ($result) {
    $exam_count =
        (int)$result->fetch_assoc()["total"];
}


/* Pending Fees */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM fees
     WHERE payment_status IN ('PENDING','PARTIAL')"
);

if ($result) {
    $pending_fee_count =
        (int)$result->fetch_assoc()["total"];
}


/* Notifications */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE is_read = 0"
);

if ($result) {
    $notification_count =
        (int)$result->fetch_assoc()["total"];
}


/* =========================
   RECENT UPDATES
========================= */

$updates = [];

$result = $conn->query(
    "SELECT title, target_role, publish_date, status
     FROM information_updates
     ORDER BY publish_date DESC
     LIMIT 5"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $updates[] = $row;
    }

}


/* =========================
   RECENT USERS
========================= */

$recent_users = [];

$result = $conn->query(
    "SELECT username, role, status, created_at
     FROM users
     ORDER BY user_id DESC
     LIMIT 5"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Dashboard | SmartCampus
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body class="admin-dashboard-page">


<!-- =========================
     SIDEBAR
========================= -->

<aside class="admin-sidebar">

    <div class="admin-brand">

        <div class="admin-brand-logo">
            SC
        </div>

        <div>

            <h2>SmartCampus</h2>

            <span>
                Administrator
            </span>

        </div>

    </div>


    <nav class="admin-nav">

        <a
            href="dashboard.php"
            class="admin-nav-link active"
        >
            <span>🏠</span>
            Dashboard
        </a>


        <a
            href="users.php"
            class="admin-nav-link"
        >
            <span>👥</span>
            Users
        </a>


        <a
            href="students.php"
            class="admin-nav-link"
        >
            <span>🎓</span>
            Students
        </a>


        <a
            href="faculty.php"
            class="admin-nav-link"
        >
            <span>👨‍🏫</span>
            Faculty
        </a>


        <a
            href="subjects.php"
            class="admin-nav-link"
        >
            <span>📚</span>
            Subjects
        </a>


        <a
            href="reports.php"
            class="admin-nav-link"
        >
            <span>📊</span>
            Reports
        </a>

    </nav>


    <div class="admin-sidebar-bottom">

        <button
            type="button"
            id="adminThemeToggle"
            class="admin-theme-btn"
        >
            🌙
            <span>Theme</span>
        </button>


        <a
            href="../auth/logout.php"
            class="admin-logout"
        >
            <span>🚪</span>
            Logout
        </a>

    </div>

</aside>


<!-- =========================
     MAIN CONTENT
========================= -->

<main class="admin-main">


    <!-- TOPBAR -->

    <header class="admin-topbar">

        <div>

            <p class="admin-page-label">
                Administration
            </p>

            <h1>
                Dashboard
            </h1>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">
                <?= strtoupper(
                    substr($admin_name, 0, 1)
                ) ?>
            </div>

            <div>

                <strong>
                    <?= htmlspecialchars($admin_name) ?>
                </strong>

                <span>
                    Administrator
                </span>

            </div>

        </div>

    </header>


    <!-- WELCOME -->

    <section class="admin-welcome">

        <div>

            <span class="admin-welcome-tag">
                ADMINISTRATOR PANEL
            </span>

            <h2>
                Welcome back, <?= htmlspecialchars($admin_name) ?> 👋
            </h2>

            <p>
                Manage and monitor the SmartCampus
                system from one place.
            </p>

        </div>

        <div class="admin-welcome-icon">
            🛡️
        </div>

    </section>


    <!-- STATISTICS -->

    <section class="admin-stats">


        <div class="admin-stat-card">

            <div class="admin-stat-icon">
                🎓
            </div>

            <div>

                <span>
                    Total Students
                </span>

                <strong>
                    <?= $student_count ?>
                </strong>

            </div>

        </div>


        <div class="admin-stat-card">

            <div class="admin-stat-icon">
                👨‍🏫
            </div>

            <div>

                <span>
                    Total Faculty
                </span>

                <strong>
                    <?= $faculty_count ?>
                </strong>

            </div>

        </div>


        <div class="admin-stat-card">

            <div class="admin-stat-icon">
                👥
            </div>

            <div>

                <span>
                    System Users
                </span>

                <strong>
                    <?= $user_count ?>
                </strong>

            </div>

        </div>


        <div class="admin-stat-card">

            <div class="admin-stat-icon">
                📚
            </div>

            <div>

                <span>
                    Subjects
                </span>

                <strong>
                    <?= $subject_count ?>
                </strong>

            </div>

        </div>


        <div class="admin-stat-card">

            <div class="admin-stat-icon">
                📝
            </div>

            <div>

                <span>
                    Exams
                </span>

                <strong>
                    <?= $exam_count ?>
                </strong>

            </div>

        </div>


        <div class="admin-stat-card">

            <div class="admin-stat-icon">
                💰
            </div>

            <div>

                <span>
                    Pending Fees
                </span>

                <strong>
                    <?= $pending_fee_count ?>
                </strong>

            </div>

        </div>


    </section>


    <!-- QUICK ACTIONS -->

    <section class="admin-section">

        <div class="admin-section-header">

            <div>

                <h2>
                    Quick Actions
                </h2>

                <p>
                    Frequently used administration tools
                </p>

            </div>

        </div>


        <div class="admin-quick-grid">


            <a
                href="users.php"
                class="admin-quick-card"
            >

                <span>👥</span>

                <div>

                    <strong>
                        Manage Users
                    </strong>

                    <small>
                        View and manage login accounts
                    </small>

                </div>

            </a>


            <a
                href="students.php"
                class="admin-quick-card"
            >

                <span>🎓</span>

                <div>

                    <strong>
                        Student Management
                    </strong>

                    <small>
                        View student information
                    </small>

                </div>

            </a>


            <a
                href="faculty.php"
                class="admin-quick-card"
            >

                <span>👨‍🏫</span>

                <div>

                    <strong>
                        Faculty Management
                    </strong>

                    <small>
                        View faculty information
                    </small>

                </div>

            </a>


            <a
                href="reports.php"
                class="admin-quick-card"
            >

                <span>📊</span>

                <div>

                    <strong>
                        View Reports
                    </strong>

                    <small>
                        System statistics and reports
                    </small>

                </div>

            </a>

        </div>

    </section>


    <!-- BOTTOM GRID -->

    <section class="admin-bottom-grid">


        <!-- RECENT UPDATES -->

        <div class="admin-panel">

            <div class="admin-panel-header">

                <div>

                    <h3>
                        Recent Updates
                    </h3>

                    <p>
                        Latest campus announcements
                    </p>

                </div>

                <span class="admin-panel-icon">
                    📢
                </span>

            </div>


            <?php if (empty($updates)): ?>

                <div class="admin-empty">
                    No updates available.
                </div>

            <?php else: ?>

                <div class="admin-list">

                    <?php foreach ($updates as $update): ?>

                        <div class="admin-list-item">

                            <div>

                                <strong>
                                    <?= htmlspecialchars(
                                        $update["title"]
                                    ) ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars(
                                        $update["target_role"]
                                    ) ?>
                                </small>

                            </div>

                            <span>
                                <?= htmlspecialchars(
                                    $update["status"]
                                ) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>


        <!-- RECENT USERS -->

        <div class="admin-panel">

            <div class="admin-panel-header">

                <div>

                    <h3>
                        Recent Users
                    </h3>

                    <p>
                        Latest system accounts
                    </p>

                </div>

                <span class="admin-panel-icon">
                    👤
                </span>

            </div>


            <?php if (empty($recent_users)): ?>

                <div class="admin-empty">
                    No users available.
                </div>

            <?php else: ?>

                <div class="admin-list">

                    <?php foreach ($recent_users as $recent): ?>

                        <div class="admin-list-item">

                            <div>

                                <strong>
                                    <?= htmlspecialchars(
                                        $recent["username"]
                                    ) ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars(
                                        $recent["role"]
                                    ) ?>
                                </small>

                            </div>

                            <span>
                                <?= htmlspecialchars(
                                    $recent["status"]
                                ) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>


    </section>


</main>


<script>

/* =========================
   ADMIN THEME
========================= */

const themeButton =
    document.getElementById("adminThemeToggle");

const savedTheme =
    localStorage.getItem("smartcampus-theme");


if (savedTheme === "dark") {

    document.body.classList.add("dark-theme");

    themeButton.innerHTML =
        "☀️ <span>Theme</span>";

} else {

    themeButton.innerHTML =
        "🌙 <span>Theme</span>";

}


themeButton.addEventListener(
    "click",
    function () {

        document.body.classList.toggle(
            "dark-theme"
        );

        const isDark =
            document.body.classList.contains(
                "dark-theme"
            );


        if (isDark) {

            localStorage.setItem(
                "smartcampus-theme",
                "dark"
            );

            themeButton.innerHTML =
                "☀️ <span>Theme</span>";

        } else {

            localStorage.setItem(
                "smartcampus-theme",
                "light"
            );

            themeButton.innerHTML =
                "🌙 <span>Theme</span>";

        }

    }
);

</script>

</body>

</html>