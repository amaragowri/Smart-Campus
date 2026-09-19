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
        roll_number,
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

$department = $student["department"];
$year = $student["year"];


/* ================= SEARCH ================= */

$search = "";

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}


/* ================= GET STUDY MATERIALS ================= */

if ($search !== "") {

    $searchTerm = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            m.material_id,
            m.title,
            m.description,
            m.file_name,
            m.file_path,
            m.uploaded_at,
            s.subject_code,
            s.subject_name,
            f.full_name AS faculty_name
        FROM materials m
        INNER JOIN subjects s
            ON m.subject_id = s.subject_id
        INNER JOIN faculty f
            ON m.faculty_id = f.faculty_id
        WHERE s.department = ?
          AND s.year = ?
          AND m.status = 'ACTIVE'
          AND (
              m.title LIKE ?
              OR m.description LIKE ?
              OR s.subject_name LIKE ?
              OR s.subject_code LIKE ?
          )
        ORDER BY m.uploaded_at DESC
    ");

    $stmt->bind_param(
        "sissss",
        $department,
        $year,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

} else {

    $stmt = $conn->prepare("
        SELECT
            m.material_id,
            m.title,
            m.description,
            m.file_name,
            m.file_path,
            m.uploaded_at,
            s.subject_code,
            s.subject_name,
            f.full_name AS faculty_name
        FROM materials m
        INNER JOIN subjects s
            ON m.subject_id = s.subject_id
        INNER JOIN faculty f
            ON m.faculty_id = f.faculty_id
        WHERE s.department = ?
          AND s.year = ?
          AND m.status = 'ACTIVE'
        ORDER BY m.uploaded_at DESC
    ");

    $stmt->bind_param("si", $department, $year);
}

$stmt->execute();

$materials_result = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Study Materials | SmartCampus</title>

    <!-- COMMON CSS ONLY -->

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


            <a
                href="materials.php"
                class="active"
            >

                <span>📚</span>

                Study Materials

            </a>


            <a href="#">

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
                    Learning Resources
                </p>

                <h1>
                    Study Materials
                </h1>

                <p class="student-meta">
                    Access your subject learning materials
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


        <!-- ================= MATERIALS PAGE ================= -->

        <div class="materials-page">


            <!-- PAGE INTRO -->

            <div class="materials-intro">

                <span class="materials-label">
                    LEARNING RESOURCES
                </span>

                <h2>
                    📚 Study Materials
                </h2>

                <p>
                    Notes, documents and learning resources
                    shared by faculty.
                </p>

            </div>


            <!-- ================= SEARCH ================= -->

            <div class="dashboard-panel materials-search-panel">


                <form
                    method="GET"
                    class="materials-search-form"
                >


                    <div class="materials-search-input">

                        <span>
                            🔍
                        </span>

                        <input
                            type="text"
                            name="search"
                            placeholder="Search by subject, title or description..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                    </div>


                    <button
                        type="submit"
                        class="materials-search-btn"
                    >
                        Search
                    </button>


                    <?php if ($search !== ""): ?>

                        <a
                            href="materials.php"
                            class="materials-clear-btn"
                        >
                            Clear
                        </a>

                    <?php endif; ?>


                </form>

            </div>


            <!-- ================= MATERIAL COUNT ================= -->

            <?php if ($materials_result->num_rows > 0): ?>

                <div class="materials-result-header">

                    <div>

                        <h3>
                            Available Materials
                        </h3>

                        <?php if ($search !== ""): ?>

                            <p>
                                Search results for
                                "<strong>
                                    <?php
                                    echo htmlspecialchars($search);
                                    ?>
                                </strong>"
                            </p>

                        <?php else: ?>

                            <p>
                                Materials available for
                                your department and year.
                            </p>

                        <?php endif; ?>

                    </div>


                    <span class="materials-count">

                        <?php
                        echo $materials_result->num_rows;
                        ?>

                        Materials

                    </span>

                </div>


                <!-- ================= MATERIAL CARDS ================= -->

                <div class="materials-grid">


                    <?php while (
                        $material =
                        $materials_result->fetch_assoc()
                    ): ?>


                        <div class="material-card">


                            <div class="material-card-top">


                                <div class="material-icon">
                                    📄
                                </div>


                                <span class="material-code">

                                    <?php
                                    echo htmlspecialchars(
                                        $material["subject_code"]
                                    );
                                    ?>

                                </span>


                            </div>


                            <h3 class="material-title">

                                <?php
                                echo htmlspecialchars(
                                    $material["title"]
                                );
                                ?>

                            </h3>


                            <p class="material-description">

                                <?php

                                if (
                                    !empty(
                                        $material["description"]
                                    )
                                ) {

                                    echo htmlspecialchars(
                                        $material["description"]
                                    );

                                } else {

                                    echo "No description available.";

                                }

                                ?>

                            </p>


                            <div class="material-info">


                                <div class="material-info-row">

                                    <span>
                                        📚
                                    </span>

                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $material["subject_name"]
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="material-info-row">

                                    <span>
                                        👨‍🏫
                                    </span>

                                    <span>

                                        <?php
                                        echo htmlspecialchars(
                                            $material["faculty_name"]
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="material-info-row">

                                    <span>
                                        📅
                                    </span>

                                    <span>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $material["uploaded_at"]
                                            )
                                        );
                                        ?>

                                    </span>

                                </div>


                            </div>


                            <a
                                href="<?php echo htmlspecialchars($material["file_path"]); ?>"
                                class="material-download-btn"
                                target="_blank"
                                download
                            >

                                📥 Download Material

                            </a>


                        </div>


                    <?php endwhile; ?>


                </div>


            <?php else: ?>


                <!-- ================= EMPTY STATE ================= -->

                <div class="dashboard-panel materials-empty">


                    <div class="materials-empty-icon">
                        📚
                    </div>


                    <h3>
                        No Study Materials Found
                    </h3>


                    <p>

                        <?php if ($search !== ""): ?>

                            No materials match
                            "<strong>
                                <?php
                                echo htmlspecialchars($search);
                                ?>
                            </strong>".

                        <?php else: ?>

                            Faculty have not uploaded
                            any study materials yet.

                        <?php endif; ?>

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