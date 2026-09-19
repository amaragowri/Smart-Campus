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

/* Student details */
$stmt = $conn->prepare("
    SELECT student_id, full_name, roll_number, department, year, section
    FROM students
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student profile not found.");
}

$student_id = $student["student_id"];
$department = $student["department"];
$year = $student["year"];

/* Results */
$stmt = $conn->prepare("
    SELECT
        m.mark_id,
        m.marks_obtained,
        m.max_marks,
        m.grade,
        s.subject_code,
        s.subject_name,
        e.exam_name,
        e.exam_type,
        e.exam_date
    FROM marks m
    INNER JOIN subjects s ON m.subject_id = s.subject_id
    LEFT JOIN exam_details e ON m.exam_id = e.exam_id
    WHERE m.student_id = ?
    ORDER BY e.exam_date DESC, s.subject_code ASC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();
$results = $stmt->get_result();

$total_subjects = 0;
$total_marks = 0;
$total_max_marks = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Results | SmartCampus</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="sidebar-logo">SC</div>

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
                <span>My Profile</span>
            </a>

            <a href="attendance.php">
                📊
                <span>Attendance</span>
            </a>

            <a href="timetable.php">
                📅
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

            <a href="results.php" class="active">
                🎯
                <span>Results</span>
            </a>

            <a href="hall-ticket.php">
                🎫
                <span>Hall Ticket</span>
            </a>

            <a href="fees.php">
                💳
                <span>Fees & Payments</span>
            </a>

            <a href="projects.php">
                💻
                <span>Projects</span>
            </a>

            <a href="clubs.php">
                🏆
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


    <!-- MAIN CONTENT -->
    <main class="dashboard-content">

        <!-- HEADER -->
        <div class="dashboard-header">

            <div class="welcome-text">
                <h1>Academic Results</h1>

                <div class="student-meta">
                    <?php echo htmlspecialchars($student["full_name"]); ?>
                    •
                    <?php echo htmlspecialchars($student["roll_number"]); ?>
                    •
                    <?php echo htmlspecialchars($department); ?>
                </div>
            </div>

            <button class="dashboard-theme-toggle" id="themeToggle">
                🌙
            </button>

        </div>


        <!-- SUMMARY -->
        <div class="results-summary">

            <div class="results-summary-card">
                <div class="results-summary-icon">📚</div>

                <div>
                    <span>Total Subjects</span>
                    <strong>
                        <?php echo $results->num_rows; ?>
                    </strong>
                </div>
            </div>


            <div class="results-summary-card">
                <div class="results-summary-icon">🎯</div>

                <div>
                    <span>Total Marks</span>
                    <strong>
                        <?php
                        $results->data_seek(0);

                        while ($row = $results->fetch_assoc()) {
                            $total_marks += (float)$row["marks_obtained"];
                            $total_max_marks += (float)$row["max_marks"];
                        }

                        echo number_format($total_marks, 0);
                        ?>
                    </strong>
                </div>
            </div>


            <div class="results-summary-card">
                <div class="results-summary-icon">📈</div>

                <div>
                    <span>Overall Percentage</span>

                    <strong>
                        <?php
                        $percentage = $total_max_marks > 0
                            ? ($total_marks / $total_max_marks) * 100
                            : 0;

                        echo number_format($percentage, 2) . "%";
                        ?>
                    </strong>
                </div>
            </div>

        </div>


        <!-- RESULTS TABLE -->
        <section class="results-card">

            <div class="results-card-header">

                <div>
                    <h2>Result Details</h2>
                    <p>Your examination performance</p>
                </div>

            </div>


            <?php if ($results->num_rows > 0): ?>

                <?php $results->data_seek(0); ?>

                <div class="results-table-wrapper">

                    <table class="results-table">

                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Exam</th>
                                <th>Date</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php while ($row = $results->fetch_assoc()): ?>

                            <?php
                            $marks = (float)$row["marks_obtained"];
                            $max_marks = (float)$row["max_marks"];

                            $subject_percentage = $max_marks > 0
                                ? ($marks / $max_marks) * 100
                                : 0;

                            $grade = $row["grade"] ?: "N/A";

                            $grade_class = strtolower(str_replace(" ", "-", $grade));
                            ?>

                            <tr>

                                <td>
                                    <div class="result-subject">
                                        <strong>
                                            <?php echo htmlspecialchars($row["subject_code"]); ?>
                                        </strong>

                                        <span>
                                            <?php echo htmlspecialchars($row["subject_name"]); ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["exam_name"] ?? "—"); ?>

                                    <?php if (!empty($row["exam_type"])): ?>
                                        <small>
                                            <?php echo htmlspecialchars($row["exam_type"]); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php
                                    if (!empty($row["exam_date"])) {
                                        echo date("d M Y", strtotime($row["exam_date"]));
                                    } else {
                                        echo "—";
                                    }
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo number_format($marks, 0); ?>
                                    </strong>
                                    /
                                    <?php echo number_format($max_marks, 0); ?>
                                </td>

                                <td>

                                    <div class="result-percentage">

                                        <div class="result-progress">
                                            <span style="width: <?php echo min($subject_percentage, 100); ?>%;"></span>
                                        </div>

                                        <strong>
                                            <?php echo number_format($subject_percentage, 1); ?>%
                                        </strong>

                                    </div>

                                </td>

                                <td>
                                    <span class="result-grade <?php echo htmlspecialchars($grade_class); ?>">
                                        <?php echo htmlspecialchars($grade); ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="results-empty">

                    <div class="results-empty-icon">📊</div>

                    <h3>No Results Available</h3>

                    <p>
                        Your examination results have not been published yet.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>
const themeToggle = document.getElementById("themeToggle");

if (localStorage.getItem("smartcampus-theme") === "dark") {
    document.body.classList.add("dark-theme");
    themeToggle.textContent = "☀️";
}

themeToggle.addEventListener("click", function () {

    document.body.classList.toggle("dark-theme");

    if (document.body.classList.contains("dark-theme")) {
        localStorage.setItem("smartcampus-theme", "dark");
        themeToggle.textContent = "☀️";
    } else {
        localStorage.setItem("smartcampus-theme", "light");
        themeToggle.textContent = "🌙";
    }

});
</script>

</body>
</html>