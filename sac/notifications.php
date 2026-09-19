<?php
session_start();

require_once "../config/database.php";

$allowed_roles = ['SAC_LEAD', 'DOMAIN_LEAD', 'CR', 'LR'];

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], $allowed_roles)
) {
    header("Location: ../auth/sac-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

/* =========================
   SEND NOTIFICATION
========================= */

if (isset($_POST['send_notification'])) {

    $title = trim($_POST['title']);
    $notification_message = trim($_POST['notification_message']);
    $target_role = $_POST['target_role'];
    $notification_type = $_POST['notification_type'];

    if (
        $title === "" ||
        $notification_message === ""
    ) {
        $error = "Please fill all required fields.";
    } else {

        /*
         * ALL = send to every active user
         * Otherwise send only to selected role
         */

        if ($target_role === "ALL") {

            $stmt = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE status = 'ACTIVE'
            ");

        } else {

            $stmt = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE role = ?
                AND status = 'ACTIVE'
            ");

            $stmt->bind_param("s", $target_role);
        }

        $stmt->execute();
        $users_result = $stmt->get_result();

        $insert = $conn->prepare("
            INSERT INTO notifications
            (
                user_id,
                title,
                message,
                notification_type,
                is_read,
                created_at
            )
            VALUES (?, ?, ?, ?, 0, NOW())
        ");

        $sent_count = 0;

        while ($user = $users_result->fetch_assoc()) {

            $recipient_id = $user['user_id'];

            $insert->bind_param(
                "isss",
                $recipient_id,
                $title,
                $notification_message,
                $notification_type
            );

            if ($insert->execute()) {
                $sent_count++;
            }
        }

        $insert->close();
        $stmt->close();

        if ($sent_count > 0) {
            $message = "Notification sent successfully to {$sent_count} user(s).";
        } else {
            $error = "No active users found for the selected audience.";
        }
    }
}


/* =========================
   DELETE NOTIFICATION
========================= */

if (isset($_GET['delete'])) {

    $delete_id = intval($_GET['delete']);

    $stmt = $conn->prepare("
        DELETE FROM notifications
        WHERE notification_id = ?
    ");

    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "Notification deleted successfully.";
    } else {
        $error = "Failed to delete notification.";
    }

    $stmt->close();
}


/* =========================
   STATISTICS
========================= */

$total_notifications = $conn->query("
    SELECT COUNT(*) AS total
    FROM notifications
")->fetch_assoc()['total'];

$unread_notifications = $conn->query("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE is_read = 0
")->fetch_assoc()['total'];

$read_notifications = $conn->query("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE is_read = 1
")->fetch_assoc()['total'];


/* =========================
   RECENT NOTIFICATIONS
========================= */

$notifications = $conn->query("
    SELECT
        n.notification_id,
        n.title,
        n.message,
        n.notification_type,
        n.is_read,
        n.created_at,
        u.username,
        u.role
    FROM notifications n
    LEFT JOIN users u
        ON n.user_id = u.user_id
    ORDER BY n.created_at DESC
    LIMIT 100
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SAC Notifications | SmartCampus</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="sac-notifications-page">


<!-- =========================
     SIDEBAR
========================= -->

<aside class="sac-sidebar">

    <div class="sac-brand">

        <div class="sac-logo">
            SC
        </div>

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

        <a href="updates.php">
            📢 <span>Updates</span>
        </a>

        <a href="notifications.php" class="active">
            🔔 <span>Notifications</span>
        </a>

        <a href="reports.php">
            📊 <span>Reports</span>
        </a>

    </nav>


    <div class="sac-sidebar-bottom">

        <button
            type="button"
            onclick="toggleTheme()"
            class="theme-btn"
        >
            🌙 <span>Toggle Theme</span>
        </button>

        <a
            href="../auth/logout.php"
            class="logout-btn"
        >
            🚪 <span>Logout</span>
        </a>

    </div>

</aside>


<!-- =========================
     MAIN CONTENT
========================= -->

<main class="sac-main-content">


    <!-- TOPBAR -->

    <header class="sac-topbar">

        <div>

            <h1>Notifications</h1>

            <p>
                Send and manage important SAC notifications.
            </p>

        </div>


        <div class="sac-user-area">

            <div class="sac-user-avatar">
                S
            </div>

            <div>

                <strong>SAC Portal</strong>

                <small>
                    <?php echo htmlspecialchars($_SESSION['role']); ?>
                </small>

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


    <!-- =========================
         STATISTICS
    ========================= -->

    <section class="sac-notification-stats">

        <div class="sac-notification-stat-card">

            <div class="stat-icon">
                🔔
            </div>

            <div>

                <span>Total Notifications</span>

                <strong>
                    <?php echo $total_notifications; ?>
                </strong>

            </div>

        </div>


        <div class="sac-notification-stat-card">

            <div class="stat-icon">
                📩
            </div>

            <div>

                <span>Unread</span>

                <strong>
                    <?php echo $unread_notifications; ?>
                </strong>

            </div>

        </div>


        <div class="sac-notification-stat-card">

            <div class="stat-icon">
                ✓
            </div>

            <div>

                <span>Read</span>

                <strong>
                    <?php echo $read_notifications; ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =========================
         SEND NOTIFICATION
    ========================= -->

    <section class="sac-notification-form-card">

        <div class="sac-section-heading">

            <div>

                <h2>Send Notification</h2>

                <p>
                    Send an important message to selected users.
                </p>

            </div>

        </div>


        <form
            method="POST"
            class="sac-notification-form"
        >

            <div class="form-group">

                <label>
                    Notification Title *
                </label>

                <input
                    type="text"
                    name="title"
                    placeholder="Example: Club Registration Open"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Message *
                </label>

                <textarea
                    name="notification_message"
                    id="notificationMessage"
                    rows="5"
                    maxlength="500"
                    placeholder="Write notification message..."
                    required
                ></textarea>

                <small class="message-counter">
                    0 / 500 characters
                </small>

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label>
                        Target Audience
                    </label>

                    <select name="target_role">

                        <option value="ALL">
                            Everyone
                        </option>

                        <option value="STUDENT">
                            Students
                        </option>

                        <option value="FACULTY">
                            Faculty
                        </option>

                        <option value="EXAM_CELL">
                            Exam Cell
                        </option>

                        <option value="OFFICE">
                            Office
                        </option>

                        <option value="SAC_LEAD">
                            SAC
                        </option>

                        <option value="ADMIN">
                            Admin
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Notification Type
                    </label>

                    <select name="notification_type">

                        <option value="GENERAL">
                            General
                        </option>

                        <option value="IMPORTANT">
                            Important
                        </option>

                        <option value="EVENT">
                            Event
                        </option>

                        <option value="CLUB">
                            Club
                        </option>

                        <option value="ACADEMIC">
                            Academic
                        </option>

                    </select>

                </div>

            </div>


            <button
                type="submit"
                name="send_notification"
                class="sac-primary-btn"
            >
                🔔 Send Notification
            </button>

        </form>

    </section>


    <!-- =========================
         RECENT NOTIFICATIONS
    ========================= -->

    <section class="sac-notification-list-card">

        <div class="sac-section-heading">

            <div>

                <h2>Recent Notifications</h2>

                <p>
                    Notifications currently stored in the system.
                </p>

            </div>

        </div>


        <div class="sac-notification-table-wrapper">

            <table class="sac-notification-table">

                <thead>

                    <tr>

                        <th>Notification</th>

                        <th>Recipient</th>

                        <th>Type</th>

                        <th>Status</th>

                        <th>Date</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                <?php if (
                    $notifications &&
                    $notifications->num_rows > 0
                ): ?>

                    <?php while (
                        $row = $notifications->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>

                                <div class="notification-title">
                                    <?php
                                    echo htmlspecialchars(
                                        $row['title']
                                    );
                                    ?>
                                </div>

                                <div class="notification-message">

                                    <?php
                                    $msg = $row['message'];

                                    echo htmlspecialchars(
                                        strlen($msg) > 90
                                        ? substr($msg, 0, 90) . "..."
                                        : $msg
                                    );
                                    ?>

                                </div>

                            </td>


                            <td>

                                <div class="recipient-name">
                                    <?php
                                    echo htmlspecialchars(
                                        $row['username'] ?? 'User'
                                    );
                                    ?>
                                </div>

                                <span class="recipient-role">
                                    <?php
                                    echo htmlspecialchars(
                                        $row['role'] ?? '-'
                                    );
                                    ?>
                                </span>

                            </td>


                            <td>

                                <span class="notification-type">

                                    <?php
                                    echo htmlspecialchars(
                                        $row['notification_type']
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <?php if ($row['is_read'] == 1): ?>

                                    <span class="notification-status read">
                                        Read
                                    </span>

                                <?php else: ?>

                                    <span class="notification-status unread">
                                        Unread
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    "d M Y, h:i A",
                                    strtotime($row['created_at'])
                                );
                                ?>

                            </td>


                            <td>

                                <a
                                    href="notifications.php?delete=<?php echo $row['notification_id']; ?>"
                                    class="delete-btn"
                                    onclick="return confirm('Delete this notification?');"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            class="no-data"
                        >
                            No notifications found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>


<script>

/* =========================
   THEME
========================= */

function toggleTheme() {

    document.body.classList.toggle("light-theme");

    if (
        document.body.classList.contains("light-theme")
    ) {

        localStorage.setItem(
            "smartcampus-theme",
            "light"
        );

    } else {

        localStorage.setItem(
            "smartcampus-theme",
            "dark"
        );

    }
}


if (
    localStorage.getItem("smartcampus-theme") === "light"
) {

    document.body.classList.add("light-theme");

}


/* =========================
   MESSAGE COUNTER
========================= */

const messageBox =
    document.getElementById("notificationMessage");

const counter =
    document.querySelector(".message-counter");


if (messageBox && counter) {

    function updateCounter() {

        counter.textContent =
            messageBox.value.length + " / 500 characters";

    }

    messageBox.addEventListener(
        "input",
        updateCounter
    );

    updateCounter();
}

</script>

</body>
</html>