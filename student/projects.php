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

/* Project records */
$stmt = $conn->prepare("
    SELECT
        p.project_id,
        p.project_title,
        p.description,
        p.project_type,
        p.status,
        p.start_date,
        p.end_date,
        f.full_name AS faculty_name,
        f.designation
    FROM projects p
    LEFT JOIN faculty f
        ON p.faculty_id = f.faculty_id
    WHERE p.student_id = ?
    ORDER BY p.created_at DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$projects = $stmt->get_result();

$project_rows = [];

while ($row = $projects->fetch_assoc()) {
    $project_rows[] = $row;
}

/* Project statistics */
$total_projects = count($project_rows);
$completed_projects = 0;
$ongoing_projects = 0;

foreach ($project_rows as $project) {

    $status = strtoupper(trim($project["status"] ?? ""));

    if ($status === "COMPLETED") {
        $completed_projects++;
    }

    if (
        $status === "ONGOING" ||
        $status === "IN PROGRESS" ||
        $status === "IN_PROGRESS"
    ) {
        $ongoing_projects++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Projects | SmartCampus</title>

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

            <a href="hall-ticket.php">
                🎫
                <span>Hall Ticket</span>
            </a>

            <a href="fees.php">
                💳
                <span>Fees & Payments</span>
            </a>

            <a href="projects.php" class="active">
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

                <h1>My Projects</h1>

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


        <!-- SUMMARY -->

        <div class="projects-summary">

            <div class="projects-summary-card">

                <div class="projects-summary-icon">
                    💻
                </div>

                <div>

                    <span>Total Projects</span>

                    <strong>
                        <?php echo $total_projects; ?>
                    </strong>

                </div>

            </div>


            <div class="projects-summary-card">

                <div class="projects-summary-icon">
                    🚀
                </div>

                <div>

                    <span>Ongoing</span>

                    <strong>
                        <?php echo $ongoing_projects; ?>
                    </strong>

                </div>

            </div>


            <div class="projects-summary-card">

                <div class="projects-summary-icon">
                    ✅
                </div>

                <div>

                    <span>Completed</span>

                    <strong>
                        <?php echo $completed_projects; ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- PROJECTS -->

        <?php if ($total_projects > 0): ?>

            <div class="projects-grid">

                <?php foreach ($project_rows as $project): ?>

                    <?php
                    $status = strtoupper(
                        trim($project["status"] ?? "PENDING")
                    );

                    $status_class = strtolower(
                        str_replace(
                            [" ", "_"],
                            "-",
                            $status
                        )
                    );
                    ?>

                    <article class="project-card">

                        <div class="project-card-top">

                            <div class="project-icon">
                                💻
                            </div>

                            <span class="project-status <?php
                                echo htmlspecialchars($status_class);
                            ?>">

                                <?php
                                echo htmlspecialchars($status);
                                ?>

                            </span>

                        </div>


                        <div class="project-card-body">

                            <span class="project-type">

                                <?php
                                echo htmlspecialchars(
                                    $project["project_type"]
                                    ?: "Academic Project"
                                );
                                ?>

                            </span>


                            <h2>

                                <?php
                                echo htmlspecialchars(
                                    $project["project_title"]
                                );
                                ?>

                            </h2>


                            <p>

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $project["description"]
                                        ?: "No project description available."
                                    )
                                );
                                ?>

                            </p>


                            <!-- FACULTY -->

                            <?php if (!empty($project["faculty_name"])): ?>

                                <div class="project-faculty">

                                    <div class="project-faculty-icon">
                                        👨‍🏫
                                    </div>

                                    <div>

                                        <span>Faculty Guide</span>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $project["faculty_name"]
                                            );
                                            ?>

                                        </strong>

                                        <?php if (!empty($project["designation"])): ?>

                                            <small>

                                                <?php
                                                echo htmlspecialchars(
                                                    $project["designation"]
                                                );
                                                ?>

                                            </small>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <!-- DATES -->

                            <div class="project-dates">

                                <div>

                                    <span>Start Date</span>

                                    <strong>

                                        <?php
                                        echo !empty(
                                            $project["start_date"]
                                        )
                                            ? date(
                                                "d M Y",
                                                strtotime(
                                                    $project["start_date"]
                                                )
                                            )
                                            : "—";
                                        ?>

                                    </strong>

                                </div>


                                <div>

                                    <span>End Date</span>

                                    <strong>

                                        <?php
                                        echo !empty(
                                            $project["end_date"]
                                        )
                                            ? date(
                                                "d M Y",
                                                strtotime(
                                                    $project["end_date"]
                                                )
                                            )
                                            : "—";
                                        ?>

                                    </strong>

                                </div>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <!-- EMPTY STATE -->

            <section class="projects-empty">

                <div class="projects-empty-icon">
                    💻
                </div>

                <h2>No Projects Available</h2>

                <p>
                    Your academic projects have not been added yet.
                    Project details will appear here once they are assigned
                    or recorded by the faculty.
                </p>

            </section>

        <?php endif; ?>

    </main>

</div>


<script>

const themeToggle =
    document.getElementById("themeToggle");


if (
    localStorage.getItem("smartcampus-theme")
    === "dark"
) {

    document.body.classList.add("dark-theme");

    themeToggle.textContent = "☀️";

}


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

            themeToggle.textContent = "☀️";

        } else {

            localStorage.setItem(
                "smartcampus-theme",
                "light"
            );

            themeToggle.textContent = "🌙";

        }

    }
);

</script>

</body>
</html>