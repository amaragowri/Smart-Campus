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
   DELETE SUBJECT
========================= */

if (isset($_GET['delete'])) {

    $subject_id = intval($_GET['delete']);

    /* Prevent deletion if related records exist */

    $check = $conn->prepare(
        "SELECT
            (SELECT COUNT(*) FROM attendance WHERE subject_id = ?) +
            (SELECT COUNT(*) FROM timetable WHERE subject_id = ?) +
            (SELECT COUNT(*) FROM materials WHERE subject_id = ?) +
            (SELECT COUNT(*) FROM exam_details WHERE subject_id = ?) +
            (SELECT COUNT(*) FROM marks WHERE subject_id = ?) AS total_links"
    );

    $check->bind_param(
        "iiiii",
        $subject_id,
        $subject_id,
        $subject_id,
        $subject_id,
        $subject_id
    );

    $check->execute();

    $link_result = $check->get_result()->fetch_assoc();

    $check->close();

    if (($link_result['total_links'] ?? 0) > 0) {

        $error = "This subject is already used in academic records and cannot be deleted.";

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM subjects WHERE subject_id = ?"
        );

        $stmt->bind_param("i", $subject_id);

        if ($stmt->execute()) {
            $message = "Subject deleted successfully.";
        } else {
            $error = "Unable to delete subject.";
        }

        $stmt->close();
    }
}


/* =========================
   ADD SUBJECT
========================= */

if (isset($_POST['add_subject'])) {

    $subject_code = strtoupper(trim($_POST['subject_code']));
    $subject_name = trim($_POST['subject_name']);
    $department = trim($_POST['department']);
    $year = intval($_POST['year']);
    $semester = intval($_POST['semester']);
    $credits = floatval($_POST['credits']);

    if (
        $subject_code === "" ||
        $subject_name === "" ||
        $department === "" ||
        $year < 1 ||
        $year > 4 ||
        $semester < 1 ||
        $semester > 8 ||
        $credits <= 0
    ) {

        $error = "Please enter valid subject details.";

    } else {

        $check = $conn->prepare(
            "SELECT subject_id
             FROM subjects
             WHERE subject_code = ?"
        );

        $check->bind_param(
            "s",
            $subject_code
        );

        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $error = "Subject code already exists.";

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO subjects
                (subject_code, subject_name, department, year, semester, credits)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sss iid",
                $subject_code,
                $subject_name,
                $department,
                $year,
                $semester,
                $credits
            );

            /*
             * Correct bind type string:
             * s = subject_code
             * s = subject_name
             * s = department
             * i = year
             * i = semester
             * d = credits
             */

            if ($stmt->execute()) {
                $message = "Subject added successfully.";
            } else {
                $error = "Failed to add subject.";
            }

            $stmt->close();
        }

        $check->close();
    }
}


/* =========================
   UPDATE SUBJECT
========================= */

if (isset($_POST['update_subject'])) {

    $subject_id = intval($_POST['subject_id']);
    $subject_code = strtoupper(trim($_POST['subject_code']));
    $subject_name = trim($_POST['subject_name']);
    $department = trim($_POST['department']);
    $year = intval($_POST['year']);
    $semester = intval($_POST['semester']);
    $credits = floatval($_POST['credits']);

    if (
        $subject_id <= 0 ||
        $subject_code === "" ||
        $subject_name === "" ||
        $department === "" ||
        $year < 1 ||
        $year > 4 ||
        $semester < 1 ||
        $semester > 8 ||
        $credits <= 0
    ) {

        $error = "Please enter valid subject details.";

    } else {

        $check = $conn->prepare(
            "SELECT subject_id
             FROM subjects
             WHERE subject_code = ?
             AND subject_id != ?"
        );

        $check->bind_param(
            "si",
            $subject_code,
            $subject_id
        );

        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $error = "Subject code already exists.";

        } else {

            $stmt = $conn->prepare(
                "UPDATE subjects
                 SET subject_code = ?,
                     subject_name = ?,
                     department = ?,
                     year = ?,
                     semester = ?,
                     credits = ?
                 WHERE subject_id = ?"
            );

            $stmt->bind_param(
                "sss iidi",
                $subject_code,
                $subject_name,
                $department,
                $year,
                $semester,
                $credits,
                $subject_id
            );

            /*
             * Correct bind type string:
             * s s s i i d i
             */

            if ($stmt->execute()) {
                $message = "Subject updated successfully.";
            } else {
                $error = "Failed to update subject.";
            }

            $stmt->close();
        }

        $check->close();
    }
}


/* =========================
   SEARCH & FILTER
========================= */

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : "";

$department_filter = isset($_GET['department'])
    ? trim($_GET['department'])
    : "";

$year_filter = isset($_GET['year'])
    ? intval($_GET['year'])
    : 0;

$semester_filter = isset($_GET['semester'])
    ? intval($_GET['semester'])
    : 0;


$query = "SELECT
            subject_id,
            subject_code,
            subject_name,
            department,
            year,
            semester,
            credits
          FROM subjects
          WHERE 1=1";

$params = [];
$types = "";

if ($search !== "") {

    $query .= "
        AND (
            subject_code LIKE ?
            OR subject_name LIKE ?
        )";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ss";
}

if ($department_filter !== "") {

    $query .= " AND department = ?";

    $params[] = $department_filter;
    $types .= "s";
}

if ($year_filter > 0) {

    $query .= " AND year = ?";

    $params[] = $year_filter;
    $types .= "i";
}

if ($semester_filter > 0) {

    $query .= " AND semester = ?";

    $params[] = $semester_filter;
    $types .= "i";
}

$query .= "
    ORDER BY year ASC,
             semester ASC,
             subject_code ASC";


$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();


/* =========================
   STATISTICS
========================= */

$stats_result = $conn->query(
    "SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT department) AS departments,
        COALESCE(SUM(credits), 0) AS credits,
        SUM(year = 1) AS first_year,
        SUM(year = 2) AS second_year,
        SUM(year = 3) AS third_year,
        SUM(year = 4) AS fourth_year
     FROM subjects"
);

$stats = $stats_result
    ? $stats_result->fetch_assoc()
    : [];

$total_subjects = $stats['total'] ?? 0;
$total_departments = $stats['departments'] ?? 0;
$total_credits = $stats['credits'] ?? 0;
$third_year_subjects = $stats['third_year'] ?? 0;
$fourth_year_subjects = $stats['fourth_year'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Admin - Subject Management | SmartCampus</title>

<link rel="stylesheet"
      href="../assets/css/admin.css">

</head>


<body class="admin-subjects-page">


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

        <a href="faculty.php">
            <span>👨‍🏫</span>
            Faculty
        </a>

        <a href="subjects.php" class="active">
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

        <h1>Subject Management</h1>

        <p>
            Manage subjects, curriculum structure, semesters and credits.
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


<!-- ================= ALERTS ================= -->

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
            📚
        </div>

        <div>

            <span>Total Subjects</span>

            <strong>
                <?= $total_subjects ?>
            </strong>

        </div>

    </div>


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            🏢
        </div>

        <div>

            <span>Departments</span>

            <strong>
                <?= $total_departments ?>
            </strong>

        </div>

    </div>


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            🎓
        </div>

        <div>

            <span>3rd Year Subjects</span>

            <strong>
                <?= $third_year_subjects ?>
            </strong>

        </div>

    </div>


    <div class="admin-user-stat-card">

        <div class="stat-icon">
            ⭐
        </div>

        <div>

            <span>Total Credits</span>

            <strong>
                <?= number_format((float)$total_credits, 1) ?>
            </strong>

        </div>

    </div>

</section>


<!-- ================= ADD SUBJECT ================= -->

<section class="admin-panel">

    <div class="admin-panel-header">

        <div>

            <h2>Add New Subject</h2>

            <p>
                Add a subject to the SmartCampus academic curriculum.
            </p>

        </div>

    </div>


    <form method="POST"
          class="admin-subject-form">


        <div class="admin-form-group">

            <label>Subject Code</label>

            <input type="text"
                   name="subject_code"
                   placeholder="e.g. CS301"
                   required>

        </div>


        <div class="admin-form-group subject-name-field">

            <label>Subject Name</label>

            <input type="text"
                   name="subject_name"
                   placeholder="e.g. Data Mining"
                   required>

        </div>


        <div class="admin-form-group">

            <label>Department</label>

            <select name="department"
                    required>

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

            <label>Year</label>

            <select name="year"
                    required>

                <option value="">
                    Select Year
                </option>

                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>

            </select>

        </div>


        <div class="admin-form-group">

            <label>Semester</label>

            <select name="semester"
                    required>

                <option value="">
                    Select Semester
                </option>

                <?php for ($i = 1; $i <= 8; $i++): ?>

                    <option value="<?= $i ?>">
                        Semester <?= $i ?>
                    </option>

                <?php endfor; ?>

            </select>

        </div>


        <div class="admin-form-group">

            <label>Credits</label>

            <input type="number"
                   name="credits"
                   min="0.5"
                   step="0.5"
                   placeholder="3"
                   required>

        </div>


        <div class="admin-form-action">

            <button type="submit"
                    name="add_subject"
                    class="admin-primary-btn">

                + Add Subject

            </button>

        </div>


    </form>

</section>


<!-- ================= SUBJECT LIST ================= -->

<section class="admin-panel">

    <div class="admin-panel-header">

        <div>

            <h2>All Subjects</h2>

            <p>
                Search and manage subjects registered in the curriculum.
            </p>

        </div>

    </div>


    <!-- FILTER -->

    <form method="GET"
          class="admin-filter-form">


        <input type="text"
               name="search"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="🔍 Search subject code or name...">


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
                <?= $year_filter == 1 ? 'selected' : '' ?>>
                1st Year
            </option>

            <option value="2"
                <?= $year_filter == 2 ? 'selected' : '' ?>>
                2nd Year
            </option>

            <option value="3"
                <?= $year_filter == 3 ? 'selected' : '' ?>>
                3rd Year
            </option>

            <option value="4"
                <?= $year_filter == 4 ? 'selected' : '' ?>>
                4th Year
            </option>

        </select>


        <select name="semester">

            <option value="">
                All Semesters
            </option>

            <?php for ($i = 1; $i <= 8; $i++): ?>

                <option value="<?= $i ?>"
                    <?= $semester_filter == $i ? 'selected' : '' ?>>

                    Sem <?= $i ?>

                </option>

            <?php endfor; ?>

        </select>


        <button type="submit"
                class="admin-filter-btn">

            Filter

        </button>


        <a href="subjects.php"
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
                    <th>Subject</th>
                    <th>Department</th>
                    <th>Year</th>
                    <th>Semester</th>
                    <th>Credits</th>
                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($subject = $result->fetch_assoc()): ?>

                    <tr>


                        <td>
                            #<?= $subject['subject_id'] ?>
                        </td>


                        <td>

                            <div class="subject-title">

                                <strong>
                                    <?= htmlspecialchars(
                                        $subject['subject_code']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= htmlspecialchars(
                                        $subject['subject_name']
                                    ) ?>
                                </span>

                            </div>

                        </td>


                        <td>

                            <span class="role-badge">

                                <?= htmlspecialchars(
                                    $subject['department']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            Year <?= $subject['year'] ?>

                        </td>


                        <td>

                            Semester <?= $subject['semester'] ?>

                        </td>


                        <td>

                            <span class="credit-badge">

                                <?= number_format(
                                    (float)$subject['credits'],
                                    1
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <div class="admin-actions">


                                <button
                                    class="edit-user-btn"
                                    onclick='editSubject(
                                        <?= json_encode($subject["subject_id"]) ?>,
                                        <?= json_encode($subject["subject_code"]) ?>,
                                        <?= json_encode($subject["subject_name"]) ?>,
                                        <?= json_encode($subject["department"]) ?>,
                                        <?= json_encode($subject["year"]) ?>,
                                        <?= json_encode($subject["semester"]) ?>,
                                        <?= json_encode($subject["credits"]) ?>
                                    )'>

                                    ✏ Edit

                                </button>


                                <a href="subjects.php?delete=<?= $subject['subject_id'] ?>"
                                   class="delete-user-btn"
                                   onclick="return confirm('Delete this subject?');">

                                    🗑 Delete

                                </a>


                            </div>

                        </td>


                    </tr>

                <?php endwhile; ?>


            <?php else: ?>

                <tr>

                    <td colspan="7"
                        class="no-users">

                        No subjects found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

</main>


<!-- ================= EDIT MODAL ================= -->

<div id="editSubjectModal"
     class="admin-modal">


    <div class="admin-modal-card subject-edit-card">


        <div class="admin-modal-header">

            <div>

                <h2>Edit Subject</h2>

                <p>
                    Update subject curriculum details.
                </p>

            </div>


            <button onclick="closeSubjectModal()">
                ×
            </button>

        </div>


        <form method="POST">


            <input type="hidden"
                   name="subject_id"
                   id="edit_subject_id">


            <div class="subject-edit-grid">


                <div class="admin-form-group">

                    <label>Subject Code</label>

                    <input type="text"
                           name="subject_code"
                           id="edit_subject_code"
                           required>

                </div>


                <div class="admin-form-group">

                    <label>Subject Name</label>

                    <input type="text"
                           name="subject_name"
                           id="edit_subject_name"
                           required>

                </div>


                <div class="admin-form-group">

                    <label>Department</label>

                    <select name="department"
                            id="edit_subject_department">

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
                            id="edit_subject_year">

                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>

                    </select>

                </div>


                <div class="admin-form-group">

                    <label>Semester</label>

                    <select name="semester"
                            id="edit_subject_semester">

                        <?php for ($i = 1; $i <= 8; $i++): ?>

                            <option value="<?= $i ?>">
                                Semester <?= $i ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <div class="admin-form-group">

                    <label>Credits</label>

                    <input type="number"
                           name="credits"
                           id="edit_subject_credits"
                           min="0.5"
                           step="0.5"
                           required>

                </div>


            </div>


            <div class="admin-modal-actions">


                <button type="button"
                        class="admin-cancel-btn"
                        onclick="closeSubjectModal()">

                    Cancel

                </button>


                <button type="submit"
                        name="update_subject"
                        class="admin-primary-btn">

                    Save Changes

                </button>


            </div>


        </form>

    </div>

</div>


<script>

/* =========================
   EDIT SUBJECT
========================= */

function editSubject(
    id,
    code,
    name,
    department,
    year,
    semester,
    credits
) {

    document.getElementById("edit_subject_id").value = id;

    document.getElementById("edit_subject_code").value = code;

    document.getElementById("edit_subject_name").value = name;

    document.getElementById("edit_subject_department").value =
        department;

    document.getElementById("edit_subject_year").value =
        year;

    document.getElementById("edit_subject_semester").value =
        semester;

    document.getElementById("edit_subject_credits").value =
        credits;

    document.getElementById("editSubjectModal")
        .classList.add("show");
}


function closeSubjectModal() {

    document.getElementById("editSubjectModal")
        .classList.remove("show");
}


/* Close outside */

document.getElementById("editSubjectModal")
    .addEventListener("click", function(e) {

        if (e.target === this) {
            closeSubjectModal();
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