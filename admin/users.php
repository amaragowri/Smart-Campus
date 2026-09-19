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
   ADD USER
========================= */
if (isset($_POST['add_user'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    if ($username === "" || $password === "") {
        $error = "Username and password are required.";
    } else {

        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Username already exists.";
        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (username, password, role, status)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $username,
                $hashed_password,
                $role,
                $status
            );

            if ($stmt->execute()) {
                $message = "User added successfully.";
            } else {
                $error = "Failed to add user.";
            }

            $stmt->close();
        }

        $check->close();
    }
}


/* =========================
   UPDATE USER
========================= */
if (isset($_POST['update_user'])) {

    $user_id = intval($_POST['user_id']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $password = $_POST['password'];

    if ($user_id == $_SESSION['user_id'] && $status !== "ACTIVE") {
        $error = "You cannot deactivate your own admin account.";
    } else {

        $check = $conn->prepare(
            "SELECT user_id FROM users
             WHERE username = ? AND user_id != ?"
        );

        $check->bind_param("si", $username, $user_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $error = "Username already exists.";

        } else {

            if ($password !== "") {

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "UPDATE users
                     SET username = ?, password = ?, role = ?, status = ?
                     WHERE user_id = ?"
                );

                $stmt->bind_param(
                    "ssssi",
                    $username,
                    $hashed_password,
                    $role,
                    $status,
                    $user_id
                );

            } else {

                $stmt = $conn->prepare(
                    "UPDATE users
                     SET username = ?, role = ?, status = ?
                     WHERE user_id = ?"
                );

                $stmt->bind_param(
                    "sssi",
                    $username,
                    $role,
                    $status,
                    $user_id
                );
            }

            if ($stmt->execute()) {
                $message = "User updated successfully.";
            } else {
                $error = "Failed to update user.";
            }

            $stmt->close();
        }

        $check->close();
    }
}


/* =========================
   DELETE USER
========================= */
if (isset($_GET['delete'])) {

    $delete_id = intval($_GET['delete']);

    if ($delete_id == $_SESSION['user_id']) {

        $error = "You cannot delete your own account.";

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM users WHERE user_id = ?"
        );

        $stmt->bind_param("i", $delete_id);

        if ($stmt->execute()) {
            $message = "User deleted successfully.";
        } else {
            $error = "Unable to delete user.";
        }

        $stmt->close();
    }
}


/* =========================
   SEARCH & FILTER
========================= */
$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : "";

$role_filter = isset($_GET['role'])
    ? $_GET['role']
    : "";

$status_filter = isset($_GET['status'])
    ? $_GET['status']
    : "";


$query = "SELECT user_id, username, role, status, created_at
          FROM users
          WHERE 1=1";

$params = [];
$types = "";


if ($search !== "") {

    $query .= " AND username LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}


if ($role_filter !== "") {

    $query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}


if ($status_filter !== "") {

    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}


$query .= " ORDER BY user_id DESC";


$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================
   STATISTICS
========================= */
$total_users = 0;
$active_users = 0;
$inactive_users = 0;
$admin_users = 0;

$stats_result = $conn->query(
    "SELECT
        COUNT(*) AS total_users,
        SUM(status='ACTIVE') AS active_users,
        SUM(status!='ACTIVE') AS inactive_users,
        SUM(role='ADMIN') AS admin_users
     FROM users"
);

if ($stats_result) {

    $stats = $stats_result->fetch_assoc();

    $total_users = $stats['total_users'];
    $active_users = $stats['active_users'];
    $inactive_users = $stats['inactive_users'];
    $admin_users = $stats['admin_users'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin - User Management | SmartCampus</title>

<link rel="stylesheet" href="../assets/css/admin.css">

</head>

<body class="admin-users-page">

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

        <a href="users.php" class="active">
            <span>👥</span>
            Users
        </a>

        <a href="students.php">
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

        <button id="themeToggle" class="admin-theme-btn">
            🌙 Dark Mode
        </button>

        <a href="../auth/logout.php" class="admin-logout">
            🚪 Logout
        </a>

    </div>

</aside>


<!-- ================= MAIN ================= -->

<main class="admin-main">

    <header class="admin-topbar">

        <div>

            <h1>User Management</h1>

            <p>
                Manage SmartCampus system users and access roles.
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

            <div class="stat-icon">👥</div>

            <div>
                <span>Total Users</span>
                <strong><?= $total_users ?></strong>
            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">✓</div>

            <div>
                <span>Active Users</span>
                <strong><?= $active_users ?></strong>
            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">⏸</div>

            <div>
                <span>Inactive Users</span>
                <strong><?= $inactive_users ?></strong>
            </div>

        </div>


        <div class="admin-user-stat-card">

            <div class="stat-icon">⚙</div>

            <div>
                <span>Admin Users</span>
                <strong><?= $admin_users ?></strong>
            </div>

        </div>

    </section>


    <!-- ================= ADD USER ================= -->

    <section class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>Add New User</h2>

                <p>
                    Create login credentials for a SmartCampus user.
                </p>

            </div>

        </div>


        <form method="POST"
              class="admin-user-form">

            <div class="admin-form-group">

                <label>Username</label>

                <input type="text"
                       name="username"
                       placeholder="Enter username"
                       required>

            </div>


            <div class="admin-form-group">

                <label>Password</label>

                <input type="password"
                       name="password"
                       placeholder="Enter password"
                       required>

            </div>


            <div class="admin-form-group">

                <label>Role</label>

                <select name="role" required>

                    <option value="STUDENT">
                        STUDENT
                    </option>

                    <option value="FACULTY">
                        FACULTY
                    </option>

                    <option value="EXAM_CELL">
                        EXAM CELL
                    </option>

                    <option value="OFFICE">
                        OFFICE
                    </option>

                    <option value="SAC_LEAD">
                        SAC LEAD
                    </option>

                    <option value="DOMAIN_LEAD">
                        DOMAIN LEAD
                    </option>

                    <option value="CR">
                        CR
                    </option>

                    <option value="LR">
                        LR
                    </option>

                    <option value="ADMIN">
                        ADMIN
                    </option>

                </select>

            </div>


            <div class="admin-form-group">

                <label>Status</label>

                <select name="status">

                    <option value="ACTIVE">
                        ACTIVE
                    </option>

                    <option value="INACTIVE">
                        INACTIVE
                    </option>

                </select>

            </div>


            <div class="admin-form-action">

                <button type="submit"
                        name="add_user"
                        class="admin-primary-btn">

                    + Add User

                </button>

            </div>

        </form>

    </section>


    <!-- ================= FILTER ================= -->

    <section class="admin-panel">

        <div class="admin-panel-header">

            <div>

                <h2>All Users</h2>

                <p>
                    Search and manage registered users.
                </p>

            </div>

        </div>


        <form method="GET"
              class="admin-filter-form">

            <input type="text"
                   name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="🔍 Search username...">


            <select name="role">

                <option value="">
                    All Roles
                </option>

                <option value="STUDENT"
                    <?= $role_filter === 'STUDENT' ? 'selected' : '' ?>>
                    STUDENT
                </option>

                <option value="FACULTY"
                    <?= $role_filter === 'FACULTY' ? 'selected' : '' ?>>
                    FACULTY
                </option>

                <option value="EXAM_CELL"
                    <?= $role_filter === 'EXAM_CELL' ? 'selected' : '' ?>>
                    EXAM CELL
                </option>

                <option value="OFFICE"
                    <?= $role_filter === 'OFFICE' ? 'selected' : '' ?>>
                    OFFICE
                </option>

                <option value="SAC_LEAD"
                    <?= $role_filter === 'SAC_LEAD' ? 'selected' : '' ?>>
                    SAC LEAD
                </option>

                <option value="DOMAIN_LEAD"
                    <?= $role_filter === 'DOMAIN_LEAD' ? 'selected' : '' ?>>
                    DOMAIN LEAD
                </option>

                <option value="CR"
                    <?= $role_filter === 'CR' ? 'selected' : '' ?>>
                    CR
                </option>

                <option value="LR"
                    <?= $role_filter === 'LR' ? 'selected' : '' ?>>
                    LR
                </option>

                <option value="ADMIN"
                    <?= $role_filter === 'ADMIN' ? 'selected' : '' ?>>
                    ADMIN
                </option>

            </select>


            <select name="status">

                <option value="">
                    All Status
                </option>

                <option value="ACTIVE"
                    <?= $status_filter === 'ACTIVE' ? 'selected' : '' ?>>
                    ACTIVE
                </option>

                <option value="INACTIVE"
                    <?= $status_filter === 'INACTIVE' ? 'selected' : '' ?>>
                    INACTIVE
                </option>

            </select>


            <button type="submit"
                    class="admin-filter-btn">

                Filter

            </button>


            <a href="users.php"
               class="admin-clear-btn">

                Clear

            </a>

        </form>


        <!-- ================= TABLE ================= -->

        <div class="admin-table-wrapper">

            <table class="admin-user-table">

                <thead>

                    <tr>

                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($user = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                #<?= $user['user_id'] ?>
                            </td>

                            <td>

                                <div class="admin-user-name">

                                    <div class="user-mini-avatar">
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </div>

                                    <strong>
                                        <?= htmlspecialchars($user['username']) ?>
                                    </strong>

                                </div>

                            </td>


                            <td>

                                <span class="role-badge">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>

                            </td>


                            <td>

                                <?php if ($user['status'] === 'ACTIVE'): ?>

                                    <span class="status-badge active">
                                        ● Active
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge inactive">
                                        ● Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= date(
                                    "d M Y",
                                    strtotime($user['created_at'])
                                ) ?>

                            </td>


                            <td>

                                <div class="admin-actions">

                                    <button
                                        class="edit-user-btn"
                                        onclick='editUser(
                                            <?= json_encode($user["user_id"]) ?>,
                                            <?= json_encode($user["username"]) ?>,
                                            <?= json_encode($user["role"]) ?>,
                                            <?= json_encode($user["status"]) ?>
                                        )'>

                                        ✏ Edit

                                    </button>


                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>

                                        <a href="users.php?delete=<?= $user['user_id'] ?>"
                                           class="delete-user-btn"
                                           onclick="return confirm('Are you sure you want to delete this user?');">

                                            🗑 Delete

                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6"
                            class="no-users">

                            No users found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>


<!-- ================= EDIT MODAL ================= -->

<div id="editModal"
     class="admin-modal">

    <div class="admin-modal-card">

        <div class="admin-modal-header">

            <div>

                <h2>Edit User</h2>

                <p>
                    Update user account details.
                </p>

            </div>

            <button onclick="closeEditModal()">
                ×
            </button>

        </div>


        <form method="POST">

            <input type="hidden"
                   name="user_id"
                   id="edit_user_id">


            <div class="admin-form-group">

                <label>Username</label>

                <input type="text"
                       name="username"
                       id="edit_username"
                       required>

            </div>


            <div class="admin-form-group">

                <label>New Password</label>

                <input type="password"
                       name="password"
                       placeholder="Leave blank to keep current password">

            </div>


            <div class="admin-form-group">

                <label>Role</label>

                <select name="role"
                        id="edit_role">

                    <option value="STUDENT">STUDENT</option>
                    <option value="FACULTY">FACULTY</option>
                    <option value="EXAM_CELL">EXAM CELL</option>
                    <option value="OFFICE">OFFICE</option>
                    <option value="SAC_LEAD">SAC LEAD</option>
                    <option value="DOMAIN_LEAD">DOMAIN LEAD</option>
                    <option value="CR">CR</option>
                    <option value="LR">LR</option>
                    <option value="ADMIN">ADMIN</option>

                </select>

            </div>


            <div class="admin-form-group">

                <label>Status</label>

                <select name="status"
                        id="edit_status">

                    <option value="ACTIVE">
                        ACTIVE
                    </option>

                    <option value="INACTIVE">
                        INACTIVE
                    </option>

                </select>

            </div>


            <div class="admin-modal-actions">

                <button type="button"
                        class="admin-cancel-btn"
                        onclick="closeEditModal()">

                    Cancel

                </button>

                <button type="submit"
                        name="update_user"
                        class="admin-primary-btn">

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>


<script>

function editUser(id, username, role, status) {

    document.getElementById("edit_user_id").value = id;
    document.getElementById("edit_username").value = username;
    document.getElementById("edit_role").value = role;
    document.getElementById("edit_status").value = status;

    document.getElementById("editModal").classList.add("show");
}


function closeEditModal() {

    document.getElementById("editModal")
        .classList.remove("show");
}


/* Theme */

const themeToggle =
    document.getElementById("themeToggle");

if (localStorage.getItem("smartcampus-theme") === "dark") {

    document.body.classList.add("dark-mode");

    themeToggle.innerHTML = "☀️ Light Mode";
}


themeToggle.addEventListener("click", function () {

    document.body.classList.toggle("dark-mode");

    if (document.body.classList.contains("dark-mode")) {

        localStorage.setItem(
            "smartcampus-theme",
            "dark"
        );

        themeToggle.innerHTML = "☀️ Light Mode";

    } else {

        localStorage.setItem(
            "smartcampus-theme",
            "light"
        );

        themeToggle.innerHTML = "🌙 Dark Mode";
    }

});


/* Close modal outside */

document.getElementById("editModal")
    .addEventListener("click", function(e) {

        if (e.target === this) {
            closeEditModal();
        }

    });

</script>

</body>
</html>