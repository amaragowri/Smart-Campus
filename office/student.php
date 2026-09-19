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
$department = trim($_GET["department"] ?? "");
$year = trim($_GET["year"] ?? "");

/* Office user details */
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


/* Build student query */
$sql = "
    SELECT
        student_id,
        roll_number,
        full_name,
        email,
        phone,
        department,
        year,
        section,
        admission_year
    FROM students
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= "
        AND (
            full_name LIKE ?
            OR roll_number LIKE ?
            OR email LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sss";
}

if ($department !== "") {
    $sql .= " AND department = ?";
    $params[] = $department;
    $types .= "s";
}

if ($year !== "") {
    $sql .= " AND year = ?";
    $params[] = $year;
    $types .= "s";
}

$sql .= " ORDER BY department ASC, year ASC, section ASC, full_name ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();


/* Total students */
$total_result = $conn->query("SELECT COUNT(*) AS total FROM students");
$total_students = 0;

if ($total_result) {
    $total_row = $total_result->fetch_assoc();
    $total_students = (int)$total_row["total"];
}


/* Department list */
$department_result = $conn->query("
    SELECT DISTINCT department
    FROM students
    WHERE department IS NOT NULL
      AND department != ''
    ORDER BY department
");

$departments = [];

if ($department_result) {
    while ($row = $department_result->fetch_assoc()) {
        $departments[] = $row["department"];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Students | SmartCampus</title>

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

            <a href="student.php" class="active">
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

                <h1>Student Management</h1>

                <p>
                    View and manage student information
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
                    type="button"
                    title="Toggle theme">

                    🌙

                </button>

            </div>

        </header>


        <!-- SUMMARY -->
        <section class="office-student-summary">

            <div class="office-student-summary-card">

                <div class="office-student-summary-icon">
                    👨‍🎓
                </div>

                <div>

                    <span>Total Students</span>

                    <strong>
                        <?php echo $total_students; ?>
                    </strong>

                </div>

            </div>


            <div class="office-student-summary-card">

                <div class="office-student-summary-icon">
                    🔎
                </div>

                <div>

                    <span>Displayed</span>

                    <strong>
                        <?php echo count($students); ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- FILTER -->
        <section class="office-student-filter">

            <div class="office-student-filter-header">

                <div>
                    <h2>Find Students</h2>

                    <p>
                        Search by name, roll number or email
                    </p>
                </div>

            </div>


            <form method="GET"
                  action="student.php"
                  class="office-student-search-form">

                <div class="office-student-input-group">

                    <label for="search">
                        Search
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Name, roll number or email"
                    >

                </div>


                <div class="office-student-input-group">

                    <label for="department">
                        Department
                    </label>

                    <select
                        id="department"
                        name="department">

                        <option value="">
                            All Departments
                        </option>

                        <?php foreach ($departments as $dept): ?>

                            <option
                                value="<?php echo htmlspecialchars($dept); ?>"
                                <?php
                                echo ($department === $dept)
                                    ? "selected"
                                    : "";
                                ?>>

                                <?php echo htmlspecialchars($dept); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="office-student-input-group">

                    <label for="year">
                        Year
                    </label>

                    <select
                        id="year"
                        name="year">

                        <option value="">
                            All Years
                        </option>

                        <option value="1"
                            <?php echo ($year === "1") ? "selected" : ""; ?>>
                            1st Year
                        </option>

                        <option value="2"
                            <?php echo ($year === "2") ? "selected" : ""; ?>>
                            2nd Year
                        </option>

                        <option value="3"
                            <?php echo ($year === "3") ? "selected" : ""; ?>>
                            3rd Year
                        </option>

                        <option value="4"
                            <?php echo ($year === "4") ? "selected" : ""; ?>>
                            4th Year
                        </option>

                    </select>

                </div>


                <div class="office-student-filter-actions">

                    <button
                        type="submit"
                        class="office-student-search-btn">

                        🔍 Search

                    </button>


                    <a
                        href="student.php"
                        class="office-student-clear-btn">

                        Clear

                    </a>

                </div>

            </form>

        </section>


        <!-- STUDENTS TABLE -->
        <section class="office-student-table-section">

            <div class="office-student-table-header">

                <div>

                    <h2>Students List</h2>

                    <p>
                        <?php echo count($students); ?>
                        student record(s) found
                    </p>

                </div>

            </div>


            <?php if (count($students) > 0): ?>

                <div class="office-student-table-wrapper">

                    <table class="office-student-table">

                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Student</th>

                                <th>Roll Number</th>

                                <th>Department</th>

                                <th>Year</th>

                                <th>Section</th>

                                <th>Contact</th>

                                <th>Admission Year</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php
                        $count = 1;

                        foreach ($students as $student):
                        ?>

                            <tr>

                                <td>
                                    <?php echo $count++; ?>
                                </td>


                                <td>

                                    <div class="office-student-name">

                                        <div class="office-student-avatar">

                                            <?php
                                            echo strtoupper(
                                                substr(
                                                    $student["full_name"],
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
                                                    $student["full_name"]
                                                );
                                                ?>
                                            </strong>

                                            <span>
                                                <?php
                                                echo htmlspecialchars(
                                                    $student["email"] ?? "No email"
                                                );
                                                ?>
                                            </span>

                                        </div>

                                    </div>

                                </td>


                                <td>

                                    <span class="office-roll-number">

                                        <?php
                                        echo htmlspecialchars(
                                            $student["roll_number"]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="office-department-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $student["department"]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student["year"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student["section"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <div class="office-student-contact">

                                        <span>
                                            📧
                                            <?php
                                            echo htmlspecialchars(
                                                $student["email"] ?? "-"
                                            );
                                            ?>
                                        </span>

                                        <span>
                                            📱
                                            <?php
                                            echo htmlspecialchars(
                                                $student["phone"] ?? "-"
                                            );
                                            ?>
                                        </span>

                                    </div>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student["admission_year"] ?? "-"
                                    );
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="office-student-empty">

                    <div class="office-student-empty-icon">
                        🔍
                    </div>

                    <h3>No Students Found</h3>

                    <p>
                        No student records match your search criteria.
                    </p>

                    <a href="student.php">
                        View All Students
                    </a>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>

/* Theme Toggle */
const themeToggle = document.getElementById("themeToggle");

const savedTheme = localStorage.getItem("theme");

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