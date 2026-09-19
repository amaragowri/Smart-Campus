<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "OFFICE") {
    header("Location: ../auth/office-login.php");
    exit();
}

require_once "../config/database.php";

$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");

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


/* Fee summary */
$summary_query = $conn->query("
    SELECT
        COALESCE(SUM(amount), 0) AS total_fee,
        COALESCE(SUM(paid_amount), 0) AS total_paid
    FROM fees
");

$summary = $summary_query->fetch_assoc();

$total_fee = (float)$summary["total_fee"];
$total_paid = (float)$summary["total_paid"];
$total_pending = $total_fee - $total_paid;

if ($total_pending < 0) {
    $total_pending = 0;
}


/* Pending records */
$pending_query = $conn->query("
    SELECT COUNT(*) AS total
    FROM fees
    WHERE payment_status IN ('PENDING', 'PARTIAL')
");

$pending_row = $pending_query->fetch_assoc();
$pending_records = (int)$pending_row["total"];


/* Fee records */
$sql = "
    SELECT
        f.fee_id,
        f.student_id,
        f.fee_type,
        f.amount,
        f.paid_amount,
        f.due_date,
        f.payment_status,
        f.transaction_id,
        f.receipt_number,
        f.paid_at,
        s.full_name,
        s.roll_number,
        s.department,
        s.year,
        s.section
    FROM fees f
    INNER JOIN students s
        ON f.student_id = s.student_id
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== "") {

    $sql .= "
        AND (
            s.full_name LIKE ?
            OR s.roll_number LIKE ?
            OR f.receipt_number LIKE ?
            OR f.transaction_id LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ssss";
}

if ($status !== "") {
    $sql .= " AND f.payment_status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= "
    ORDER BY
        CASE
            WHEN f.payment_status = 'PENDING' THEN 1
            WHEN f.payment_status = 'PARTIAL' THEN 2
            WHEN f.payment_status = 'PAID' THEN 3
            ELSE 4
        END,
        f.due_date ASC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

$fees = [];

while ($row = $result->fetch_assoc()) {
    $fees[] = $row;
}

$stmt->close();


/* Format status */
function feeStatusClass($status)
{
    $status = strtoupper(trim($status));

    if ($status === "PAID") {
        return "paid";
    }

    if ($status === "PARTIAL") {
        return "partial";
    }

    return "pending";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Fee Management | SmartCampus</title>

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

            <a href="fees.php" class="active">
                <span>💰</span>
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


    <!-- MAIN CONTENT -->
    <main class="dashboard-content">

        <!-- HEADER -->
        <header class="dashboard-header">

            <div class="welcome-text">

                <h1>Fee Management</h1>

                <p>
                    Monitor student fees and payment records
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


        <!-- SUMMARY CARDS -->
        <section class="office-fee-summary">

            <div class="office-fee-card">

                <div class="office-fee-icon">
                    💰
                </div>

                <div>

                    <span>Total Fee</span>

                    <strong>
                        ₹<?php echo number_format($total_fee, 2); ?>
                    </strong>

                </div>

            </div>


            <div class="office-fee-card">

                <div class="office-fee-icon">
                    ✅
                </div>

                <div>

                    <span>Total Collected</span>

                    <strong>
                        ₹<?php echo number_format($total_paid, 2); ?>
                    </strong>

                </div>

            </div>


            <div class="office-fee-card">

                <div class="office-fee-icon">
                    ⏳
                </div>

                <div>

                    <span>Total Pending</span>

                    <strong>
                        ₹<?php echo number_format($total_pending, 2); ?>
                    </strong>

                </div>

            </div>


            <div class="office-fee-card">

                <div class="office-fee-icon">
                    📋
                </div>

                <div>

                    <span>Pending Records</span>

                    <strong>
                        <?php echo $pending_records; ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- SEARCH / FILTER -->
        <section class="office-fee-filter">

            <div class="office-fee-filter-header">

                <div>

                    <h2>Fee Records</h2>

                    <p>
                        Search student payments and fee details
                    </p>

                </div>

            </div>


            <form
                method="GET"
                action="fees.php"
                class="office-fee-search-form">

                <div class="office-fee-input-group">

                    <label for="search">
                        Search
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Name, roll number, receipt or transaction ID"
                    >

                </div>


                <div class="office-fee-input-group">

                    <label for="status">
                        Payment Status
                    </label>

                    <select
                        id="status"
                        name="status">

                        <option value="">
                            All Status
                        </option>

                        <option value="PAID"
                            <?php echo ($status === "PAID") ? "selected" : ""; ?>>
                            Paid
                        </option>

                        <option value="PARTIAL"
                            <?php echo ($status === "PARTIAL") ? "selected" : ""; ?>>
                            Partial
                        </option>

                        <option value="PENDING"
                            <?php echo ($status === "PENDING") ? "selected" : ""; ?>>
                            Pending
                        </option>

                    </select>

                </div>


                <div class="office-fee-filter-actions">

                    <button
                        type="submit"
                        class="office-fee-search-btn">

                        🔍 Search

                    </button>


                    <a
                        href="fees.php"
                        class="office-fee-clear-btn">

                        Clear

                    </a>

                </div>

            </form>

        </section>


        <!-- PAYMENT TABLE -->
        <section class="office-fee-table-section">

            <div class="office-fee-table-header">

                <div>

                    <h2>Payment Details</h2>

                    <p>
                        <?php echo count($fees); ?>
                        record(s) found
                    </p>

                </div>

            </div>


            <?php if (count($fees) > 0): ?>

                <div class="office-fee-table-wrapper">

                    <table class="office-fee-table">

                        <thead>

                            <tr>

                                <th>Student</th>

                                <th>Fee Type</th>

                                <th>Total</th>

                                <th>Paid</th>

                                <th>Balance</th>

                                <th>Due Date</th>

                                <th>Status</th>

                                <th>Receipt</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($fees as $fee): ?>

                            <?php
                            $balance =
                                (float)$fee["amount"]
                                - (float)$fee["paid_amount"];

                            if ($balance < 0) {
                                $balance = 0;
                            }

                            $status_class =
                                feeStatusClass(
                                    $fee["payment_status"]
                                );
                            ?>

                            <tr>

                                <!-- Student -->
                                <td>

                                    <div class="office-fee-student">

                                        <div class="office-fee-avatar">

                                            <?php
                                            echo strtoupper(
                                                substr(
                                                    $fee["full_name"],
                                                    0,
                                                    1
                                                )
                                            );
                                            ?>

                                        </div>

                                        <div>

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $fee["full_name"]
                                                );
                                                ?>
                                            </strong>

                                            <span>
                                                <?php
                                                echo htmlspecialchars(
                                                    $fee["roll_number"]
                                                );
                                                ?>
                                            </span>

                                            <small>
                                                <?php
                                                echo htmlspecialchars(
                                                    $fee["department"]
                                                );
                                                ?>
                                                -
                                                Year
                                                <?php
                                                echo htmlspecialchars(
                                                    $fee["year"]
                                                );
                                                ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <!-- Fee Type -->
                                <td>

                                    <span class="office-fee-type">

                                        <?php
                                        echo htmlspecialchars(
                                            $fee["fee_type"]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- Total -->
                                <td>

                                    <strong class="office-fee-amount">

                                        ₹<?php
                                        echo number_format(
                                            (float)$fee["amount"],
                                            2
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- Paid -->
                                <td>

                                    <strong class="office-fee-paid">

                                        ₹<?php
                                        echo number_format(
                                            (float)$fee["paid_amount"],
                                            2
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- Balance -->
                                <td>

                                    <strong class="office-fee-balance">

                                        ₹<?php
                                        echo number_format(
                                            $balance,
                                            2
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- Due Date -->
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

                                        echo "-";

                                    }

                                    ?>

                                </td>


                                <!-- Status -->
                                <td>

                                    <span
                                        class="office-fee-status <?php echo $status_class; ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $fee["payment_status"]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- Receipt -->
                                <td>

                                    <?php if (!empty($fee["receipt_number"])): ?>

                                        <div class="office-fee-receipt">

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $fee["receipt_number"]
                                                );
                                                ?>
                                            </strong>

                                            <?php if (!empty($fee["transaction_id"])): ?>

                                                <span>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $fee["transaction_id"]
                                                    );
                                                    ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    <?php else: ?>

                                        <span class="office-fee-no-receipt">
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

                <div class="office-fee-empty">

                    <div class="office-fee-empty-icon">
                        💰
                    </div>

                    <h3>No Fee Records Found</h3>

                    <p>
                        No payment records match your search criteria.
                    </p>

                    <a href="fees.php">
                        View All Records
                    </a>

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