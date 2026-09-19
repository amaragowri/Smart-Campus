<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "EXAM_CELL") {
    header("Location: ../auth/exam-login.php");
    exit();
}

require_once "../config/database.php";

$message = "";
$message_type = "";

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


/* Add Exam */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_exam"])) {

    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $exam_name = trim($_POST["exam_name"] ?? "");
    $exam_type = trim($_POST["exam_type"] ?? "");
    $exam_date = $_POST["exam_date"] ?? "";
    $start_time = $_POST["start_time"] ?? "";
    $end_time = $_POST["end_time"] ?? "";
    $room_number = trim($_POST["room_number"] ?? "");
    $max_marks = (int)($_POST["max_marks"] ?? 0);
    $status = trim($_POST["status"] ?? "SCHEDULED");

    if (
        $subject_id <= 0 ||
        $exam_name === "" ||
        $exam_type === "" ||
        $exam_date === "" ||
        $start_time === "" ||
        $end_time === "" ||
        $room_number === "" ||
        $max_marks <= 0
    ) {

        $message = "Please fill all required fields.";
        $message_type = "error";

    } elseif ($end_time <= $start_time) {

        $message = "End time must be later than start time.";
        $message_type = "error";

    } else {

        /* Check subject */
        $check = $conn->prepare("
            SELECT subject_id
            FROM subjects
            WHERE subject_id = ?
            LIMIT 1
        ");

        $check->bind_param("i", $subject_id);
        $check->execute();

        $check_result = $check->get_result();

        if ($check_result->num_rows === 0) {

            $message = "Selected subject does not exist.";
            $message_type = "error";

        } else {

            $stmt = $conn->prepare("
                INSERT INTO exam_details
                (
                    subject_id,
                    exam_name,
                    exam_type,
                    exam_date,
                    start_time,
                    end_time,
                    room_number,
                    max_marks,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "issssssis",
                $subject_id,
                $exam_name,
                $exam_type,
                $exam_date,
                $start_time,
                $end_time,
                $room_number,
                $max_marks,
                $status
            );

            if ($stmt->execute()) {

                $message = "Exam scheduled successfully.";
                $message_type = "success";

            } else {

                $message = "Failed to schedule exam: " . $stmt->error;
                $message_type = "error";
            }

            $stmt->close();
        }

        $check->close();
    }
}


/* Delete Exam */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_exam"])) {

    $exam_id = (int)($_POST["exam_id"] ?? 0);

    if ($exam_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM exam_details
            WHERE exam_id = ?
        ");

        $stmt->bind_param("i", $exam_id);

        if ($stmt->execute()) {

            $message = "Exam deleted successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to delete exam. It may have related records.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* Load Subjects */
$subjects = [];

$result = $conn->query("
    SELECT
        subject_id,
        subject_code,
        subject_name,
        department,
        year,
        semester
    FROM subjects
    ORDER BY department, year, subject_code
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}


/* Load Exams */
$exams = [];

$result = $conn->query("
    SELECT
        e.exam_id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_number,
        e.max_marks,
        e.status,
        s.subject_code,
        s.subject_name,
        s.department,
        s.year,
        s.semester
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    ORDER BY e.exam_date ASC, e.start_time ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
}


/* Statistics */
$total_exams = count($exams);
$scheduled_exams = 0;
$completed_exams = 0;

foreach ($exams as $exam) {

    $status = strtoupper(trim($exam["status"]));

    if ($status === "SCHEDULED") {
        $scheduled_exams++;
    }

    if ($status === "COMPLETED") {
        $completed_exams++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Exam Schedule | SmartCampus</title>

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

            <a href="dashboard.php">

                <span>🏠</span>
                Dashboard

            </a>


            <a href="exams.php" class="active">

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


    <!-- MAIN -->

    <main class="dashboard-content">

        <!-- HEADER -->

        <header class="dashboard-header">

            <div class="welcome-text">

                <h1>Exam Schedule</h1>

                <p>
                    Create and manage examination schedules
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $exam_user["username"] ?? "Exam Cell"
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


        <!-- MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="exam-schedule-message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <section class="exam-schedule-summary">

            <div class="exam-schedule-summary-card">

                <div class="exam-schedule-summary-icon">
                    📝
                </div>

                <div>

                    <span>Total Exams</span>

                    <strong>
                        <?php echo $total_exams; ?>
                    </strong>

                </div>

            </div>


            <div class="exam-schedule-summary-card">

                <div class="exam-schedule-summary-icon">
                    📅
                </div>

                <div>

                    <span>Scheduled</span>

                    <strong>
                        <?php echo $scheduled_exams; ?>
                    </strong>

                </div>

            </div>


            <div class="exam-schedule-summary-card">

                <div class="exam-schedule-summary-icon">
                    ✅
                </div>

                <div>

                    <span>Completed</span>

                    <strong>
                        <?php echo $completed_exams; ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- ADD EXAM -->

        <section class="exam-schedule-form-section">

            <div class="exam-schedule-section-header">

                <div>

                    <h2>Schedule New Exam</h2>

                    <p>
                        Add examination date, time and venue
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="exams.php"
                class="exam-schedule-form">

                <input
                    type="hidden"
                    name="add_exam"
                    value="1"
                >


                <div class="exam-schedule-form-group">

                    <label for="subject_id">
                        Subject *
                    </label>

                    <select
                        id="subject_id"
                        name="subject_id"
                        required>

                        <option value="">
                            Select Subject
                        </option>

                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?php echo (int)$subject["subject_id"]; ?>">

                                <?php
                                echo htmlspecialchars(
                                    $subject["subject_code"]
                                    . " - "
                                    . $subject["subject_name"]
                                    . " ("
                                    . $subject["department"]
                                    . ")"
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="exam-schedule-form-group">

                    <label for="exam_name">
                        Exam Name *
                    </label>

                    <input
                        type="text"
                        id="exam_name"
                        name="exam_name"
                        placeholder="Example: Mid Semester Examination"
                        maxlength="255"
                        required
                    >

                </div>


                <div class="exam-schedule-form-group">

                    <label for="exam_type">
                        Exam Type *
                    </label>

                    <select
                        id="exam_type"
                        name="exam_type"
                        required>

                        <option value="">
                            Select Type
                        </option>

                        <option value="MID">Mid Semester</option>

                        <option value="INTERNAL">Internal</option>

                        <option value="MODEL">Model Examination</option>

                        <option value="SEMESTER">Semester Examination</option>

                        <option value="PRACTICAL">Practical</option>

                        <option value="SUPPLEMENTARY">Supplementary</option>

                    </select>

                </div>


                <div class="exam-schedule-form-group">

                    <label for="exam_date">
                        Exam Date *
                    </label>

                    <input
                        type="date"
                        id="exam_date"
                        name="exam_date"
                        required
                    >

                </div>


                <div class="exam-schedule-form-group">

                    <label for="start_time">
                        Start Time *
                    </label>

                    <input
                        type="time"
                        id="start_time"
                        name="start_time"
                        required
                    >

                </div>


                <div class="exam-schedule-form-group">

                    <label for="end_time">
                        End Time *
                    </label>

                    <input
                        type="time"
                        id="end_time"
                        name="end_time"
                        required
                    >

                </div>


                <div class="exam-schedule-form-group">

                    <label for="room_number">
                        Room Number *
                    </label>

                    <input
                        type="text"
                        id="room_number"
                        name="room_number"
                        placeholder="Example: A-101"
                        maxlength="50"
                        required
                    >

                </div>


                <div class="exam-schedule-form-group">

                    <label for="max_marks">
                        Maximum Marks *
                    </label>

                    <input
                        type="number"
                        id="max_marks"
                        name="max_marks"
                        min="1"
                        max="1000"
                        placeholder="100"
                        required
                    >

                </div>


                <div class="exam-schedule-form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status">

                        <option value="SCHEDULED">
                            Scheduled
                        </option>

                        <option value="COMPLETED">
                            Completed
                        </option>

                        <option value="CANCELLED">
                            Cancelled
                        </option>

                    </select>

                </div>


                <div class="exam-schedule-form-actions">

                    <button
                        type="submit"
                        class="exam-schedule-submit-btn">

                        📝 Schedule Exam

                    </button>

                </div>

            </form>

        </section>


        <!-- EXAM LIST -->

        <section class="exam-schedule-list-section">

            <div class="exam-schedule-section-header">

                <div>

                    <h2>Examination Schedule</h2>

                    <p>
                        All examinations registered in SmartCampus
                    </p>

                </div>

            </div>


            <?php if (count($exams) > 0): ?>

                <div class="exam-schedule-table-wrapper">

                    <table class="exam-schedule-table">

                        <thead>

                            <tr>

                                <th>Subject</th>

                                <th>Exam</th>

                                <th>Date</th>

                                <th>Time</th>

                                <th>Room</th>

                                <th>Marks</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($exams as $exam): ?>

                                <?php
                                $exam_status =
                                    strtoupper(
                                        trim($exam["status"])
                                    );

                                $status_class = "scheduled";

                                if ($exam_status === "COMPLETED") {
                                    $status_class = "completed";
                                } elseif ($exam_status === "CANCELLED") {
                                    $status_class = "cancelled";
                                }
                                ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $exam["subject_code"]
                                            );
                                            ?>
                                        </strong>

                                        <small>
                                            <?php
                                            echo htmlspecialchars(
                                                $exam["subject_name"]
                                            );
                                            ?>
                                        </small>

                                    </td>


                                    <td>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $exam["exam_name"]
                                            );
                                            ?>
                                        </strong>

                                        <small>
                                            <?php
                                            echo htmlspecialchars(
                                                $exam["exam_type"]
                                            );
                                            ?>
                                        </small>

                                    </td>


                                    <td>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $exam["exam_date"]
                                            )
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo date(
                                            "h:i A",
                                            strtotime(
                                                $exam["start_time"]
                                            )
                                        );
                                        ?>

                                        <br>

                                        <small>
                                            to
                                            <?php
                                            echo date(
                                                "h:i A",
                                                strtotime(
                                                    $exam["end_time"]
                                                )
                                            );
                                            ?>
                                        </small>

                                    </td>


                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $exam["room_number"]
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo (int)$exam["max_marks"];
                                        ?>

                                    </td>


                                    <td>

                                        <span
                                            class="exam-schedule-status <?php echo $status_class; ?>">

                                            <?php
                                            echo htmlspecialchars(
                                                $exam_status
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <form
                                            method="POST"
                                            action="exams.php"
                                            onsubmit="return confirm('Are you sure you want to delete this exam?');">

                                            <input
                                                type="hidden"
                                                name="delete_exam"
                                                value="1"
                                            >

                                            <input
                                                type="hidden"
                                                name="exam_id"
                                                value="<?php
                                                echo (int)$exam["exam_id"];
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="exam-schedule-delete-btn">

                                                🗑

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="exam-schedule-empty">

                    <div>
                        📝
                    </div>

                    <h3>
                        No Exams Scheduled
                    </h3>

                    <p>
                        Use the form above to create the first examination schedule.
                    </p>

                </div>

            <?php endif; ?>

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


/* Prevent past date */
const examDate =
    document.getElementById("exam_date");

if (examDate) {

    const today =
        new Date().toISOString().split("T")[0];

    examDate.min = today;
}

</script>

</body>
</html>