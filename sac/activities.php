<?php
session_start();

require_once "../config/database.php";

/* =========================
   AUTH CHECK
   ========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/sac-login.php");
    exit();
}

$allowed_roles = [
    "SAC_LEAD",
    "DOMAIN_LEAD",
    "CR",
    "LR"
];

if (!in_array($_SESSION["role"] ?? "", $allowed_roles, true)) {
    header("Location: ../auth/sac-login.php");
    exit();
}

$username = $_SESSION["username"] ?? "SAC User";

$success = "";
$error = "";


/* =========================
   ADD ACTIVITY
   ========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "add_activity") {

        $club_id = intval($_POST["club_id"] ?? 0);
        $activity_name = trim($_POST["activity_name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $activity_date = $_POST["activity_date"] ?? "";
        $venue = trim($_POST["venue"] ?? "");

        if (
            $club_id <= 0 ||
            $activity_name === "" ||
            $activity_date === "" ||
            $venue === ""
        ) {

            $error = "Please fill all required fields.";

        } else {

            /* Verify club exists */

            $club_check = $conn->prepare(
                "SELECT club_id FROM clubs WHERE club_id = ? LIMIT 1"
            );

            $club_check->bind_param("i", $club_id);
            $club_check->execute();

            $club_result = $club_check->get_result();

            if ($club_result->num_rows !== 1) {

                $error = "Selected club does not exist.";

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO club_activities
                    (club_id, activity_name, description, activity_date, venue)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "issss",
                    $club_id,
                    $activity_name,
                    $description,
                    $activity_date,
                    $venue
                );

                if ($stmt->execute()) {

                    $success = "Activity added successfully.";

                } else {

                    $error = "Unable to add activity.";
                }

                $stmt->close();
            }

            $club_check->close();
        }
    }


    /* =========================
       DELETE ACTIVITY
       ========================= */

    if ($action === "delete_activity") {

        $activity_id = intval($_POST["activity_id"] ?? 0);

        if ($activity_id > 0) {

            $stmt = $conn->prepare(
                "DELETE FROM club_activities
                 WHERE activity_id = ?"
            );

            $stmt->bind_param("i", $activity_id);

            if ($stmt->execute()) {
                $success = "Activity deleted successfully.";
            } else {
                $error = "Unable to delete activity.";
            }

            $stmt->close();
        }
    }
}


/* =========================
   STATISTICS
   ========================= */

$total_activities = 0;
$upcoming_activities = 0;
$total_clubs = 0;


/* Total Activities */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM club_activities"
);

if ($result) {
    $row = $result->fetch_assoc();
    $total_activities = (int)$row["total"];
}


/* Upcoming Activities */

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM club_activities
     WHERE activity_date >= CURDATE()"
);

$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();
    $upcoming_activities = (int)$row["total"];
}

$stmt->close();


/* Active Clubs */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM clubs
     WHERE UPPER(status) = 'ACTIVE'"
);

if ($result) {
    $row = $result->fetch_assoc();
    $total_clubs = (int)$row["total"];
}


/* =========================
   LOAD CLUBS
   ========================= */

$clubs = [];

$result = $conn->query(
    "SELECT club_id, club_name
     FROM clubs
     WHERE UPPER(status) = 'ACTIVE'
     ORDER BY club_name ASC"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
}


/* =========================
   SEARCH
   ========================= */

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT
            a.activity_id,
            a.activity_name,
            a.description,
            a.activity_date,
            a.venue,
            c.club_name
         FROM club_activities a
         INNER JOIN clubs c
             ON a.club_id = c.club_id
         WHERE
            a.activity_name LIKE ?
            OR a.venue LIKE ?
            OR c.club_name LIKE ?
         ORDER BY a.activity_date DESC, a.activity_id DESC"
    );

    $search_term = "%" . $search . "%";

    $stmt->bind_param(
        "sss",
        $search_term,
        $search_term,
        $search_term
    );

    $stmt->execute();

    $activities = $stmt->get_result();

} else {

    $activities = $conn->query(
        "SELECT
            a.activity_id,
            a.activity_name,
            a.description,
            a.activity_date,
            a.venue,
            c.club_name
         FROM club_activities a
         INNER JOIN clubs c
             ON a.club_id = c.club_id
         ORDER BY a.activity_date DESC, a.activity_id DESC"
    );
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

    <title>Activities | SAC | SmartCampus</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body class="sac-dashboard-page sac-activity-page">


<!-- =========================
     SIDEBAR
     ========================= -->

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

        <a href="clubs.php">
            <span>👥</span>
            Clubs
        </a>

        <a href="activities.php" class="active">
            <span>🎯</span>
            Activities
        </a>

        <a href="student-leaders.php">
            <span>👤</span>
            Student Leaders
        </a>

        <a href="information-updates.php">
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


<!-- =========================
     MAIN CONTENT
     ========================= -->

<main class="main-content">

    <div class="topbar">

        <div>

            <h1>Activities Management</h1>

            <p>
                Manage club activities and campus events
            </p>

        </div>


        <div class="topbar-actions">

            <div class="user-info">

                <strong>
                    <?= htmlspecialchars($username) ?>
                </strong>

                <span>
                    <?= htmlspecialchars($_SESSION["role"]) ?>
                </span>

            </div>


            <button
                type="button"
                class="theme-toggle"
                onclick="toggleTheme()"
                id="themeToggle"
            >
                🌙
            </button>

        </div>

    </div>


    <!-- =========================
         ALERTS
         ========================= -->

    <?php if ($success !== ""): ?>

        <div class="sac-alert success">
            ✅ <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="sac-alert error">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- =========================
         STATS
         ========================= -->

    <div class="sac-activity-stats">

        <div class="sac-activity-stat-card">

            <div class="sac-activity-stat-icon">
                🎯
            </div>

            <div>

                <span>Total Activities</span>

                <h2>
                    <?= $total_activities ?>
                </h2>

            </div>

        </div>


        <div class="sac-activity-stat-card">

            <div class="sac-activity-stat-icon">
                📅
            </div>

            <div>

                <span>Upcoming Activities</span>

                <h2>
                    <?= $upcoming_activities ?>
                </h2>

            </div>

        </div>


        <div class="sac-activity-stat-card">

            <div class="sac-activity-stat-icon">
                👥
            </div>

            <div>

                <span>Active Clubs</span>

                <h2>
                    <?= $total_clubs ?>
                </h2>

            </div>

        </div>

    </div>


    <!-- =========================
         ADD ACTIVITY
         ========================= -->

    <section class="sac-activity-card">

        <div class="sac-activity-section-title">

            <h2>
                Add New Activity
            </h2>

            <p>
                Create an activity for a student club
            </p>

        </div>


        <form
            method="POST"
            class="sac-activity-form"
        >

            <input
                type="hidden"
                name="action"
                value="add_activity"
            >


            <div class="sac-activity-form-group">

                <label>
                    Club *
                </label>

                <select
                    name="club_id"
                    required
                >

                    <option value="">
                        Select Club
                    </option>

                    <?php foreach ($clubs as $club): ?>

                        <option
                            value="<?= (int)$club["club_id"] ?>"
                        >
                            <?= htmlspecialchars($club["club_name"]) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="sac-activity-form-group">

                <label>
                    Activity Name *
                </label>

                <input
                    type="text"
                    name="activity_name"
                    placeholder="Example: Coding Workshop"
                    maxlength="150"
                    required
                >

            </div>


            <div class="sac-activity-form-group">

                <label>
                    Activity Date *
                </label>

                <input
                    type="date"
                    name="activity_date"
                    required
                >

            </div>


            <div class="sac-activity-form-group">

                <label>
                    Venue *
                </label>

                <input
                    type="text"
                    name="venue"
                    placeholder="Example: Seminar Hall"
                    maxlength="150"
                    required
                >

            </div>


            <div class="sac-activity-form-group full">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    rows="4"
                    placeholder="Describe the activity..."
                ></textarea>

            </div>


            <div class="sac-activity-form-actions">

                <button
                    type="submit"
                    class="sac-primary-btn"
                >
                    ➕ Add Activity
                </button>

            </div>

        </form>

    </section>


    <!-- =========================
         ACTIVITIES LIST
         ========================= -->

    <section class="sac-activity-card">

        <div class="sac-activity-section-title">

            <h2>
                All Activities
            </h2>

            <p>
                View and manage club activities
            </p>

        </div>


        <form
            method="GET"
            class="sac-activity-search"
        >

            <input
                type="text"
                name="search"
                placeholder="Search activity, club or venue..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit">
                🔍 Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="activities.php">
                    Clear
                </a>

            <?php endif; ?>

        </form>


        <div class="sac-activity-table-wrapper">

            <table class="sac-activity-table">

                <thead>

                    <tr>

                        <th>Activity</th>

                        <th>Club</th>

                        <th>Date</th>

                        <th>Venue</th>

                        <th>Description</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($activities && $activities->num_rows > 0): ?>

                    <?php while ($activity = $activities->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $activity["activity_name"]
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <span class="activity-club">
                                    <?= htmlspecialchars(
                                        $activity["club_name"]
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <span class="activity-date">

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $activity["activity_date"]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                📍
                                <?= htmlspecialchars(
                                    $activity["venue"]
                                ) ?>

                            </td>


                            <td>

                                <?php
                                $description =
                                    trim(
                                        $activity["description"] ?? ""
                                    );
                                ?>

                                <?php if ($description !== ""): ?>

                                    <small>
                                        <?= htmlspecialchars(
                                            $description
                                        ) ?>
                                    </small>

                                <?php else: ?>

                                    <small class="muted">
                                        No description
                                    </small>

                                <?php endif; ?>

                            </td>


                            <td>

                                <form
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Delete this activity?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete_activity"
                                    >

                                    <input
                                        type="hidden"
                                        name="activity_id"
                                        value="<?= (int)$activity["activity_id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="sac-delete-btn"
                                    >
                                        🗑️
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            class="sac-empty"
                        >
                            🎯 No activities found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>


<script>

function toggleTheme() {

    document.body.classList.toggle("dark-theme");

    const button =
        document.getElementById("themeToggle");

    if (
        document.body.classList.contains("dark-theme")
    ) {

        button.textContent = "☀️";

        localStorage.setItem(
            "smartcampus-theme",
            "dark"
        );

    } else {

        button.textContent = "🌙";

        localStorage.setItem(
            "smartcampus-theme",
            "light"
        );
    }
}


document.addEventListener(
    "DOMContentLoaded",
    function () {

        const savedTheme =
            localStorage.getItem(
                "smartcampus-theme"
            );

        if (savedTheme === "dark") {

            document.body.classList.add(
                "dark-theme"
            );

            document.getElementById(
                "themeToggle"
            ).textContent = "☀️";
        }

    }
);

</script>


</body>
</html>