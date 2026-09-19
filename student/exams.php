<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "STUDENT") {
    header("Location: ../auth/student-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];


/* ================= STUDENT DETAILS ================= */

$stmt = $conn->prepare("
    SELECT
        student_id,
        full_name,
        department,
        year,
        section
    FROM students
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student profile not found.");
}

$student_id = $student["student_id"];
$department = $student["department"];
$year = $student["year"];
$section = $student["section"];


/* ================= EXAM DETAILS ================= */

$stmt = $conn->prepare("
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
        s.subject_name
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    WHERE s.department = ?
      AND s.year = ?
    ORDER BY e.exam_date ASC, e.start_time ASC
");

$stmt->bind_param(
    "si",
    $department,
    $year
);

$stmt->execute();

$exams_result = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Exams | SmartCampus</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body>


<div class="dashboard">


    <!-- ================= SIDEBAR ================= -->

    <aside class="sidebar">


        <div class="sidebar-brand">

            <div class="sidebar-logo">
                SC
            </div>

            <div>

                <h2>
                    SmartCampus
                </h2>

                <span>
                    Student Portal
                </span>

            </div>

        </div>


        <div class="sidebar-menu">


            <a href="dashboard.php">

                <span>🏠</span>

                Dashboard

            </a>


            <a href="profile.php">

                <span>👤</span>

                My Profile

            </a>


            <a href="attendance.php">

                <span>📊</span>

                Attendance

            </a>


            <a href="timetable.php">

                <span>🕘</span>

                Timetable

            </a>


            <a href="materials.php">

                <span>📚</span>

                Study Materials

            </a>


            <a
                href="exams.php"
                class="active"
            >

                <span>📝</span>

                Exams

            </a>


            <a href="#">

                <span>📈</span>

                Results

            </a>


            <a href="#">

                <span>🎫</span>

                Hall Ticket

            </a>


            <a href="#">

                <span>💰</span>

                Fees & Payments

            </a>


            <a href="#">

                <span>💻</span>

                Projects

            </a>


            <a href="#">

                <span>🎯</span>

                Clubs & Activities

            </a>


            <a href="#">

                <span>🔔</span>

                Notifications

            </a>


        </div>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">

                <span>🚪</span>

                Logout

            </a>

        </div>


    </aside>


    <!-- ================= MAIN CONTENT ================= -->

    <main class="dashboard-content">


        <!-- HEADER -->

        <div class="dashboard-header">


            <div class="welcome-text">

                <p class="student-meta">
                    Academic Schedule
                </p>

                <h1>
                    Examinations
                </h1>

                <p class="student-meta">
                    View your upcoming and completed examinations
                </p>

            </div>


            <button
                id="themeToggle"
                class="dashboard-theme-toggle"
                type="button"
            >
                🌙
            </button>


        </div>


        <!-- ================= EXAMS PAGE ================= -->

        <div class="exams-page">


            <div class="exams-intro">

                <span class="exams-label">
                    EXAMINATION PORTAL
                </span>

                <h2>
                    📝 Exam Schedule
                </h2>

                <p>
                    View your examination dates, timings,
                    rooms and maximum marks.
                </p>

            </div>


            <?php if ($exams_result->num_rows > 0): ?>


                <div class="exams-summary">

                    <div class="exam-summary-card">

                        <span class="exam-summary-icon">
                            📝
                        </span>

                        <div>

                            <strong>
                                <?php
                                echo $exams_result->num_rows;
                                ?>
                            </strong>

                            <small>
                                Total Exams
                            </small>

                        </div>

                    </div>


                    <div class="exam-summary-card">

                        <span class="exam-summary-icon">
                            🎓
                        </span>

                        <div>

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $department
                                );
                                ?>
                            </strong>

                            <small>
                                Department
                            </small>

                        </div>

                    </div>


                    <div class="exam-summary-card">

                        <span class="exam-summary-icon">
                            📚
                        </span>

                        <div>

                            <strong>
                                Year
                                <?php
                                echo htmlspecialchars(
                                    $year
                                );
                                ?>
                            </strong>

                            <small>
                                Academic Year
                            </small>

                        </div>

                    </div>


                </div>


                <!-- ================= EXAM LIST ================= -->

                <div class="exams-list">


                    <?php while (
                        $exam =
                        $exams_result->fetch_assoc()
                    ): ?>


                        <?php

                        $examStatus =
                            strtoupper(
                                $exam["status"]
                            );

                        ?>


                        <div class="exam-card">


                            <!-- DATE -->

                            <div class="exam-date-box">

                                <span class="exam-date-day">

                                    <?php
                                    echo date(
                                        "d",
                                        strtotime(
                                            $exam["exam_date"]
                                        )
                                    );
                                    ?>

                                </span>

                                <span class="exam-date-month">

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


                            <!-- EXAM DETAILS -->

                            <div class="exam-main">


                                <div class="exam-top-row">

                                    <span class="exam-subject-code">

                                        <?php
                                        echo htmlspecialchars(
                                            $exam["subject_code"]
                                        );
                                        ?>

                                    </span>


                                    <?php if (
                                        $examStatus === "UPCOMING"
                                    ): ?>

                                        <span class="exam-status upcoming">
                                            Upcoming
                                        </span>

                                    <?php elseif (
                                        $examStatus === "COMPLETED"
                                    ): ?>

                                        <span class="exam-status completed">
                                            Completed
                                        </span>

                                    <?php else: ?>

                                        <span class="exam-status">
                                            <?php
                                            echo htmlspecialchars(
                                                $exam["status"]
                                            );
                                            ?>
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <h3>

                                    <?php
                                    echo htmlspecialchars(
                                        $exam["subject_name"]
                                    );
                                    ?>

                                </h3>


                                <p class="exam-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $exam["exam_name"]
                                    );
                                    ?>

                                </p>


                                <div class="exam-details-grid">


                                    <div class="exam-detail">

                                        <span>📋</span>

                                        <div>

                                            <small>
                                                Exam Type
                                            </small>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $exam["exam_type"]
                                                );
                                                ?>

                                            </strong>

                                        </div>

                                    </div>


                                    <div class="exam-detail">

                                        <span>⏰</span>

                                        <div>

                                            <small>
                                                Time
                                            </small>

                                            <strong>

                                                <?php
                                                echo date(
                                                    "h:i A",
                                                    strtotime(
                                                        $exam["start_time"]
                                                    )
                                                );

                                                echo " - ";

                                                echo date(
                                                    "h:i A",
                                                    strtotime(
                                                        $exam["end_time"]
                                                    )
                                                );
                                                ?>

                                            </strong>

                                        </div>

                                    </div>


                                    <div class="exam-detail">

                                        <span>🏫</span>

                                        <div>

                                            <small>
                                                Room
                                            </small>

                                            <strong>

                                                <?php

                                                if (
                                                    !empty(
                                                        $exam["room_number"]
                                                    )
                                                ) {

                                                    echo htmlspecialchars(
                                                        $exam["room_number"]
                                                    );

                                                } else {

                                                    echo "Not Assigned";

                                                }

                                                ?>

                                            </strong>

                                        </div>

                                    </div>


                                    <div class="exam-detail">

                                        <span>🎯</span>

                                        <div>

                                            <small>
                                                Maximum Marks
                                            </small>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $exam["max_marks"]
                                                );
                                                ?>

                                            </strong>

                                        </div>

                                    </div>


                                </div>


                            </div>


                        </div>


                    <?php endwhile; ?>


                </div>


            <?php else: ?>


                <!-- ================= EMPTY STATE ================= -->

                <div class="dashboard-panel exams-empty">


                    <div class="exams-empty-icon">
                        📝
                    </div>


                    <h3>
                        No Examinations Scheduled
                    </h3>


                    <p>
                        No examination details are currently
                        available for your department and year.
                    </p>


                </div>


            <?php endif; ?>


        </div>


    </main>


</div>


<!-- ================= THEME SCRIPT ================= -->

<script>

const themeToggle =
    document.getElementById("themeToggle");


function updateThemeIcon() {

    if (
        document.body.classList.contains(
            "dark-theme"
        )
    ) {

        themeToggle.textContent = "☀️";

    } else {

        themeToggle.textContent = "🌙";

    }

}


/* Load saved theme */

if (
    localStorage.getItem(
        "smartcampus-theme"
    ) === "dark"
) {

    document.body.classList.add(
        "dark-theme"
    );

}


updateThemeIcon();


/* Toggle theme */

themeToggle.addEventListener(
    "click",
    function () {

        document.body.classList.toggle(
            "dark-theme"
        );


        if (
            document.body.classList.contains(
                "dark-theme"
            )
        ) {

            localStorage.setItem(
                "smartcampus-theme",
                "dark"
            );

        } else {

            localStorage.setItem(
                "smartcampus-theme",
                "light"
            );

        }


        updateThemeIcon();

    }
);

</script>


</body>

</html>