<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "OFFICE") {
    header("Location: ../auth/office-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

$message = "";
$message_type = "";


/* Office user */
$stmt = $conn->prepare("
    SELECT username
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$office_user = $result->fetch_assoc();

$stmt->close();


/* Send notification */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["send_notification"])) {

    $target_user = trim($_POST["target_user"] ?? "");
    $title = trim($_POST["title"] ?? "");
    $notification_message = trim($_POST["message"] ?? "");
    $notification_type = trim($_POST["notification_type"] ?? "GENERAL");

    if ($target_user === "" || $title === "" || $notification_message === "") {

        $message = "Please fill all required fields.";
        $message_type = "error";

    } else {

        /* Send to all users */
        if ($target_user === "ALL") {

            $stmt = $conn->prepare("
                INSERT INTO notifications
                (user_id, title, message, notification_type, is_read)
                SELECT
                    user_id,
                    ?,
                    ?,
                    ?,
                    0
                FROM users
                WHERE status = 'ACTIVE'
            ");

            $stmt->bind_param(
                "sss",
                $title,
                $notification_message,
                $notification_type
            );

            if ($stmt->execute()) {

                $message = "Notification sent to all active users.";
                $message_type = "success";

            } else {

                $message = "Failed to send notification.";
                $message_type = "error";
            }

            $stmt->close();

        } else {

            $target_user_id = (int)$target_user;

            $check = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE user_id = ?
                AND status = 'ACTIVE'
                LIMIT 1
            ");

            $check->bind_param("i", $target_user_id);
            $check->execute();

            $check_result = $check->get_result();

            if ($check_result->num_rows === 0) {

                $message = "Selected user is not available.";
                $message_type = "error";

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO notifications
                    (user_id, title, message, notification_type, is_read)
                    VALUES (?, ?, ?, ?, 0)
                ");

                $stmt->bind_param(
                    "isss",
                    $target_user_id,
                    $title,
                    $notification_message,
                    $notification_type
                );

                if ($stmt->execute()) {

                    $message = "Notification sent successfully.";
                    $message_type = "success";

                } else {

                    $message = "Failed to send notification.";
                    $message_type = "error";
                }

                $stmt->close();
            }

            $check->close();
        }
    }
}


/* Delete notification */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_notification"])) {

    $notification_id = (int)($_POST["notification_id"] ?? 0);

    if ($notification_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM notifications
            WHERE notification_id = ?
        ");

        $stmt->bind_param("i", $notification_id);

        if ($stmt->execute()) {

            $message = "Notification deleted successfully.";
            $message_type = "success";

        } else {

            $message = "Unable to delete notification.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* Get active users */
$users = [];

$user_query = $conn->query("
    SELECT
        u.user_id,
        u.username,
        u.role,
        s.full_name AS student_name,
        f.full_name AS faculty_name
    FROM users u
    LEFT JOIN students s
        ON u.user_id = s.user_id
    LEFT JOIN faculty f
        ON u.user_id = f.user_id
    WHERE u.status = 'ACTIVE'
    ORDER BY u.role, u.username
");

if ($user_query) {

    while ($row = $user_query->fetch_assoc()) {
        $users[] = $row;
    }
}


/* Recent notifications */
$notifications = [];

$notification_query = $conn->query("
    SELECT
        n.notification_id,
        n.user_id,
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
    LIMIT 50
");

if ($notification_query) {

    while ($row = $notification_query->fetch_assoc()) {
        $notifications[] = $row;
    }
}


/* Statistics */
$total_notifications = count($notifications);

$unread_notifications = 0;

foreach ($notifications as $notification) {

    if ((int)$notification["is_read"] === 0) {
        $unread_notifications++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Notifications | SmartCampus</title>

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

            <a href="updates.php">
                <span>📢</span>
                Information Updates
            </a>

            <a href="notifications.php" class="active">
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

                <h1>Notifications</h1>

                <p>
                    Send important notifications to SmartCampus users
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

            <div class="office-notification-message <?php echo $message_type; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->
        <section class="office-notification-summary">

            <div class="office-notification-summary-card">

                <div class="office-notification-summary-icon">
                    🔔
                </div>

                <div>

                    <span>Recent Notifications</span>

                    <strong>
                        <?php echo $total_notifications; ?>
                    </strong>

                </div>

            </div>


            <div class="office-notification-summary-card">

                <div class="office-notification-summary-icon">
                    📩
                </div>

                <div>

                    <span>Unread</span>

                    <strong>
                        <?php echo $unread_notifications; ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- SEND NOTIFICATION -->
        <section class="office-notification-form-section">

            <div class="office-notification-section-header">

                <h2>Send New Notification</h2>

                <p>
                    Send a notification to a specific user or everyone
                </p>

            </div>


            <form
                method="POST"
                action="notifications.php"
                class="office-notification-form">

                <input
                    type="hidden"
                    name="send_notification"
                    value="1"
                >


                <div class="office-notification-form-group">

                    <label for="target_user">
                        Recipient *
                    </label>

                    <select
                        id="target_user"
                        name="target_user"
                        required>

                        <option value="">
                            Select Recipient
                        </option>

                        <option value="ALL">
                            📢 All Active Users
                        </option>

                        <?php foreach ($users as $user): ?>

                            <?php
                            $display_name =
                                $user["student_name"]
                                ?: $user["faculty_name"]
                                ?: $user["username"];
                            ?>

                            <option
                                value="<?php echo (int)$user["user_id"]; ?>">

                                <?php
                                echo htmlspecialchars(
                                    $display_name
                                    . " — "
                                    . $user["role"]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="office-notification-form-group">

                    <label for="notification_type">
                        Notification Type
                    </label>

                    <select
                        id="notification_type"
                        name="notification_type">

                        <option value="GENERAL">
                            General
                        </option>

                        <option value="IMPORTANT">
                            Important
                        </option>

                        <option value="EXAM">
                            Exam
                        </option>

                        <option value="FEE">
                            Fee
                        </option>

                        <option value="ACADEMIC">
                            Academic
                        </option>

                        <option value="EVENT">
                            Event
                        </option>

                    </select>

                </div>


                <div class="office-notification-form-group full">

                    <label for="title">
                        Notification Title *
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        placeholder="Enter notification title"
                        required
                    >

                </div>


                <div class="office-notification-form-group full">

                    <label for="message">
                        Message *
                    </label>

                    <textarea
                        id="message"
                        name="message"
                        rows="5"
                        placeholder="Enter notification message..."
                        required
                    ></textarea>

                </div>


                <div class="office-notification-form-actions">

                    <button
                        type="submit"
                        class="office-notification-send-btn">

                        🔔 Send Notification

                    </button>

                </div>

            </form>

        </section>


        <!-- RECENT NOTIFICATIONS -->
        <section class="office-notification-list-section">

            <div class="office-notification-section-header">

                <h2>Recent Notifications</h2>

                <p>
                    Latest notifications stored in the system
                </p>

            </div>


            <?php if (count($notifications) > 0): ?>

                <div class="office-notification-list">

                    <?php foreach ($notifications as $notification): ?>

                        <div class="office-notification-card">

                            <div class="office-notification-card-icon">
                                🔔
                            </div>


                            <div class="office-notification-card-content">

                                <div class="office-notification-card-top">

                                    <div>

                                        <h3>
                                            <?php
                                            echo htmlspecialchars(
                                                $notification["title"]
                                            );
                                            ?>
                                        </h3>

                                        <div class="office-notification-meta">

                                            <span>
                                                👤
                                                <?php
                                                echo htmlspecialchars(
                                                    $notification["username"]
                                                    ?? "User"
                                                );
                                                ?>
                                            </span>

                                            <span>
                                                <?php
                                                echo htmlspecialchars(
                                                    $notification["role"]
                                                    ?? ""
                                                );
                                                ?>
                                            </span>

                                            <span>
                                                📅
                                                <?php
                                                echo date(
                                                    "d M Y, h:i A",
                                                    strtotime(
                                                        $notification["created_at"]
                                                    )
                                                );
                                                ?>
                                            </span>

                                        </div>

                                    </div>


                                    <span class="office-notification-type">

                                        <?php
                                        echo htmlspecialchars(
                                            $notification[
                                                "notification_type"
                                            ]
                                        );
                                        ?>

                                    </span>

                                </div>


                                <p>

                                    <?php
                                    echo nl2br(
                                        htmlspecialchars(
                                            $notification["message"]
                                        )
                                    );
                                    ?>

                                </p>


                                <div class="office-notification-card-bottom">

                                    <span class="
                                        <?php
                                        echo ((int)$notification["is_read"] === 1)
                                            ? "read"
                                            : "unread";
                                        ?>">

                                        <?php
                                        echo ((int)$notification["is_read"] === 1)
                                            ? "Read"
                                            : "Unread";
                                        ?>

                                    </span>


                                    <form
                                        method="POST"
                                        action="notifications.php"
                                        onsubmit="return confirm('Delete this notification?');">

                                        <input
                                            type="hidden"
                                            name="delete_notification"
                                            value="1"
                                        >

                                        <input
                                            type="hidden"
                                            name="notification_id"
                                            value="<?php
                                            echo (int)$notification[
                                                "notification_id"
                                            ];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="office-notification-delete-btn">

                                            🗑 Delete

                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="office-notification-empty">

                    <div class="office-notification-empty-icon">
                        🔔
                    </div>

                    <h3>No Notifications</h3>

                    <p>
                        No notifications have been created yet.
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