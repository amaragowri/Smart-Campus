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

/* Exam Cell User */
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


/* Add Marks */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_marks"])) {

    $student_id = (int)($_POST["student_id"] ?? 0);
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $exam_id = (int)($_POST["exam_id"] ?? 0);
    $marks_obtained = (float)($_POST["marks_obtained"] ?? 0);

    if (
        $student_id <= 0 ||
        $subject_id <= 0 ||
        $exam_id <= 0
    ) {

        $message = "Please select student, subject and exam.";
        $message_type = "error";

    } else {

        /* Get exam maximum marks and subject */
        $stmt = $conn->prepare("
            SELECT
                e.max_marks,
                e.subject_id
            FROM exam_details e
            WHERE e.exam_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $exam_id);
        $stmt->execute();

        $exam_result = $stmt->get_result();
        $exam_data = $exam_result->fetch_assoc();

        $stmt->close();

        if (!$exam_data) {

            $message = "Selected exam was not found.";
            $message_type = "error";

        } elseif ((int)$exam_data["subject_id"] !== $subject_id) {

            $message = "Selected subject does not match the exam.";
            $message_type = "error";

        } elseif ($marks_obtained < 0 || $marks_obtained > (float)$exam_data["max_marks"]) {

            $message = "Marks must be between 0 and " . $exam_data["max_marks"] . ".";
            $message_type = "error";

        } else {

            /* Check student */
            $stmt = $conn->prepare("
                SELECT student_id
                FROM students
                WHERE student_id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $student_id);
            $stmt->execute();

            $student_result = $stmt->get_result();
            $student_exists = $student_result->num_rows > 0;

            $stmt->close();


            if (!$student_exists) {

                $message = "Selected student was not found.";
                $message_type = "error";

            } else {

                /* Check duplicate */
                $stmt = $conn->prepare("
                    SELECT mark_id
                    FROM marks
                    WHERE student_id = ?
                      AND subject_id = ?
                      AND exam_id = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "iii",
                    $student_id,
                    $subject_id,
                    $exam_id
                );

                $stmt->execute();

                $duplicate_result = $stmt->get_result();

                if ($duplicate_result->num_rows > 0) {

                    $message = "Marks already entered for this student and exam.";
                    $message_type = "error";

                } else {

                    $max_marks = (float)$exam_data["max_marks"];

                    $percentage =
                        ($marks_obtained / $max_marks) * 100;

                    /* Grade */
                    if ($percentage >= 90) {
                        $grade = "A+";
                    } elseif ($percentage >= 80) {
                        $grade = "A";
                    } elseif ($percentage >= 70) {
                        $grade = "B+";
                    } elseif ($percentage >= 60) {
                        $grade = "B";
                    } elseif ($percentage >= 50) {
                        $grade = "C";
                    } elseif ($percentage >= 40) {
                        $grade = "D";
                    } else {
                        $grade = "F";
                    }


                    $insert = $conn->prepare("
                        INSERT INTO marks
                        (
                            student_id,
                            subject_id,
                            exam_id,
                            marks_obtained,
                            max_marks,
                            grade
                        )
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    $insert->bind_param(
                        "iiidds",
                        $student_id,
                        $subject_id,
                        $exam_id,
                        $marks_obtained,
                        $max_marks,
                        $grade
                    );

                    if ($insert->execute()) {

                        $message = "Marks added successfully.";
                        $message_type = "success";

                    } else {

                        $message = "Failed to add marks: " . $insert->error;
                        $message_type = "error";
                    }

                    $insert->close();
                }

                $stmt->close();
            }
        }
    }
}


/* Delete Marks */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_marks"])) {

    $mark_id = (int)($_POST["mark_id"] ?? 0);

    if ($mark_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM marks
            WHERE mark_id = ?
        ");

        $stmt->bind_param("i", $mark_id);

        if ($stmt->execute()) {

            $message = "Marks deleted successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to delete marks.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* Load Students */
$students = [];

$result = $conn->query("
    SELECT
        student_id,
        roll_number,
        full_name,
        department,
        year,
        section
    FROM students
    ORDER BY roll_number
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
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
$exam_options = [];

$result = $conn->query("
    SELECT
        e.exam_id,
        e.subject_id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.max_marks,
        s.subject_code,
        s.subject_name
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    ORDER BY e.exam_date DESC, e.exam_id DESC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $exam_options[] = $row;
    }
}


/* Load Marks */
$marks_list = [];

$result = $conn->query("
    SELECT
        m.mark_id,
        m.marks_obtained,
        m.max_marks,
        m.grade,
        st.roll_number,
        st.full_name,
        st.department,
        st.year,
        s.subject_code,
        s.subject_name,
        e.exam_name,
        e.exam_type,
        e.exam_date
    FROM marks m
    INNER JOIN students st
        ON m.student_id = st.student_id
    INNER JOIN subjects s
        ON m.subject_id = s.subject_id
    LEFT JOIN exam_details e
        ON m.exam_id = e.exam_id
    ORDER BY e.exam_date DESC, st.roll_number ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $marks_list[] = $row;
    }
}


/* Statistics */
$total_results = count($marks_list);
$passed = 0;
$failed = 0;
$total_percentage = 0;

foreach ($marks_list as $mark) {

    $max = (float)$mark["max_marks"];
    $obtained = (float)$mark["marks_obtained"];

    $percentage = $max > 0
        ? ($obtained / $max) * 100
        : 0;

    $total_percentage += $percentage;

    if ($percentage >= 40) {
        $passed++;
    } else {
        $failed++;
    }
}

$average_percentage =
    $total_results > 0
        ? $total_percentage / $total_results
        : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Marks Management | SmartCampus</title>

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

            <a href="exams.php">
                <span>📝</span>
                Exam Schedule
            </a>

            <a href="marks.php" class="active">
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

                <h1>Marks Management</h1>

                <p>
                    Enter and manage student examination marks
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

            <div class="exam-marks-message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <section class="exam-marks-summary">

            <div class="exam-marks-summary-card">

                <div class="exam-marks-summary-icon">
                    📊
                </div>

                <div>
                    <span>Total Results</span>
                    <strong>
                        <?php echo $total_results; ?>
                    </strong>
                </div>

            </div>


            <div class="exam-marks-summary-card">

                <div class="exam-marks-summary-icon">
                    ✅
                </div>

                <div>
                    <span>Passed</span>
                    <strong>
                        <?php echo $passed; ?>
                    </strong>
                </div>

            </div>


            <div class="exam-marks-summary-card">

                <div class="exam-marks-summary-icon">
                    ❌
                </div>

                <div>
                    <span>Failed</span>
                    <strong>
                        <?php echo $failed; ?>
                    </strong>
                </div>

            </div>


            <div class="exam-marks-summary-card">

                <div class="exam-marks-summary-icon">
                    📈
                </div>

                <div>
                    <span>Average</span>
                    <strong>
                        <?php echo number_format($average_percentage, 1); ?>%
                    </strong>
                </div>

            </div>

        </section>


        <!-- ADD MARKS -->

        <section class="exam-marks-form-section">

            <div class="exam-marks-section-header">

                <div>

                    <h2>Enter Student Marks</h2>

                    <p>
                        Add marks for a student examination
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="marks.php"
                class="exam-marks-form">

                <input
                    type="hidden"
                    name="add_marks"
                    value="1"
                >


                <div class="exam-marks-form-group">

                    <label for="student_id">
                        Student *
                    </label>

                    <select
                        name="student_id"
                        id="student_id"
                        required>

                        <option value="">
                            Select Student
                        </option>

                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?php echo (int)$student["student_id"]; ?>">

                                <?php
                                echo htmlspecialchars(
                                    $student["roll_number"]
                                    . " - "
                                    . $student["full_name"]
                                    . " ("
                                    . $student["department"]
                                    . ")"
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="exam-marks-form-group">

                    <label for="subject_id">
                        Subject *
                    </label>

                    <select
                        name="subject_id"
                        id="subject_id"
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
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="exam-marks-form-group">

                    <label for="exam_id">
                        Examination *
                    </label>

                    <select
                        name="exam_id"
                        id="exam_id"
                        required>

                        <option value="">
                            Select Examination
                        </option>

                        <?php foreach ($exam_options as $exam): ?>

                            <option
                                value="<?php echo (int)$exam["exam_id"]; ?>"
                                data-subject="<?php echo (int)$exam["subject_id"]; ?>"
                                data-max="<?php echo htmlspecialchars($exam["max_marks"]); ?>">

                                <?php
                                echo htmlspecialchars(
                                    $exam["subject_code"]
                                    . " - "
                                    . $exam["exam_name"]
                                    . " - "
                                    . $exam["exam_date"]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="exam-marks-form-group">

                    <label for="max_marks_display">
                        Maximum Marks
                    </label>

                    <input
                        type="text"
                        id="max_marks_display"
                        value="-"
                        readonly
                    >

                </div>


                <div class="exam-marks-form-group">

                    <label for="marks_obtained">
                        Marks Obtained *
                    </label>

                    <input
                        type="number"
                        name="marks_obtained"
                        id="marks_obtained"
                        min="0"
                        step="0.01"
                        placeholder="Enter marks"
                        required
                    >

                </div>


                <div class="exam-marks-form-group">

                    <label>
                        Grade Preview
                    </label>

                    <div
                        id="gradePreview"
                        class="exam-grade-preview">

                        -

                    </div>

                </div>


                <div class="exam-marks-form-actions">

                    <button
                        type="submit"
                        class="exam-marks-submit-btn">

                        📊 Save Marks

                    </button>

                </div>

            </form>

        </section>


        <!-- MARKS TABLE -->

        <section class="exam-marks-list-section">

            <div class="exam-marks-section-header">

                <div>

                    <h2>Student Results</h2>

                    <p>
                        All examination marks entered in the system
                    </p>

                </div>

            </div>


            <?php if (count($marks_list) > 0): ?>

                <div class="exam-marks-table-wrapper">

                    <table class="exam-marks-table">

                        <thead>

                            <tr>

                                <th>Student</th>
                                <th>Subject</th>
                                <th>Exam</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($marks_list as $mark): ?>

                                <?php

                                $max_marks =
                                    (float)$mark["max_marks"];

                                $obtained =
                                    (float)$mark["marks_obtained"];

                                $percentage =
                                    $max_marks > 0
                                    ? ($obtained / $max_marks) * 100
                                    : 0;

                                $grade =
                                    strtoupper(
                                        trim($mark["grade"])
                                    );

                                $grade_class =
                                    $percentage >= 40
                                    ? "pass"
                                    : "fail";

                                ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $mark["roll_number"]
                                            );
                                            ?>
                                        </strong>

                                        <small>
                                            <?php
                                            echo htmlspecialchars(
                                                $mark["full_name"]
                                            );
                                            ?>
                                        </small>

                                    </td>


                                    <td>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $mark["subject_code"]
                                            );
                                            ?>
                                        </strong>

                                        <small>
                                            <?php
                                            echo htmlspecialchars(
                                                $mark["subject_name"]
                                            );
                                            ?>
                                        </small>

                                    </td>


                                    <td>

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $mark["exam_name"]
                                            );
                                            ?>
                                        </strong>

                                        <small>
                                            <?php
                                            echo htmlspecialchars(
                                                $mark["exam_type"]
                                            );
                                            ?>
                                        </small>

                                    </td>


                                    <td>

                                        <?php
                                        echo rtrim(
                                            rtrim(
                                                number_format(
                                                    $obtained,
                                                    2,
                                                    ".",
                                                    ""
                                                ),
                                                "0"
                                            ),
                                            "."
                                        );
                                        ?>

                                        /

                                        <?php
                                        echo rtrim(
                                            rtrim(
                                                number_format(
                                                    $max_marks,
                                                    2,
                                                    ".",
                                                    ""
                                                ),
                                                "0"
                                            ),
                                            "."
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo number_format(
                                            $percentage,
                                            1
                                        );
                                        ?>%

                                    </td>


                                    <td>

                                        <span
                                            class="exam-grade-badge <?php echo $grade_class; ?>">

                                            <?php
                                            echo htmlspecialchars(
                                                $grade
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <form
                                            method="POST"
                                            action="marks.php"
                                            onsubmit="return confirm('Delete this result?');">

                                            <input
                                                type="hidden"
                                                name="delete_marks"
                                                value="1"
                                            >

                                            <input
                                                type="hidden"
                                                name="mark_id"
                                                value="<?php
                                                echo (int)$mark["mark_id"];
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="exam-marks-delete-btn">

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

                <div class="exam-marks-empty">

                    <div>📊</div>

                    <h3>
                        No Results Available
                    </h3>

                    <p>
                        Enter student marks using the form above.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>

/* Theme */

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


/* Exam filtering */

const subjectSelect =
    document.getElementById("subject_id");

const examSelect =
    document.getElementById("exam_id");

const maxMarksDisplay =
    document.getElementById("max_marks_display");

const marksInput =
    document.getElementById("marks_obtained");

const gradePreview =
    document.getElementById("gradePreview");


function filterExams() {

    const selectedSubject =
        subjectSelect.value;

    const options =
        examSelect.querySelectorAll("option");

    examSelect.value = "";

    maxMarksDisplay.value = "-";
    marksInput.max = "";
    gradePreview.textContent = "-";

    options.forEach(function(option) {

        if (option.value === "") {
            option.hidden = false;
            return;
        }

        const optionSubject =
            option.getAttribute("data-subject");

        option.hidden =
            selectedSubject !== "" &&
            optionSubject !== selectedSubject;

    });
}


subjectSelect.addEventListener(
    "change",
    filterExams
);


/* Maximum marks */

examSelect.addEventListener(
    "change",
    function () {

        const selected =
            examSelect.options[
                examSelect.selectedIndex
            ];

        const max =
            selected.getAttribute("data-max");

        if (max) {

            maxMarksDisplay.value = max;

            marksInput.max = max;

        } else {

            maxMarksDisplay.value = "-";

            marksInput.max = "";

        }

        calculateGrade();

    }
);


/* Grade preview */

marksInput.addEventListener(
    "input",
    calculateGrade
);


function calculateGrade() {

    const marks =
        parseFloat(marksInput.value);

    const max =
        parseFloat(marksInput.max);

    if (
        isNaN(marks) ||
        isNaN(max) ||
        max <= 0 ||
        marks < 0 ||
        marks > max
    ) {

        gradePreview.textContent = "-";

        return;
    }

    const percentage =
        (marks / max) * 100;

    let grade = "F";

    if (percentage >= 90) {
        grade = "A+";
    } else if (percentage >= 80) {
        grade = "A";
    } else if (percentage >= 70) {
        grade = "B+";
    } else if (percentage >= 60) {
        grade = "B";
    } else if (percentage >= 50) {
        grade = "C";
    } else if (percentage >= 40) {
        grade = "D";
    }

    gradePreview.textContent =
        grade + " (" + percentage.toFixed(1) + "%)";
}

</script>

</body>
</html>