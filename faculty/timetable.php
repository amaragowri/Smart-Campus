<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "FACULTY") {
    header("Location: ../auth/faculty-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

/* =========================
   FACULTY DETAILS
========================= */
$stmt = $conn->prepare("
    SELECT faculty_id, full_name, employee_id, department, designation
    FROM faculty
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$faculty = $stmt->get_result()->fetch_assoc();

if (!$faculty) {
    die("Faculty profile not found.");
}

$faculty_id = $faculty["faculty_id"];
$department = $faculty["department"];


/* =========================
   TIMETABLE DATA
========================= */
$stmt = $conn->prepare("
    SELECT
        t.timetable_id,
        t.day,
        t.start_time,
        t.end_time,
        t.year,
        t.section,
        s.subject_code,
        s.subject_name
    FROM timetable t
    INNER JOIN subjects s
        ON t.subject_id = s.subject_id
    WHERE t.faculty_id = ?
      AND s.department = ?
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

$stmt->bind_param("is", $faculty_id, $department);
$stmt->execute();

$result = $stmt->get_result();


/* =========================
   GROUP BY DAY
========================= */
$days = [
    "Monday" => [],
    "Tuesday" => [],
    "Wednesday" => [],
    "Thursday" => [],
    "Friday" => [],
    "Saturday" => []
];

$total_classes = 0;

while ($row = $result->fetch_assoc()) {

    $day = ucfirst(strtolower(trim($row["day"])));

    if (isset($days[$day])) {
        $days[$day][] = $row;
        $total_classes++;
    }
}


/* =========================
   DAY ICONS
========================= */
$day_icons = [
    "Monday" => "🌤️",
    "Tuesday" => "📘",
    "Wednesday" => "📚",
    "Thursday" => "💡",
    "Friday" => "🎯",
    "Saturday" => "🌟"
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Faculty Timetable | SmartCampus</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body>

<div class="dashboard">


    <!-- =========================
         SIDEBAR
    ========================== -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
                SC
            </div>

            <div>

                <h2>
                    SmartCampus
                </h2>

                <span>
                    Faculty Portal
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php">

                <span>🏠</span>
                Dashboard

            </a>


            <a href="attendance.php">

                <span>📊</span>
                Attendance

            </a>


            <a
                href="timetable.php"
                class="active"
            >

                <span>📅</span>
                Timetable

            </a>


            <a href="materials.php">

                <span>📚</span>
                Materials

            </a>


            <a href="exams.php">

                <span>📝</span>
                Exams

            </a>


            <a href="projects.php">

                <span>💻</span>
                Projects

            </a>


            <a href="../auth/logout.php">

                <span>🚪</span>
                Logout

            </a>

        </nav>

    </aside>



    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="dashboard-content">


        <!-- HEADER -->

        <header class="dashboard-header">

            <div class="welcome-text">

                <h1>
                    Timetable
                </h1>

                <p>
                    View your weekly teaching schedule.
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>
                        <?= htmlspecialchars($faculty["full_name"]) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($faculty["employee_id"]) ?>
                    </span>

                </div>


                <button
                    class="dashboard-theme-toggle"
                    id="themeToggle"
                    type="button"
                >
                    🌙
                </button>

            </div>

        </header>



        <!-- =========================
             SUMMARY
        ========================== -->

        <section class="faculty-timetable-summary">

            <div class="faculty-timetable-summary-card">

                <div class="faculty-timetable-summary-icon">
                    📅
                </div>

                <div>

                    <span>
                        Total Classes
                    </span>

                    <strong>
                        <?= $total_classes ?>
                    </strong>

                </div>

            </div>


            <div class="faculty-timetable-summary-card">

                <div class="faculty-timetable-summary-icon">
                    📚
                </div>

                <div>

                    <span>
                        Working Days
                    </span>

                    <strong>
                        <?= count(array_filter($days)) ?>
                    </strong>

                </div>

            </div>


            <div class="faculty-timetable-summary-card">

                <div class="faculty-timetable-summary-icon">
                    🏫
                </div>

                <div>

                    <span>
                        Department
                    </span>

                    <strong>
                        <?= htmlspecialchars($department) ?>
                    </strong>

                </div>

            </div>

        </section>



        <!-- =========================
             WEEKLY TIMETABLE
        ========================== -->

        <section class="faculty-timetable-section">

            <div class="faculty-timetable-section-header">

                <div>

                    <h2>
                        Weekly Schedule
                    </h2>

                    <p>
                        Your assigned classes for the week.
                    </p>

                </div>

            </div>


            <div class="faculty-timetable-grid">


                <?php foreach ($days as $day => $classes): ?>

                    <div class="faculty-day-card">


                        <!-- DAY HEADER -->

                        <div class="faculty-day-header">

                            <div class="faculty-day-icon">
                                <?= $day_icons[$day] ?>
                            </div>

                            <div>

                                <h3>
                                    <?= $day ?>
                                </h3>

                                <span>
                                    <?= count($classes) ?>
                                    <?= count($classes) === 1 ? "class" : "classes" ?>
                                </span>

                            </div>

                        </div>



                        <!-- CLASSES -->

                        <div class="faculty-day-classes">


                            <?php if (!empty($classes)): ?>


                                <?php foreach ($classes as $class): ?>


                                    <div class="faculty-class-card">


                                        <div class="faculty-class-time">

                                            <span>
                                                🕐
                                            </span>

                                            <strong>

                                                <?= date(
                                                    "h:i A",
                                                    strtotime($class["start_time"])
                                                ) ?>

                                                -

                                                <?= date(
                                                    "h:i A",
                                                    strtotime($class["end_time"])
                                                ) ?>

                                            </strong>

                                        </div>



                                        <div class="faculty-class-subject">

                                            <strong>
                                                <?= htmlspecialchars($class["subject_code"]) ?>
                                            </strong>

                                            <span>
                                                <?= htmlspecialchars($class["subject_name"]) ?>
                                            </span>

                                        </div>



                                        <div class="faculty-class-details">

                                            <span>
                                                🎓 Year <?= htmlspecialchars($class["year"]) ?>
                                            </span>

                                            <span>
                                                👥 Section <?= htmlspecialchars($class["section"]) ?>
                                            </span>

                                        </div>


                                    </div>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <div class="faculty-no-class">

                                    <div>
                                        ☕
                                    </div>

                                    <span>
                                        No classes scheduled
                                    </span>

                                </div>


                            <?php endif; ?>


                        </div>

                    </div>

                <?php endforeach; ?>


            </div>

        </section>


    </main>

</div>



<!-- =========================
     THEME SCRIPT
========================== -->

<script>

const themeToggle =
    document.getElementById("themeToggle");


if (themeToggle) {

    themeToggle.addEventListener("click", function () {

        document.body.classList.toggle("dark-theme");

        if (
            document.body.classList.contains("dark-theme")
        ) {

            themeToggle.textContent = "☀️";

            localStorage.setItem(
                "theme",
                "dark"
            );

        } else {

            themeToggle.textContent = "🌙";

            localStorage.setItem(
                "theme",
                "light"
            );

        }

    });


    if (
        localStorage.getItem("theme") === "dark"
    ) {

        document.body.classList.add("dark-theme");

        themeToggle.textContent = "☀️";

    }

}

</script>


</body>
</html>