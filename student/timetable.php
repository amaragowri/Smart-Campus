<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "STUDENT") {
    header("Location: ../auth/student-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

/* ================= STUDENT DETAILS ================= */

$stmt = $conn->prepare("
    SELECT
        student_id,
        full_name,
        roll_number,
        department,
        year,
        section
    FROM students
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student profile not found.");
}

$department = $student["department"];
$year = $student["year"];
$section = $student["section"];


/* ================= GET TIMETABLE ================= */

$stmt = $conn->prepare("
    SELECT
        t.day,
        t.start_time,
        t.end_time,
        s.subject_code,
        s.subject_name,
        f.full_name AS faculty_name
    FROM timetable t
    INNER JOIN subjects s
        ON t.subject_id = s.subject_id
    INNER JOIN faculty f
        ON t.faculty_id = f.faculty_id
    WHERE t.department = ?
      AND t.year = ?
      AND t.section = ?
    ORDER BY
        FIELD(
            t.day,
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        ),
        t.start_time
");

$stmt->bind_param("sis", $department, $year, $section);
$stmt->execute();

$timetable_result = $stmt->get_result();


/* ================= ORGANIZE BY DAY ================= */

$weekly_timetable = [
    "Monday" => [],
    "Tuesday" => [],
    "Wednesday" => [],
    "Thursday" => [],
    "Friday" => [],
    "Saturday" => []
];

while ($row = $timetable_result->fetch_assoc()) {

    if (isset($weekly_timetable[$row["day"]])) {
        $weekly_timetable[$row["day"]][] = $row;
    }
}


/* Check whether timetable exists */

$has_timetable = false;

foreach ($weekly_timetable as $classes) {

    if (!empty($classes)) {
        $has_timetable = true;
        break;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Timetable | SmartCampus</title>

    <!-- COMMON CSS -->
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


        <div class="sidebar-menu">

            <a href="dashboard.php">
                <span>🏠</span>
                Dashboard
            </a>

            <a href="profile.php">
                <span>👤</span>
                My Profile
            </a>

            <a href="attendance.php">
                <span>📊</span>
                Attendance
            </a>

            <a href="timetable.php" class="active">
                <span>🕘</span>
                Timetable
            </a>

            <a href="materials.php">
                <span>📚</span>
                Study Materials
            </a>

            <a href="#">
                <span>📝</span>
                Exams
            </a>

            <a href="#">
                <span>📈</span>
                Results
            </a>

            <a href="#">
                <span>🎫</span>
                Hall Ticket
            </a>

            <a href="#">
                <span>💰</span>
                Fees & Payments
            </a>

            <a href="#">
                <span>💻</span>
                Projects
            </a>

            <a href="#">
                <span>🎯</span>
                Clubs & Activities
            </a>

            <a href="#">
                <span>🔔</span>
                Notifications
            </a>

        </div>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">
                <span>🚪</span>
                Logout
            </a>

        </div>

    </aside>


    <!-- ================= MAIN CONTENT ================= -->

    <main class="dashboard-content">

        <!-- HEADER -->

        <div class="dashboard-header">

            <div class="welcome-text">

                <p class="student-meta">
                    Academic Schedule
                </p>

                <h1>
                    Timetable
                </h1>

                <p class="student-meta">
                    View your weekly class schedule
                </p>

            </div>


            <button
                id="themeToggle"
                class="dashboard-theme-toggle"
                type="button"
            >
                🌙
            </button>

        </div>


        <!-- ================= TIMETABLE PAGE ================= -->

        <div class="timetable-page">


            <!-- PAGE INTRO -->

            <div class="timetable-intro">

                <div>

                    <span class="timetable-label">
                        ACADEMIC SCHEDULE
                    </span>

                    <h2>
                        📅 Weekly Timetable
                    </h2>

                    <p>
                        Your class schedule for the current academic year.
                    </p>

                </div>

            </div>


            <!-- ================= STUDENT INFO ================= -->

            <div class="dashboard-panel timetable-student-card">

                <div class="timetable-info-grid">


                    <div class="timetable-info-item">

                        <span>
                            STUDENT
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["full_name"]
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="timetable-info-item">

                        <span>
                            ROLL NUMBER
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["roll_number"]
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="timetable-info-item">

                        <span>
                            DEPARTMENT
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $department
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="timetable-info-item">

                        <span>
                            YEAR / SECTION
                        </span>

                        <strong>
                            Year <?php echo htmlspecialchars($year); ?>
                            /
                            <?php echo htmlspecialchars($section); ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- ================= CLASS SCHEDULE ================= -->

            <div class="dashboard-panel timetable-panel">

                <div class="timetable-panel-header">

                    <div>

                        <h2>
                            📚 Class Schedule
                        </h2>

                        <p>
                            Classes scheduled for your department,
                            year and section.
                        </p>

                    </div>

                    <?php if ($has_timetable): ?>

                        <span class="timetable-count">

                            <?php
                            $total_classes = 0;

                            foreach ($weekly_timetable as $classes) {
                                $total_classes += count($classes);
                            }

                            echo $total_classes;
                            ?>

                            Classes

                        </span>

                    <?php endif; ?>

                </div>


                <?php if (!$has_timetable): ?>

                    <!-- EMPTY STATE -->

                    <div class="timetable-empty">

                        <div class="timetable-empty-icon">
                            📅
                        </div>

                        <h3>
                            No Timetable Available
                        </h3>

                        <p>
                            Your timetable has not been added yet.
                        </p>

                    </div>


                <?php else: ?>


                    <!-- TABLE -->

                    <div class="timetable-table-wrapper">

                        <table class="timetable-table">

                            <thead>

                                <tr>

                                    <th>DAY</th>

                                    <th>TIME</th>

                                    <th>SUBJECT CODE</th>

                                    <th>SUBJECT</th>

                                    <th>FACULTY</th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php foreach (
                                $weekly_timetable
                                as $day => $classes
                            ): ?>

                                <?php foreach (
                                    $classes
                                    as $class
                                ): ?>

                                    <tr>

                                        <td>

                                            <span class="timetable-day">

                                                <?php
                                                echo htmlspecialchars(
                                                    $day
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span class="timetable-time">

                                                <?php
                                                echo date(
                                                    "h:i A",
                                                    strtotime(
                                                        $class["start_time"]
                                                    )
                                                );
                                                ?>

                                                -

                                                <?php
                                                echo date(
                                                    "h:i A",
                                                    strtotime(
                                                        $class["end_time"]
                                                    )
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span class="timetable-subject-code">

                                                <?php
                                                echo htmlspecialchars(
                                                    $class["subject_code"]
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span class="timetable-subject-name">

                                                <?php
                                                echo htmlspecialchars(
                                                    $class["subject_name"]
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span class="timetable-faculty">

                                                👨‍🏫

                                                <?php
                                                echo htmlspecialchars(
                                                    $class["faculty_name"]
                                                );
                                                ?>

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                <?php endif; ?>

            </div>

        </div>

    </main>

</div>


<!-- ================= THEME SCRIPT ================= -->

<script>

const themeToggle = document.getElementById("themeToggle");


function updateThemeIcon() {

    if (document.body.classList.contains("dark-theme")) {

        themeToggle.textContent = "☀️";

    } else {

        themeToggle.textContent = "🌙";

    }

}


/* Load saved theme */

if (
    localStorage.getItem("smartcampus-theme") === "dark"
) {

    document.body.classList.add("dark-theme");

}


updateThemeIcon();


/* Toggle theme */

themeToggle.addEventListener("click", function () {

    document.body.classList.toggle("dark-theme");

    if (
        document.body.classList.contains("dark-theme")
    ) {

        localStorage.setItem(
            "smartcampus-theme",
            "dark"
        );

    } else {

        localStorage.setItem(
            "smartcampus-theme",
            "light"
        );

    }

    updateThemeIcon();

});

</script>

</body>

</html>