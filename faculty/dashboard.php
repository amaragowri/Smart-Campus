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

/* =========================================================
   FACULTY DETAILS
   ========================================================= */

$faculty_sql = "
    SELECT
        faculty_id,
        employee_id,
        full_name,
        email,
        phone,
        department,
        designation,
        specialization
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($faculty_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$faculty_result = $stmt->get_result();
$faculty = $faculty_result->fetch_assoc();

$stmt->close();

if (!$faculty) {
    die("Faculty profile not found.");
}

$faculty_id = $faculty["faculty_id"];
$department = $faculty["department"];


/* =========================================================
   TOTAL STUDENTS
   ========================================================= */

$student_sql = "
    SELECT COUNT(*) AS total
    FROM students
    WHERE department = ?
";

$stmt = $conn->prepare($student_sql);
$stmt->bind_param("s", $department);
$stmt->execute();

$student_count = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   TOTAL SUBJECTS
   ========================================================= */

$subject_sql = "
    SELECT COUNT(*) AS total
    FROM subjects
    WHERE department = ?
";

$stmt = $conn->prepare($subject_sql);
$stmt->bind_param("s", $department);
$stmt->execute();

$subject_count = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   TIMETABLE CLASSES
   ========================================================= */

$timetable_sql = "
    SELECT COUNT(*) AS total
    FROM timetable
    WHERE faculty_id = ?
";

$stmt = $conn->prepare($timetable_sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$timetable_count = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   UPLOADED MATERIALS
   ========================================================= */

$material_sql = "
    SELECT COUNT(*) AS total
    FROM materials
    WHERE faculty_id = ?
";

$stmt = $conn->prepare($material_sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$material_count = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   PROJECTS GUIDED
   ========================================================= */

$project_sql = "
    SELECT COUNT(*) AS total
    FROM projects
    WHERE faculty_id = ?
";

$stmt = $conn->prepare($project_sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$project_count = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   UPCOMING EXAMS
   ========================================================= */

$exam_sql = "
    SELECT COUNT(*) AS total
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    WHERE s.department = ?
      AND e.exam_date >= CURDATE()
";

$stmt = $conn->prepare($exam_sql);
$stmt->bind_param("s", $department);
$stmt->execute();

$exam_count = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   RECENT EXAMS
   ========================================================= */

$recent_exam_sql = "
    SELECT
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.room_number,
        s.subject_code,
        s.subject_name
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    WHERE s.department = ?
      AND e.exam_date >= CURDATE()
    ORDER BY e.exam_date ASC, e.start_time ASC
    LIMIT 5
";

$stmt = $conn->prepare($recent_exam_sql);
$stmt->bind_param("s", $department);
$stmt->execute();

$recent_exam_result = $stmt->get_result();

$recent_exams = [];

while ($row = $recent_exam_result->fetch_assoc()) {
    $recent_exams[] = $row;
}

$stmt->close();


/* =========================================================
   RECENT PROJECTS
   ========================================================= */

$recent_project_sql = "
    SELECT
        p.project_title,
        p.project_type,
        p.status,
        s.full_name AS student_name,
        s.roll_number
    FROM projects p
    INNER JOIN students s
        ON p.student_id = s.student_id
    WHERE p.faculty_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
";

$stmt = $conn->prepare($recent_project_sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$recent_project_result = $stmt->get_result();

$recent_projects = [];

while ($row = $recent_project_result->fetch_assoc()) {
    $recent_projects[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Dashboard | SmartCampus</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<div class="dashboard">

    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
                SC
            </div>

            <div>
                <h2>SmartCampus</h2>
                <span>Faculty Portal</span>
            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php" class="active">
                🏠
                <span>Dashboard</span>
            </a>

            <a href="profile.php">
                👤
                <span>Profile</span>
            </a>

            <a href="timetable.php">
                🗓️
                <span>Timetable</span>
            </a>

            <a href="attendance.php">
                📋
                <span>Attendance</span>
            </a>

            <a href="materials.php">
                📚
                <span>Study Materials</span>
            </a>

            <a href="exams.php">
                📝
                <span>Exams</span>
            </a>

            <a href="marks.php">
                📊
                <span>Marks</span>
            </a>

            <a href="projects.php">
                💻
                <span>Projects</span>
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


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main class="dashboard-content">

        <!-- HEADER -->

        <div class="dashboard-header">

            <div class="welcome-text">

                <h1>
                    Welcome, <?php echo htmlspecialchars($faculty["full_name"]); ?> 👋
                </h1>

                <p>
                    Here's an overview of your faculty activities.
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>
                        <?php echo htmlspecialchars($faculty["employee_id"]); ?>
                    </strong>

                    <span>
                        <?php echo htmlspecialchars($faculty["department"]); ?>
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


        <!-- =================================================
             SUMMARY CARDS
             ================================================= -->

        <div class="faculty-stats-grid">

            <div class="faculty-stat-card">

                <div class="faculty-stat-icon">
                    👨‍🎓
                </div>

                <div>

                    <span>Total Students</span>

                    <strong>
                        <?php echo $student_count; ?>
                    </strong>

                </div>

            </div>


            <div class="faculty-stat-card">

                <div class="faculty-stat-icon">
                    📚
                </div>

                <div>

                    <span>Department Subjects</span>

                    <strong>
                        <?php echo $subject_count; ?>
                    </strong>

                </div>

            </div>


            <div class="faculty-stat-card">

                <div class="faculty-stat-icon">
                    🗓️
                </div>

                <div>

                    <span>My Classes</span>

                    <strong>
                        <?php echo $timetable_count; ?>
                    </strong>

                </div>

            </div>


            <div class="faculty-stat-card">

                <div class="faculty-stat-icon">
                    📚
                </div>

                <div>

                    <span>Materials Uploaded</span>

                    <strong>
                        <?php echo $material_count; ?>
                    </strong>

                </div>

            </div>


            <div class="faculty-stat-card">

                <div class="faculty-stat-icon">
                    📝
                </div>

                <div>

                    <span>Upcoming Exams</span>

                    <strong>
                        <?php echo $exam_count; ?>
                    </strong>

                </div>

            </div>


            <div class="faculty-stat-card">

                <div class="faculty-stat-icon">
                    💻
                </div>

                <div>

                    <span>Projects Guided</span>

                    <strong>
                        <?php echo $project_count; ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- =================================================
             CONTENT GRID
             ================================================= -->

        <div class="faculty-dashboard-grid">


            <!-- UPCOMING EXAMS -->

            <section class="faculty-panel">

                <div class="faculty-panel-header">

                    <div>

                        <h2>Upcoming Exams</h2>

                        <p>
                            Exams scheduled for your department
                        </p>

                    </div>

                    <a href="exams.php">
                        View All
                    </a>

                </div>


                <?php if (!empty($recent_exams)): ?>

                    <div class="faculty-exam-list">

                        <?php foreach ($recent_exams as $exam): ?>

                            <div class="faculty-exam-item">

                                <div class="faculty-exam-date">

                                    <strong>
                                        <?php
                                        echo date(
                                            "d",
                                            strtotime($exam["exam_date"])
                                        );
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo date(
                                            "M",
                                            strtotime($exam["exam_date"])
                                        );
                                        ?>
                                    </span>

                                </div>


                                <div class="faculty-exam-info">

                                    <h3>
                                        <?php
                                        echo htmlspecialchars(
                                            $exam["subject_code"]
                                        );
                                        ?>
                                    </h3>

                                    <p>
                                        <?php
                                        echo htmlspecialchars(
                                            $exam["exam_name"]
                                        );
                                        ?>
                                    </p>

                                    <div>

                                        <span>
                                            🕐
                                            <?php
                                            echo date(
                                                "h:i A",
                                                strtotime($exam["start_time"])
                                            );
                                            ?>
                                        </span>

                                        <span>
                                            📍
                                            <?php
                                            echo htmlspecialchars(
                                                $exam["room_number"] ?: "TBA"
                                            );
                                            ?>
                                        </span>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="faculty-empty-small">
                        📝
                        <p>No upcoming exams available.</p>
                    </div>

                <?php endif; ?>

            </section>


            <!-- RECENT PROJECTS -->

            <section class="faculty-panel">

                <div class="faculty-panel-header">

                    <div>

                        <h2>Recent Projects</h2>

                        <p>
                            Projects under your guidance
                        </p>

                    </div>

                    <a href="projects.php">
                        View All
                    </a>

                </div>


                <?php if (!empty($recent_projects)): ?>

                    <div class="faculty-project-list">

                        <?php foreach ($recent_projects as $project): ?>

                            <div class="faculty-project-item">

                                <div class="faculty-project-icon">
                                    💻
                                </div>


                                <div class="faculty-project-info">

                                    <h3>
                                        <?php
                                        echo htmlspecialchars(
                                            $project["project_title"]
                                        );
                                        ?>
                                    </h3>

                                    <p>
                                        <?php
                                        echo htmlspecialchars(
                                            $project["student_name"]
                                        );
                                        ?>

                                        —

                                        <?php
                                        echo htmlspecialchars(
                                            $project["roll_number"]
                                        );
                                        ?>
                                    </p>

                                </div>


                                <span class="faculty-project-status">

                                    <?php
                                    echo htmlspecialchars(
                                        $project["status"]
                                    );
                                    ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="faculty-empty-small">
                        💻
                        <p>No projects assigned yet.</p>
                    </div>

                <?php endif; ?>

            </section>


        </div>


        <!-- =================================================
             QUICK ACTIONS
             ================================================= -->

        <section class="faculty-quick-section">

            <div class="faculty-panel-header">

                <div>

                    <h2>Quick Actions</h2>

                    <p>
                        Frequently used faculty tools
                    </p>

                </div>

            </div>


            <div class="faculty-quick-grid">

                <a href="attendance.php" class="faculty-quick-card">

                    <span>📋</span>

                    <div>
                        <strong>Manage Attendance</strong>
                        <small>Record student attendance</small>
                    </div>

                </a>


                <a href="materials.php" class="faculty-quick-card">

                    <span>📚</span>

                    <div>
                        <strong>Upload Materials</strong>
                        <small>Share study resources</small>
                    </div>

                </a>


                <a href="marks.php" class="faculty-quick-card">

                    <span>📊</span>

                    <div>
                        <strong>Manage Marks</strong>
                        <small>Enter student marks</small>
                    </div>

                </a>


                <a href="projects.php" class="faculty-quick-card">

                    <span>💻</span>

                    <div>
                        <strong>Student Projects</strong>
                        <small>Review guided projects</small>
                    </div>

                </a>

            </div>

        </section>

    </main>

</div>


<!-- =========================================================
     THEME SCRIPT
     ========================================================= -->

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