<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "STUDENT") {
    header("Location: ../auth/student-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

/* ---------------- STUDENT DETAILS ---------------- */
$student_sql = "
    SELECT student_id, full_name, roll_number, department, year, section
    FROM students
    WHERE user_id = ?
";

$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();

$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$student_id = $student["student_id"];

/* ---------------- CLUBS & ACTIVITIES ---------------- */
$club_sql = "
    SELECT
        c.club_id,
        c.club_name,
        c.description,
        c.status,
        f.full_name AS faculty_name,
        f.designation,
        ca.activity_id,
        ca.activity_name,
        ca.description AS activity_description,
        ca.activity_date,
        ca.venue
    FROM clubs c
    LEFT JOIN faculty f
        ON c.faculty_id = f.faculty_id
    LEFT JOIN club_activities ca
        ON c.club_id = ca.club_id
    WHERE c.status = 'ACTIVE'
    ORDER BY c.club_name ASC, ca.activity_date DESC
";

$stmt = $conn->prepare($club_sql);
$stmt->execute();

$club_result = $stmt->get_result();

$clubs = [];

while ($row = $club_result->fetch_assoc()) {

    $club_id = $row["club_id"];

    if (!isset($clubs[$club_id])) {
        $clubs[$club_id] = [
            "club_id" => $row["club_id"],
            "club_name" => $row["club_name"],
            "description" => $row["description"],
            "status" => $row["status"],
            "faculty_name" => $row["faculty_name"],
            "designation" => $row["designation"],
            "activities" => []
        ];
    }

    if (!empty($row["activity_id"])) {
        $clubs[$club_id]["activities"][] = [
            "activity_id" => $row["activity_id"],
            "activity_name" => $row["activity_name"],
            "description" => $row["activity_description"],
            "activity_date" => $row["activity_date"],
            "venue" => $row["venue"]
        ];
    }
}

$stmt->close();

/* ---------------- COUNTS ---------------- */
$total_clubs = count($clubs);

$total_activities = 0;

foreach ($clubs as $club) {
    $total_activities += count($club["activities"]);
}

/* Upcoming activities */
$upcoming_activities = 0;

foreach ($clubs as $club) {

    foreach ($club["activities"] as $activity) {

        if (!empty($activity["activity_date"])) {

            $activity_date = strtotime($activity["activity_date"]);

            if ($activity_date >= strtotime(date("Y-m-d"))) {
                $upcoming_activities++;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clubs & Activities | SmartCampus</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="dashboard">

    <!-- ================= SIDEBAR ================= -->

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

            <a href="dashboard.php">
                🏠
                <span>Dashboard</span>
            </a>

            <a href="profile.php">
                👤
                <span>Profile</span>
            </a>

            <a href="attendance.php">
                📊
                <span>Attendance</span>
            </a>

            <a href="timetable.php">
                🗓️
                <span>Timetable</span>
            </a>

            <a href="materials.php">
                📚
                <span>Study Materials</span>
            </a>

            <a href="exams.php">
                📝
                <span>Exams</span>
            </a>

            <a href="results.php">
                📈
                <span>Results</span>
            </a>

            <a href="hall-ticket.php">
                🎫
                <span>Hall Ticket</span>
            </a>

            <a href="fees.php">
                💳
                <span>Fees</span>
            </a>

            <a href="projects.php">
                💻
                <span>Projects</span>
            </a>

            <a href="clubs.php" class="active">
                🎯
                <span>Clubs & Activities</span>
            </a>

            <a href="notifications.php">
                🔔
                <span>Notifications</span>
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">
                🚪
                <span>Logout</span>
            </a>

        </div>

    </aside>


    <!-- ================= MAIN CONTENT ================= -->

    <main class="dashboard-content">

        <!-- HEADER -->

        <div class="dashboard-header">

            <div class="welcome-text">

                <h1>Clubs & Activities</h1>

                <p>
                    Explore campus clubs and upcoming activities
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>
                        <?php echo htmlspecialchars($student["full_name"]); ?>
                    </strong>

                    <span>
                        <?php echo htmlspecialchars($student["roll_number"]); ?>
                    </span>

                </div>


                <button
                    type="button"
                    class="dashboard-theme-toggle"
                    id="themeToggle"
                    title="Toggle theme"
                >
                    🌙
                </button>

            </div>

        </div>


        <!-- ================= SUMMARY ================= -->

        <div class="clubs-summary">

            <div class="clubs-summary-card">

                <div class="clubs-summary-icon">
                    🎯
                </div>

                <div>

                    <span>Total Clubs</span>

                    <strong>
                        <?php echo $total_clubs; ?>
                    </strong>

                </div>

            </div>


            <div class="clubs-summary-card">

                <div class="clubs-summary-icon">
                    🎉
                </div>

                <div>

                    <span>Total Activities</span>

                    <strong>
                        <?php echo $total_activities; ?>
                    </strong>

                </div>

            </div>


            <div class="clubs-summary-card">

                <div class="clubs-summary-icon">
                    📅
                </div>

                <div>

                    <span>Upcoming Activities</span>

                    <strong>
                        <?php echo $upcoming_activities; ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- ================= CLUBS ================= -->

        <?php if ($total_clubs > 0): ?>

            <div class="clubs-grid">

                <?php foreach ($clubs as $club): ?>

                    <div class="club-card">

                        <!-- CLUB HEADER -->

                        <div class="club-card-header">

                            <div class="club-icon">
                                🎯
                            </div>

                            <div>

                                <h2>
                                    <?php echo htmlspecialchars($club["club_name"]); ?>
                                </h2>

                                <span class="club-status">
                                    <?php echo htmlspecialchars($club["status"]); ?>
                                </span>

                            </div>

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="club-card-body">

                            <p class="club-description">

                                <?php

                                echo !empty($club["description"])
                                    ? htmlspecialchars($club["description"])
                                    : "No description available for this club.";

                                ?>

                            </p>


                            <!-- FACULTY -->

                            <?php if (!empty($club["faculty_name"])): ?>

                                <div class="club-faculty">

                                    <span class="club-faculty-icon">
                                        👨‍🏫
                                    </span>

                                    <div>

                                        <strong>
                                            Faculty Coordinator
                                        </strong>

                                        <span>
                                            <?php
                                            echo htmlspecialchars($club["faculty_name"]);
                                            ?>

                                            <?php if (!empty($club["designation"])): ?>

                                                —
                                                <?php
                                                echo htmlspecialchars($club["designation"]);
                                                ?>

                                            <?php endif; ?>

                                        </span>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <!-- ACTIVITIES -->

                            <div class="club-activities-section">

                                <div class="club-section-title">

                                    <h3>
                                        Activities
                                    </h3>

                                    <span>
                                        <?php
                                        echo count($club["activities"]);
                                        ?>
                                    </span>

                                </div>


                                <?php if (!empty($club["activities"])): ?>

                                    <div class="club-activities-list">

                                        <?php foreach ($club["activities"] as $activity): ?>

                                            <?php

                                            $activity_timestamp = !empty($activity["activity_date"])
                                                ? strtotime($activity["activity_date"])
                                                : false;

                                            $is_upcoming =
                                                $activity_timestamp &&
                                                $activity_timestamp >= strtotime(date("Y-m-d"));

                                            ?>

                                            <div class="club-activity">

                                                <div class="activity-date">

                                                    <?php if ($activity_timestamp): ?>

                                                        <strong>
                                                            <?php
                                                            echo date(
                                                                "d",
                                                                $activity_timestamp
                                                            );
                                                            ?>
                                                        </strong>

                                                        <span>
                                                            <?php
                                                            echo date(
                                                                "M",
                                                                $activity_timestamp
                                                            );
                                                            ?>
                                                        </span>

                                                    <?php else: ?>

                                                        <strong>
                                                            —
                                                        </strong>

                                                        <span>
                                                            Date
                                                        </span>

                                                    <?php endif; ?>

                                                </div>


                                                <div class="activity-info">

                                                    <div class="activity-title-row">

                                                        <h4>
                                                            <?php
                                                            echo htmlspecialchars(
                                                                $activity["activity_name"]
                                                            );
                                                            ?>
                                                        </h4>

                                                        <?php if ($is_upcoming): ?>

                                                            <span class="activity-upcoming">
                                                                Upcoming
                                                            </span>

                                                        <?php endif; ?>

                                                    </div>


                                                    <?php if (!empty($activity["description"])): ?>

                                                        <p>
                                                            <?php
                                                            echo htmlspecialchars(
                                                                $activity["description"]
                                                            );
                                                            ?>
                                                        </p>

                                                    <?php endif; ?>


                                                    <div class="activity-meta">

                                                        <?php if (!empty($activity["venue"])): ?>

                                                            <span>
                                                                📍
                                                                <?php
                                                                echo htmlspecialchars(
                                                                    $activity["venue"]
                                                                );
                                                                ?>
                                                            </span>

                                                        <?php endif; ?>


                                                        <?php if ($activity_timestamp): ?>

                                                            <span>
                                                                📅
                                                                <?php
                                                                echo date(
                                                                    "d M Y",
                                                                    $activity_timestamp
                                                                );
                                                                ?>
                                                            </span>

                                                        <?php endif; ?>

                                                    </div>

                                                </div>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>

                                <?php else: ?>

                                    <div class="club-no-activities">

                                        <div>
                                            📭
                                        </div>

                                        <p>
                                            No activities available for this club.
                                        </p>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <!-- ================= EMPTY STATE ================= -->

            <div class="clubs-empty">

                <div class="clubs-empty-icon">
                    🎯
                </div>

                <h2>
                    No Clubs Available
                </h2>

                <p>
                    There are currently no active clubs or activities
                    available in SmartCampus.
                </p>

            </div>

        <?php endif; ?>


    </main>

</div>


<!-- ================= THEME SCRIPT ================= -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    const themeToggle = document.getElementById("themeToggle");

    const savedTheme = localStorage.getItem("theme");

    if (savedTheme === "dark") {

        document.body.classList.add("dark-theme");

        if (themeToggle) {
            themeToggle.textContent = "☀️";
        }

    }


    if (themeToggle) {

        themeToggle.addEventListener("click", function () {

            document.body.classList.toggle("dark-theme");

            const isDark =
                document.body.classList.contains("dark-theme");

            localStorage.setItem(
                "theme",
                isDark ? "dark" : "light"
            );

            themeToggle.textContent =
                isDark ? "☀️" : "🌙";

        });

    }

});

</script>

</body>

</html>