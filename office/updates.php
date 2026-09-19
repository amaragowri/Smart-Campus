<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "OFFICE") {
    header("Location: ../auth/office-login.php");
    exit();
}

require_once "../config/database.php";

$message = "";
$message_type = "";

/* Office user */
$user_id = $_SESSION["user_id"];

$user_stmt = $conn->prepare("
    SELECT username
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();

$user_result = $user_stmt->get_result();
$office_user = $user_result->fetch_assoc();

$user_stmt->close();


/* Add new information update */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_update"])) {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $target_role = trim($_POST["target_role"] ?? "");
    $publish_date = $_POST["publish_date"] ?? "";
    $status = trim($_POST["status"] ?? "ACTIVE");

    if ($title === "" || $description === "" || $target_role === "" || $publish_date === "") {

        $message = "Please fill all required fields.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO information_updates
            (title, description, posted_by, target_role, publish_date, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {

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

                $message = "Information update posted successfully.";
                $message_type = "success";

            } else {

                $message = "Failed to post update: " . $stmt->error;
                $message_type = "error";
            }

            $stmt->close();

        } else {

            $message = "Database error: " . $conn->error;
            $message_type = "error";
        }
    }
}


/* Delete update */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_update"])) {

    $update_id = (int)($_POST["update_id"] ?? 0);

    if ($update_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM information_updates
            WHERE update_id = ?
        ");

        $stmt->bind_param("i", $update_id);

        if ($stmt->execute()) {

            $message = "Information update deleted successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to delete update.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* Get all updates */
$updates = [];

$updates_query = $conn->query("
    SELECT
        update_id,
        title,
        description,
        posted_by,
        target_role,
        publish_date,
        status
    FROM information_updates
    ORDER BY publish_date DESC, update_id DESC
");

if ($updates_query) {

    while ($row = $updates_query->fetch_assoc()) {
        $updates[] = $row;
    }
}


/* Count active updates */
$active_count = 0;

foreach ($updates as $update) {

    if (strtoupper($update["status"]) === "ACTIVE") {
        $active_count++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Information Updates | SmartCampus</title>

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
                <span>Office Portal</span>
            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php">
                <span>🏠</span>
                Dashboard
            </a>

            <a href="student.php">
                <span>👨‍🎓</span>
                Students
            </a>

            <a href="fees.php">
                <span>💰</span>
                Fees
            </a>

            <a href="updates.php" class="active">
                <span>📢</span>
                Information Updates
            </a>

            <a href="notifications.php">
                <span>🔔</span>
                Notifications
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

                <h1>Information Updates</h1>

                <p>
                    Publish important college information and notices
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $office_user["username"] ?? "Office Admin"
                        );
                        ?>
                    </strong>

                    <span>Office Administrator</span>

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

            <div class="office-update-message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->
        <section class="office-update-summary">

            <div class="office-update-summary-card">

                <div class="office-update-summary-icon">
                    📢
                </div>

                <div>

                    <span>Total Updates</span>

                    <strong>
                        <?php echo count($updates); ?>
                    </strong>

                </div>

            </div>


            <div class="office-update-summary-card">

                <div class="office-update-summary-icon">
                    🟢
                </div>

                <div>

                    <span>Active Updates</span>

                    <strong>
                        <?php echo $active_count; ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- ADD UPDATE -->
        <section class="office-update-form-section">

            <div class="office-update-section-header">

                <div>

                    <h2>Post New Information</h2>

                    <p>
                        Create an announcement for students, faculty or other users
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="updates.php"
                class="office-update-form">

                <input
                    type="hidden"
                    name="add_update"
                    value="1"
                >


                <div class="office-update-form-group full">

                    <label for="title">
                        Title *
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        placeholder="Enter notice title"
                        maxlength="255"
                        required
                    >

                </div>


                <div class="office-update-form-group full">

                    <label for="description">
                        Description *
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                        placeholder="Enter complete information or notice..."
                        required
                    ></textarea>

                </div>


                <div class="office-update-form-group">

                    <label for="target_role">
                        Target Audience *
                    </label>

                    <select
                        id="target_role"
                        name="target_role"
                        required>

                        <option value="">
                            Select Audience
                        </option>

                        <option value="STUDENT">
                            Students
                        </option>

                        <option value="FACULTY">
                            Faculty
                        </option>

                        <option value="OFFICE">
                            Office
                        </option>

                        <option value="ALL">
                            Everyone
                        </option>

                    </select>

                </div>


                <div class="office-update-form-group">

                    <label for="publish_date">
                        Publish Date *
                    </label>

                    <input
                        type="date"
                        id="publish_date"
                        name="publish_date"
                        value="<?php echo date('Y-m-d'); ?>"
                        required
                    >

                </div>


                <div class="office-update-form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status">

                        <option value="ACTIVE">
                            Active
                        </option>

                        <option value="INACTIVE">
                            Inactive
                        </option>

                    </select>

                </div>


                <div class="office-update-form-actions">

                    <button
                        type="submit"
                        class="office-update-submit-btn">

                        📢 Publish Update

                    </button>

                </div>

            </form>

        </section>


        <!-- UPDATES LIST -->
        <section class="office-update-list-section">

            <div class="office-update-section-header">

                <div>

                    <h2>Published Updates</h2>

                    <p>
                        Manage information currently available in the system
                    </p>

                </div>

            </div>


            <?php if (count($updates) > 0): ?>

                <div class="office-update-list">

                    <?php foreach ($updates as $update): ?>

                        <?php
                        $update_status =
                            strtoupper(trim($update["status"]));

                        $status_class =
                            ($update_status === "ACTIVE")
                            ? "active"
                            : "inactive";
                        ?>

                        <div class="office-update-card">

                            <div class="office-update-card-icon">
                                📢
                            </div>


                            <div class="office-update-card-content">

                                <div class="office-update-card-top">

                                    <div>

                                        <h3>
                                            <?php
                                            echo htmlspecialchars(
                                                $update["title"]
                                            );
                                            ?>
                                        </h3>

                                        <div class="office-update-meta">

                                            <span>
                                                📅
                                                <?php
                                                echo date(
                                                    "d M Y",
                                                    strtotime(
                                                        $update["publish_date"]
                                                    )
                                                );
                                                ?>
                                            </span>

                                            <span>
                                                👥
                                                <?php
                                                echo htmlspecialchars(
                                                    $update["target_role"]
                                                );
                                                ?>
                                            </span>

                                        </div>

                                    </div>


                                    <span
                                        class="office-update-status <?php echo $status_class; ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $update_status
                                        );
                                        ?>

                                    </span>

                                </div>


                                <p class="office-update-description">

                                    <?php
                                    echo nl2br(
                                        htmlspecialchars(
                                            $update["description"]
                                        )
                                    );
                                    ?>

                                </p>


                                <div class="office-update-card-bottom">

                                    <span>
                                        Posted by Office
                                    </span>


                                    <form
                                        method="POST"
                                        action="updates.php"
                                        onsubmit="return confirm('Are you sure you want to delete this update?');">

                                        <input
                                            type="hidden"
                                            name="delete_update"
                                            value="1"
                                        >

                                        <input
                                            type="hidden"
                                            name="update_id"
                                            value="<?php
                                            echo (int)$update["update_id"];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="office-update-delete-btn">

                                            🗑 Delete

                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="office-update-empty">

                    <div class="office-update-empty-icon">
                        📢
                    </div>

                    <h3>No Information Updates</h3>

                    <p>
                        No notices have been posted yet.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>

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

</script>

</body>
</html>