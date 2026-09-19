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

/* Fee records */
$stmt = $conn->prepare("
    SELECT
        fee_id,
        fee_type,
        amount,
        paid_amount,
        due_date,
        payment_status,
        transaction_id,
        receipt_number,
        paid_at
    FROM fees
    WHERE student_id = ?
    ORDER BY due_date DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();

$fees = $stmt->get_result();

/* Calculate summary */
$total_fee = 0;
$total_paid = 0;

$fee_rows = [];

while ($row = $fees->fetch_assoc()) {

    $amount = (float)$row["amount"];
    $paid = (float)$row["paid_amount"];

    $total_fee += $amount;
    $total_paid += $paid;

    $fee_rows[] = $row;
}

$total_pending = $total_fee - $total_paid;

if ($total_pending < 0) {
    $total_pending = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fees & Payments | SmartCampus</title>

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

            <a href="fees.php" class="active">
                💳
                <span>Fees & Payments</span>
            </a>

            <a href="projects.php">
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

                <h1>Fees & Payments</h1>

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


        <!-- SUMMARY CARDS -->

        <div class="fees-summary">

            <div class="fees-summary-card">

                <div class="fees-summary-icon">
                    💰
                </div>

                <div>

                    <span>Total Fee</span>

                    <strong>
                        ₹<?php echo number_format($total_fee, 2); ?>
                    </strong>

                </div>

            </div>


            <div class="fees-summary-card">

                <div class="fees-summary-icon">
                    ✅
                </div>

                <div>

                    <span>Total Paid</span>

                    <strong>
                        ₹<?php echo number_format($total_paid, 2); ?>
                    </strong>

                </div>

            </div>


            <div class="fees-summary-card">

                <div class="fees-summary-icon">
                    ⏳
                </div>

                <div>

                    <span>Pending Amount</span>

                    <strong>
                        ₹<?php echo number_format($total_pending, 2); ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- PAYMENT PROGRESS -->

        <section class="fees-progress-card">

            <div class="fees-progress-header">

                <div>

                    <h2>Payment Progress</h2>

                    <p>Your overall fee payment status</p>

                </div>

                <strong>

                    <?php
                    $payment_percentage = $total_fee > 0
                        ? ($total_paid / $total_fee) * 100
                        : 0;

                    echo number_format(
                        min($payment_percentage, 100),
                        1
                    );
                    ?>%

                </strong>

            </div>


            <div class="fees-progress">

                <span
                    style="width: <?php echo min($payment_percentage, 100); ?>%;">
                </span>

            </div>


            <div class="fees-progress-info">

                <span>
                    Paid: ₹<?php echo number_format($total_paid, 2); ?>
                </span>

                <span>
                    Remaining: ₹<?php echo number_format($total_pending, 2); ?>
                </span>

            </div>

        </section>


        <!-- FEE DETAILS -->

        <section class="fees-card">

            <div class="fees-card-header">

                <div>

                    <h2>Fee Details</h2>

                    <p>
                        View your fee records and payment information
                    </p>

                </div>

            </div>


            <?php if (count($fee_rows) > 0): ?>

                <div class="fees-table-wrapper">

                    <table class="fees-table">

                        <thead>

                            <tr>

                                <th>Fee Type</th>

                                <th>Total Amount</th>

                                <th>Paid</th>

                                <th>Pending</th>

                                <th>Due Date</th>

                                <th>Status</th>

                                <th>Receipt</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($fee_rows as $fee): ?>

                            <?php

                            $amount = (float)$fee["amount"];

                            $paid = (float)$fee["paid_amount"];

                            $pending = max($amount - $paid, 0);

                            $status = strtoupper(
                                $fee["payment_status"] ?: "PENDING"
                            );

                            $status_class = strtolower(
                                str_replace(" ", "-", $status)
                            );

                            ?>

                            <tr>

                                <td>

                                    <div class="fee-type">

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $fee["fee_type"]
                                            );
                                            ?>
                                        </strong>

                                        <span>
                                            Fee Record
                                        </span>

                                    </div>

                                </td>


                                <td>

                                    <strong>
                                        ₹<?php
                                        echo number_format(
                                            $amount,
                                            2
                                        );
                                        ?>
                                    </strong>

                                </td>


                                <td class="fee-paid">

                                    ₹<?php
                                    echo number_format(
                                        $paid,
                                        2
                                    );
                                    ?>

                                </td>


                                <td class="fee-pending">

                                    ₹<?php
                                    echo number_format(
                                        $pending,
                                        2
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    if (!empty($fee["due_date"])) {

                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $fee["due_date"]
                                            )
                                        );

                                    } else {

                                        echo "—";

                                    }
                                    ?>

                                </td>


                                <td>

                                    <span class="fee-status <?php
                                        echo htmlspecialchars(
                                            $status_class
                                        );
                                    ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $status
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <?php if (!empty($fee["receipt_number"])): ?>

                                        <div class="fee-receipt">

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $fee["receipt_number"]
                                                );
                                                ?>
                                            </strong>

                                            <?php if (!empty($fee["paid_at"])): ?>

                                                <small>
                                                    <?php
                                                    echo date(
                                                        "d M Y",
                                                        strtotime(
                                                            $fee["paid_at"]
                                                        )
                                                    );
                                                    ?>
                                                </small>

                                            <?php endif; ?>

                                        </div>

                                    <?php else: ?>

                                        <span class="fee-no-receipt">
                                            Not Available
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <!-- EMPTY STATE -->

                <div class="fees-empty">

                    <div class="fees-empty-icon">
                        💳
                    </div>

                    <h3>No Fee Records</h3>

                    <p>
                        Your fee details have not been added yet.
                        Please contact the college office for more information.
                    </p>

                </div>

            <?php endif; ?>

        </section>

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