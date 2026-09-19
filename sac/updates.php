<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['SAC_LEAD', 'DOMAIN_LEAD', 'CR', 'LR'])) {
    header("Location: ../auth/sac-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$message = "";
$error = "";

/* =========================
   ADD UPDATE
========================= */
if (isset($_POST['add_update'])) {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $target_role = $_POST['target_role'];
    $publish_date = $_POST['publish_date'];
    $status = $_POST['status'];

    if ($title === "" || $description === "" || $publish_date === "") {
        $error = "Please fill all required fields.";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO information_updates
            (title, description, posted_by, target_role, publish_date, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssisss",
            $title,
            $description,
            $user_id,
            $target_role,
            $publish_date,
            $status
        );

        if ($stmt->execute()) {
            $message = "Announcement added successfully.";
        } else {
            $error = "Failed to add announcement.";
        }

        $stmt->close();
    }
}

/* =========================
   DELETE UPDATE
========================= */
if (isset($_GET['delete'])) {

    $delete_id = intval($_GET['delete']);

    $stmt = $conn->prepare(
        "DELETE FROM information_updates WHERE update_id = ?"
    );
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "Announcement deleted successfully.";
    } else {
        $error = "Failed to delete announcement.";
    }

    $stmt->close();
}

/* =========================
   SEARCH
========================= */
$search = trim($_GET['search'] ?? '');

if ($search !== "") {

    $like = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT *
        FROM information_updates
        WHERE title LIKE ?
           OR description LIKE ?
        ORDER BY publish_date DESC
    ");

    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();

    $updates = $stmt->get_result();

} else {

    $updates = $conn->query("
        SELECT *
        FROM information_updates
        ORDER BY publish_date DESC
    ");
}

/* =========================
   STATISTICS
========================= */

$total_updates = $conn->query("
    SELECT COUNT(*) AS total
    FROM information_updates
")->fetch_assoc()['total'];

$active_updates = $conn->query("
    SELECT COUNT(*) AS total
    FROM information_updates
    WHERE status = 'ACTIVE'
")->fetch_assoc()['total'];

$inactive_updates = $conn->query("
    SELECT COUNT(*) AS total
    FROM information_updates
    WHERE status = 'INACTIVE'
")->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SAC Updates | SmartCampus</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="sac-updates-page">

<!-- ================= SIDEBAR ================= -->

<aside class="sac-sidebar">

    <div class="sac-brand">
        <div class="sac-logo">SC</div>

        <div>
            <h2>SmartCampus</h2>
            <span>SAC Portal</span>
        </div>
    </div>

    <nav class="sac-nav">

        <a href="dashboard.php">
            🏠 <span>Dashboard</span>
        </a>

        <a href="clubs.php">
            👥 <span>Clubs</span>
        </a>

        <a href="activities.php">
            📅 <span>Activities</span>
        </a>

        <a href="updates.php" class="active">
            📢 <span>Updates</span>
        </a>

        <a href="notifications.php">
            🔔 <span>Notifications</span>
        </a>

        <a href="reports.php">
            📊 <span>Reports</span>
        </a>

    </nav>

    <div class="sac-sidebar-bottom">

        <button onclick="toggleTheme()" class="theme-btn">
            🌙 <span>Toggle Theme</span>
        </button>

        <a href="../auth/logout.php" class="logout-btn">
            🚪 <span>Logout</span>
        </a>

    </div>

</aside>


<!-- ================= MAIN CONTENT ================= -->

<main class="sac-main-content">

    <!-- TOPBAR -->

    <header class="sac-topbar">

        <div>
            <h1>Updates & Announcements</h1>
            <p>Manage SAC announcements and important campus updates.</p>
        </div>

        <div class="sac-user-area">
            <div class="sac-user-avatar">S</div>

            <div>
                <strong>SAC Portal</strong>
                <small><?php echo htmlspecialchars($_SESSION['role']); ?></small>
            </div>
        </div>

    </header>


    <!-- ALERTS -->

    <?php if ($message): ?>

        <div class="sac-alert success">
            ✓ <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="sac-alert error">
            ⚠ <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <!-- ================= STATS ================= -->

    <section class="sac-update-stats">

        <div class="sac-update-stat-card">
            <div class="stat-icon">📢</div>
            <div>
                <span>Total Updates</span>
                <strong><?php echo $total_updates; ?></strong>
            </div>
        </div>

        <div class="sac-update-stat-card">
            <div class="stat-icon">🟢</div>
            <div>
                <span>Active</span>
                <strong><?php echo $active_updates; ?></strong>
            </div>
        </div>

        <div class="sac-update-stat-card">
            <div class="stat-icon">🔴</div>
            <div>
                <span>Inactive</span>
                <strong><?php echo $inactive_updates; ?></strong>
            </div>
        </div>

    </section>


    <!-- ================= ADD UPDATE ================= -->

    <section class="sac-update-form-card">

        <div class="sac-section-heading">
            <div>
                <h2>Create Announcement</h2>
                <p>Publish an important update for students or staff.</p>
            </div>
        </div>

        <form method="POST" class="sac-update-form">

            <div class="form-group">

                <label>Title *</label>

                <input
                    type="text"
                    name="title"
                    placeholder="Enter announcement title"
                    required
                >

            </div>


            <div class="form-group">

                <label>Description *</label>

                <textarea
                    name="description"
                    rows="5"
                    placeholder="Write announcement details..."
                    required
                ></textarea>

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label>Target Audience</label>

                    <select name="target_role">

                        <option value="ALL">Everyone</option>
                        <option value="STUDENT">Students</option>
                        <option value="FACULTY">Faculty</option>
                        <option value="EXAM_CELL">Exam Cell</option>
                        <option value="OFFICE">Office</option>
                        <option value="SAC_LEAD">SAC</option>
                        <option value="ADMIN">Admin</option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Publish Date *</label>

                    <input
                        type="date"
                        name="publish_date"
                        value="<?php echo date('Y-m-d'); ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Status</label>

                    <select name="status">

                        <option value="ACTIVE">Active</option>
                        <option value="INACTIVE">Inactive</option>

                    </select>

                </div>

            </div>


            <button type="submit" name="add_update" class="sac-primary-btn">
                + Publish Announcement
            </button>

        </form>

    </section>


    <!-- ================= SEARCH ================= -->

    <section class="sac-update-list-card">

        <div class="sac-section-heading">

            <div>
                <h2>All Announcements</h2>
                <p>View and manage published updates.</p>
            </div>

            <form method="GET" class="sac-search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search announcements..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >

                <button type="submit">Search</button>

                <?php if ($search !== ""): ?>

                    <a href="updates.php">Clear</a>

                <?php endif; ?>

            </form>

        </div>


        <!-- ================= TABLE ================= -->

        <div class="sac-update-table-wrapper">

            <table class="sac-update-table">

                <thead>

                    <tr>
                        <th>Announcement</th>
                        <th>Target</th>
                        <th>Publish Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($updates && $updates->num_rows > 0): ?>

                    <?php while ($row = $updates->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <div class="announcement-title">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </div>

                                <div class="announcement-description">
                                    <?php
                                    echo htmlspecialchars(
                                        strlen($row['description']) > 100
                                        ? substr($row['description'], 0, 100) . "..."
                                        : $row['description']
                                    );
                                    ?>
                                </div>

                            </td>


                            <td>

                                <span class="target-badge">
                                    <?php echo htmlspecialchars($row['target_role']); ?>
                                </span>

                            </td>


                            <td>
                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime($row['publish_date'])
                                );
                                ?>
                            </td>


                            <td>

                                <?php if ($row['status'] === 'ACTIVE'): ?>

                                    <span class="status-badge active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <a
                                    href="updates.php?delete=<?php echo $row['update_id']; ?>"
                                    class="delete-btn"
                                    onclick="return confirm('Delete this announcement?');"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="5" class="no-data">
                            No announcements found.
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
    document.body.classList.toggle("light-theme");

    if (document.body.classList.contains("light-theme")) {
        localStorage.setItem("smartcampus-theme", "light");
    } else {
        localStorage.setItem("smartcampus-theme", "dark");
    }
}

if (localStorage.getItem("smartcampus-theme") === "light") {
    document.body.classList.add("light-theme");
}
</script>

</body>
</html>