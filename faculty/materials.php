<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "FACULTY") {
    header("Location: ../auth/faculty-login.php");
    exit();
}

require_once "../config/database.php";

$user_id = $_SESSION["user_id"];

/* =========================
   FACULTY DETAILS
========================= */
$stmt = $conn->prepare("
    SELECT faculty_id, full_name, employee_id, email, department, designation
    FROM faculty
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$faculty_result = $stmt->get_result();
$faculty = $faculty_result->fetch_assoc();
$stmt->close();

if (!$faculty) {
    die("Faculty profile not found.");
}

$faculty_id = $faculty["faculty_id"];

/* =========================
   MESSAGE VARIABLES
========================= */
$success_message = "";
$error_message = "";

/* =========================
   UPLOAD MATERIAL
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["upload_material"])) {

    $subject_id = intval($_POST["subject_id"] ?? 0);
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($subject_id <= 0 || empty($title)) {
        $error_message = "Please select a subject and enter material title.";
    } elseif (!isset($_FILES["material_file"]) || $_FILES["material_file"]["error"] !== UPLOAD_ERR_OK) {
        $error_message = "Please select a valid file.";
    } else {

        $file = $_FILES["material_file"];

        $allowed_extensions = [
            "pdf",
            "doc",
            "docx",
            "ppt",
            "pptx"
        ];

        $file_extension = strtolower(
            pathinfo($file["name"], PATHINFO_EXTENSION)
        );

        $max_file_size = 10 * 1024 * 1024; // 10 MB

        if (!in_array($file_extension, $allowed_extensions)) {

            $error_message = "Only PDF, DOC, DOCX, PPT and PPTX files are allowed.";

        } elseif ($file["size"] > $max_file_size) {

            $error_message = "File size must be less than 10 MB.";

        } else {

            $upload_directory = "../uploads/materials/";

            if (!is_dir($upload_directory)) {
                mkdir($upload_directory, 0777, true);
            }

            $original_name = basename($file["name"]);

            $safe_name = preg_replace(
                "/[^A-Za-z0-9._-]/",
                "_",
                $original_name
            );

            $unique_name = time() . "_" . uniqid() . "_" . $safe_name;

            $file_path = $upload_directory . $unique_name;

            if (move_uploaded_file($file["tmp_name"], $file_path)) {

                $db_file_path = "uploads/materials/" . $unique_name;

                $stmt = $conn->prepare("
                    INSERT INTO materials
                    (
                        subject_id,
                        faculty_id,
                        title,
                        description,
                        file_name,
                        file_path,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE')
                ");

                $stmt->bind_param(
                    "iissss",
                    $subject_id,
                    $faculty_id,
                    $title,
                    $description,
                    $original_name,
                    $db_file_path
                );

                if ($stmt->execute()) {

                    $success_message = "Material uploaded successfully.";

                } else {

                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }

                    $error_message = "Unable to save material details.";
                }

                $stmt->close();

            } else {

                $error_message = "File upload failed. Please try again.";
            }
        }
    }
}

/* =========================
   DELETE MATERIAL
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_material"])) {

    $material_id = intval($_POST["material_id"] ?? 0);

    if ($material_id > 0) {

        $stmt = $conn->prepare("
            SELECT file_path
            FROM materials
            WHERE material_id = ?
            AND faculty_id = ?
        ");

        $stmt->bind_param("ii", $material_id, $faculty_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $material = $result->fetch_assoc();
        $stmt->close();

        if ($material) {

            $physical_path = "../" . $material["file_path"];

            $stmt = $conn->prepare("
                DELETE FROM materials
                WHERE material_id = ?
                AND faculty_id = ?
            ");

            $stmt->bind_param("ii", $material_id, $faculty_id);

            if ($stmt->execute()) {

                if (file_exists($physical_path)) {
                    unlink($physical_path);
                }

                $success_message = "Material deleted successfully.";

            } else {

                $error_message = "Unable to delete material.";
            }

            $stmt->close();

        } else {

            $error_message = "Material not found.";
        }
    }
}

/* =========================
   SUBJECT LIST
========================= */
$subjects = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.year,
        s.semester
    FROM subjects s
    INNER JOIN timetable t
        ON s.subject_id = t.subject_id
    WHERE t.faculty_id = ?
      AND s.department = ?
    ORDER BY s.year ASC, s.semester ASC, s.subject_code ASC
");

$stmt->bind_param(
    "is",
    $faculty_id,
    $faculty["department"]
);

$stmt->execute();

$subject_result = $stmt->get_result();

while ($row = $subject_result->fetch_assoc()) {
    $subjects[] = $row;
}

$stmt->close();

/* =========================
   MATERIAL LIST
========================= */
$materials = [];

$stmt = $conn->prepare("
    SELECT
        m.material_id,
        m.title,
        m.description,
        m.file_name,
        m.file_path,
        m.uploaded_at,
        m.status,
        s.subject_code,
        s.subject_name,
        s.year,
        s.semester
    FROM materials m
    INNER JOIN subjects s
        ON m.subject_id = s.subject_id
    WHERE m.faculty_id = ?
    ORDER BY m.uploaded_at DESC
");

$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$material_result = $stmt->get_result();

while ($row = $material_result->fetch_assoc()) {
    $materials[] = $row;
}

$stmt->close();

/* =========================
   COUNTS
========================= */
$total_materials = count($materials);

$active_materials = 0;

foreach ($materials as $material) {
    if (strtoupper($material["status"]) === "ACTIVE") {
        $active_materials++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Faculty Materials | SmartCampus</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

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
                <span>Faculty Portal</span>
            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php">
                <span>🏠</span>
                Dashboard
            </a>

            <a href="attendance.php">
                <span>📊</span>
                Attendance
            </a>

            <a href="timetable.php">
                <span>🗓️</span>
                Timetable
            </a>

            <a href="materials.php" class="active">
                <span>📚</span>
                Materials
            </a>

            <a href="#">
                <span>📝</span>
                Exams
            </a>

            <a href="#">
                <span>📈</span>
                Results
            </a>

            <a href="#">
                <span>📢</span>
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

        <header class="dashboard-header">

            <div class="welcome-text">

                <h1>Study Materials</h1>

                <p>
                    Upload and manage learning materials for your students.
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>
                        <?php echo htmlspecialchars($faculty["full_name"]); ?>
                    </strong>

                    <span>
                        <?php echo htmlspecialchars($faculty["employee_id"]); ?>
                    </span>

                </div>


                <button
                    type="button"
                    class="dashboard-theme-toggle"
                    id="themeToggle"
                    title="Toggle theme">

                    🌙

                </button>

            </div>

        </header>


        <!-- =========================
             MESSAGES
        ========================== -->

        <?php if (!empty($success_message)): ?>

            <div class="faculty-material-message success">
                <span>✓</span>
                <?php echo htmlspecialchars($success_message); ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($error_message)): ?>

            <div class="faculty-material-message error">
                <span>!</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>

        <?php endif; ?>


        <!-- =========================
             SUMMARY
        ========================== -->

        <section class="faculty-material-summary">

            <div class="faculty-material-summary-card">

                <div class="faculty-material-summary-icon">
                    📚
                </div>

                <div>
                    <span>Total Materials</span>
                    <strong>
                        <?php echo $total_materials; ?>
                    </strong>
                </div>

            </div>


            <div class="faculty-material-summary-card">

                <div class="faculty-material-summary-icon">
                    ✓
                </div>

                <div>
                    <span>Active Materials</span>
                    <strong>
                        <?php echo $active_materials; ?>
                    </strong>
                </div>

            </div>


            <div class="faculty-material-summary-card">

                <div class="faculty-material-summary-icon">
                    👨‍🏫
                </div>

                <div>
                    <span>Faculty</span>
                    <strong>
                        <?php echo htmlspecialchars($faculty["designation"]); ?>
                    </strong>
                </div>

            </div>

        </section>


        <!-- =========================
             UPLOAD SECTION
        ========================== -->

        <section class="faculty-material-upload">

            <div class="faculty-material-section-header">

                <div>
                    <h2>Upload New Material</h2>
                    <p>
                        Share notes, presentations and study resources.
                    </p>
                </div>

            </div>


            <form
                method="POST"
                enctype="multipart/form-data"
                class="faculty-material-form">


                <div class="faculty-material-field">

                    <label>
                        Subject
                    </label>

                    <select
                        name="subject_id"
                        required>

                        <option value="">
                            Select Subject
                        </option>

                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?php echo $subject["subject_id"]; ?>">

                                <?php
                                echo htmlspecialchars(
                                    $subject["subject_code"] .
                                    " - " .
                                    $subject["subject_name"]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="faculty-material-field">

                    <label>
                        Material Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        placeholder="Example: Unit 1 Notes"
                        maxlength="150"
                        required>

                </div>


                <div class="faculty-material-field faculty-material-full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="4"
                        placeholder="Enter a short description..."
                        maxlength="500"></textarea>

                </div>


                <div class="faculty-material-field faculty-material-full">

                    <label>
                        Select File
                    </label>

                    <input
                        type="file"
                        name="material_file"
                        accept=".pdf,.doc,.docx,.ppt,.pptx"
                        required>

                    <small>
                        Allowed: PDF, DOC, DOCX, PPT, PPTX | Maximum size: 10 MB
                    </small>

                </div>


                <div class="faculty-material-form-actions">

                    <button
                        type="submit"
                        name="upload_material"
                        class="faculty-material-upload-btn">

                        📤 Upload Material

                    </button>

                </div>

            </form>

        </section>


        <!-- =========================
             MATERIALS LIST
        ========================== -->

        <section class="faculty-material-list-section">

            <div class="faculty-material-section-header">

                <div>
                    <h2>Uploaded Materials</h2>
                    <p>
                        Materials uploaded by you.
                    </p>
                </div>

            </div>


            <?php if (empty($materials)): ?>

                <div class="faculty-material-empty">

                    <div class="faculty-material-empty-icon">
                        📚
                    </div>

                    <h3>No Materials Uploaded</h3>

                    <p>
                        Upload your first study material using the form above.
                    </p>

                </div>

            <?php else: ?>

                <div class="faculty-material-grid">

                    <?php foreach ($materials as $material): ?>

                        <?php
                        $extension = strtoupper(
                            pathinfo(
                                $material["file_name"],
                                PATHINFO_EXTENSION
                            )
                        );

                        $file_icon = "📄";

                        if ($extension === "PDF") {
                            $file_icon = "📕";
                        } elseif (
                            $extension === "PPT" ||
                            $extension === "PPTX"
                        ) {
                            $file_icon = "📊";
                        } elseif (
                            $extension === "DOC" ||
                            $extension === "DOCX"
                        ) {
                            $file_icon = "📝";
                        }
                        ?>

                        <article class="faculty-material-card">

                            <div class="faculty-material-card-top">

                                <div class="faculty-material-file-icon">
                                    <?php echo $file_icon; ?>
                                </div>

                                <span class="faculty-material-file-type">
                                    <?php echo $extension; ?>
                                </span>

                            </div>


                            <div class="faculty-material-card-body">

                                <h3>
                                    <?php
                                    echo htmlspecialchars(
                                        $material["title"]
                                    );
                                    ?>
                                </h3>


                                <div class="faculty-material-subject">

                                    📘

                                    <?php
                                    echo htmlspecialchars(
                                        $material["subject_code"]
                                    );
                                    ?>

                                    -
                                    <?php
                                    echo htmlspecialchars(
                                        $material["subject_name"]
                                    );
                                    ?>

                                </div>


                                <?php if (!empty($material["description"])): ?>

                                    <p class="faculty-material-description">

                                        <?php
                                        echo htmlspecialchars(
                                            $material["description"]
                                        );
                                        ?>

                                    </p>

                                <?php endif; ?>


                                <div class="faculty-material-info">

                                    <span>
                                        📅
                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $material["uploaded_at"]
                                            )
                                        );
                                        ?>
                                    </span>

                                    <span>
                                        Year
                                        <?php
                                        echo htmlspecialchars(
                                            $material["year"]
                                        );
                                        ?>
                                    </span>

                                </div>

                            </div>


                            <div class="faculty-material-card-actions">

                                <a
                                    href="../<?php echo htmlspecialchars($material["file_path"]); ?>"
                                    target="_blank"
                                    class="faculty-material-download">

                                    ⬇ Download

                                </a>


                                <form
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this material?');">

                                    <input
                                        type="hidden"
                                        name="material_id"
                                        value="<?php echo $material["material_id"]; ?>">

                                    <button
                                        type="submit"
                                        name="delete_material"
                                        class="faculty-material-delete">

                                        🗑 Delete

                                    </button>

                                </form>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script>

document.addEventListener("DOMContentLoaded", function () {

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

        themeToggle.addEventListener("click", function () {

            document.body.classList.toggle("dark-theme");

            const isDark =
                document.body.classList.contains("dark-theme");

            localStorage.setItem(
                "smartcampus-theme",
                isDark ? "dark" : "light"
            );

            themeToggle.textContent =
                isDark ? "☀️" : "🌙";

        });

    }

});

</script>

</body>
</html>