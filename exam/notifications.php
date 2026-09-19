<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "EXAM_CELL") {
    header("Location: ../auth/exam-login.php");
    exit();
}

require_once "../config/database.php";

$message = "";
$message_type = "";

$user_id = $_SESSION["user_id"];


/* Exam Cell User */
$stmt = $conn->prepare("
    SELECT username
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$exam_user = $result->fetch_assoc();

$stmt->close();


/* Send Notification */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["send_notification"])) {

    $title = trim($_POST["title"] ?? "");
    $notification_message = trim($_POST["notification_message"] ?? "");
    $target_role = trim($_POST["target_role"] ?? "STUDENT");
    $notification_type = trim($_POST["notification_type"] ?? "EXAM");

    if ($title === "" || $notification_message === "") {

        $message = "Please enter notification title and message.";
        $message_type = "error";

    } else {

        /*
         * Get target users.
         * ALL = every active user.
         * Otherwise selected role only.
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
                is_read
            )
            VALUES (?, ?, ?, ?, 0)
        ");

        $count = 0;

        while ($target_user = $users_result->fetch_assoc()) {

            $target_user_id = (int)$target_user["user_id"];

            $insert->bind_param(
                "isss",
                $target_user_id,
                $title,
                $notification_message,
                $notification_type
            );

            if ($insert->execute()) {
                $count++;
            }
        }

        $insert->close();
        $stmt->close();


        if ($count > 0) {

            $message =
                "Notification sent successfully to "
                . $count
                . " user(s).";

            $message_type = "success";

        } else {

            $message =
                "No active users found for the selected role.";

            $message_type = "error";
        }
    }
}


/* Delete Notification */
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_notification"])
) {

    $notification_id =
        (int)($_POST["notification_id"] ?? 0);


    if ($notification_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM notifications
            WHERE notification_id = ?
        ");

        $stmt->bind_param(
            "i",
            $notification_id
        );


        if ($stmt->execute()) {

            $message =
                "Notification deleted successfully.";

            $message_type = "success";

        } else {

            $message =
                "Unable to delete notification.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* Notification Statistics */

$total_notifications = 0;
$unread_notifications = 0;
$read_notifications = 0;


/* Total notifications created for all users */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM notifications
");

if ($result) {

    $row = $result->fetch_assoc();

    $total_notifications =
        (int)$row["total"];
}


/* Unread */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE is_read = 0
");

if ($result) {

    $row = $result->fetch_assoc();

    $unread_notifications =
        (int)$row["total"];
}


/* Read */
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE is_read = 1
");

if ($result) {

    $row = $result->fetch_assoc();

    $read_notifications =
        (int)$row["total"];
}


/* Recent Notifications */

$notifications = [];

$result = $conn->query("
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
    INNER JOIN users u
        ON n.user_id = u.user_id
    ORDER BY n.created_at DESC
    LIMIT 100
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
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

                <span>Exam Cell Portal</span>

            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php">

                <span>🏠</span>
                Dashboard

            </a>


            <a href="exams.php">

                <span>📝</span>
                Exam Schedule

            </a>


            <a href="marks.php">

                <span>📊</span>
                Marks Management

            </a>


            <a href="hall-tickets.php">

                <span>🎫</span>
                Hall Tickets

            </a>


            <a href="notifications.php"
               class="active">

                <span>🔔</span>
                Notifications

            </a>


            <a href="reports.php">

                <span>📄</span>
                Exam Reports

            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">

                <span>🚪</span>
                Logout

            </a>

        </div>

    </aside>


    <!-- MAIN -->

    <main class="dashboard-content">


        <!-- HEADER -->

        <header class="dashboard-header">

            <div class="welcome-text">

                <h1>Notifications</h1>

                <p>
                    Send examination related notifications
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $exam_user["username"]
                            ?? "Exam Cell"
                        );
                        ?>

                    </strong>

                    <span>
                        Examination Cell
                    </span>

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

            <div class="exam-notification-message
                        <?php echo $message_type; ?>">

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <section class="exam-notification-summary">


            <div class="exam-notification-summary-card">

                <div class="exam-notification-summary-icon">
                    🔔
                </div>

                <div>

                    <span>Total Notifications</span>

                    <strong>
                        <?php
                        echo $total_notifications;
                        ?>
                    </strong>

                </div>

            </div>


            <div class="exam-notification-summary-card">

                <div class="exam-notification-summary-icon">
                    📩
                </div>

                <div>

                    <span>Unread</span>

                    <strong>
                        <?php
                        echo $unread_notifications;
                        ?>
                    </strong>

                </div>

            </div>


            <div class="exam-notification-summary-card">

                <div class="exam-notification-summary-icon">
                    ✅
                </div>

                <div>

                    <span>Read</span>

                    <strong>
                        <?php
                        echo $read_notifications;
                        ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- SEND NOTIFICATION -->

        <section class="exam-notification-form-section">


            <div class="exam-notification-section-header">

                <div>

                    <h2>
                        Send New Notification
                    </h2>

                    <p>
                        Notify students, faculty or other users
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="notifications.php"
                class="exam-notification-form">


                <input
                    type="hidden"
                    name="send_notification"
                    value="1"
                >


                <div class="exam-notification-form-group">

                    <label for="title">
                        Notification Title *
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        placeholder="Example: Semester Exam Schedule Released"
                        required
                    >

                </div>


                <div class="exam-notification-form-group">

                    <label for="target_role">
                        Send To *
                    </label>

                    <select
                        id="target_role"
                        name="target_role"
                        required>

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
                            SAC Lead
                        </option>

                        <option value="ADMIN">
                            Admin
                        </option>

                        <option value="ALL">
                            All Active Users
                        </option>

                    </select>

                </div>


                <div class="exam-notification-form-group">

                    <label for="notification_type">
                        Notification Type
                    </label>

                    <select
                        id="notification_type"
                        name="notification_type">

                        <option value="EXAM">
                            Examination
                        </option>

                        <option value="RESULT">
                            Results
                        </option>

                        <option value="HALL_TICKET">
                            Hall Ticket
                        </option>

                        <option value="IMPORTANT">
                            Important
                        </option>

                        <option value="GENERAL">
                            General
                        </option>

                    </select>

                </div>


                <div class="exam-notification-form-group full">

                    <label for="notification_message">
                        Message *
                    </label>

                    <textarea
                        id="notification_message"
                        name="notification_message"
                        rows="5"
                        maxlength="1000"
                        placeholder="Enter notification message..."
                        required></textarea>

                    <div
                        id="notificationCounter"
                        class="exam-notification-counter">

                        0 / 1000

                    </div>

                </div>


                <div class="exam-notification-form-actions">

                    <button
                        type="submit"
                        class="exam-notification-send-btn">

                        🔔 Send Notification

                    </button>

                </div>

            </form>

        </section>


        <!-- NOTIFICATION LIST -->

        <section class="exam-notification-list-section">


            <div class="exam-notification-section-header">

                <div>

                    <h2>
                        Recent Notifications
                    </h2>

                    <p>
                        Latest notifications delivered to users
                    </p>

                </div>

            </div>


            <?php if (count($notifications) > 0): ?>


                <div class="exam-notification-table-wrapper">

                    <table class="exam-notification-table">

                        <thead>

                            <tr>

                                <th>Title</th>

                                <th>Message</th>

                                <th>Recipient</th>

                                <th>Type</th>

                                <th>Status</th>

                                <th>Date</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($notifications as $notification): ?>


                                <tr>


                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $notification["title"]
                                            );
                                            ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <span
                                            class="exam-notification-text">

                                            <?php
                                            echo htmlspecialchars(
                                                $notification["message"]
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $notification["username"]
                                            );
                                            ?>

                                        </strong>

                                        <small>

                                            <?php
                                            echo htmlspecialchars(
                                                $notification["role"]
                                            );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <span
                                            class="exam-notification-type">

                                            <?php
                                            echo htmlspecialchars(
                                                $notification[
                                                    "notification_type"
                                                ]
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php

                                        $read =
                                            (int)$notification[
                                                "is_read"
                                            ];

                                        ?>

                                        <?php if ($read === 1): ?>

                                            <span
                                                class="exam-notification-status read">

                                                READ

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="exam-notification-status unread">

                                                UNREAD

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo date(
                                            "d M Y, h:i A",
                                            strtotime(
                                                $notification[
                                                    "created_at"
                                                ]
                                            )
                                        );
                                        ?>

                                    </td>


                                    <td>

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
                                                class="exam-notification-delete-btn">

                                                🗑

                                            </button>

                                        </form>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="exam-notification-empty">

                    <div>
                        🔔
                    </div>

                    <h3>
                        No Notifications
                    </h3>

                    <p>
                        Send your first examination notification
                        using the form above.
                    </p>

                </div>


            <?php endif; ?>


        </section>

    </main>

</div>


<script>

/* Theme */

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

    themeToggle.addEventListener(
        "click",
        function () {

            document.body.classList.toggle(
                "dark-theme"
            );

            const isDark =
                document.body.classList.contains(
                    "dark-theme"
                );

            localStorage.setItem(
                "theme",
                isDark ? "dark" : "light"
            );

            themeToggle.textContent =
                isDark ? "☀️" : "🌙";

        }
    );

}


/* Character Counter */

const messageBox =
    document.getElementById(
        "notification_message"
    );

const counter =
    document.getElementById(
        "notificationCounter"
    );


if (messageBox && counter) {

    messageBox.addEventListener(
        "input",
        function () {

            counter.textContent =
                messageBox.value.length
                + " / 1000";

        }
    );

}

</script>

</body>
</html>