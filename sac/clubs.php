<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["SAC_LEAD", "DOMAIN_LEAD", "CR", "LR"], true)) {
    header("Location: ../auth/sac-login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];
$role = $_SESSION["role"];

$message = "";
$error = "";

/* =========================
   ADD CLUB
   ========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_club"])) {

    $club_name = trim($_POST["club_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $faculty_id = intval($_POST["faculty_id"] ?? 0);
    $student_lead_id = intval($_POST["student_lead_id"] ?? 0);
    $status = $_POST["status"] ?? "ACTIVE";

    if ($club_name === "") {
        $error = "Club name is required.";
    } else {

        $check = $conn->prepare("SELECT club_id FROM clubs WHERE club_name = ? LIMIT 1");
        $check->bind_param("s", $club_name);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {

            $error = "A club with this name already exists.";

        } else {

            $stmt = $conn->prepare("
                INSERT INTO clubs
                (club_name, description, faculty_id, student_lead_id, status)
                VALUES (?, ?, NULLIF(?, 0), NULLIF(?, 0), ?)
            ");

            $stmt->bind_param(
                "ssiis",
                $club_name,
                $description,
                $faculty_id,
                $student_lead_id,
                $status
            );

            if ($stmt->execute()) {
                $message = "Club added successfully.";
            } else {
                $error = "Unable to add club.";
            }

            $stmt->close();
        }

        $check->close();
    }
}


/* =========================
   DELETE CLUB
   ========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_club"])) {

    $club_id = intval($_POST["club_id"] ?? 0);

    if ($club_id > 0) {

        $stmt = $conn->prepare("DELETE FROM clubs WHERE club_id = ?");
        $stmt->bind_param("i", $club_id);

        if ($stmt->execute()) {
            $message = "Club deleted successfully.";
        } else {
            $error = "Unable to delete club.";
        }

        $stmt->close();
    }
}


/* =========================
   SEARCH
   ========================= */
$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $search_like = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            c.club_id,
            c.club_name,
            c.description,
            c.status,
            c.created_at,
            f.full_name AS faculty_name,
            s.full_name AS student_lead_name,
            s.roll_number
        FROM clubs c
        LEFT JOIN faculty f
            ON c.faculty_id = f.faculty_id
        LEFT JOIN students s
            ON c.student_lead_id = s.student_id
        WHERE c.club_name LIKE ?
           OR c.description LIKE ?
        ORDER BY c.created_at DESC
    ");

    $stmt->bind_param("ss", $search_like, $search_like);

} else {

    $stmt = $conn->prepare("
        SELECT
            c.club_id,
            c.club_name,
            c.description,
            c.status,
            c.created_at,
            f.full_name AS faculty_name,
            s.full_name AS student_lead_name,
            s.roll_number
        FROM clubs c
        LEFT JOIN faculty f
            ON c.faculty_id = f.faculty_id
        LEFT JOIN students s
            ON c.student_lead_id = s.student_id
        ORDER BY c.created_at DESC
    ");
}

$stmt->execute();
$clubs = $stmt->get_result();

$total_clubs = $clubs->num_rows;


/* =========================
   SUMMARY COUNTS
   ========================= */
$active_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM clubs
    WHERE status = 'ACTIVE'
");

$active_clubs = $active_result
    ? intval($active_result->fetch_assoc()["total"])
    : 0;

$inactive_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM clubs
    WHERE status != 'ACTIVE'
");

$inactive_clubs = $inactive_result
    ? intval($inactive_result->fetch_assoc()["total"])
    : 0;


/* =========================
   FACULTY
   ========================= */
$faculty_list = $conn->query("
    SELECT faculty_id, full_name, designation
    FROM faculty
    ORDER BY full_name ASC
");


/* =========================
   STUDENTS
   ========================= */
$student_list = $conn->query("
    SELECT student_id, full_name, roll_number
    FROM students
    ORDER BY full_name ASC
");

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Clubs | SAC | SmartCampus</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

</head>

<body>

<div class="sac-club-page">

    <!-- ================= SIDEBAR ================= -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="brand-logo">
                SC
            </div>

            <div>
                <h2>SmartCampus</h2>
                <span>SAC Portal</span>
            </div>

        </div>


        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <span>🏠</span>
                Dashboard
            </a>

            <a href="clubs.php" class="active">
                <span>👥</span>
                Clubs
            </a>

            <a href="activities.php">
                <span>🎯</span>
                Activities
            </a>

            <a href="student-leaders.php">
                <span>👤</span>
                Student Leaders
            </a>

            <a href="updates.php">
                <span>📢</span>
                Information Updates
            </a>

            <a href="notifications.php">
                <span>🔔</span>
                Notifications
            </a>

            <a href="reports.php">
                <span>📊</span>
                Reports
            </a>

            <a href="../auth/logout.php">
                <span>🚪</span>
                Logout
            </a>

        </nav>

    </aside>


    <!-- ================= MAIN ================= -->

    <main class="main-content">

        <!-- TOPBAR -->

        <div class="topbar">

            <div>

                <h1>Clubs Management</h1>

                <p>
                    Manage student clubs and their coordinators
                </p>

            </div>


            <div class="topbar-actions">

                <div class="user-info">

                    <strong>
                        <?= htmlspecialchars($username) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($role) ?>
                    </span>

                </div>

                <button
                    type="button"
                    class="theme-toggle"
                    onclick="toggleTheme()">

                    🌙

                </button>

            </div>

        </div>


        <!-- ================= MESSAGE ================= -->

        <?php if ($message !== ""): ?>

            <div class="sac-alert success">
                ✅ <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="sac-alert error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <!-- ================= STATS ================= -->

        <div class="sac-club-stats">

            <div class="sac-club-stat-card">

                <div class="sac-club-stat-icon">
                    👥
                </div>

                <div>

                    <span>Total Clubs</span>

                    <h2><?= $total_clubs ?></h2>

                </div>

            </div>


            <div class="sac-club-stat-card">

                <div class="sac-club-stat-icon">
                    ✅
                </div>

                <div>

                    <span>Active Clubs</span>

                    <h2><?= $active_clubs ?></h2>

                </div>

            </div>


            <div class="sac-club-stat-card">

                <div class="sac-club-stat-icon">
                    ⏸️
                </div>

                <div>

                    <span>Inactive Clubs</span>

                    <h2><?= $inactive_clubs ?></h2>

                </div>

            </div>

        </div>


        <!-- ================= ADD CLUB ================= -->

        <section class="sac-club-card">

            <div class="sac-club-section-title">

                <div>

                    <h2>Add New Club</h2>

                    <p>
                        Create a new student club
                    </p>

                </div>

            </div>


            <form method="POST"
                  class="sac-club-form">

                <div class="sac-form-group">

                    <label>
                        Club Name *
                    </label>

                    <input
                        type="text"
                        name="club_name"
                        placeholder="Example: Coding Club"
                        required>

                </div>


                <div class="sac-form-group">

                    <label>
                        Faculty Coordinator
                    </label>

                    <select name="faculty_id">

                        <option value="0">
                            Select Faculty
                        </option>

                        <?php if ($faculty_list): ?>

                            <?php while ($faculty = $faculty_list->fetch_assoc()): ?>

                                <option value="<?= $faculty["faculty_id"] ?>">

                                    <?= htmlspecialchars($faculty["full_name"]) ?>

                                    <?php if (!empty($faculty["designation"])): ?>
                                        - <?= htmlspecialchars($faculty["designation"]) ?>
                                    <?php endif; ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <div class="sac-form-group">

                    <label>
                        Student Lead
                    </label>

                    <select name="student_lead_id">

                        <option value="0">
                            Select Student
                        </option>

                        <?php if ($student_list): ?>

                            <?php while ($student = $student_list->fetch_assoc()): ?>

                                <option value="<?= $student["student_id"] ?>">

                                    <?= htmlspecialchars($student["full_name"]) ?>

                                    -

                                    <?= htmlspecialchars($student["roll_number"]) ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <div class="sac-form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="ACTIVE">
                            Active
                        </option>

                        <option value="INACTIVE">
                            Inactive
                        </option>

                    </select>

                </div>


                <div class="sac-form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="4"
                        placeholder="Brief description of the club"></textarea>

                </div>


                <div class="sac-form-actions">

                    <button
                        type="submit"
                        name="add_club"
                        class="sac-primary-btn">

                        ➕ Add Club

                    </button>

                </div>

            </form>

        </section>


        <!-- ================= CLUB LIST ================= -->

        <section class="sac-club-card">

            <div class="sac-club-section-title">

                <div>

                    <h2>All Clubs</h2>

                    <p>
                        View and manage registered student clubs
                    </p>

                </div>

            </div>


            <!-- SEARCH -->

            <form method="GET"
                  class="sac-club-search">

                <input
                    type="text"
                    name="search"
                    placeholder="Search clubs..."
                    value="<?= htmlspecialchars($search) ?>">

                <button type="submit">
                    🔍 Search
                </button>

                <?php if ($search !== ""): ?>

                    <a href="clubs.php">
                        Clear
                    </a>

                <?php endif; ?>

            </form>


            <!-- TABLE -->

            <div class="sac-club-table-wrapper">

                <table class="sac-club-table">

                    <thead>

                    <tr>

                        <th>Club</th>

                        <th>Faculty Coordinator</th>

                        <th>Student Lead</th>

                        <th>Status</th>

                        <th>Created</th>

                        <th>Action</th>

                    </tr>

                    </thead>


                    <tbody>

                    <?php if ($clubs->num_rows > 0): ?>

                        <?php while ($club = $clubs->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($club["club_name"]) ?>
                                    </strong>

                                    <?php if (!empty($club["description"])): ?>

                                        <small>
                                            <?= htmlspecialchars($club["description"]) ?>
                                        </small>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= !empty($club["faculty_name"])
                                        ? htmlspecialchars($club["faculty_name"])
                                        : "Not assigned" ?>

                                </td>


                                <td>

                                    <?php if (!empty($club["student_lead_name"])): ?>

                                        <strong>
                                            <?= htmlspecialchars($club["student_lead_name"]) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars($club["roll_number"]) ?>
                                        </small>

                                    <?php else: ?>

                                        Not assigned

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (strtoupper($club["status"]) === "ACTIVE"): ?>

                                        <span class="club-status active">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="club-status inactive">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= !empty($club["created_at"])
                                        ? date("d M Y", strtotime($club["created_at"]))
                                        : "-" ?>

                                </td>


                                <td>

                                    <form method="POST"
                                          onsubmit="return confirm('Are you sure you want to delete this club?');">

                                        <input
                                            type="hidden"
                                            name="club_id"
                                            value="<?= $club["club_id"] ?>">

                                        <button
                                            type="submit"
                                            name="delete_club"
                                            class="sac-delete-btn">

                                            🗑️

                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6"
                                class="sac-empty">

                                👥 No clubs found.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>


<script>

function toggleTheme() {

    document.body.classList.toggle("dark-theme");

    const button =
        document.querySelector(".theme-toggle");

    if (document.body.classList.contains("dark-theme")) {

        button.textContent = "☀️";

        localStorage.setItem("smartcampus-theme", "dark");

    } else {

        button.textContent = "🌙";

        localStorage.setItem("smartcampus-theme", "light");

    }

}


document.addEventListener("DOMContentLoaded", function () {

    const savedTheme =
        localStorage.getItem("smartcampus-theme");

    const button =
        document.querySelector(".theme-toggle");

    if (savedTheme === "dark") {

        document.body.classList.add("dark-theme");

        if (button) {
            button.textContent = "☀️";
        }

    }

});

</script>

</body>
</html>