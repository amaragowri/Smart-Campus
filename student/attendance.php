<?php

session_start();
require_once "../config/database.php";

/* =========================
   AUTHENTICATION
   ========================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "STUDENT") {
    header("Location: ../auth/student-login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

/* =========================
   GET STUDENT
   ========================= */

$sql = "SELECT *
        FROM students
        WHERE user_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$student_id = $student["student_id"];

/* =========================
   SUBJECT-WISE ATTENDANCE
   ========================= */

$attendance_sql = "
    SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,

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

    FROM subjects s

    LEFT JOIN attendance a
        ON s.subject_id = a.subject_id
        AND a.student_id = ?

    WHERE s.department = ?
      AND s.year = ?

    GROUP BY
        s.subject_id,
        s.subject_code,
        s.subject_name

    ORDER BY s.subject_code
";

$stmt = $conn->prepare($attendance_sql);

$stmt->bind_param(
    "isi",
    $student_id,
    $student["department"],
    $student["year"]
);

$stmt->execute();

$attendance_result = $stmt->get_result();

/* =========================
   OVERALL ATTENDANCE
   ========================= */

$total_classes = 0;
$total_present = 0;
$total_absent = 0;

$attendance_rows = [];

while ($row = $attendance_result->fetch_assoc()) {

    $row_total = (int)$row["total_classes"];
    $row_present = (int)$row["present_classes"];
    $row_absent = (int)$row["absent_classes"];

    if ($row_total > 0) {

        $row_percentage =
            round(($row_present / $row_total) * 100, 1);

    } else {

        $row_percentage = 0;
    }

    if ($row_percentage >= 75) {

        $row_status = "Good";

    } elseif ($row_percentage >= 65) {

        $row_status = "Warning";

    } else {

        $row_status = "Low";
    }

    $row["percentage"] = $row_percentage;
    $row["status_label"] = $row_status;

    $attendance_rows[] = $row;

    $total_classes += $row_total;
    $total_present += $row_present;
    $total_absent += $row_absent;
}

$stmt->close();


if ($total_classes > 0) {

    $overall_percentage =
        round(($total_present / $total_classes) * 100, 1);

} else {

    $overall_percentage = 0;
}


/* =========================
   OVERALL STATUS
   ========================= */

if ($overall_percentage >= 75) {

    $overall_status = "Good";

} elseif ($overall_percentage >= 65) {

    $overall_status = "Warning";

} else {

    $overall_status = "Low";
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Attendance | SmartCampus</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">


    <style>

        /* =========================
           ATTENDANCE PAGE
           ========================= */

        .attendance-overview {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 18px;

            margin-bottom: 22px;
        }


        .attendance-overview-card {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 16px;

            padding: 22px;

            box-shadow:
                0 6px 20px rgba(15, 23, 42, 0.05);

        }


        .attendance-overview-card span {

            display: block;

            color: #64748b;

            font-size: 12px;

            margin-bottom: 7px;
        }


        .attendance-overview-card strong {

            display: block;

            color: #0f172a;

            font-size: 25px;

            font-weight: 800;
        }


        .attendance-overview-card small {

            display: block;

            margin-top: 5px;

            color: #94a3b8;

            font-size: 11px;
        }


        .attendance-good {

            color: #16a34a !important;
        }


        .attendance-warning {

            color: #d97706 !important;
        }


        .attendance-low {

            color: #dc2626 !important;
        }


        /* =========================
           ATTENDANCE TABLE
           ========================= */

        .attendance-table-wrapper {

            overflow-x: auto;
        }


        .attendance-table {

            width: 100%;

            border-collapse: collapse;

            min-width: 700px;
        }


        .attendance-table th {

            padding: 14px;

            text-align: left;

            background: #f8fafc;

            color: #64748b;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 0.5px;

            border-bottom: 1px solid #e2e8f0;
        }


        .attendance-table td {

            padding: 15px 14px;

            color: #334155;

            font-size: 13px;

            border-bottom: 1px solid #e2e8f0;
        }


        .attendance-table tbody tr {

            transition: 0.2s ease;
        }


        .attendance-table tbody tr:hover {

            background: #f8fafc;
        }


        .subject-name {

            font-weight: 700;

            color: #0f172a;
        }


        .subject-code {

            display: inline-block;

            margin-top: 3px;

            color: #64748b;

            font-size: 11px;
        }


        /* =========================
           PROGRESS BAR
           ========================= */

        .attendance-progress {

            width: 130px;

            height: 7px;

            background: #e2e8f0;

            border-radius: 20px;

            overflow: hidden;

            margin-bottom: 5px;
        }


        .attendance-progress-bar {

            height: 100%;

            border-radius: 20px;

            background: #2563eb;
        }


        .progress-good {

            background: #16a34a;
        }


        .progress-warning {

            background: #f59e0b;
        }


        .progress-low {

            background: #dc2626;
        }


        .attendance-percentage {

            font-weight: 800;

            color: #0f172a;

            font-size: 13px;
        }


        /* =========================
           STATUS BADGE
           ========================= */

        .attendance-status {

            display: inline-flex;

            align-items: center;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;
        }


        .status-good {

            background: #dcfce7;

            color: #166534;
        }


        .status-warning {

            background: #fef3c7;

            color: #92400e;
        }


        .status-low {

            background: #fee2e2;

            color: #991b1b;
        }


        /* =========================
           EMPTY STATE
           ========================= */

        .attendance-empty {

            text-align: center;

            padding: 55px 20px;

            color: #94a3b8;
        }


        .attendance-empty-icon {

            font-size: 42px;

            margin-bottom: 12px;
        }


        .attendance-empty h3 {

            color: #475569;

            font-size: 16px;

            margin-bottom: 5px;
        }


        .attendance-empty p {

            font-size: 12px;
        }


        /* =========================
           DARK THEME
           ========================= */

        body.dark-theme .attendance-overview-card {

            background: #0f172a;

            border-color: #1e293b;
        }


        body.dark-theme .attendance-overview-card strong {

            color: #f8fafc;
        }


        body.dark-theme .attendance-table th {

            background: #111c31;

            color: #94a3b8;

            border-color: #1e293b;
        }


        body.dark-theme .attendance-table td {

            color: #cbd5e1;

            border-color: #1e293b;
        }


        body.dark-theme .attendance-table tbody tr:hover {

            background: #111c31;
        }


        body.dark-theme .subject-name {

            color: #f1f5f9;
        }


        body.dark-theme .subject-code {

            color: #94a3b8;
        }


        body.dark-theme .attendance-percentage {

            color: #f8fafc;
        }


        body.dark-theme .attendance-progress {

            background: #334155;
        }


        body.dark-theme .attendance-empty h3 {

            color: #cbd5e1;
        }


        /* =========================
           RESPONSIVE
           ========================= */

        @media (max-width: 800px) {

            .attendance-overview {

                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =========================
         SIDEBAR
         ========================= -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
                SC
            </div>

            <div>

                <h2>SmartCampus</h2>

                <span>Student Portal</span>

            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php">
                🏠 Dashboard
            </a>

            <a href="profile.php">
                👤 My Profile
            </a>

            <a href="attendance.php" class="active">
                📊 Attendance
            </a>

            <a href="timetable.php">
                🕐 Timetable
            </a>

            <a href="materials.php">
                📚 Study Materials
            </a>

            <a href="exams.php">
                📝 Exams
            </a>

            <a href="results.php">
                📈 Results
            </a>

            <a href="hall-ticket.php">
                🎫 Hall Ticket
            </a>

            <a href="fees.php">
                💰 Fees & Payments
            </a>

            <a href="projects.php">
                💻 Projects
            </a>

            <a href="clubs.php">
                🎯 Clubs & Activities
            </a>

            <a href="notifications.php">
                🔔 Notifications
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">
                🚪 Logout
            </a>

        </div>

    </aside>


    <!-- =========================
         MAIN CONTENT
         ========================= -->

    <main class="dashboard-content">


        <header class="dashboard-header">

            <div>

                <p class="welcome-text">
                    Academic Performance
                </p>

                <h1>
                    Attendance
                </h1>

                <p class="student-meta">

                    <?php
                    echo htmlspecialchars(
                        $student["full_name"]
                    );
                    ?>

                    &nbsp; • &nbsp;

                    <?php
                    echo htmlspecialchars(
                        $student["roll_number"]
                    );
                    ?>

                </p>

            </div>


            <button
                type="button"
                class="dashboard-theme-toggle"
                id="attendanceThemeToggle">

                🌙

            </button>

        </header>


        <!-- =========================
             OVERVIEW
             ========================= -->

        <section class="attendance-overview">


            <div class="attendance-overview-card">

                <span>
                    Overall Attendance
                </span>

                <strong
                    class="<?php

                    if ($overall_status === "Good") {

                        echo "attendance-good";

                    } elseif ($overall_status === "Warning") {

                        echo "attendance-warning";

                    } else {

                        echo "attendance-low";
                    }

                    ?>">

                    <?php
                    echo $overall_percentage;
                    ?>%

                </strong>

                <small>
                    Minimum required: 75%
                </small>

            </div>


            <div class="attendance-overview-card">

                <span>
                    Present Classes
                </span>

                <strong class="attendance-good">

                    <?php
                    echo $total_present;
                    ?>

                </strong>

                <small>
                    Out of <?php echo $total_classes; ?> classes
                </small>

            </div>


            <div class="attendance-overview-card">

                <span>
                    Absent Classes
                </span>

                <strong class="attendance-low">

                    <?php
                    echo $total_absent;
                    ?>

                </strong>

                <small>
                    Total missed classes
                </small>

            </div>

        </section>


        <!-- =========================
             SUBJECT ATTENDANCE
             ========================= -->

        <div class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Subject-wise Attendance
                    </h2>

                    <p>
                        Attendance records for your current academic year
                    </p>

                </div>


                <span class="panel-icon">
                    📊
                </span>

            </div>


            <?php if (count($attendance_rows) > 0): ?>


                <div class="attendance-table-wrapper">

                    <table class="attendance-table">

                        <thead>

                            <tr>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Total Classes
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


                        <?php foreach ($attendance_rows as $row): ?>


                            <tr>


                                <td>

                                    <div class="subject-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $row["subject_name"]
                                        );
                                        ?>

                                    </div>

                                    <span class="subject-code">

                                        <?php
                                        echo htmlspecialchars(
                                            $row["subject_code"]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <?php
                                    echo $row["total_classes"];
                                    ?>

                                </td>


                                <td>

                                    <strong
                                        class="attendance-good">

                                        <?php
                                        echo $row["present_classes"];
                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    <strong
                                        class="attendance-low">

                                        <?php
                                        echo $row["absent_classes"];
                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    <div class="attendance-progress">

                                        <div
                                            class="attendance-progress-bar

                                            <?php

                                            if ($row["status_label"] === "Good") {

                                                echo "progress-good";

                                            } elseif (
                                                $row["status_label"] === "Warning"
                                            ) {

                                                echo "progress-warning";

                                            } else {

                                                echo "progress-low";
                                            }

                                            ?>"

                                            style="width:
                                            <?php
                                            echo min(
                                                100,
                                                $row["percentage"]
                                            );
                                            ?>%;">

                                        </div>

                                    </div>


                                    <span class="attendance-percentage">

                                        <?php
                                        echo $row["percentage"];
                                        ?>%

                                    </span>

                                </td>


                                <td>


                                    <span
                                        class="attendance-status

                                        <?php

                                        if (
                                            $row["status_label"] === "Good"
                                        ) {

                                            echo "status-good";

                                        } elseif (
                                            $row["status_label"] === "Warning"
                                        ) {

                                            echo "status-warning";

                                        } else {

                                            echo "status-low";
                                        }

                                        ?>">

                                        <?php
                                        echo $row["status_label"];
                                        ?>

                                    </span>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="attendance-empty">

                    <div class="attendance-empty-icon">
                        📊
                    </div>

                    <h3>
                        No Attendance Records
                    </h3>

                    <p>
                        Attendance data has not been added yet.
                    </p>

                </div>


            <?php endif; ?>


        </div>


        <footer class="dashboard-footer">

            © 2026 SmartCampus · Student Management Portal

        </footer>


    </main>

</div>


<script>

/* =========================
   THEME
   ========================= */

const themeButton =
    document.getElementById("attendanceThemeToggle");

const savedTheme =
    localStorage.getItem("smartcampus-theme");

if (savedTheme === "dark") {

    document.body.classList.add("dark-theme");

    themeButton.textContent = "☀️";

}


themeButton.addEventListener("click", function () {

    document.body.classList.toggle("dark-theme");

    const isDark =
        document.body.classList.contains("dark-theme");

    localStorage.setItem(
        "smartcampus-theme",
        isDark ? "dark" : "light"
    );

    themeButton.textContent =
        isDark ? "☀️" : "🌙";

});

</script>


</body>

</html>