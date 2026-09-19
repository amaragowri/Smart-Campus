<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !in_array($_SESSION["role"], ["SAC_LEAD", "DOMAIN_LEAD", "CR", "LR"])
) {
    header("Location: ../auth/student-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

/* User */
$userQuery = $conn->prepare("
    SELECT username, role
    FROM users
    WHERE user_id = ?
");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();


/* Student details if available */
$student = null;

$studentQuery = $conn->prepare("
    SELECT
        student_id,
        roll_number,
        full_name,
        email,
        department,
        year,
        section
    FROM students
    WHERE user_id = ?
");

$studentQuery->bind_param("i", $user_id);
$studentQuery->execute();

$student = $studentQuery->get_result()->fetch_assoc();


/* Statistics */

$totalClubs = 0;
$totalActivities = 0;
$totalStudents = 0;
$totalUpdates = 0;
$totalNotifications = 0;


/* Clubs */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM clubs
    WHERE UPPER(status) = 'ACTIVE'
");

if ($result) {
    $totalClubs = (int)$result->fetch_assoc()["total"];
}


/* Activities */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM club_activities
");

if ($result) {
    $totalActivities = (int)$result->fetch_assoc()["total"];
}


/* Students */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
");

if ($result) {
    $totalStudents = (int)$result->fetch_assoc()["total"];
}


/* Updates */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM information_updates
    WHERE UPPER(status) = 'ACTIVE'
");

if ($result) {
    $totalUpdates = (int)$result->fetch_assoc()["total"];
}


/* Notifications */
$notificationQuery = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE user_id = ?
");

$notificationQuery->bind_param("i", $user_id);
$notificationQuery->execute();

$notificationResult = $notificationQuery->get_result()->fetch_assoc();

$totalNotifications = (int)($notificationResult["total"] ?? 0);


/* Recent clubs */

$recentClubs = $conn->query("
    SELECT
        c.club_id,
        c.club_name,
        c.description,
        c.status,
        c.created_at,
        s.full_name AS student_lead
    FROM clubs c
    LEFT JOIN students s
        ON c.student_lead_id = s.student_id
    ORDER BY c.created_at DESC
    LIMIT 5
");


/* Recent activities */

$recentActivities = $conn->query("
    SELECT
        ca.activity_id,
        ca.activity_name,
        ca.description,
        ca.activity_date,
        ca.venue,
        c.club_name
    FROM club_activities ca
    INNER JOIN clubs c
        ON ca.club_id = c.club_id
    ORDER BY ca.activity_date DESC
    LIMIT 5
");


/* Recent updates */

$recentUpdates = $conn->query("
    SELECT
        update_id,
        title,
        description,
        target_role,
        publish_date
    FROM information_updates
    WHERE UPPER(status) = 'ACTIVE'
    ORDER BY publish_date DESC
    LIMIT 5
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>SAC Dashboard | SmartCampus</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body>

<div class="dashboard-layout sac-dashboard-page">


    <!-- ================= SIDEBAR ================= -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="brand-logo">
                SC
            </div>

            <div>

                <h2>
                    SmartCampus
                </h2>

                <span>
                    SAC Portal
                </span>

            </div>

        </div>


        <nav class="sidebar-nav">

            <a
                href="dashboard.php"
                class="active"
            >
                <span>🏠</span>
                Dashboard
            </a>


            <a href="clubs.php">
                <span>👥</span>
                Clubs
            </a>


            <a href="activities.php">
                <span>🎯</span>
                Activities
            </a>


            <a href="leaders.php">
                <span>👤</span>
                Student Leaders
            </a>


            <a href="updates.php">
                <span>📢</span>
                Information Updates
            </a>


            <a href="notifications.php">
                <span>🔔</span>
                Notifications
            </a>


            <a href="reports.php">
                <span>📊</span>
                Reports
            </a>


            <a href="../auth/logout.php">
                <span>🚪</span>
                Logout
            </a>

        </nav>

    </aside>



    <!-- ================= MAIN ================= -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">

            <div>

                <h1>
                    SAC Dashboard
                </h1>

                <p>
                    Manage student activities, clubs and campus events
                </p>

            </div>


            <div class="topbar-actions">

                <button
                    type="button"
                    class="theme-toggle"
                    onclick="toggleTheme()"
                    title="Toggle theme"
                >
                    🌙
                </button>


                <div class="user-info">

                    <strong>
                        <?= htmlspecialchars(
                            $student["full_name"]
                            ?? $user["username"]
                            ?? "SAC User"
                        ) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($role) ?>
                    </span>

                </div>

            </div>

        </header>



        <!-- ================= WELCOME ================= -->

        <section class="sac-welcome-card">

            <div>

                <span class="sac-welcome-label">
                    Student Activity Council
                </span>

                <h2>
                    Welcome,
                    <?= htmlspecialchars(
                        $student["full_name"]
                        ?? $user["username"]
                        ?? "User"
                    ) ?>
                    👋
                </h2>

                <p>
                    Coordinate clubs, activities, events and
                    student engagement from one place.
                </p>

            </div>


            <div class="sac-welcome-icon">
                🎓
            </div>

        </section>



        <!-- ================= STATISTICS ================= -->

        <section class="sac-stats">


            <div class="sac-stat-card">

                <div class="sac-stat-icon">
                    👥
                </div>

                <div>

                    <span>
                        Active Clubs
                    </span>

                    <h2>
                        <?= $totalClubs ?>
                    </h2>

                </div>

            </div>


            <div class="sac-stat-card">

                <div class="sac-stat-icon">
                    🎯
                </div>

                <div>

                    <span>
                        Activities
                    </span>

                    <h2>
                        <?= $totalActivities ?>
                    </h2>

                </div>

            </div>


            <div class="sac-stat-card">

                <div class="sac-stat-icon">
                    🎓
                </div>

                <div>

                    <span>
                        Students
                    </span>

                    <h2>
                        <?= $totalStudents ?>
                    </h2>

                </div>

            </div>


            <div class="sac-stat-card">

                <div class="sac-stat-icon">
                    📢
                </div>

                <div>

                    <span>
                        Active Updates
                    </span>

                    <h2>
                        <?= $totalUpdates ?>
                    </h2>

                </div>

            </div>

        </section>



        <!-- ================= QUICK ACTIONS ================= -->

        <section class="sac-section">

            <div class="sac-section-header">

                <div>

                    <h2>
                        Quick Actions
                    </h2>

                    <p>
                        Common SAC management activities
                    </p>

                </div>

            </div>


            <div class="sac-quick-grid">


                <a
                    href="clubs.php"
                    class="sac-action-card"
                >

                    <div class="sac-action-icon">
                        👥
                    </div>

                    <div>

                        <h3>
                            Manage Clubs
                        </h3>

                        <p>
                            View and manage student clubs
                        </p>

                    </div>

                    <span class="sac-action-arrow">
                        →
                    </span>

                </a>



                <a
                    href="activities.php"
                    class="sac-action-card"
                >

                    <div class="sac-action-icon">
                        🎯
                    </div>

                    <div>

                        <h3>
                            Activities
                        </h3>

                        <p>
                            Manage events and activities
                        </p>

                    </div>

                    <span class="sac-action-arrow">
                        →
                    </span>

                </a>



                <a
                    href="updates.php"
                    class="sac-action-card"
                >

                    <div class="sac-action-icon">
                        📢
                    </div>

                    <div>

                        <h3>
                            Information Updates
                        </h3>

                        <p>
                            Publish campus information
                        </p>

                    </div>

                    <span class="sac-action-arrow">
                        →
                    </span>

                </a>



                <a
                    href="reports.php"
                    class="sac-action-card"
                >

                    <div class="sac-action-icon">
                        📊
                    </div>

                    <div>

                        <h3>
                            View Reports
                        </h3>

                        <p>
                            Analyze SAC activities
                        </p>

                    </div>

                    <span class="sac-action-arrow">
                        →
                    </span>

                </a>

            </div>

        </section>



        <!-- ================= TWO COLUMNS ================= -->

        <div class="sac-two-column">


            <!-- RECENT CLUBS -->

            <section class="sac-section">

                <div class="sac-section-header">

                    <div>

                        <h2>
                            Recent Clubs
                        </h2>

                        <p>
                            Latest registered clubs
                        </p>

                    </div>

                    <a
                        href="clubs.php"
                        class="sac-view-link"
                    >
                        View All
                    </a>

                </div>


                <div class="sac-list">

                    <?php if (
                        $recentClubs &&
                        $recentClubs->num_rows > 0
                    ): ?>

                        <?php while (
                            $club = $recentClubs->fetch_assoc()
                        ): ?>

                            <div class="sac-list-item">

                                <div class="sac-list-icon">
                                    👥
                                </div>

                                <div class="sac-list-content">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $club["club_name"]
                                        ) ?>
                                    </strong>

                                    <span>
                                        Lead:
                                        <?= htmlspecialchars(
                                            $club["student_lead"]
                                            ?? "Not assigned"
                                        ) ?>
                                    </span>

                                </div>

                                <span class="sac-status">
                                    <?= htmlspecialchars(
                                        $club["status"]
                                    ) ?>
                                </span>

                            </div>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <div class="sac-empty">
                            No clubs available yet.
                        </div>

                    <?php endif; ?>

                </div>

            </section>



            <!-- RECENT ACTIVITIES -->

            <section class="sac-section">

                <div class="sac-section-header">

                    <div>

                        <h2>
                            Recent Activities
                        </h2>

                        <p>
                            Latest club activities
                        </p>

                    </div>

                    <a
                        href="activities.php"
                        class="sac-view-link"
                    >
                        View All
                    </a>

                </div>


                <div class="sac-list">

                    <?php if (
                        $recentActivities &&
                        $recentActivities->num_rows > 0
                    ): ?>

                        <?php while (
                            $activity =
                            $recentActivities->fetch_assoc()
                        ): ?>

                            <div class="sac-list-item">

                                <div class="sac-list-icon">
                                    🎯
                                </div>

                                <div class="sac-list-content">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $activity["activity_name"]
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $activity["club_name"]
                                        ) ?>

                                        •

                                        <?= !empty(
                                            $activity["activity_date"]
                                        )
                                            ? date(
                                                "d M Y",
                                                strtotime(
                                                    $activity[
                                                        "activity_date"
                                                    ]
                                                )
                                            )
                                            : "-"
                                        ?>
                                    </span>

                                </div>

                            </div>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <div class="sac-empty">
                            No activities available yet.
                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </div>



        <!-- ================= INFORMATION UPDATES ================= -->

        <section class="sac-section">

            <div class="sac-section-header">

                <div>

                    <h2>
                        Latest Information Updates
                    </h2>

                    <p>
                        Recent campus announcements
                    </p>

                </div>

                <a
                    href="updates.php"
                    class="sac-view-link"
                >
                    View All
                </a>

            </div>


            <div class="sac-updates-grid">

                <?php if (
                    $recentUpdates &&
                    $recentUpdates->num_rows > 0
                ): ?>

                    <?php while (
                        $update =
                        $recentUpdates->fetch_assoc()
                    ): ?>

                        <div class="sac-update-card">

                            <div class="sac-update-icon">
                                📢
                            </div>

                            <div>

                                <h3>
                                    <?= htmlspecialchars(
                                        $update["title"]
                                    ) ?>
                                </h3>

                                <p>
                                    <?= htmlspecialchars(
                                        $update["description"]
                                    ) ?>
                                </p>

                                <span>

                                    <?= !empty(
                                        $update["publish_date"]
                                    )
                                        ? date(
                                            "d M Y",
                                            strtotime(
                                                $update[
                                                    "publish_date"
                                                ]
                                            )
                                        )
                                        : "-"
                                    ?>

                                </span>

                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="sac-empty">
                        No information updates available.
                    </div>

                <?php endif; ?>

            </div>

        </section>


    </main>

</div>



<script>

function toggleTheme() {

    document.body.classList.toggle("dark-theme");

    localStorage.setItem(
        "smartCampusTheme",
        document.body.classList.contains("dark-theme")
            ? "dark"
            : "light"
    );

}


document.addEventListener(
    "DOMContentLoaded",
    function () {

        const savedTheme =
            localStorage.getItem("smartCampusTheme");

        if (savedTheme === "dark") {

            document.body.classList.add("dark-theme");

        }

    }
);

</script>


</body>
</html>