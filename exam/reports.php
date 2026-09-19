<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "EXAM_CELL") {
    header("Location: ../auth/exam-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

/* Exam Cell user */
$userQuery = $conn->prepare("
    SELECT username, role
    FROM users
    WHERE user_id = ?
");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

/* Summary statistics */
$totalExams = 0;
$totalStudents = 0;
$totalResults = 0;
$totalHallTickets = 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM exam_details");
if ($result) {
    $totalExams = $result->fetch_assoc()["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM students");
if ($result) {
    $totalStudents = $result->fetch_assoc()["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM marks");
if ($result) {
    $totalResults = $result->fetch_assoc()["total"];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM hall_tickets");
if ($result) {
    $totalHallTickets = $result->fetch_assoc()["total"];
}

/* Exam-wise report */
$examReport = $conn->query("
    SELECT
        e.exam_id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.max_marks,
        e.status,
        s.subject_code,
        s.subject_name,
        COUNT(DISTINCT m.mark_id) AS result_count,
        COUNT(DISTINCT h.hall_ticket_id) AS hall_ticket_count
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    LEFT JOIN marks m
        ON e.exam_id = m.exam_id
    LEFT JOIN hall_tickets h
        ON e.exam_id = h.exam_id
    GROUP BY
        e.exam_id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.max_marks,
        e.status,
        s.subject_code,
        s.subject_name
    ORDER BY e.exam_date DESC
");

/* Result performance report */
$performanceReport = $conn->query("
    SELECT
        s.subject_code,
        s.subject_name,
        COUNT(m.mark_id) AS total_students,
        COALESCE(AVG(m.marks_obtained), 0) AS average_marks,
        COALESCE(MAX(m.marks_obtained), 0) AS highest_marks,
        COALESCE(MIN(m.marks_obtained), 0) AS lowest_marks,
        SUM(
            CASE
                WHEN m.marks_obtained >= (m.max_marks * 0.40)
                THEN 1
                ELSE 0
            END
        ) AS passed_students
    FROM marks m
    INNER JOIN subjects s
        ON m.subject_id = s.subject_id
    GROUP BY
        s.subject_id,
        s.subject_code,
        s.subject_name
    ORDER BY s.subject_code
");

/* Hall ticket report */
$hallTicketReport = $conn->query("
    SELECT
        e.exam_name,
        e.exam_date,
        s.subject_code,
        s.subject_name,
        COUNT(h.hall_ticket_id) AS total_tickets,
        SUM(
            CASE
                WHEN UPPER(h.status) = 'ACTIVE'
                THEN 1
                ELSE 0
            END
        ) AS active_tickets
    FROM hall_tickets h
    INNER JOIN exam_details e
        ON h.exam_id = e.exam_id
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    GROUP BY
        e.exam_id,
        e.exam_name,
        e.exam_date,
        s.subject_code,
        s.subject_name
    ORDER BY e.exam_date DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Exam Reports | SmartCampus</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="dashboard-layout exam-report-page">

    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="brand-logo">SC</div>

            <div>
                <h2>SmartCampus</h2>
                <span>Exam Cell</span>
            </div>
        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <span>🏠</span>
                Dashboard
            </a>

            <a href="exams.php">
                <span>📝</span>
                Exams
            </a>

            <a href="marks.php">
                <span>📊</span>
                Marks
            </a>

            <a href="hall-tickets.php">
                <span>🎫</span>
                Hall Tickets
            </a>

            <a href="notifications.php">
                <span>🔔</span>
                Notifications
            </a>

            <a href="reports.php" class="active">
                <span>📈</span>
                Reports
            </a>

            <a href="../auth/logout.php">
                <span>🚪</span>
                Logout
            </a>

        </nav>

    </aside>


    <!-- Main Content -->
    <main class="main-content">

        <!-- Topbar -->
        <header class="topbar">

            <div>
                <h1>Exam Reports</h1>
                <p>Analyze examinations, results and hall tickets</p>
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
                        <?= htmlspecialchars($user["username"] ?? "Exam Cell") ?>
                    </strong>

                    <span>Exam Cell</span>
                </div>

            </div>

        </header>


        <!-- Summary Cards -->
        <section class="exam-report-stats">

            <div class="exam-report-stat-card">
                <div class="exam-report-stat-icon">📝</div>

                <div>
                    <span>Total Exams</span>
                    <h2><?= $totalExams ?></h2>
                </div>
            </div>


            <div class="exam-report-stat-card">
                <div class="exam-report-stat-icon">👨‍🎓</div>

                <div>
                    <span>Total Students</span>
                    <h2><?= $totalStudents ?></h2>
                </div>
            </div>


            <div class="exam-report-stat-card">
                <div class="exam-report-stat-icon">📊</div>

                <div>
                    <span>Total Results</span>
                    <h2><?= $totalResults ?></h2>
                </div>
            </div>


            <div class="exam-report-stat-card">
                <div class="exam-report-stat-icon">🎫</div>

                <div>
                    <span>Hall Tickets</span>
                    <h2><?= $totalHallTickets ?></h2>
                </div>
            </div>

        </section>


        <!-- Exam-wise Report -->
        <section class="exam-report-section">

            <div class="exam-report-section-header">
                <div>
                    <h2>Exam-wise Report</h2>
                    <p>Overview of examinations, results and hall tickets</p>
                </div>
            </div>


            <div class="exam-report-table-wrapper">

                <table class="exam-report-table">

                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Max Marks</th>
                            <th>Results</th>
                            <th>Hall Tickets</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($examReport && $examReport->num_rows > 0): ?>

                        <?php while ($row = $examReport->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row["exam_name"]) ?>
                                    </strong>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row["subject_code"]) ?>
                                    </strong>

                                    <small>
                                        <?= htmlspecialchars($row["subject_name"]) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row["exam_type"]) ?>
                                </td>

                                <td>
                                    <?= !empty($row["exam_date"])
                                        ? date("d M Y", strtotime($row["exam_date"]))
                                        : "-"
                                    ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row["max_marks"]) ?>
                                </td>

                                <td>
                                    <span class="exam-report-number">
                                        <?= $row["result_count"] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="exam-report-number">
                                        <?= $row["hall_ticket_count"] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="exam-report-status">
                                        <?= htmlspecialchars($row["status"]) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="exam-report-empty">
                                No examination records available.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- Performance Report -->
        <section class="exam-report-section">

            <div class="exam-report-section-header">

                <div>
                    <h2>Subject Performance</h2>
                    <p>Student marks and subject-wise performance</p>
                </div>

            </div>


            <div class="exam-report-table-wrapper">

                <table class="exam-report-table">

                    <thead>

                        <tr>
                            <th>Subject</th>
                            <th>Students</th>
                            <th>Average</th>
                            <th>Highest</th>
                            <th>Lowest</th>
                            <th>Passed</th>
                            <th>Pass %</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($performanceReport && $performanceReport->num_rows > 0): ?>

                        <?php while ($row = $performanceReport->fetch_assoc()): ?>

                            <?php
                            $students = (int)$row["total_students"];
                            $passed = (int)$row["passed_students"];

                            $passPercentage = $students > 0
                                ? ($passed / $students) * 100
                                : 0;
                            ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($row["subject_code"]) ?>
                                    </strong>

                                    <small>
                                        <?= htmlspecialchars($row["subject_name"]) ?>
                                    </small>

                                </td>

                                <td>
                                    <?= $students ?>
                                </td>

                                <td>
                                    <?= number_format((float)$row["average_marks"], 2) ?>
                                </td>

                                <td>
                                    <?= number_format((float)$row["highest_marks"], 2) ?>
                                </td>

                                <td>
                                    <?= number_format((float)$row["lowest_marks"], 2) ?>
                                </td>

                                <td>
                                    <?= $passed ?>
                                </td>

                                <td>

                                    <div class="exam-report-progress">

                                        <div
                                            class="exam-report-progress-bar"
                                            style="width: <?= min(100, $passPercentage) ?>%;"
                                        ></div>

                                    </div>

                                    <span>
                                        <?= number_format($passPercentage, 1) ?>%
                                    </span>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="exam-report-empty">
                                No result data available.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- Hall Ticket Report -->
        <section class="exam-report-section">

            <div class="exam-report-section-header">

                <div>
                    <h2>Hall Ticket Report</h2>
                    <p>Exam-wise hall ticket generation status</p>
                </div>

            </div>


            <div class="exam-report-table-wrapper">

                <table class="exam-report-table">

                    <thead>

                        <tr>
                            <th>Exam</th>
                            <th>Subject</th>
                            <th>Exam Date</th>
                            <th>Total Tickets</th>
                            <th>Active Tickets</th>
                            <th>Generation Status</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($hallTicketReport && $hallTicketReport->num_rows > 0): ?>

                        <?php while ($row = $hallTicketReport->fetch_assoc()): ?>

                            <?php
                            $totalTickets = (int)$row["total_tickets"];
                            $activeTickets = (int)$row["active_tickets"];

                            $ticketStatus = "Pending";

                            if ($totalTickets > 0 && $activeTickets == $totalTickets) {
                                $ticketStatus = "Completed";
                            } elseif ($totalTickets > 0) {
                                $ticketStatus = "Partial";
                            }
                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row["exam_name"]) ?>
                                    </strong>
                                </td>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($row["subject_code"]) ?>
                                    </strong>

                                    <small>
                                        <?= htmlspecialchars($row["subject_name"]) ?>
                                    </small>

                                </td>

                                <td>
                                    <?= !empty($row["exam_date"])
                                        ? date("d M Y", strtotime($row["exam_date"]))
                                        : "-"
                                    ?>
                                </td>

                                <td>
                                    <?= $totalTickets ?>
                                </td>

                                <td>
                                    <?= $activeTickets ?>
                                </td>

                                <td>
                                    <span class="exam-report-ticket-status">
                                        <?= $ticketStatus ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="exam-report-empty">
                                No hall ticket records available.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>


<script src="../assets/js/theme.js"></script>

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

document.addEventListener("DOMContentLoaded", function () {

    const savedTheme = localStorage.getItem("smartCampusTheme");

    if (savedTheme === "dark") {
        document.body.classList.add("dark-theme");
    }

});
</script>

</body>
</html>