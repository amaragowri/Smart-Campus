<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "OFFICE") {
    header("Location: ../auth/office-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

/* =========================
   OFFICE USER DETAILS
========================= */

$stmt = $conn->prepare("
    SELECT user_id, username, role, status
    FROM users
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Office user not found.");
}


/* =========================
   STUDENT COUNT
========================= */

$student_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
");

if ($result) {
    $row = $result->fetch_assoc();
    $student_count = intval($row["total"]);
}


/* =========================
   FACULTY COUNT
========================= */

$faculty_count = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM faculty
");

if ($result) {
    $row = $result->fetch_assoc();
    $faculty_count = intval($row["total"]);
}


/* =========================
   PENDING FEES
========================= */

$pending_fees = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM fees
    WHERE payment_status IN ('PENDING', 'PARTIAL')
");

if ($result) {
    $row = $result->fetch_assoc();
    $pending_fees = intval($row["total"]);
}


/* =========================
   TOTAL FEE COLLECTION
========================= */

$total_collection = 0;

$result = $conn->query("
    SELECT COALESCE(SUM(paid_amount), 0) AS total
    FROM fees
");

if ($result) {
    $row = $result->fetch_assoc();
    $total_collection = floatval($row["total"]);
}


/* =========================
   RECENT UPDATES
========================= */

$updates = [];

$stmt = $conn->prepare("
    SELECT
        update_id,
        title,
        description,
        target_role,
        publish_date,
        status
    FROM information_updates
    ORDER BY publish_date DESC
    LIMIT 5
");

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $updates[] = $row;
}

$stmt->close();


/* =========================
   RECENT FEE PAYMENTS
========================= */

$payments = [];

$stmt = $conn->prepare("
    SELECT
        fp.payment_id,
        fp.amount_paid,
        fp.payment_method,
        fp.transaction_id,
        fp.receipt_number,
        fp.paid_at,
        s.full_name,
        s.roll_number
    FROM fee_payments fp
    INNER JOIN students s
        ON fp.student_id = s.student_id
    ORDER BY fp.paid_at DESC
    LIMIT 5
");

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Office Dashboard | SmartCampus</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>

<body>

<div class="dashboard">

    <!-- =========================
         SIDEBAR
    ========================== -->

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

            <a
                href="dashboard.php"
                class="active"
            >
                <span>🏠</span>
                Dashboard
            </a>

            <a href="students.php">
                <span>👨‍🎓</span>
                Students
            </a>

            <a href="fees.php">
                <span>💳</span>
                Fees
            </a>

            <a href="updates.php">
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


    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="dashboard-content">


        <!-- HEADER -->

        <header class="dashboard-header">

            <div class="welcome-text">

                <span class="dashboard-label">
                    Office Portal
                </span>

                <h1>Office Dashboard</h1>

                <p>
                    Manage students, fees and college information.
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <div class="student-avatar">
                        O
                    </div>

                    <div>

                        <strong>
                            Office Administrator
                        </strong>

                        <span>
                            <?php
                            echo htmlspecialchars(
                                strtoupper($user["role"])
                            );
                            ?>
                        </span>

                    </div>

                </div>


                <button
                    type="button"
                    class="dashboard-theme-toggle"
                    id="themeToggle"
                    title="Toggle theme"
                >
                    🌙
                </button>

            </div>

        </header>


        <!-- =========================
             SUMMARY
        ========================== -->

        <section class="office-summary-grid">


            <div class="office-summary-card">

                <div class="office-summary-icon">
                    👨‍🎓
                </div>

                <div>

                    <span>Total Students</span>

                    <strong>
                        <?php echo $student_count; ?>
                    </strong>

                    <small>
                        Registered students
                    </small>

                </div>

            </div>


            <div class="office-summary-card">

                <div class="office-summary-icon">
                    👨‍🏫
                </div>

                <div>

                    <span>Total Faculty</span>

                    <strong>
                        <?php echo $faculty_count; ?>
                    </strong>

                    <small>
                        Faculty members
                    </small>

                </div>

            </div>


            <div class="office-summary-card">

                <div class="office-summary-icon">
                    ⚠️
                </div>

                <div>

                    <span>Pending Fees</span>

                    <strong>
                        <?php echo $pending_fees; ?>
                    </strong>

                    <small>
                        Pending / partial payments
                    </small>

                </div>

            </div>


            <div class="office-summary-card">

                <div class="office-summary-icon">
                    ₹
                </div>

                <div>

                    <span>Fee Collection</span>

                    <strong>
                        ₹<?php
                        echo number_format(
                            $total_collection,
                            2
                        );
                        ?>
                    </strong>

                    <small>
                        Total paid amount
                    </small>

                </div>

            </div>

        </section>


        <!-- =========================
             QUICK ACTIONS
        ========================== -->

        <section class="office-quick-section">

            <div class="office-section-header">

                <div>

                    <span>
                        Quick Actions
                    </span>

                    <h2>
                        Office Management
                    </h2>

                </div>

            </div>


            <div class="office-quick-grid">


                <a
                    href="students.php"
                    class="office-quick-card"
                >

                    <div class="office-quick-icon">
                        👨‍🎓
                    </div>

                    <div>

                        <strong>
                            Student Management
                        </strong>

                        <span>
                            View and manage student records
                        </span>

                    </div>

                    <b>→</b>

                </a>


                <a
                    href="fees.php"
                    class="office-quick-card"
                >

                    <div class="office-quick-icon">
                        💳
                    </div>

                    <div>

                        <strong>
                            Fee Management
                        </strong>

                        <span>
                            Track payments and pending fees
                        </span>

                    </div>

                    <b>→</b>

                </a>


                <a
                    href="updates.php"
                    class="office-quick-card"
                >

                    <div class="office-quick-icon">
                        📢
                    </div>

                    <div>

                        <strong>
                            Information Updates
                        </strong>

                        <span>
                            Publish important college updates
                        </span>

                    </div>

                    <b>→</b>

                </a>


                <a
                    href="notifications.php"
                    class="office-quick-card"
                >

                    <div class="office-quick-icon">
                        🔔
                    </div>

                    <div>

                        <strong>
                            Notifications
                        </strong>

                        <span>
                            Send notifications to users
                        </span>

                    </div>

                    <b>→</b>

                </a>

            </div>

        </section>


        <!-- =========================
             LOWER CONTENT
        ========================== -->

        <section class="office-dashboard-grid">


            <!-- RECENT PAYMENTS -->

            <div class="office-panel">

                <div class="office-panel-header">

                    <div>

                        <span>
                            Fee Activity
                        </span>

                        <h2>
                            Recent Payments
                        </h2>

                    </div>

                    <a href="fees.php">
                        View All
                    </a>

                </div>


                <?php if (!empty($payments)): ?>

                    <div class="office-payment-list">

                        <?php foreach ($payments as $payment): ?>

                            <div class="office-payment-item">

                                <div class="office-payment-icon">
                                    ₹
                                </div>

                                <div class="office-payment-info">

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $payment["full_name"]
                                        );
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $payment["roll_number"]
                                        );
                                        ?>
                                    </span>

                                </div>

                                <div class="office-payment-amount">

                                    <strong>
                                        ₹<?php
                                        echo number_format(
                                            floatval(
                                                $payment["amount_paid"]
                                            ),
                                            2
                                        );
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $payment["payment_method"]
                                        );
                                        ?>
                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="office-empty-small">
                        <span>💳</span>
                        <p>No recent payments.</p>
                    </div>

                <?php endif; ?>

            </div>


            <!-- INFORMATION UPDATES -->

            <div class="office-panel">

                <div class="office-panel-header">

                    <div>

                        <span>
                            Communication
                        </span>

                        <h2>
                            Recent Updates
                        </h2>

                    </div>

                    <a href="updates.php">
                        View All
                    </a>

                </div>


                <?php if (!empty($updates)): ?>

                    <div class="office-update-list">

                        <?php foreach ($updates as $update): ?>

                            <div class="office-update-item">

                                <div class="office-update-icon">
                                    📢
                                </div>

                                <div>

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $update["title"]
                                        );
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $update["target_role"]
                                        );
                                        ?>
                                    </span>

                                    <small>
                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $update["publish_date"]
                                            )
                                        );
                                        ?>
                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="office-empty-small">
                        <span>📢</span>
                        <p>No information updates yet.</p>
                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>


<script>

/* =========================
   THEME TOGGLE
========================= */

const themeToggle =
    document.getElementById("themeToggle");

const savedTheme =
    localStorage.getItem("smartcampus-theme");

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
                "smartcampus-theme",
                isDark ? "dark" : "light"
            );

            themeToggle.textContent =
                isDark ? "☀️" : "🌙";

        }
    );

}

</script>

</body>
</html>