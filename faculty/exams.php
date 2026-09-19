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
    SELECT faculty_id, full_name, employee_id, email, department, designation
    FROM faculty
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$faculty = $result->fetch_assoc();
$stmt->close();

if (!$faculty) {
    die("Faculty profile not found.");
}

$faculty_id = $faculty["faculty_id"];

$success_message = "";
$error_message = "";


/* =========================
   ADD EXAM
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_exam"])) {

    $subject_id = intval($_POST["subject_id"] ?? 0);
    $exam_name = trim($_POST["exam_name"] ?? "");
    $exam_type = trim($_POST["exam_type"] ?? "");
    $exam_date = $_POST["exam_date"] ?? "";
    $start_time = $_POST["start_time"] ?? "";
    $end_time = $_POST["end_time"] ?? "";
    $room_number = trim($_POST["room_number"] ?? "");
    $max_marks = intval($_POST["max_marks"] ?? 0);

    if (
        $subject_id <= 0 ||
        empty($exam_name) ||
        empty($exam_type) ||
        empty($exam_date) ||
        empty($start_time) ||
        empty($end_time) ||
        empty($room_number) ||
        $max_marks <= 0
    ) {

        $error_message = "Please fill all exam details.";

    } elseif ($end_time <= $start_time) {

        $error_message = "End time must be after start time.";

    } else {

        /*
         * Make sure the selected subject is actually
         * assigned to this faculty.
         */

        $stmt = $conn->prepare("
            SELECT subject_id
            FROM timetable
            WHERE subject_id = ?
              AND faculty_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("ii", $subject_id, $faculty_id);
        $stmt->execute();

        $subject_check = $stmt->get_result();
        $valid_subject = $subject_check->fetch_assoc();

        $stmt->close();

        if (!$valid_subject) {

            $error_message = "Invalid subject selected.";

        } else {

            $status = "SCHEDULED";

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

                $success_message = "Exam scheduled successfully.";

            } else {

                $error_message = "Unable to schedule exam.";
            }

            $stmt->close();
        }
    }
}


/* =========================
   DELETE EXAM
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_exam"])) {

    $exam_id = intval($_POST["exam_id"] ?? 0);

    if ($exam_id > 0) {

        /*
         * Delete only if the exam belongs to
         * a subject assigned to this faculty.
         */

        $stmt = $conn->prepare("
            SELECT e.exam_id
            FROM exam_details e
            INNER JOIN timetable t
                ON e.subject_id = t.subject_id
            WHERE e.exam_id = ?
              AND t.faculty_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("ii", $exam_id, $faculty_id);
        $stmt->execute();

        $check_result = $stmt->get_result();
        $valid_exam = $check_result->fetch_assoc();

        $stmt->close();

        if ($valid_exam) {

            $stmt = $conn->prepare("
                DELETE FROM exam_details
                WHERE exam_id = ?
            ");

            $stmt->bind_param("i", $exam_id);

            if ($stmt->execute()) {

                $success_message = "Exam deleted successfully.";

            } else {

                $error_message = "Unable to delete exam.";
            }

            $stmt->close();

        } else {

            $error_message = "Exam not found.";
        }
    }
}


/* =========================
   SUBJECTS
========================= */

$subjects = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.year,
        s.semester
    FROM subjects s
    INNER JOIN timetable t
        ON s.subject_id = t.subject_id
    WHERE t.faculty_id = ?
      AND s.department = ?
    ORDER BY s.year, s.semester, s.subject_code
");

$stmt->bind_param(
    "is",
    $faculty_id,
    $faculty["department"]
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

$stmt->close();


/* =========================
   EXAMS
========================= */

$exams = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
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
        s.year,
        s.semester
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    INNER JOIN timetable t
        ON e.subject_id = t.subject_id
    WHERE t.faculty_id = ?
      AND s.department = ?
    ORDER BY e.exam_date ASC, e.start_time ASC
");

$stmt->bind_param(
    "is",
    $faculty_id,
    $faculty["department"]
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

$stmt->close();


/* =========================
   COUNTS
========================= */

$total_exams = count($exams);
$upcoming_exams = 0;
$completed_exams = 0;

$today = date("Y-m-d");

foreach ($exams as $exam) {

    if ($exam["exam_date"] >= $today) {
        $upcoming_exams++;
    } else {
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

    <title>Faculty Exams | SmartCampus</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

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
                <h2>SmartCampus</h2>
                <span>Faculty Portal</span>
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

            <a href="timetable.php">
                <span>🗓️</span>
                Timetable
            </a>

            <a href="materials.php">
                <span>📚</span>
                Materials
            </a>

            <a href="exams.php" class="active">
                <span>📝</span>
                Exams
            </a>

            <a href="#">
                <span>📈</span>
                Results
            </a>

            <a href="#">
                <span>📢</span>
                Notifications
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">
                <span>🚪</span>
                Logout
            </a>

        </div>

    </aside>


    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="dashboard-content">

        <header class="dashboard-header">

            <div class="welcome-text">

                <h1>Exams</h1>

                <p>
                    Schedule and manage examinations for your subjects.
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $faculty["full_name"]
                        );
                        ?>
                    </strong>

                    <span>
                        <?php
                        echo htmlspecialchars(
                            $faculty["employee_id"]
                        );
                        ?>
                    </span>

                </div>


                <button
                    type="button"
                    class="dashboard-theme-toggle"
                    id="themeToggle">

                    🌙

                </button>

            </div>

        </header>


        <!-- =========================
             MESSAGES
        ========================== -->

        <?php if (!empty($success_message)): ?>

            <div class="faculty-exam-message success">

                <span>✓</span>

                <?php
                echo htmlspecialchars(
                    $success_message
                );
                ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($error_message)): ?>

            <div class="faculty-exam-message error">

                <span>!</span>

                <?php
                echo htmlspecialchars(
                    $error_message
                );
                ?>

            </div>

        <?php endif; ?>


        <!-- =========================
             SUMMARY
        ========================== -->

        <section class="faculty-exam-summary">

            <div class="faculty-exam-summary-card">

                <div class="faculty-exam-summary-icon">
                    📝
                </div>

                <div>
                    <span>Total Exams</span>
                    <strong>
                        <?php echo $total_exams; ?>
                    </strong>
                </div>

            </div>


            <div class="faculty-exam-summary-card">

                <div class="faculty-exam-summary-icon">
                    📅
                </div>

                <div>
                    <span>Upcoming</span>
                    <strong>
                        <?php echo $upcoming_exams; ?>
                    </strong>
                </div>

            </div>


            <div class="faculty-exam-summary-card">

                <div class="faculty-exam-summary-icon">
                    ✓
                </div>

                <div>
                    <span>Completed</span>
                    <strong>
                        <?php echo $completed_exams; ?>
                    </strong>
                </div>

            </div>

        </section>


        <!-- =========================
             ADD EXAM
        ========================== -->

        <section class="faculty-exam-form-section">

            <div class="faculty-exam-section-header">

                <div>

                    <h2>Schedule New Exam</h2>

                    <p>
                        Add examination details for your assigned subject.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                class="faculty-exam-form">


                <div class="faculty-exam-field">

                    <label>Subject</label>

                    <select
                        name="subject_id"
                        required>

                        <option value="">
                            Select Subject
                        </option>

                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?php echo $subject["subject_id"]; ?>">

                                <?php
                                echo htmlspecialchars(
                                    $subject["subject_code"] .
                                    " - " .
                                    $subject["subject_name"]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="faculty-exam-field">

                    <label>Exam Name</label>

                    <input
                        type="text"
                        name="exam_name"
                        placeholder="Example: Mid-1 Examination"
                        maxlength="100"
                        required>

                </div>


                <div class="faculty-exam-field">

                    <label>Exam Type</label>

                    <select
                        name="exam_type"
                        required>

                        <option value="">
                            Select Type
                        </option>

                        <option value="MID-1">
                            Mid-1
                        </option>

                        <option value="MID-2">
                            Mid-2
                        </option>

                        <option value="INTERNAL">
                            Internal
                        </option>

                        <option value="LAB">
                            Lab
                        </option>

                        <option value="SEMESTER">
                            Semester
                        </option>

                        <option value="OTHER">
                            Other
                        </option>

                    </select>

                </div>


                <div class="faculty-exam-field">

                    <label>Exam Date</label>

                    <input
                        type="date"
                        name="exam_date"
                        min="<?php echo date('Y-m-d'); ?>"
                        required>

                </div>


                <div class="faculty-exam-field">

                    <label>Start Time</label>

                    <input
                        type="time"
                        name="start_time"
                        required>

                </div>


                <div class="faculty-exam-field">

                    <label>End Time</label>

                    <input
                        type="time"
                        name="end_time"
                        required>

                </div>


                <div class="faculty-exam-field">

                    <label>Room Number</label>

                    <input
                        type="text"
                        name="room_number"
                        placeholder="Example: Block A - 203"
                        maxlength="50"
                        required>

                </div>


                <div class="faculty-exam-field">

                    <label>Maximum Marks</label>

                    <input
                        type="number"
                        name="max_marks"
                        min="1"
                        max="1000"
                        placeholder="Example: 50"
                        required>

                </div>


                <div class="faculty-exam-form-actions">

                    <button
                        type="submit"
                        name="add_exam"
                        class="faculty-exam-add-btn">

                        ➕ Schedule Exam

                    </button>

                </div>

            </form>

        </section>


        <!-- =========================
             EXAM LIST
        ========================== -->

        <section class="faculty-exam-list-section">

            <div class="faculty-exam-section-header">

                <div>

                    <h2>Scheduled Exams</h2>

                    <p>
                        Examination schedule for your assigned subjects.
                    </p>

                </div>

            </div>


            <?php if (empty($exams)): ?>

                <div class="faculty-exam-empty">

                    <div class="faculty-exam-empty-icon">
                        📝
                    </div>

                    <h3>No Exams Scheduled</h3>

                    <p>
                        Schedule your first examination using the form above.
                    </p>

                </div>

            <?php else: ?>

                <div class="faculty-exam-grid">

                    <?php foreach ($exams as $exam): ?>

                        <?php
                        $exam_date = strtotime(
                            $exam["exam_date"]
                        );

                        $is_upcoming =
                            $exam["exam_date"] >= $today;
                        ?>

                        <article class="faculty-exam-card">

                            <div class="faculty-exam-card-top">

                                <div class="faculty-exam-date">

                                    <strong>
                                        <?php
                                        echo date(
                                            "d",
                                            $exam_date
                                        );
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo date(
                                            "M",
                                            $exam_date
                                        );
                                        ?>
                                    </span>

                                </div>


                                <span class="
                                    faculty-exam-status
                                    <?php
                                    echo $is_upcoming
                                        ? "upcoming"
                                        : "completed";
                                    ?>
                                ">

                                    <?php
                                    echo $is_upcoming
                                        ? "Upcoming"
                                        : "Completed";
                                    ?>

                                </span>

                            </div>


                            <div class="faculty-exam-card-body">

                                <div class="faculty-exam-type">
                                    <?php
                                    echo htmlspecialchars(
                                        $exam["exam_type"]
                                    );
                                    ?>
                                </div>


                                <h3>
                                    <?php
                                    echo htmlspecialchars(
                                        $exam["exam_name"]
                                    );
                                    ?>
                                </h3>


                                <div class="faculty-exam-subject">

                                    📘

                                    <?php
                                    echo htmlspecialchars(
                                        $exam["subject_code"]
                                    );
                                    ?>

                                    -
                                    <?php
                                    echo htmlspecialchars(
                                        $exam["subject_name"]
                                    );
                                    ?>

                                </div>


                                <div class="faculty-exam-details">

                                    <span>
                                        🕐
                                        <?php
                                        echo date(
                                            "h:i A",
                                            strtotime(
                                                $exam["start_time"]
                                            )
                                        );
                                        ?>

                                        -

                                        <?php
                                        echo date(
                                            "h:i A",
                                            strtotime(
                                                $exam["end_time"]
                                            )
                                        );
                                        ?>
                                    </span>


                                    <span>
                                        🏫
                                        <?php
                                        echo htmlspecialchars(
                                            $exam["room_number"]
                                        );
                                        ?>
                                    </span>


                                    <span>
                                        🎯
                                        <?php
                                        echo htmlspecialchars(
                                            $exam["max_marks"]
                                        );
                                        ?>
                                        Marks
                                    </span>

                                </div>

                            </div>


                            <div class="faculty-exam-card-actions">

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this exam?');">

                                    <input
                                        type="hidden"
                                        name="exam_id"
                                        value="<?php echo $exam["exam_id"]; ?>">

                                    <button
                                        type="submit"
                                        name="delete_exam"
                                        class="faculty-exam-delete-btn">

                                        🗑 Delete

                                    </button>

                                </form>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>

document.addEventListener("DOMContentLoaded", function () {

    const themeToggle =
        document.getElementById("themeToggle");

    const savedTheme =
        localStorage.getItem("smartcampus-theme");

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
                "smartcampus-theme",
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