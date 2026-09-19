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
$message = "";
$message_type = "";

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

$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$faculty) {
    die("Faculty profile not found.");
}

$faculty_id = $faculty["faculty_id"];
$department = $faculty["department"];

/* =========================
   HELPER - GRADE
========================= */

function calculateGrade($percentage)
{
    if ($percentage >= 90) {
        return "A+";
    } elseif ($percentage >= 80) {
        return "A";
    } elseif ($percentage >= 70) {
        return "B+";
    } elseif ($percentage >= 60) {
        return "B";
    } elseif ($percentage >= 50) {
        return "C";
    } elseif ($percentage >= 40) {
        return "D";
    } else {
        return "F";
    }
}

/* =========================
   ADD RESULT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_result"])) {

    $subject_id = intval($_POST["subject_id"] ?? 0);
    $exam_id = intval($_POST["exam_id"] ?? 0);
    $student_id = intval($_POST["student_id"] ?? 0);
    $marks_obtained = floatval($_POST["marks_obtained"] ?? -1);
    $max_marks = floatval($_POST["max_marks"] ?? 0);

    if (
        $subject_id <= 0 ||
        $exam_id <= 0 ||
        $student_id <= 0 ||
        $marks_obtained < 0 ||
        $max_marks <= 0
    ) {
        $message = "Please fill all fields correctly.";
        $message_type = "error";
    } elseif ($marks_obtained > $max_marks) {
        $message = "Marks obtained cannot be greater than maximum marks.";
        $message_type = "error";
    } else {

        /* Verify subject is assigned to faculty */
        $stmt = $conn->prepare("
            SELECT subject_id
            FROM timetable
            WHERE faculty_id = ?
              AND subject_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("ii", $faculty_id, $subject_id);
        $stmt->execute();

        $subject_check = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        /* Verify exam belongs to selected subject */
        $stmt = $conn->prepare("
            SELECT exam_id, max_marks
            FROM exam_details
            WHERE exam_id = ?
              AND subject_id = ?
        ");

        $stmt->bind_param("ii", $exam_id, $subject_id);
        $stmt->execute();

        $exam_check = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        /* Verify student belongs to faculty department */
        $stmt = $conn->prepare("
            SELECT student_id
            FROM students
            WHERE student_id = ?
              AND department = ?
        ");

        $stmt->bind_param("is", $student_id, $department);
        $stmt->execute();

        $student_check = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$subject_check) {

            $message = "You are not assigned to this subject.";
            $message_type = "error";

        } elseif (!$exam_check) {

            $message = "Invalid exam selected for this subject.";
            $message_type = "error";

        } elseif (!$student_check) {

            $message = "Invalid student selected.";
            $message_type = "error";

        } else {

            /*
             * Use exam's max marks as official maximum.
             * If exam max marks is available, use that.
             */
            $official_max_marks = floatval($exam_check["max_marks"]);

            if ($official_max_marks > 0) {
                $max_marks = $official_max_marks;
            }

            if ($marks_obtained > $max_marks) {

                $message = "Marks cannot exceed exam maximum marks.";
                $message_type = "error";

            } else {

                /* Check duplicate result */
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

                $duplicate = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($duplicate) {

                    $message = "Result already exists for this student and exam.";
                    $message_type = "error";

                } else {

                    $percentage = ($marks_obtained / $max_marks) * 100;
                    $grade = calculateGrade($percentage);

                    $stmt = $conn->prepare("
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

                    $stmt->bind_param(
                        "iiidds",
                        $student_id,
                        $subject_id,
                        $exam_id,
                        $marks_obtained,
                        $max_marks,
                        $grade
                    );

                    if ($stmt->execute()) {
                        $message = "Result added successfully.";
                        $message_type = "success";
                    } else {
                        $message = "Failed to add result.";
                        $message_type = "error";
                    }

                    $stmt->close();
                }
            }
        }
    }
}

/* =========================
   DELETE RESULT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_result"])) {

    $mark_id = intval($_POST["mark_id"] ?? 0);

    if ($mark_id > 0) {

        /*
         * Delete only if the result belongs
         * to a subject assigned to this faculty.
         */
        $stmt = $conn->prepare("
            DELETE FROM marks
            WHERE mark_id = ?
              AND EXISTS (
                  SELECT 1
                  FROM timetable t
                  WHERE t.subject_id = marks.subject_id
                    AND t.faculty_id = ?
              )
        ");

        $stmt->bind_param("ii", $mark_id, $faculty_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Result deleted successfully.";
            $message_type = "success";
        } else {
            $message = "Unable to delete result.";
            $message_type = "error";
        }

        $stmt->close();
    }
}

/* =========================
   SUBJECTS ASSIGNED TO FACULTY
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
    ORDER BY s.year, s.subject_code
");

$stmt->bind_param("is", $faculty_id, $department);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

$stmt->close();

/* =========================
   EXAMS FOR FACULTY SUBJECTS
========================= */

$exams = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        e.exam_id,
        e.subject_id,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_number,
        e.max_marks
    FROM exam_details e
    INNER JOIN timetable t
        ON e.subject_id = t.subject_id
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    WHERE t.faculty_id = ?
      AND s.department = ?
    ORDER BY e.exam_date DESC, e.start_time DESC
");

$stmt->bind_param("is", $faculty_id, $department);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $exams[] = $row;
}

$stmt->close();

/* =========================
   STUDENTS
========================= */

$students = [];

$stmt = $conn->prepare("
    SELECT
        student_id,
        roll_number,
        full_name,
        year,
        section
    FROM students
    WHERE department = ?
    ORDER BY year, section, roll_number
");

$stmt->bind_param("s", $department);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();

/* =========================
   RESULTS LIST
========================= */

$results = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        m.mark_id,
        m.marks_obtained,
        m.max_marks,
        m.grade,
        s.subject_code,
        s.subject_name,
        e.exam_name,
        e.exam_type,
        e.exam_date,
        st.roll_number,
        st.full_name,
        st.year,
        st.section
    FROM marks m
    INNER JOIN subjects s
        ON m.subject_id = s.subject_id
    INNER JOIN students st
        ON m.student_id = st.student_id
    LEFT JOIN exam_details e
        ON m.exam_id = e.exam_id
    INNER JOIN timetable t
        ON m.subject_id = t.subject_id
    WHERE t.faculty_id = ?
      AND s.department = ?
    ORDER BY e.exam_date DESC, st.roll_number ASC
");

$stmt->bind_param("is", $faculty_id, $department);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $results[] = $row;
}

$stmt->close();

/* =========================
   SUMMARY
========================= */

$total_results = count($results);

$passed_results = 0;
$failed_results = 0;
$total_percentage = 0;

foreach ($results as $r) {

    $max = floatval($r["max_marks"]);
    $obtained = floatval($r["marks_obtained"]);

    $percentage = $max > 0
        ? ($obtained / $max) * 100
        : 0;

    $total_percentage += $percentage;

    if ($percentage >= 40) {
        $passed_results++;
    } else {
        $failed_results++;
    }
}

$average_percentage = $total_results > 0
    ? $total_percentage / $total_results
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Results | SmartCampus</title>

    <link rel="stylesheet" href="../assets/css/style.css">
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

            <a href="exams.php">
                <span>📝</span>
                Exams
            </a>

            <a href="results.php" class="active">
                <span>📈</span>
                Results
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

                <span class="dashboard-label">
                    Faculty Portal
                </span>

                <h1>Results Management</h1>

                <p>
                    Enter and manage student examination results.
                </p>

            </div>

            <div class="dashboard-header-right">

                <div class="student-meta">

                    <div class="student-avatar">
                        <?php echo strtoupper(substr($faculty["full_name"], 0, 1)); ?>
                    </div>

                    <div>
                        <strong>
                            <?php echo htmlspecialchars($faculty["full_name"]); ?>
                        </strong>

                        <span>
                            <?php echo htmlspecialchars($faculty["designation"]); ?>
                        </span>
                    </div>

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

        </header>


        <!-- MESSAGE -->

        <?php if ($message): ?>

            <div class="faculty-result-message <?php echo $message_type; ?>">

                <span>
                    <?php echo $message_type === "success" ? "✓" : "⚠"; ?>
                </span>

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <section class="faculty-result-summary">

            <div class="faculty-result-summary-card">

                <div class="faculty-result-summary-icon">
                    📊
                </div>

                <div>
                    <span>Total Results</span>
                    <strong><?php echo $total_results; ?></strong>
                </div>

            </div>


            <div class="faculty-result-summary-card">

                <div class="faculty-result-summary-icon">
                    ✓
                </div>

                <div>
                    <span>Passed</span>
                    <strong><?php echo $passed_results; ?></strong>
                </div>

            </div>


            <div class="faculty-result-summary-card">

                <div class="faculty-result-summary-icon">
                    ⚠
                </div>

                <div>
                    <span>Failed</span>
                    <strong><?php echo $failed_results; ?></strong>
                </div>

            </div>


            <div class="faculty-result-summary-card">

                <div class="faculty-result-summary-icon">
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


        <!-- ADD RESULT -->

        <section class="faculty-result-form-section">

            <div class="faculty-result-section-header">

                <div>
                    <span class="faculty-result-section-label">
                        Result Entry
                    </span>

                    <h2>Add Student Result</h2>

                    <p>
                        Select the subject, examination and student,
                        then enter the obtained marks.
                    </p>
                </div>

            </div>


            <form method="POST" class="faculty-result-form">

                <div class="faculty-result-field">

                    <label for="subject_id">
                        Subject
                    </label>

                    <select
                        name="subject_id"
                        id="subject_id"
                        required
                    >

                        <option value="">
                            Select Subject
                        </option>

                        <?php foreach ($subjects as $subject): ?>

                            <option value="<?php echo $subject["subject_id"]; ?>">

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


                <div class="faculty-result-field">

                    <label for="exam_id">
                        Examination
                    </label>

                    <select
                        name="exam_id"
                        id="exam_id"
                        required
                    >

                        <option value="">
                            Select Examination
                        </option>

                        <?php foreach ($exams as $exam): ?>

                            <option
                                value="<?php echo $exam["exam_id"]; ?>"
                                data-subject="<?php echo $exam["subject_id"]; ?>"
                                data-max="<?php echo $exam["max_marks"]; ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $exam["exam_name"]
                                    . " - "
                                    . $exam["exam_type"]
                                    . " ("
                                    . date("d M Y", strtotime($exam["exam_date"]))
                                    . ")"
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="faculty-result-field">

                    <label for="student_id">
                        Student
                    </label>

                    <select
                        name="student_id"
                        id="student_id"
                        required
                    >

                        <option value="">
                            Select Student
                        </option>

                        <?php foreach ($students as $student): ?>

                            <option value="<?php echo $student["student_id"]; ?>">

                                <?php
                                echo htmlspecialchars(
                                    $student["roll_number"]
                                    . " - "
                                    . $student["full_name"]
                                    . " | Year "
                                    . $student["year"]
                                    . " - "
                                    . $student["section"]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="faculty-result-field">

                    <label for="max_marks">
                        Maximum Marks
                    </label>

                    <input
                        type="number"
                        name="max_marks"
                        id="max_marks"
                        step="0.01"
                        min="1"
                        placeholder="Maximum marks"
                        required
                    >

                </div>


                <div class="faculty-result-field">

                    <label for="marks_obtained">
                        Marks Obtained
                    </label>

                    <input
                        type="number"
                        name="marks_obtained"
                        id="marks_obtained"
                        step="0.01"
                        min="0"
                        placeholder="Enter marks"
                        required
                    >

                </div>


                <div class="faculty-result-field">

                    <label>
                        Grade
                    </label>

                    <div
                        class="faculty-result-grade-preview"
                        id="gradePreview"
                    >
                        -
                    </div>

                </div>


                <div class="faculty-result-form-actions">

                    <button
                        type="submit"
                        name="add_result"
                        class="faculty-result-add-btn"
                    >
                        + Add Result
                    </button>

                </div>

            </form>

        </section>


        <!-- RESULTS TABLE -->

        <section class="faculty-result-list-section">

            <div class="faculty-result-section-header">

                <div>

                    <span class="faculty-result-section-label">
                        Result Records
                    </span>

                    <h2>Student Results</h2>

                    <p>
                        View results entered for your assigned subjects.
                    </p>

                </div>

            </div>


            <?php if (!empty($results)): ?>

                <div class="faculty-result-table-wrapper">

                    <table class="faculty-result-table">

                        <thead>

                            <tr>

                                <th>Student</th>
                                <th>Subject</th>
                                <th>Exam</th>
                                <th>Date</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($results as $row): ?>

                                <?php

                                $max = floatval($row["max_marks"]);
                                $obtained = floatval($row["marks_obtained"]);

                                $percentage = $max > 0
                                    ? ($obtained / $max) * 100
                                    : 0;

                                $percentage = min(100, max(0, $percentage));

                                $grade_class = "";

                                if ($percentage >= 80) {
                                    $grade_class = "excellent";
                                } elseif ($percentage >= 60) {
                                    $grade_class = "good";
                                } elseif ($percentage >= 40) {
                                    $grade_class = "average";
                                } else {
                                    $grade_class = "poor";
                                }

                                ?>

                                <tr>

                                    <td>

                                        <div class="faculty-result-student">

                                            <div class="faculty-result-avatar">
                                                <?php
                                                echo strtoupper(
                                                    substr($row["full_name"], 0, 1)
                                                );
                                                ?>
                                            </div>

                                            <div>

                                                <strong>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $row["full_name"]
                                                    );
                                                    ?>
                                                </strong>

                                                <span>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $row["roll_number"]
                                                    );
                                                    ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <div class="faculty-result-subject">

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $row["subject_code"]
                                                );
                                                ?>
                                            </strong>

                                            <span>
                                                <?php
                                                echo htmlspecialchars(
                                                    $row["subject_name"]
                                                );
                                                ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>

                                        <div class="faculty-result-exam">

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $row["exam_name"]
                                                );
                                                ?>
                                            </strong>

                                            <span>
                                                <?php
                                                echo htmlspecialchars(
                                                    $row["exam_type"]
                                                );
                                                ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>
                                        <?php
                                        echo $row["exam_date"]
                                            ? date(
                                                "d M Y",
                                                strtotime($row["exam_date"])
                                            )
                                            : "-";
                                        ?>
                                    </td>


                                    <td>

                                        <strong class="faculty-result-marks">
                                            <?php
                                            echo htmlspecialchars(
                                                $row["marks_obtained"]
                                            );
                                            ?>
                                            /
                                            <?php
                                            echo htmlspecialchars(
                                                $row["max_marks"]
                                            );
                                            ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <div class="faculty-result-percentage">

                                            <strong>
                                                <?php
                                                echo number_format(
                                                    $percentage,
                                                    1
                                                );
                                                ?>%
                                            </strong>

                                            <div class="faculty-result-progress">

                                                <span
                                                    style="width: <?php echo $percentage; ?>%;"
                                                ></span>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <span
                                            class="faculty-result-grade <?php echo $grade_class; ?>"
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $row["grade"]
                                            );
                                            ?>
                                        </span>

                                    </td>


                                    <td>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Delete this result?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="mark_id"
                                                value="<?php echo $row["mark_id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_result"
                                                class="faculty-result-delete-btn"
                                            >
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

                <div class="faculty-result-empty">

                    <div class="faculty-result-empty-icon">
                        📊
                    </div>

                    <h3>No Results Available</h3>

                    <p>
                        No student results have been entered yet.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>

/* =========================
   THEME
========================= */

const themeToggle = document.getElementById("themeToggle");

const savedTheme = localStorage.getItem("smartcampus-theme");

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


/* =========================
   EXAM FILTER BY SUBJECT
========================= */

const subjectSelect =
    document.getElementById("subject_id");

const examSelect =
    document.getElementById("exam_id");

const maxMarksInput =
    document.getElementById("max_marks");

if (subjectSelect && examSelect) {

    subjectSelect.addEventListener("change", function () {

        const selectedSubject = this.value;

        const options =
            examSelect.querySelectorAll("option");

        examSelect.value = "";

        maxMarksInput.value = "";

        options.forEach(function (option) {

            if (!option.value) {
                option.style.display = "";
                return;
            }

            const optionSubject =
                option.getAttribute("data-subject");

            option.style.display =
                optionSubject === selectedSubject
                    ? ""
                    : "none";

        });

    });


    examSelect.addEventListener("change", function () {

        const selectedOption =
            this.options[this.selectedIndex];

        if (selectedOption) {

            const max =
                selectedOption.getAttribute("data-max");

            if (max) {
                maxMarksInput.value = max;
            }

        }

        updateGrade();

    });

}


/* =========================
   GRADE PREVIEW
========================= */

const marksInput =
    document.getElementById("marks_obtained");

const gradePreview =
    document.getElementById("gradePreview");

function updateGrade() {

    const marks =
        parseFloat(marksInput.value);

    const max =
        parseFloat(maxMarksInput.value);

    if (
        isNaN(marks) ||
        isNaN(max) ||
        max <= 0 ||
        marks < 0
    ) {

        gradePreview.textContent = "-";
        gradePreview.className =
            "faculty-result-grade-preview";

        return;
    }

    const percentage =
        (marks / max) * 100;

    let grade = "";

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
    } else {
        grade = "F";
    }

    gradePreview.textContent =
        grade + " (" + percentage.toFixed(1) + "%)";

    gradePreview.className =
        "faculty-result-grade-preview";

    if (percentage >= 80) {
        gradePreview.classList.add("excellent");
    } else if (percentage >= 60) {
        gradePreview.classList.add("good");
    } else if (percentage >= 40) {
        gradePreview.classList.add("average");
    } else {
        gradePreview.classList.add("poor");
    }

}


if (marksInput) {
    marksInput.addEventListener("input", updateGrade);
}

if (maxMarksInput) {
    maxMarksInput.addEventListener("input", updateGrade);
}

</script>

</body>
</html>