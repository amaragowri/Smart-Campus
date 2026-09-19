<?php

session_start();
require_once "../config/database.php";

/* =========================
   AUTHENTICATION CHECK
   ========================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "STUDENT") {
    header("Location: ../auth/student-login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

/* =========================
   GET STUDENT DETAILS
   ========================= */

$sql = "SELECT *
        FROM students
        WHERE user_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$student_id = $student["student_id"];

/* =========================
   ATTENDANCE
   ========================= */

$attendance_sql = "
    SELECT
        COUNT(*) AS total_classes,
        SUM(CASE WHEN status = 'PRESENT' THEN 1 ELSE 0 END) AS present_classes
    FROM attendance
    WHERE student_id = ?
";

$stmt = $conn->prepare($attendance_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$attendance_result = $stmt->get_result();
$attendance = $attendance_result->fetch_assoc();

$stmt->close();

$total_classes = (int)($attendance["total_classes"] ?? 0);
$present_classes = (int)($attendance["present_classes"] ?? 0);

if ($total_classes > 0) {
    $attendance_percentage =
        round(($present_classes / $total_classes) * 100, 1);
} else {
    $attendance_percentage = 0;
}

/* =========================
   SUBJECT COUNT
   ========================= */

$subject_sql = "
    SELECT COUNT(*) AS total_subjects
    FROM subjects
    WHERE department = ?
    AND year = ?
";

$stmt = $conn->prepare($subject_sql);
$stmt->bind_param(
    "si",
    $student["department"],
    $student["year"]
);

$stmt->execute();

$subject_result = $stmt->get_result();
$subject_data = $subject_result->fetch_assoc();

$stmt->close();

$total_subjects = (int)($subject_data["total_subjects"] ?? 0);

/* =========================
   FEE DETAILS
   ========================= */

$fee_sql = "
    SELECT
        COALESCE(SUM(amount), 0) AS total_fee,
        COALESCE(SUM(paid_amount), 0) AS paid_fee
    FROM fees
    WHERE student_id = ?
";

$stmt = $conn->prepare($fee_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();

$fee_result = $stmt->get_result();
$fee_data = $fee_result->fetch_assoc();

$stmt->close();

$total_fee = (float)($fee_data["total_fee"] ?? 0);
$paid_fee = (float)($fee_data["paid_fee"] ?? 0);
$pending_fee = max(0, $total_fee - $paid_fee);

/* =========================
   NOTIFICATIONS
   ========================= */

$notification_sql = "
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ?
    AND is_read = 0
";

$stmt = $conn->prepare($notification_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$notification_result = $stmt->get_result();
$notification_data = $notification_result->fetch_assoc();

$stmt->close();

$unread_notifications =
    (int)($notification_data["unread_count"] ?? 0);

/* =========================
   RECENT NOTIFICATIONS
   ========================= */

$recent_sql = "
    SELECT title, message, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
";

$stmt = $conn->prepare($recent_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$recent_notifications = $stmt->get_result();

/* =========================
   ATTENDANCE STATUS
   ========================= */

if ($attendance_percentage >= 75) {
    $attendance_status = "Good";
} elseif ($attendance_percentage >= 65) {
    $attendance_status = "Warning";
} else {
    $attendance_status = "Low";
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Student Dashboard | SmartCampus
    </title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

</head>

<body>

<div class="dashboard">

    <!-- =========================
         SIDEBAR
         ========================= -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
                SC
            </div>

            <div>
                <h2>SmartCampus</h2>
                <span>Student Portal</span>
            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php" class="active">
                🏠 Dashboard
            </a>

            <a href="profile.php">
                👤 My Profile
            </a>

            <a href="attendance.php">
                📊 Attendance
            </a>

            <a href="timetable.php">
                🕐 Timetable
            </a>

            <a href="materials.php">
                📚 Study Materials
            </a>

            <a href="exams.php">
                📝 Exams
            </a>

            <a href="results.php">
                📈 Results
            </a>

            <a href="hall-ticket.php">
                🎫 Hall Ticket
            </a>

            <a href="fees.php">
                💰 Fees & Payments
            </a>

            <a href="projects.php">
                💻 Projects
            </a>

            <a href="clubs.php">
                🎯 Clubs & Activities
            </a>

            <a href="notifications.php">
                🔔 Notifications
                <?php if ($unread_notifications > 0): ?>
                    <span class="notification-badge">
                        <?php echo $unread_notifications; ?>
                    </span>
                <?php endif; ?>
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">
                🚪 Logout
            </a>

        </div>

    </aside>


    <!-- =========================
         MAIN CONTENT
         ========================= -->

    <main class="dashboard-content">

        <!-- TOP BAR -->

        <header class="dashboard-header">

            <div>

                <p class="welcome-text">
                    Welcome back,
                </p>

                <h1>
                    <?php echo htmlspecialchars($student["full_name"]); ?>
                </h1>

                <p class="student-meta">
                    <?php echo htmlspecialchars($student["roll_number"]); ?>
                    &nbsp; • &nbsp;
                    <?php echo htmlspecialchars($student["department"]); ?>
                    &nbsp; • &nbsp;
                    Year <?php echo htmlspecialchars($student["year"]); ?>
                    - Section <?php echo htmlspecialchars($student["section"]); ?>
                </p>

            </div>


            <button
                type="button"
                class="dashboard-theme-toggle"
                id="dashboardThemeToggle"
                title="Change theme">

                🌙

            </button>

        </header>


        <!-- =========================
             STAT CARDS
             ========================= -->

        <section class="stats-grid">

            <div class="stat-card dashboard-stat">

                <div class="stat-icon">
                    📊
                </div>

                <div>

                    <span>
                        Attendance
                    </span>

                    <strong>
                        <?php echo $attendance_percentage; ?>%
                    </strong>

                    <small>
                        <?php echo $attendance_status; ?>
                    </small>

                </div>

            </div>


            <div class="stat-card dashboard-stat">

                <div class="stat-icon">
                    📚
                </div>

                <div>

                    <span>
                        Subjects
                    </span>

                    <strong>
                        <?php echo $total_subjects; ?>
                    </strong>

                    <small>
                        Current semester
                    </small>

                </div>

            </div>


            <div class="stat-card dashboard-stat">

                <div class="stat-icon">
                    💰
                </div>

                <div>

                    <span>
                        Pending Fees
                    </span>

                    <strong>
                        ₹<?php echo number_format($pending_fee, 2); ?>
                    </strong>

                    <small>
                        Amount due
                    </small>

                </div>

            </div>


            <div class="stat-card dashboard-stat">

                <div class="stat-icon">
                    🔔
                </div>

                <div>

                    <span>
                        Notifications
                    </span>

                    <strong>
                        <?php echo $unread_notifications; ?>
                    </strong>

                    <small>
                        Unread
                    </small>

                </div>

            </div>

        </section>


        <!-- =========================
             MAIN DASHBOARD GRID
             ========================= -->

        <section class="dashboard-grid">


            <!-- PROFILE CARD -->

            <div class="dashboard-panel profile-panel">

                <div class="panel-header">

                    <div>

                        <h2>Student Profile</h2>

                        <p>
                            Your academic information
                        </p>

                    </div>

                    <span class="profile-avatar">
                        <?php
                        echo strtoupper(
                            substr($student["full_name"], 0, 1)
                        );
                        ?>
                    </span>

                </div>


                <div class="profile-details">

                    <div>
                        <span>Full Name</span>
                        <strong>
                            <?php echo htmlspecialchars($student["full_name"]); ?>
                        </strong>
                    </div>

                    <div>
                        <span>Roll Number</span>
                        <strong>
                            <?php echo htmlspecialchars($student["roll_number"]); ?>
                        </strong>
                    </div>

                    <div>
                        <span>Email</span>
                        <strong>
                            <?php echo htmlspecialchars($student["email"]); ?>
                        </strong>
                    </div>

                    <div>
                        <span>Department</span>
                        <strong>
                            <?php echo htmlspecialchars($student["department"]); ?>
                        </strong>
                    </div>

                </div>

            </div>


            <!-- FEE CARD -->

            <div class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <h2>Fee Overview</h2>

                        <p>
                            Current payment status
                        </p>

                    </div>

                    <span class="panel-icon">
                        💳
                    </span>

                </div>


                <div class="fee-summary">

                    <div>
                        <span>Total Fee</span>
                        <strong>
                            ₹<?php echo number_format($total_fee, 2); ?>
                        </strong>
                    </div>

                    <div>
                        <span>Paid</span>
                        <strong class="success-text">
                            ₹<?php echo number_format($paid_fee, 2); ?>
                        </strong>
                    </div>

                    <div>
                        <span>Pending</span>
                        <strong class="danger-text">
                            ₹<?php echo number_format($pending_fee, 2); ?>
                        </strong>
                    </div>

                </div>


                <a href="fees.php"
                   class="btn btn-primary panel-button">

                    View Fee Details →

                </a>

            </div>


            <!-- NOTIFICATIONS -->

            <div class="dashboard-panel notifications-panel">

                <div class="panel-header">

                    <div>

                        <h2>Recent Notifications</h2>

                        <p>
                            Latest updates for you
                        </p>

                    </div>

                    <span class="panel-icon">
                        🔔
                    </span>

                </div>


                <?php if ($recent_notifications->num_rows > 0): ?>

                    <div class="notification-list">

                        <?php while ($notification =
                            $recent_notifications->fetch_assoc()): ?>

                            <div class="notification-item">

                                <div class="notification-dot"></div>

                                <div>

                                    <h3>
                                        <?php
                                        echo htmlspecialchars(
                                            $notification["title"]
                                        );
                                        ?>
                                    </h3>

                                    <p>
                                        <?php
                                        echo htmlspecialchars(
                                            $notification["message"]
                                        );
                                        ?>
                                    </p>

                                    <small>
                                        <?php
                                        echo htmlspecialchars(
                                            $notification["created_at"]
                                        );
                                        ?>
                                    </small>

                                </div>

                            </div>

                        <?php endwhile; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <div>
                            🔔
                        </div>

                        <p>
                            No notifications yet.
                        </p>

                    </div>

                <?php endif; ?>

            </div>


            <!-- QUICK ACCESS -->

            <div class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <h2>Quick Access</h2>

                        <p>
                            Frequently used services
                        </p>

                    </div>

                </div>


                <div class="quick-actions">

                    <a href="attendance.php">
                        <span>📊</span>
                        Attendance
                    </a>

                    <a href="timetable.php">
                        <span>🕐</span>
                        Timetable
                    </a>

                    <a href="materials.php">
                        <span>📚</span>
                        Materials
                    </a>

                    <a href="results.php">
                        <span>📈</span>
                        Results
                    </a>

                    <a href="fees.php">
                        <span>💰</span>
                        Fees
                    </a>

                    <a href="hall-ticket.php">
                        <span>🎫</span>
                        Hall Ticket
                    </a>

                </div>

            </div>

        </section>


        <footer class="dashboard-footer">

            © 2026 SmartCampus · Student Management Portal

        </footer>

    </main>

</div>


<script>

/* =========================
   DASHBOARD THEME
   ========================= */

const themeButton =
    document.getElementById("dashboardThemeToggle");

const savedTheme =
    localStorage.getItem("smartcampus-theme");

if (savedTheme === "dark") {

    document.body.classList.add("dark-theme");

    themeButton.textContent = "☀️";

} else {

    themeButton.textContent = "🌙";
}


themeButton.addEventListener("click", function () {

    document.body.classList.toggle("dark-theme");

    const isDark =
        document.body.classList.contains("dark-theme");

    if (isDark) {

        localStorage.setItem(
            "smartcampus-theme",
            "dark"
        );

        themeButton.textContent = "☀️";

    } else {

        localStorage.setItem(
            "smartcampus-theme",
            "light"
        );

        themeButton.textContent = "🌙";
    }

});

</script>

</body>

</html>