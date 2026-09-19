<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "EXAM_CELL") {
    header("Location: ../auth/exam-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

/* Exam Cell user */
$stmt = $conn->prepare("
    SELECT username
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$exam_user = $result->fetch_assoc();

$stmt->close();


/* Total Students */
$total_students = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_students = (int)$row["total"];
}


/* Total Subjects */
$total_subjects = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM subjects
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_subjects = (int)$row["total"];
}


/* Total Exams */
$total_exams = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM exam_details
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_exams = (int)$row["total"];
}


/* Total Hall Tickets */
$total_hall_tickets = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM hall_tickets
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_hall_tickets = (int)$row["total"];
}


/* Upcoming Exams */
$upcoming_exams = [];

$result = $conn->query("
    SELECT
        e.exam_id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_number,
        e.status,
        s.subject_code,
        s.subject_name
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    WHERE e.exam_date >= CURDATE()
    ORDER BY e.exam_date ASC, e.start_time ASC
    LIMIT 5
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $upcoming_exams[] = $row;
    }
}


/* Recent Information Updates */
$recent_updates = [];

$stmt = $conn->prepare("
    SELECT
        update_id,
        title,
        description,
        target_role,
        publish_date
    FROM information_updates
    WHERE status = 'ACTIVE'
    ORDER BY publish_date DESC, update_id DESC
    LIMIT 5
");

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recent_updates[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Exam Cell Dashboard | SmartCampus</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
                SC
            </div>

            <div>

                <h2>SmartCampus</h2>

                <span>Exam Cell Portal</span>

            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php" class="active">

                <span>🏠</span>

                Dashboard

            </a>


            <a href="exams.php">

                <span>📝</span>

                Exam Schedule

            </a>


            <a href="marks.php">

                <span>📊</span>

                Marks Management

            </a>


            <a href="hall-tickets.php">

                <span>🎫</span>

                Hall Tickets

            </a>


            <a href="notifications.php">

                <span>🔔</span>

                Notifications

            </a>


            <a href="reports.php">

                <span>📄</span>

                Exam Reports

            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">

                <span>🚪</span>

                Logout

            </a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="dashboard-content">

        <!-- HEADER -->

        <header class="dashboard-header">

            <div class="welcome-text">

                <h1>Exam Cell Dashboard</h1>

                <p>
                    Manage examinations, marks and hall tickets
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $exam_user["username"]
                            ?? "Exam Cell"
                        );
                        ?>

                    </strong>

                    <span>
                        Examination Cell
                    </span>

                </div>


                <button
                    class="dashboard-theme-toggle"
                    id="themeToggle"
                    type="button">

                    🌙

                </button>

            </div>

        </header>


        <!-- STATISTICS -->

        <section class="exam-dashboard-stats">

            <div class="exam-stat-card">

                <div class="exam-stat-icon students">
                    👨‍🎓
                </div>

                <div>

                    <span>Total Students</span>

                    <strong>
                        <?php echo $total_students; ?>
                    </strong>

                </div>

            </div>


            <div class="exam-stat-card">

                <div class="exam-stat-icon subjects">
                    📚
                </div>

                <div>

                    <span>Total Subjects</span>

                    <strong>
                        <?php echo $total_subjects; ?>
                    </strong>

                </div>

            </div>


            <div class="exam-stat-card">

                <div class="exam-stat-icon exams">
                    📝
                </div>

                <div>

                    <span>Total Exams</span>

                    <strong>
                        <?php echo $total_exams; ?>
                    </strong>

                </div>

            </div>


            <div class="exam-stat-card">

                <div class="exam-stat-icon tickets">
                    🎫
                </div>

                <div>

                    <span>Hall Tickets</span>

                    <strong>
                        <?php echo $total_hall_tickets; ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- QUICK ACTIONS -->

        <section class="exam-dashboard-section">

            <div class="exam-section-header">

                <div>

                    <h2>Quick Actions</h2>

                    <p>
                        Frequently used examination functions
                    </p>

                </div>

            </div>


            <div class="exam-quick-actions">

                <a href="exams.php" class="exam-quick-card">

                    <div class="exam-quick-icon">
                        📝
                    </div>

                    <div>

                        <h3>Exam Schedule</h3>

                        <p>
                            Create and manage examination schedules
                        </p>

                    </div>

                </a>


                <a href="marks.php" class="exam-quick-card">

                    <div class="exam-quick-icon">
                        📊
                    </div>

                    <div>

                        <h3>Marks Management</h3>

                        <p>
                            Enter and manage student marks
                        </p>

                    </div>

                </a>


                <a href="hall-tickets.php" class="exam-quick-card">

                    <div class="exam-quick-icon">
                        🎫
                    </div>

                    <div>

                        <h3>Hall Tickets</h3>

                        <p>
                            Generate and manage hall tickets
                        </p>

                    </div>

                </a>


                <a href="reports.php" class="exam-quick-card">

                    <div class="exam-quick-icon">
                        📄
                    </div>

                    <div>

                        <h3>Exam Reports</h3>

                        <p>
                            View examination reports and statistics
                        </p>

                    </div>

                </a>

            </div>

        </section>


        <!-- TWO COLUMN AREA -->

        <section class="exam-dashboard-grid">

            <!-- UPCOMING EXAMS -->

            <div class="exam-dashboard-panel">

                <div class="exam-panel-header">

                    <div>

                        <h2>Upcoming Exams</h2>

                        <p>
                            Next scheduled examinations
                        </p>

                    </div>

                    <a href="exams.php">
                        View All
                    </a>

                </div>


                <?php if (count($upcoming_exams) > 0): ?>

                    <div class="exam-upcoming-list">

                        <?php foreach ($upcoming_exams as $exam): ?>

                            <div class="exam-upcoming-item">

                                <div class="exam-date-box">

                                    <strong>

                                        <?php
                                        echo date(
                                            "d",
                                            strtotime(
                                                $exam["exam_date"]
                                            )
                                        );
                                        ?>

                                    </strong>

                                    <span>

                                        <?php
                                        echo date(
                                            "M",
                                            strtotime(
                                                $exam["exam_date"]
                                            )
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="exam-upcoming-info">

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
                                            $exam["subject_name"]
                                        );
                                        ?>

                                    </p>

                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $exam["exam_type"]
                                        );
                                        ?>

                                        •

                                        <?php
                                        echo date(
                                            "h:i A",
                                            strtotime(
                                                $exam["start_time"]
                                            )
                                        );
                                        ?>

                                    </span>

                                </div>


                                <span class="exam-status-badge">

                                    <?php
                                    echo htmlspecialchars(
                                        $exam["status"]
                                    );
                                    ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="exam-empty-small">

                        <span>📅</span>

                        <p>
                            No upcoming exams scheduled.
                        </p>

                    </div>

                <?php endif; ?>

            </div>


            <!-- RECENT UPDATES -->

            <div class="exam-dashboard-panel">

                <div class="exam-panel-header">

                    <div>

                        <h2>Recent Updates</h2>

                        <p>
                            Latest college information
                        </p>

                    </div>

                    <a href="../office/updates.php">
                        View All
                    </a>

                </div>


                <?php if (count($recent_updates) > 0): ?>

                    <div class="exam-update-list">

                        <?php foreach ($recent_updates as $update): ?>

                            <div class="exam-update-item">

                                <div class="exam-update-icon">
                                    📢
                                </div>

                                <div>

                                    <h3>

                                        <?php
                                        echo htmlspecialchars(
                                            $update["title"]
                                        );
                                        ?>

                                    </h3>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            mb_strimwidth(
                                                $update["description"],
                                                0,
                                                100,
                                                "..."
                                            )
                                        );
                                        ?>

                                    </p>

                                    <span>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $update["publish_date"]
                                            )
                                        );
                                        ?>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="exam-empty-small">

                        <span>📢</span>

                        <p>
                            No recent updates available.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>


<script>

const themeToggle =
    document.getElementById("themeToggle");

const savedTheme =
    localStorage.getItem("theme");

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

</script>

</body>

</html>