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

/* Hall tickets */
$stmt = $conn->prepare("
    SELECT
        h.hall_ticket_id,
        h.hall_ticket_number,
        h.issue_date,
        h.status,
        e.exam_id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_number,
        e.max_marks,
        s.subject_code,
        s.subject_name
    FROM hall_tickets h
    INNER JOIN exam_details e
        ON h.exam_id = e.exam_id
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    WHERE h.student_id = ?
    ORDER BY e.exam_date ASC, e.start_time ASC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$hall_tickets = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hall Ticket | SmartCampus</title>

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

            <a href="results.php">
                🎯
                <span>Results</span>
            </a>

            <a href="hall-ticket.php" class="active">
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

                <h1>Hall Ticket</h1>

                <div class="student-meta">
                    <?php echo htmlspecialchars($student["full_name"]); ?>
                    •
                    <?php echo htmlspecialchars($student["roll_number"]); ?>
                    •
                    <?php echo htmlspecialchars($student["department"]); ?>
                </div>

            </div>

            <button class="dashboard-theme-toggle" id="themeToggle">
                🌙
            </button>

        </div>


        <!-- STUDENT INFORMATION -->
        <section class="hall-ticket-student-card">

            <div class="hall-ticket-student-icon">
                👤
            </div>

            <div class="hall-ticket-student-info">

                <h2>
                    <?php echo htmlspecialchars($student["full_name"]); ?>
                </h2>

                <div class="hall-ticket-student-details">

                    <span>
                        <strong>Roll Number:</strong>
                        <?php echo htmlspecialchars($student["roll_number"]); ?>
                    </span>

                    <span>
                        <strong>Department:</strong>
                        <?php echo htmlspecialchars($student["department"]); ?>
                    </span>

                    <span>
                        <strong>Year:</strong>
                        <?php echo htmlspecialchars($student["year"]); ?>
                    </span>

                    <span>
                        <strong>Section:</strong>
                        <?php echo htmlspecialchars($student["section"]); ?>
                    </span>

                </div>

            </div>

        </section>


        <!-- HALL TICKETS -->

        <?php if ($hall_tickets->num_rows > 0): ?>

            <div class="hall-ticket-list">

                <?php while ($ticket = $hall_tickets->fetch_assoc()): ?>

                    <section class="hall-ticket-card">

                        <!-- CARD HEADER -->

                        <div class="hall-ticket-card-header">

                            <div>

                                <span class="hall-ticket-label">
                                    HALL TICKET
                                </span>

                                <h2>
                                    <?php echo htmlspecialchars($ticket["exam_name"]); ?>
                                </h2>

                                <p>
                                    <?php echo htmlspecialchars($ticket["exam_type"]); ?>
                                </p>

                            </div>

                            <div class="hall-ticket-number">

                                <span>Ticket No.</span>

                                <strong>
                                    <?php echo htmlspecialchars($ticket["hall_ticket_number"]); ?>
                                </strong>

                            </div>

                        </div>


                        <!-- SUBJECT -->

                        <div class="hall-ticket-subject">

                            <div class="hall-ticket-subject-code">
                                <?php echo htmlspecialchars($ticket["subject_code"]); ?>
                            </div>

                            <div>
                                <strong>
                                    <?php echo htmlspecialchars($ticket["subject_name"]); ?>
                                </strong>

                                <span>Examination Subject</span>
                            </div>

                        </div>


                        <!-- EXAM DETAILS -->

                        <div class="hall-ticket-details">

                            <div class="hall-ticket-detail">

                                <span>📅 Exam Date</span>

                                <strong>
                                    <?php
                                    echo !empty($ticket["exam_date"])
                                        ? date("d M Y", strtotime($ticket["exam_date"]))
                                        : "—";
                                    ?>
                                </strong>

                            </div>


                            <div class="hall-ticket-detail">

                                <span>⏰ Time</span>

                                <strong>

                                    <?php
                                    if (!empty($ticket["start_time"])) {
                                        echo date("h:i A", strtotime($ticket["start_time"]));

                                        if (!empty($ticket["end_time"])) {
                                            echo " - " . date(
                                                "h:i A",
                                                strtotime($ticket["end_time"])
                                            );
                                        }
                                    } else {
                                        echo "—";
                                    }
                                    ?>

                                </strong>

                            </div>


                            <div class="hall-ticket-detail">

                                <span>🏫 Room</span>

                                <strong>
                                    <?php echo htmlspecialchars($ticket["room_number"] ?: "—"); ?>
                                </strong>

                            </div>


                            <div class="hall-ticket-detail">

                                <span>📊 Maximum Marks</span>

                                <strong>
                                    <?php echo htmlspecialchars($ticket["max_marks"] ?: "—"); ?>
                                </strong>

                            </div>

                        </div>


                        <!-- FOOTER -->

                        <div class="hall-ticket-card-footer">

                            <div>

                                <span>Issue Date</span>

                                <strong>
                                    <?php
                                    echo !empty($ticket["issue_date"])
                                        ? date("d M Y", strtotime($ticket["issue_date"]))
                                        : "—";
                                    ?>
                                </strong>

                            </div>


                            <span class="hall-ticket-status
                                <?php echo strtolower(htmlspecialchars($ticket["status"])); ?>">

                                <?php
                                echo htmlspecialchars(
                                    $ticket["status"] ?: "Issued"
                                );
                                ?>

                            </span>

                        </div>

                    </section>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <!-- EMPTY STATE -->

            <section class="hall-ticket-empty">

                <div class="hall-ticket-empty-icon">
                    🎫
                </div>

                <h2>No Hall Ticket Available</h2>

                <p>
                    Your hall ticket has not been issued yet.
                    Please check again after the examination cell publishes it.
                </p>

                <a href="exams.php" class="btn btn-primary">
                    View Exam Schedule
                </a>

            </section>

        <?php endif; ?>

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