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
   DELETE FACULTY
========================= */

if (isset($_GET['delete'])) {

    $faculty_id = intval($_GET['delete']);

    $stmt = $conn->prepare(
        "DELETE FROM faculty WHERE faculty_id = ?"
    );

    $stmt->bind_param("i", $faculty_id);

    if ($stmt->execute()) {
        $message = "Faculty deleted successfully.";
    } else {
        $error = "Unable to delete faculty.";
    }

    $stmt->close();
}


/* =========================
   ADD FACULTY
========================= */

if (isset($_POST['add_faculty'])) {

    $user_id = intval($_POST['user_id']);
    $employee_id = trim($_POST['employee_id']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $designation = trim($_POST['designation']);
    $specialization = trim($_POST['specialization']);

    if (
        $user_id <= 0 ||
        $employee_id === "" ||
        $full_name === "" ||
        $department === "" ||
        $designation === ""
    ) {

        $error = "Please fill all required fields.";

    } else {

        /* Verify FACULTY login */

        $user_check = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE user_id = ?
             AND role = 'FACULTY'"
        );

        $user_check->bind_param("i", $user_id);
        $user_check->execute();
        $user_check->store_result();

        if ($user_check->num_rows === 0) {

            $error = "Selected account is not a valid FACULTY account.";

        } else {

            /* Duplicate check */

            $check = $conn->prepare(
                "SELECT faculty_id
                 FROM faculty
                 WHERE user_id = ?
                 OR employee_id = ?"
            );

            $check->bind_param(
                "is",
                $user_id,
                $employee_id
            );

            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {

                $error = "Faculty account or employee ID already exists.";

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO faculty
                    (user_id, employee_id, full_name, email, phone,
                     department, designation, specialization)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "isssssss",
                    $user_id,
                    $employee_id,
                    $full_name,
                    $email,
                    $phone,
                    $department,
                    $designation,
                    $specialization
                );

                if ($stmt->execute()) {
                    $message = "Faculty added successfully.";
                } else {
                    $error = "Failed to add faculty.";
                }

                $stmt->close();
            }

            $check->close();
        }

        $user_check->close();
    }
}


/* =========================
   UPDATE FACULTY
========================= */

if (isset($_POST['update_faculty'])) {

    $faculty_id = intval($_POST['faculty_id']);
    $employee_id = trim($_POST['employee_id']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $designation = trim($_POST['designation']);
    $specialization = trim($_POST['specialization']);

    $check = $conn->prepare(
        "SELECT faculty_id
         FROM faculty
         WHERE employee_id = ?
         AND faculty_id != ?"
    );

    $check->bind_param(
        "si",
        $employee_id,
        $faculty_id
    );

    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {

        $error = "Employee ID already exists.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE faculty
             SET employee_id = ?,
                 full_name = ?,
                 email = ?,
                 phone = ?,
                 department = ?,
                 designation = ?,
                 specialization = ?
             WHERE faculty_id = ?"
        );

        $stmt->bind_param(
            "sssssssi",
            $employee_id,
            $full_name,
            $email,
            $phone,
            $department,
            $designation,
            $specialization,
            $faculty_id
        );

        if ($stmt->execute()) {
            $message = "Faculty updated successfully.";
        } else {
            $error = "Failed to update faculty.";
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


$query = "SELECT
            f.faculty_id,
            f.user_id,
            f.employee_id,
            f.full_name,
            f.email,
            f.phone,
            f.department,
            f.designation,
            f.specialization,
            u.username
          FROM faculty f
          LEFT JOIN users u
          ON f.user_id = u.user_id
          WHERE 1=1";

$params = [];
$types = "";


if ($search !== "") {

    $query .= " AND
        (f.full_name LIKE ?
         OR f.employee_id LIKE ?
         OR f.email LIKE ?
         OR u.username LIKE ?)";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ssss";
}


if ($department_filter !== "") {

    $query .= " AND f.department = ?";

    $params[] = $department_filter;
    $types .= "s";
}


$query .= " ORDER BY f.faculty_id DESC";


$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================
   AVAILABLE FACULTY USERS
========================= */

$faculty_users = $conn->query(
    "SELECT u.user_id, u.username
     FROM users u
     LEFT JOIN faculty f
     ON u.user_id = f.user_id
     WHERE u.role = 'FACULTY'
     AND f.faculty_id IS NULL
     ORDER BY u.username"
);


/* =========================
   STATISTICS
========================= */

$stats_result = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(department='CSE') AS cse,
        SUM(department='ECE') AS ece,
        SUM(department='EEE') AS eee
     FROM faculty"
);

$stats = $stats_result
    ? $stats_result->fetch_assoc()
    : [];

$total_faculty = $stats['total'] ?? 0;
$cse_faculty = $stats['cse'] ?? 0;
$ece_faculty = $stats['ece'] ?? 0;
$eee_faculty = $stats['eee'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin - Faculty Management | SmartCampus</title>

<link rel="stylesheet"
      href="../assets/css/admin.css">

</head>

<body class="admin-faculty-page">


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

        <a href="students.php">
            <span>🎓</span>
            Students
        </a>

        <a href="faculty.php" class="active">
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

            <h1>Faculty Management</h1>

            <p>
                Manage faculty profiles, departments and academic roles.
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


    <!-- ALERTS -->

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
                👨‍🏫
            </div>

            <div>

                <span>Total Faculty</span>

                <strong>
                    <?= $total_faculty ?>
                </strong>

            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">
                💻
            </div>

            <div>

                <span>CSE Faculty</span>

                <strong>
                    <?= $cse_faculty ?>
                </strong>

            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">
                📡
            </div>

            <div>

                <span>ECE Faculty</span>

                <strong>
                    <?= $ece_faculty ?>
                </strong>

            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">
                ⚡
            </div>

            <div>

                <span>EEE Faculty</span>

                <strong>
                    <?= $eee_faculty ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- ================= ADD FACULTY ================= -->

    <section class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>Add New Faculty</h2>

                <p>
                    Create a faculty profile for an existing FACULTY login account.
                </p>

            </div>

        </div>


        <form method="POST"
              class="admin-faculty-form">


            <div class="admin-form-group">

                <label>Faculty Login</label>

                <select name="user_id" required>

                    <option value="">
                        Select faculty account
                    </option>

                    <?php if ($faculty_users && $faculty_users->num_rows > 0): ?>

                        <?php while ($u = $faculty_users->fetch_assoc()): ?>

                            <option value="<?= $u['user_id'] ?>">

                                <?= htmlspecialchars(
                                    $u['username']
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    <?php endif; ?>

                </select>

            </div>


            <div class="admin-form-group">

                <label>Employee ID</label>

                <input type="text"
                       name="employee_id"
                       placeholder="e.g. FAC001"
                       required>

            </div>


            <div class="admin-form-group">

                <label>Full Name</label>

                <input type="text"
                       name="full_name"
                       placeholder="Faculty full name"
                       required>

            </div>


            <div class="admin-form-group">

                <label>Email</label>

                <input type="email"
                       name="email"
                       placeholder="faculty@example.com">

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

                    <option value="">
                        Select Department
                    </option>

                    <option value="CSE">CSE</option>
                    <option value="ECE">ECE</option>
                    <option value="EEE">EEE</option>
                    <option value="MECH">MECH</option>
                    <option value="CIVIL">CIVIL</option>
                    <option value="IT">IT</option>

                </select>

            </div>


            <div class="admin-form-group">

                <label>Designation</label>

                <input type="text"
                       name="designation"
                       placeholder="Assistant Professor"
                       required>

            </div>


            <div class="admin-form-group">

                <label>Specialization</label>

                <input type="text"
                       name="specialization"
                       placeholder="AI / Data Science / Networks">

            </div>


            <div class="admin-form-action">

                <button type="submit"
                        name="add_faculty"
                        class="admin-primary-btn">

                    + Add Faculty

                </button>

            </div>

        </form>

    </section>


    <!-- ================= FACULTY LIST ================= -->

    <section class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>All Faculty</h2>

                <p>
                    Search and manage registered faculty members.
                </p>

            </div>

        </div>


        <!-- FILTER -->

        <form method="GET"
              class="admin-filter-form">

            <input type="text"
                   name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="🔍 Search name, employee ID, email...">


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


            <button type="submit"
                    class="admin-filter-btn">

                Filter

            </button>


            <a href="faculty.php"
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
                        <th>Faculty</th>
                        <th>Employee ID</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Specialization</th>
                        <th>Contact</th>
                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($faculty = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                #<?= $faculty['faculty_id'] ?>
                            </td>


                            <td>

                                <div class="admin-user-name">

                                    <div class="user-mini-avatar">

                                        <?= strtoupper(
                                            substr(
                                                $faculty['full_name'],
                                                0,
                                                1
                                            )
                                        ) ?>

                                    </div>

                                    <div>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $faculty['full_name']
                                            ) ?>
                                        </strong>

                                        <small>
                                            @<?= htmlspecialchars(
                                                $faculty['username'] ?? '-'
                                            ) ?>
                                        </small>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $faculty['employee_id']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <span class="role-badge">

                                    <?= htmlspecialchars(
                                        $faculty['department']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $faculty['designation']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $faculty['specialization'] ?: '-'
                                ) ?>

                            </td>


                            <td>

                                <div class="student-contact">

                                    <span>
                                        <?= htmlspecialchars(
                                            $faculty['email'] ?: '-'
                                        ) ?>
                                    </span>

                                    <span>
                                        <?= htmlspecialchars(
                                            $faculty['phone'] ?: '-'
                                        ) ?>
                                    </span>

                                </div>

                            </td>


                            <td>

                                <div class="admin-actions">

                                    <button
                                        class="edit-user-btn"
                                        onclick='editFaculty(
                                            <?= json_encode($faculty["faculty_id"]) ?>,
                                            <?= json_encode($faculty["employee_id"]) ?>,
                                            <?= json_encode($faculty["full_name"]) ?>,
                                            <?= json_encode($faculty["email"]) ?>,
                                            <?= json_encode($faculty["phone"]) ?>,
                                            <?= json_encode($faculty["department"]) ?>,
                                            <?= json_encode($faculty["designation"]) ?>,
                                            <?= json_encode($faculty["specialization"]) ?>
                                        )'>

                                        ✏ Edit

                                    </button>


                                    <a href="faculty.php?delete=<?= $faculty['faculty_id'] ?>"
                                       class="delete-user-btn"
                                       onclick="return confirm('Delete this faculty profile?');">

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

                            No faculty found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>


<!-- ================= EDIT MODAL ================= -->

<div id="editFacultyModal"
     class="admin-modal">

    <div class="admin-modal-card faculty-edit-card">

        <div class="admin-modal-header">

            <div>

                <h2>Edit Faculty</h2>

                <p>
                    Update faculty profile and academic details.
                </p>

            </div>

            <button onclick="closeFacultyModal()">
                ×
            </button>

        </div>


        <form method="POST">

            <input type="hidden"
                   name="faculty_id"
                   id="edit_faculty_id">


            <div class="faculty-edit-grid">


                <div class="admin-form-group">

                    <label>Employee ID</label>

                    <input type="text"
                           name="employee_id"
                           id="edit_employee_id"
                           required>

                </div>


                <div class="admin-form-group">

                    <label>Full Name</label>

                    <input type="text"
                           name="full_name"
                           id="edit_faculty_name"
                           required>

                </div>


                <div class="admin-form-group">

                    <label>Email</label>

                    <input type="email"
                           name="email"
                           id="edit_faculty_email">

                </div>


                <div class="admin-form-group">

                    <label>Phone</label>

                    <input type="text"
                           name="phone"
                           id="edit_faculty_phone"
                           maxlength="10">

                </div>


                <div class="admin-form-group">

                    <label>Department</label>

                    <select name="department"
                            id="edit_faculty_department">

                        <option value="CSE">CSE</option>
                        <option value="ECE">ECE</option>
                        <option value="EEE">EEE</option>
                        <option value="MECH">MECH</option>
                        <option value="CIVIL">CIVIL</option>
                        <option value="IT">IT</option>

                    </select>

                </div>


                <div class="admin-form-group">

                    <label>Designation</label>

                    <input type="text"
                           name="designation"
                           id="edit_faculty_designation"
                           required>

                </div>


                <div class="admin-form-group"
                     style="grid-column: 1 / -1;">

                    <label>Specialization</label>

                    <input type="text"
                           name="specialization"
                           id="edit_faculty_specialization">

                </div>


            </div>


            <div class="admin-modal-actions">

                <button type="button"
                        class="admin-cancel-btn"
                        onclick="closeFacultyModal()">

                    Cancel

                </button>


                <button type="submit"
                        name="update_faculty"
                        class="admin-primary-btn">

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================
   EDIT FACULTY
========================= */

function editFaculty(
    id,
    employeeId,
    name,
    email,
    phone,
    department,
    designation,
    specialization
) {

    document.getElementById("edit_faculty_id").value = id;

    document.getElementById("edit_employee_id").value =
        employeeId;

    document.getElementById("edit_faculty_name").value =
        name;

    document.getElementById("edit_faculty_email").value =
        email || "";

    document.getElementById("edit_faculty_phone").value =
        phone || "";

    document.getElementById("edit_faculty_department").value =
        department;

    document.getElementById("edit_faculty_designation").value =
        designation;

    document.getElementById("edit_faculty_specialization").value =
        specialization || "";

    document.getElementById("editFacultyModal")
        .classList.add("show");
}


function closeFacultyModal() {

    document.getElementById("editFacultyModal")
        .classList.remove("show");
}


/* Close modal outside */

document.getElementById("editFacultyModal")
    .addEventListener("click", function(e) {

        if (e.target === this) {
            closeFacultyModal();
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