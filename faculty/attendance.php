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
   FILTER VALUES
========================= */
$subject_id = isset($_GET["subject_id"]) ? (int)$_GET["subject_id"] : 0;
$year = isset($_GET["year"]) ? (int)$_GET["year"] : 0;
$section = isset($_GET["section"]) ? trim($_GET["section"]) : "";


/* =========================
   FACULTY SUBJECTS
========================= */
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
    ORDER BY s.year ASC, s.subject_code ASC
");

$stmt->bind_param("is", $faculty_id, $department);
$stmt->execute();

$subjects = $stmt->get_result();


/* =========================
   ATTENDANCE DATA
========================= */
$attendance_rows = [];

$total_students = 0;
$total_present = 0;
$total_absent = 0;

if ($subject_id > 0) {

    $query = "
        SELECT
            st.student_id,
            st.roll_number,
            st.full_name,
            st.year,
            st.section,

            COUNT(a.attendance_id) AS total_classes,

            COALESCE(
                SUM(
                    CASE
                        WHEN UPPER(a.status) = 'PRESENT'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS present_classes,

            COALESCE(
                SUM(
                    CASE
                        WHEN UPPER(a.status) = 'ABSENT'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS absent_classes

        FROM students st

        LEFT JOIN attendance a
            ON st.student_id = a.student_id
            AND a.subject_id = ?
            AND a.faculty_id = ?

        WHERE st.department = ?
    ";

    /* YEAR FILTER */
    if ($year > 0) {
        $query .= " AND st.year = ?";
    }

    /* SECTION FILTER */
    if ($section !== "") {
        $query .= " AND st.section = ?";
    }

    $query .= "
        GROUP BY
            st.student_id,
            st.roll_number,
            st.full_name,
            st.year,
            st.section

        ORDER BY st.roll_number ASC
    ";


    /* =========================
       PREPARE QUERY
    ========================= */

    if ($year > 0 && $section !== "") {

        $stmt = $conn->prepare($query);

        $stmt->bind_param(
            "iisis",
            $subject_id,
            $faculty_id,
            $department,
            $year,
            $section
        );

    } elseif ($year > 0) {

        $stmt = $conn->prepare($query);

        $stmt->bind_param(
            "iisi",
            $subject_id,
            $faculty_id,
            $department,
            $year
        );

    } elseif ($section !== "") {

        $stmt = $conn->prepare($query);

        $stmt->bind_param(
            "iiss",
            $subject_id,
            $faculty_id,
            $department,
            $section
        );

    } else {

        $stmt = $conn->prepare($query);

        $stmt->bind_param(
            "iis",
            $subject_id,
            $faculty_id,
            $department
        );
    }


    $stmt->execute();

    $result = $stmt->get_result();


    /* =========================
       PROCESS ATTENDANCE
    ========================= */

    while ($row = $result->fetch_assoc()) {

        $row["total_classes"] = (int)$row["total_classes"];
        $row["present_classes"] = (int)$row["present_classes"];
        $row["absent_classes"] = (int)$row["absent_classes"];

        if ($row["total_classes"] > 0) {

            $row["percentage"] =
                ($row["present_classes"] / $row["total_classes"]) * 100;

        } else {

            $row["percentage"] = 0;
        }


        /* STATUS */

        if ($row["percentage"] >= 75) {

            $row["status_text"] = "Good";
            $row["status_class"] = "good";

        } elseif ($row["percentage"] >= 65) {

            $row["status_text"] = "Warning";
            $row["status_class"] = "warning";

        } else {

            $row["status_text"] = "Low";
            $row["status_class"] = "low";
        }


        $attendance_rows[] = $row;

        $total_students++;

        $total_present += $row["present_classes"];
        $total_absent += $row["absent_classes"];
    }
}


/* =========================
   OVERALL PERCENTAGE
========================= */

$total_classes = $total_present + $total_absent;

if ($total_classes > 0) {

    $overall_percentage =
        ($total_present / $total_classes) * 100;

} else {

    $overall_percentage = 0;
}


/* =========================
   SELECTED SUBJECT NAME
========================= */

$selected_subject_name = "";

if ($subject_id > 0) {

    $subject_stmt = $conn->prepare("
        SELECT subject_code, subject_name
        FROM subjects
        WHERE subject_id = ?
    ");

    $subject_stmt->bind_param("i", $subject_id);
    $subject_stmt->execute();

    $selected_subject = $subject_stmt
        ->get_result()
        ->fetch_assoc();

    if ($selected_subject) {

        $selected_subject_name =
            $selected_subject["subject_code"] .
            " - " .
            $selected_subject["subject_name"];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Faculty Attendance | SmartCampus</title>

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

                <h2>SmartCampus</h2>

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


            <a
                href="attendance.php"
                class="active"
            >

                <span>📊</span>

                Attendance

            </a>


            <a href="timetable.php">

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
                    Attendance
                </h1>

                <p>
                    View and monitor student attendance.
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
             FILTER CARD
        ========================== -->

        <section class="faculty-attendance-filter">

            <div class="faculty-attendance-filter-header">

                <div>

                    <h2>
                        Attendance Records
                    </h2>

                    <p>
                        Select subject, year and section.
                    </p>

                </div>

            </div>


            <form
                method="GET"
                class="faculty-attendance-form"
            >


                <!-- SUBJECT -->

                <div class="faculty-attendance-field">

                    <label>
                        Subject
                    </label>

                    <select
                        name="subject_id"
                        required
                    >

                        <option value="">
                            Select Subject
                        </option>


                        <?php while ($subject = $subjects->fetch_assoc()): ?>

                            <option
                                value="<?= $subject["subject_id"] ?>"
                                <?= ($subject_id == $subject["subject_id"]) ? "selected" : "" ?>
                            >

                                <?= htmlspecialchars($subject["subject_code"]) ?>

                                -

                                <?= htmlspecialchars($subject["subject_name"]) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>



                <!-- YEAR -->

                <div class="faculty-attendance-field">

                    <label>
                        Year
                    </label>

                    <select name="year">

                        <option value="">
                            All Years
                        </option>

                        <option
                            value="1"
                            <?= ($year == 1) ? "selected" : "" ?>
                        >
                            1st Year
                        </option>

                        <option
                            value="2"
                            <?= ($year == 2) ? "selected" : "" ?>
                        >
                            2nd Year
                        </option>

                        <option
                            value="3"
                            <?= ($year == 3) ? "selected" : "" ?>
                        >
                            3rd Year
                        </option>

                        <option
                            value="4"
                            <?= ($year == 4) ? "selected" : "" ?>
                        >
                            4th Year
                        </option>

                    </select>

                </div>



                <!-- SECTION -->

                <div class="faculty-attendance-field">

                    <label>
                        Section
                    </label>

                    <select name="section">

                        <option value="">
                            All Sections
                        </option>

                        <option
                            value="A"
                            <?= ($section === "A") ? "selected" : "" ?>
                        >
                            Section A
                        </option>

                        <option
                            value="B"
                            <?= ($section === "B") ? "selected" : "" ?>
                        >
                            Section B
                        </option>

                        <option
                            value="C"
                            <?= ($section === "C") ? "selected" : "" ?>
                        >
                            Section C
                        </option>

                    </select>

                </div>



                <!-- BUTTONS -->

                <div class="faculty-attendance-actions">

                    <button
                        type="submit"
                        class="faculty-attendance-btn"
                    >

                        View Attendance

                    </button>


                    <a
                        href="attendance.php"
                        class="faculty-attendance-clear"
                    >

                        Clear

                    </a>

                </div>

            </form>

        </section>



        <?php if ($subject_id > 0): ?>


            <!-- =========================
                 SELECTED SUBJECT
            ========================== -->

            <div class="faculty-attendance-selected">

                <span>
                    Selected Subject
                </span>

                <strong>
                    <?= htmlspecialchars($selected_subject_name) ?>
                </strong>

            </div>



            <!-- =========================
                 SUMMARY CARDS
            ========================== -->

            <section class="faculty-attendance-summary">


                <div class="faculty-attendance-summary-card">

                    <div class="faculty-attendance-summary-icon">
                        👨‍🎓
                    </div>

                    <div>

                        <span>
                            Students
                        </span>

                        <strong>
                            <?= $total_students ?>
                        </strong>

                    </div>

                </div>



                <div class="faculty-attendance-summary-card">

                    <div class="faculty-attendance-summary-icon">
                        📚
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



                <div class="faculty-attendance-summary-card">

                    <div class="faculty-attendance-summary-icon">
                        ✅
                    </div>

                    <div>

                        <span>
                            Present
                        </span>

                        <strong>
                            <?= $total_present ?>
                        </strong>

                    </div>

                </div>



                <div class="faculty-attendance-summary-card">

                    <div class="faculty-attendance-summary-icon">
                        📈
                    </div>

                    <div>

                        <span>
                            Overall
                        </span>

                        <strong>
                            <?= number_format($overall_percentage, 1) ?>%
                        </strong>

                    </div>

                </div>

            </section>



            <!-- =========================
                 TABLE
            ========================== -->

            <section class="faculty-attendance-table-section">


                <div class="faculty-attendance-table-header">

                    <div>

                        <h2>
                            Student Attendance
                        </h2>

                        <p>
                            <?= count($attendance_rows) ?>
                            student records found
                        </p>

                    </div>

                </div>



                <?php if (!empty($attendance_rows)): ?>


                    <div class="faculty-attendance-table-wrapper">

                        <table class="faculty-attendance-table">

                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Roll Number
                                    </th>

                                    <th>
                                        Student Name
                                    </th>

                                    <th>
                                        Year
                                    </th>

                                    <th>
                                        Section
                                    </th>

                                    <th>
                                        Classes
                                    </th>

                                    <th>
                                        Present
                                    </th>

                                    <th>
                                        Absent
                                    </th>

                                    <th>
                                        Attendance
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($attendance_rows as $index => $row): ?>


                                <tr>


                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <strong>
                                            <?= htmlspecialchars($row["roll_number"]) ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <div class="faculty-student-name">

                                            <div class="faculty-student-avatar">
                                                <?= strtoupper(substr($row["full_name"], 0, 1)) ?>
                                            </div>

                                            <span>
                                                <?= htmlspecialchars($row["full_name"]) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>
                                        <?= htmlspecialchars($row["year"]) ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars($row["section"]) ?>
                                    </td>


                                    <td>
                                        <?= $row["total_classes"] ?>
                                    </td>


                                    <td class="attendance-present">
                                        <?= $row["present_classes"] ?>
                                    </td>


                                    <td class="attendance-absent">
                                        <?= $row["absent_classes"] ?>
                                    </td>


                                    <td>

                                        <div class="attendance-percentage">

                                            <div class="attendance-progress">

                                                <span
                                                    style="width: <?= min(100, $row["percentage"]) ?>%;"
                                                ></span>

                                            </div>


                                            <strong>

                                                <?= number_format($row["percentage"], 1) ?>%

                                            </strong>

                                        </div>

                                    </td>


                                    <td>

                                        <span
                                            class="attendance-status <?= $row["status_class"] ?>"
                                        >

                                            <?= $row["status_text"] ?>

                                        </span>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <div class="faculty-attendance-empty">

                        <div class="faculty-attendance-empty-icon">
                            📊
                        </div>

                        <h3>
                            No Attendance Records
                        </h3>

                        <p>
                            No attendance data was found for the selected subject and filters.
                        </p>

                    </div>


                <?php endif; ?>

            </section>


        <?php else: ?>


            <!-- =========================
                 INITIAL STATE
            ========================== -->

            <section class="faculty-attendance-empty">

                <div class="faculty-attendance-empty-icon">
                    📊
                </div>

                <h3>
                    Select a Subject
                </h3>

                <p>
                    Select a subject above to view student attendance records.
                </p>

            </section>


        <?php endif; ?>


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