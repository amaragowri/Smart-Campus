<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: ../auth/admin-login.php");
    exit();
}

require_once "../config/database.php";

$message = "";
$error = "";

/* =========================
   DELETE STUDENT
========================= */

if (isset($_GET['delete'])) {

    $student_id = intval($_GET['delete']);

    $stmt = $conn->prepare(
        "DELETE FROM students WHERE student_id = ?"
    );

    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        $message = "Student deleted successfully.";
    } else {
        $error = "Unable to delete student.";
    }

    $stmt->close();
}


/* =========================
   ADD STUDENT
========================= */

if (isset($_POST['add_student'])) {

    $user_id = intval($_POST['user_id']);
    $roll_number = trim($_POST['roll_number']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $year = intval($_POST['year']);
    $section = trim($_POST['section']);
    $admission_year = intval($_POST['admission_year']);

    if (
        $user_id <= 0 ||
        $roll_number === "" ||
        $full_name === "" ||
        $department === "" ||
        $year <= 0 ||
        $section === "" ||
        $admission_year <= 0
    ) {

        $error = "Please fill all required fields.";

    } else {

        /* Check selected user */

        $user_check = $conn->prepare(
            "SELECT user_id FROM users
             WHERE user_id = ? AND role = 'STUDENT'"
        );

        $user_check->bind_param("i", $user_id);
        $user_check->execute();
        $user_check->store_result();

        if ($user_check->num_rows === 0) {

            $error = "Selected user is not a valid STUDENT account.";

        } else {

            /* Check duplicate user / roll */

            $check = $conn->prepare(
                "SELECT student_id
                 FROM students
                 WHERE user_id = ? OR roll_number = ?"
            );

            $check->bind_param(
                "is",
                $user_id,
                $roll_number
            );

            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {

                $error = "Student account or roll number already exists.";

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO students
                    (user_id, roll_number, full_name, email, phone,
                     department, year, section, admission_year)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "isssssisi",
                    $user_id,
                    $roll_number,
                    $full_name,
                    $email,
                    $phone,
                    $department,
                    $year,
                    $section,
                    $admission_year
                );

                if ($stmt->execute()) {
                    $message = "Student added successfully.";
                } else {
                    $error = "Failed to add student.";
                }

                $stmt->close();
            }

            $check->close();
        }

        $user_check->close();
    }
}


/* =========================
   UPDATE STUDENT
========================= */

if (isset($_POST['update_student'])) {

    $student_id = intval($_POST['student_id']);
    $roll_number = trim($_POST['roll_number']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $year = intval($_POST['year']);
    $section = trim($_POST['section']);
    $admission_year = intval($_POST['admission_year']);

    $check = $conn->prepare(
        "SELECT student_id
         FROM students
         WHERE roll_number = ?
         AND student_id != ?"
    );

    $check->bind_param(
        "si",
        $roll_number,
        $student_id
    );

    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {

        $error = "Roll number already exists.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE students
             SET roll_number = ?,
                 full_name = ?,
                 email = ?,
                 phone = ?,
                 department = ?,
                 year = ?,
                 section = ?,
                 admission_year = ?
             WHERE student_id = ?"
        );

        $stmt->bind_param(
            "sssssisis",
            $roll_number,
            $full_name,
            $email,
            $phone,
            $department,
            $year,
            $section,
            $admission_year,
            $student_id
        );

        if ($stmt->execute()) {
            $message = "Student updated successfully.";
        } else {
            $error = "Failed to update student.";
        }

        $stmt->close();
    }

    $check->close();
}


/* =========================
   SEARCH & FILTER
========================= */

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : "";

$department_filter = isset($_GET['department'])
    ? $_GET['department']
    : "";

$year_filter = isset($_GET['year'])
    ? $_GET['year']
    : "";


$query = "SELECT
            s.student_id,
            s.user_id,
            s.roll_number,
            s.full_name,
            s.email,
            s.phone,
            s.department,
            s.year,
            s.section,
            s.admission_year,
            u.username
          FROM students s
          LEFT JOIN users u
          ON s.user_id = u.user_id
          WHERE 1=1";

$params = [];
$types = "";

if ($search !== "") {

    $query .= " AND
        (s.full_name LIKE ?
         OR s.roll_number LIKE ?
         OR s.email LIKE ?
         OR u.username LIKE ?)";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ssss";
}

if ($department_filter !== "") {

    $query .= " AND s.department = ?";

    $params[] = $department_filter;
    $types .= "s";
}

if ($year_filter !== "") {

    $query .= " AND s.year = ?";

    $params[] = intval($year_filter);
    $types .= "i";
}

$query .= " ORDER BY s.student_id DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================
   STUDENT USER ACCOUNTS
========================= */

$student_users = $conn->query(
    "SELECT u.user_id, u.username
     FROM users u
     LEFT JOIN students s
     ON u.user_id = s.user_id
     WHERE u.role = 'STUDENT'
     AND s.student_id IS NULL
     ORDER BY u.username"
);


/* =========================
   STATISTICS
========================= */

$stats = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(department='CSE') AS cse,
        SUM(year=1) AS first_year,
        SUM(year=2) AS second_year,
        SUM(year=3) AS third_year,
        SUM(year=4) AS fourth_year
     FROM students"
)->fetch_assoc();

$total_students = $stats['total'] ?? 0;
$cse_students = $stats['cse'] ?? 0;
$first_year = $stats['first_year'] ?? 0;
$second_year = $stats['second_year'] ?? 0;
$third_year = $stats['third_year'] ?? 0;
$fourth_year = $stats['fourth_year'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin - Student Management | SmartCampus</title>

<link rel="stylesheet"
      href="../assets/css/admin.css">

</head>

<body class="admin-students-page">


<!-- ================= SIDEBAR ================= -->

<aside class="admin-sidebar">

    <div class="admin-brand">

        <div class="admin-brand-icon">
            SC
        </div>

        <div>
            <strong>SmartCampus</strong>
            <span>Admin Panel</span>
        </div>

    </div>


    <nav class="admin-nav">

        <a href="dashboard.php">
            <span>📊</span>
            Dashboard
        </a>

        <a href="users.php">
            <span>👥</span>
            Users
        </a>

        <a href="students.php" class="active">
            <span>🎓</span>
            Students
        </a>

        <a href="faculty.php">
            <span>👨‍🏫</span>
            Faculty
        </a>

        <a href="subjects.php">
            <span>📚</span>
            Subjects
        </a>

        <a href="reports.php">
            <span>📈</span>
            Reports
        </a>

    </nav>


    <div class="admin-sidebar-bottom">

        <button id="themeToggle"
                class="admin-theme-btn">
            🌙 Dark Mode
        </button>

        <a href="../auth/logout.php"
           class="admin-logout">
            🚪 Logout
        </a>

    </div>

</aside>


<!-- ================= MAIN ================= -->

<main class="admin-main">

    <header class="admin-topbar">

        <div>

            <h1>Student Management</h1>

            <p>
                Manage student profiles, academic details and accounts.
            </p>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">
                A
            </div>

            <div>

                <strong>Administrator</strong>

                <span>ADMIN</span>

            </div>

        </div>

    </header>


    <!-- ALERT -->

    <?php if ($message): ?>

        <div class="admin-alert success">
            ✓ <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="admin-alert error">
            ⚠ <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- ================= STATS ================= -->

    <section class="admin-user-stats">

        <div class="admin-user-stat-card">

            <div class="stat-icon">
                🎓
            </div>

            <div>

                <span>Total Students</span>

                <strong>
                    <?= $total_students ?>
                </strong>

            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">
                💻
            </div>

            <div>

                <span>CSE Students</span>

                <strong>
                    <?= $cse_students ?>
                </strong>

            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">
                📘
            </div>

            <div>

                <span>3rd Year</span>

                <strong>
                    <?= $third_year ?>
                </strong>

            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">
                📚
            </div>

            <div>

                <span>4th Year</span>

                <strong>
                    <?= $fourth_year ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- ================= ADD STUDENT ================= -->

    <section class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>Add New Student</h2>

                <p>
                    Create a student profile for an existing STUDENT login account.
                </p>

            </div>

        </div>


        <form method="POST"
              class="admin-student-form">

            <div class="admin-form-group">

                <label>Student Login</label>

                <select name="user_id" required>

                    <option value="">
                        Select student account
                    </option>

                    <?php if ($student_users && $student_users->num_rows > 0): ?>

                        <?php while ($u = $student_users->fetch_assoc()): ?>

                            <option value="<?= $u['user_id'] ?>">
                                <?= htmlspecialchars($u['username']) ?>
                            </option>

                        <?php endwhile; ?>

                    <?php endif; ?>

                </select>

            </div>


            <div class="admin-form-group">

                <label>Roll Number</label>

                <input type="text"
                       name="roll_number"
                       placeholder="e.g. SC2027CS001"
                       required>

            </div>


            <div class="admin-form-group">

                <label>Full Name</label>

                <input type="text"
                       name="full_name"
                       placeholder="Student full name"
                       required>

            </div>


            <div class="admin-form-group">

                <label>Email</label>

                <input type="email"
                       name="email"
                       placeholder="student@example.com">

            </div>


            <div class="admin-form-group">

                <label>Phone</label>

                <input type="text"
                       name="phone"
                       maxlength="10"
                       placeholder="10 digit number">

            </div>


            <div class="admin-form-group">

                <label>Department</label>

                <select name="department" required>

                    <option value="">Select Department</option>
                    <option value="CSE">CSE</option>
                    <option value="ECE">ECE</option>
                    <option value="EEE">EEE</option>
                    <option value="MECH">MECH</option>
                    <option value="CIVIL">CIVIL</option>
                    <option value="IT">IT</option>

                </select>

            </div>


            <div class="admin-form-group">

                <label>Year</label>

                <select name="year" required>

                    <option value="">Select Year</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>

                </select>

            </div>


            <div class="admin-form-group">

                <label>Section</label>

                <input type="text"
                       name="section"
                       placeholder="A"
                       required>

            </div>


            <div class="admin-form-group">

                <label>Admission Year</label>

                <input type="number"
                       name="admission_year"
                       placeholder="2024"
                       required>

            </div>


            <div class="admin-form-action">

                <button type="submit"
                        name="add_student"
                        class="admin-primary-btn">

                    + Add Student

                </button>

            </div>

        </form>

    </section>


    <!-- ================= STUDENT LIST ================= -->

    <section class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>All Students</h2>

                <p>
                    Search and manage registered student profiles.
                </p>

            </div>

        </div>


        <!-- FILTER -->

        <form method="GET"
              class="admin-filter-form">

            <input type="text"
                   name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="🔍 Search name, roll number, email...">


            <select name="department">

                <option value="">
                    All Departments
                </option>

                <option value="CSE"
                    <?= $department_filter === 'CSE' ? 'selected' : '' ?>>
                    CSE
                </option>

                <option value="ECE"
                    <?= $department_filter === 'ECE' ? 'selected' : '' ?>>
                    ECE
                </option>

                <option value="EEE"
                    <?= $department_filter === 'EEE' ? 'selected' : '' ?>>
                    EEE
                </option>

                <option value="MECH"
                    <?= $department_filter === 'MECH' ? 'selected' : '' ?>>
                    MECH
                </option>

                <option value="CIVIL"
                    <?= $department_filter === 'CIVIL' ? 'selected' : '' ?>>
                    CIVIL
                </option>

                <option value="IT"
                    <?= $department_filter === 'IT' ? 'selected' : '' ?>>
                    IT
                </option>

            </select>


            <select name="year">

                <option value="">
                    All Years
                </option>

                <option value="1"
                    <?= $year_filter === '1' ? 'selected' : '' ?>>
                    1st Year
                </option>

                <option value="2"
                    <?= $year_filter === '2' ? 'selected' : '' ?>>
                    2nd Year
                </option>

                <option value="3"
                    <?= $year_filter === '3' ? 'selected' : '' ?>>
                    3rd Year
                </option>

                <option value="4"
                    <?= $year_filter === '4' ? 'selected' : '' ?>>
                    4th Year
                </option>

            </select>


            <button type="submit"
                    class="admin-filter-btn">

                Filter

            </button>


            <a href="students.php"
               class="admin-clear-btn">

                Clear

            </a>

        </form>


        <!-- TABLE -->

        <div class="admin-table-wrapper">

            <table class="admin-user-table">

                <thead>

                    <tr>

                        <th>ID</th>
                        <th>Student</th>
                        <th>Roll Number</th>
                        <th>Department</th>
                        <th>Year</th>
                        <th>Section</th>
                        <th>Contact</th>
                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($student = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                #<?= $student['student_id'] ?>
                            </td>


                            <td>

                                <div class="admin-user-name">

                                    <div class="user-mini-avatar">

                                        <?= strtoupper(
                                            substr(
                                                $student['full_name'],
                                                0,
                                                1
                                            )
                                        ) ?>

                                    </div>

                                    <div>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $student['full_name']
                                            ) ?>
                                        </strong>

                                        <small>
                                            @<?= htmlspecialchars(
                                                $student['username'] ?? '-'
                                            ) ?>
                                        </small>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $student['roll_number']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <span class="role-badge">

                                    <?= htmlspecialchars(
                                        $student['department']
                                    ) ?>

                                </span>

                            </td>


                            <td>
                                Year <?= $student['year'] ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $student['section']
                                ) ?>
                            </td>


                            <td>

                                <div class="student-contact">

                                    <span>
                                        <?= htmlspecialchars(
                                            $student['email'] ?? '-'
                                        ) ?>
                                    </span>

                                    <span>
                                        <?= htmlspecialchars(
                                            $student['phone'] ?? '-'
                                        ) ?>
                                    </span>

                                </div>

                            </td>


                            <td>

                                <div class="admin-actions">

                                    <button
                                        class="edit-user-btn"
                                        onclick='editStudent(
                                            <?= json_encode($student["student_id"]) ?>,
                                            <?= json_encode($student["roll_number"]) ?>,
                                            <?= json_encode($student["full_name"]) ?>,
                                            <?= json_encode($student["email"]) ?>,
                                            <?= json_encode($student["phone"]) ?>,
                                            <?= json_encode($student["department"]) ?>,
                                            <?= json_encode($student["year"]) ?>,
                                            <?= json_encode($student["section"]) ?>,
                                            <?= json_encode($student["admission_year"]) ?>
                                        )'>

                                        ✏ Edit

                                    </button>


                                    <a href="students.php?delete=<?= $student['student_id'] ?>"
                                       class="delete-user-btn"
                                       onclick="return confirm('Delete this student profile?');">

                                        🗑 Delete

                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8"
                            class="no-users">

                            No students found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>


<!-- ================= EDIT MODAL ================= -->

<div id="editStudentModal"
     class="admin-modal">

    <div class="admin-modal-card student-edit-card">

        <div class="admin-modal-header">

            <div>

                <h2>Edit Student</h2>

                <p>
                    Update student academic and contact details.
                </p>

            </div>

            <button onclick="closeStudentModal()">
                ×
            </button>

        </div>


        <form method="POST">

            <input type="hidden"
                   name="student_id"
                   id="edit_student_id">


            <div class="student-edit-grid">

                <div class="admin-form-group">

                    <label>Roll Number</label>

                    <input type="text"
                           name="roll_number"
                           id="edit_roll_number"
                           required>

                </div>


                <div class="admin-form-group">

                    <label>Full Name</label>

                    <input type="text"
                           name="full_name"
                           id="edit_full_name"
                           required>

                </div>


                <div class="admin-form-group">

                    <label>Email</label>

                    <input type="email"
                           name="email"
                           id="edit_email">

                </div>


                <div class="admin-form-group">

                    <label>Phone</label>

                    <input type="text"
                           name="phone"
                           id="edit_phone"
                           maxlength="10">

                </div>


                <div class="admin-form-group">

                    <label>Department</label>

                    <select name="department"
                            id="edit_department">

                        <option value="CSE">CSE</option>
                        <option value="ECE">ECE</option>
                        <option value="EEE">EEE</option>
                        <option value="MECH">MECH</option>
                        <option value="CIVIL">CIVIL</option>
                        <option value="IT">IT</option>

                    </select>

                </div>


                <div class="admin-form-group">

                    <label>Year</label>

                    <select name="year"
                            id="edit_year">

                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>

                    </select>

                </div>


                <div class="admin-form-group">

                    <label>Section</label>

                    <input type="text"
                           name="section"
                           id="edit_section"
                           required>

                </div>


                <div class="admin-form-group">

                    <label>Admission Year</label>

                    <input type="number"
                           name="admission_year"
                           id="edit_admission_year"
                           required>

                </div>

            </div>


            <div class="admin-modal-actions">

                <button type="button"
                        class="admin-cancel-btn"
                        onclick="closeStudentModal()">

                    Cancel

                </button>


                <button type="submit"
                        name="update_student"
                        class="admin-primary-btn">

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================
   EDIT STUDENT
========================= */

function editStudent(
    id,
    roll,
    name,
    email,
    phone,
    department,
    year,
    section,
    admissionYear
) {

    document.getElementById("edit_student_id").value = id;

    document.getElementById("edit_roll_number").value = roll;

    document.getElementById("edit_full_name").value = name;

    document.getElementById("edit_email").value =
        email || "";

    document.getElementById("edit_phone").value =
        phone || "";

    document.getElementById("edit_department").value =
        department;

    document.getElementById("edit_year").value =
        year;

    document.getElementById("edit_section").value =
        section;

    document.getElementById("edit_admission_year").value =
        admissionYear;

    document.getElementById("editStudentModal")
        .classList.add("show");
}


function closeStudentModal() {

    document.getElementById("editStudentModal")
        .classList.remove("show");
}


/* Close outside */

document.getElementById("editStudentModal")
    .addEventListener("click", function(e) {

        if (e.target === this) {
            closeStudentModal();
        }

    });


/* =========================
   THEME
========================= */

const themeToggle =
    document.getElementById("themeToggle");


if (localStorage.getItem("smartcampus-theme") === "dark") {

    document.body.classList.add("dark-mode");

    themeToggle.innerHTML =
        "☀️ Light Mode";
}


themeToggle.addEventListener("click", function() {

    document.body.classList.toggle("dark-mode");

    if (document.body.classList.contains("dark-mode")) {

        localStorage.setItem(
            "smartcampus-theme",
            "dark"
        );

        themeToggle.innerHTML =
            "☀️ Light Mode";

    } else {

        localStorage.setItem(
            "smartcampus-theme",
            "light"
        );

        themeToggle.innerHTML =
            "🌙 Dark Mode";
    }

});

</script>

</body>
</html>