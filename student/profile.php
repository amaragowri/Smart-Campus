<?php

session_start();
require_once "../config/database.php";

/* =========================
   AUTHENTICATION
   ========================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "STUDENT") {
    header("Location: ../auth/student-login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$message = "";
$message_type = "";

/* =========================
   UPDATE PROFILE
   ========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

        $message = "Please enter a valid 10-digit phone number.";
        $message_type = "error";

    } else {

        $update_sql = "
            UPDATE students
            SET email = ?, phone = ?
            WHERE user_id = ?
        ";

        $stmt = $conn->prepare($update_sql);

        $stmt->bind_param(
            "ssi",
            $email,
            $phone,
            $user_id
        );

        if ($stmt->execute()) {

            $message = "Profile updated successfully!";
            $message_type = "success";

        } else {

            $message = "Unable to update profile.";
            $message_type = "error";
        }

        $stmt->close();
    }
}

/* =========================
   GET STUDENT DETAILS
   ========================= */

$sql = "
    SELECT *
    FROM students
    WHERE user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Profile | SmartCampus</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

    <style>

        /* =========================
           PROFILE EDIT
           ========================= */

        .profile-edit-box {
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid #e2e8f0;
        }

        .profile-edit-box h3 {
            font-size: 17px;
            margin-bottom: 5px;
            color: #0f172a;
        }

        .profile-edit-box p {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 18px;
        }

        .edit-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .form-group input {
            width: 100%;
            box-sizing: border-box;

            padding: 12px 14px;

            border: 1px solid #cbd5e1;
            border-radius: 10px;

            background: #ffffff;
            color: #0f172a;

            font-size: 13px;

            outline: none;
            transition: 0.2s ease;
        }

        .form-group input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .edit-buttons {
            grid-column: 1 / -1;

            display: flex;
            gap: 10px;
            margin-top: 4px;
        }

        .save-profile-btn {
            border: none;
            border-radius: 10px;

            padding: 12px 22px;

            background: linear-gradient(
                135deg,
                #2563eb,
                #4f46e5
            );

            color: white;

            font-size: 13px;
            font-weight: 700;

            cursor: pointer;
        }

        .save-profile-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 18px rgba(37, 99, 235, 0.25);
        }

        .cancel-profile-btn {
            border: 1px solid #cbd5e1;
            border-radius: 10px;

            padding: 12px 22px;

            background: #ffffff;
            color: #475569;

            font-size: 13px;
            font-weight: 600;

            cursor: pointer;
        }

        .profile-message {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 18px;

            font-size: 13px;
            font-weight: 600;
        }

        .profile-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .profile-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* DARK THEME */

        body.dark-theme .profile-edit-box {
            border-color: #1e293b;
        }

        body.dark-theme .profile-edit-box h3 {
            color: #f8fafc;
        }

        body.dark-theme .profile-edit-box p {
            color: #94a3b8;
        }

        body.dark-theme .form-group label {
            color: #cbd5e1;
        }

        body.dark-theme .form-group input {
            background: #111c31;
            border-color: #334155;
            color: #f8fafc;
        }

        body.dark-theme .cancel-profile-btn {
            background: #111c31;
            border-color: #334155;
            color: #cbd5e1;
        }

        @media (max-width: 650px) {

            .edit-form {
                grid-template-columns: 1fr;
            }

            .edit-buttons {
                grid-column: auto;
            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =========================
         SIDEBAR
         ========================= -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
                SC
            </div>

            <div>
                <h2>SmartCampus</h2>
                <span>Student Portal</span>
            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php">
                🏠 Dashboard
            </a>

            <a href="profile.php" class="active">
                👤 My Profile
            </a>

            <a href="attendance.php">
                📊 Attendance
            </a>

            <a href="timetable.php">
                🕐 Timetable
            </a>

            <a href="materials.php">
                📚 Study Materials
            </a>

            <a href="exams.php">
                📝 Exams
            </a>

            <a href="results.php">
                📈 Results
            </a>

            <a href="hall-ticket.php">
                🎫 Hall Ticket
            </a>

            <a href="fees.php">
                💰 Fees & Payments
            </a>

            <a href="projects.php">
                💻 Projects
            </a>

            <a href="clubs.php">
                🎯 Clubs & Activities
            </a>

            <a href="notifications.php">
                🔔 Notifications
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">
                🚪 Logout
            </a>

        </div>

    </aside>


    <!-- =========================
         MAIN CONTENT
         ========================= -->

    <main class="dashboard-content">


        <header class="dashboard-header">

            <div>

                <p class="welcome-text">
                    Student Account
                </p>

                <h1>
                    My Profile
                </h1>

                <p class="student-meta">
                    Manage your personal information
                </p>

            </div>


            <button
                type="button"
                class="dashboard-theme-toggle"
                id="profileThemeToggle">

                🌙

            </button>

        </header>


        <!-- =========================
             PROFILE CARD
             ========================= -->

        <div class="dashboard-panel">


            <?php if ($message !== ""): ?>

                <div class="profile-message
                    <?php
                    echo $message_type === "success"
                        ? "profile-success"
                        : "profile-error";
                    ?>">

                    <?php echo htmlspecialchars($message); ?>

                </div>

            <?php endif; ?>


            <div class="panel-header">

                <div>

                    <h2>
                        Personal Information
                    </h2>

                    <p>
                        Your registered student details
                    </p>

                </div>


                <div class="profile-avatar">

                    <?php

                    echo strtoupper(
                        substr($student["full_name"], 0, 1)
                    );

                    ?>

                </div>

            </div>


            <!-- VIEW INFORMATION -->

            <div class="profile-details">

                <div>

                    <span>Full Name</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $student["full_name"]
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>Roll Number</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $student["roll_number"]
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>Department</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $student["department"]
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>Year</span>

                    <strong>
                        Year
                        <?php
                        echo htmlspecialchars(
                            $student["year"]
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>Section</span>

                    <strong>
                        Section
                        <?php
                        echo htmlspecialchars(
                            $student["section"]
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>Admission Year</span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $student["admission_year"]
                        );
                        ?>
                    </strong>

                </div>

            </div>


            <!-- =========================
                 EDIT PROFILE
                 ========================= -->

            <div class="profile-edit-box">

                <h3>
                    ✏️ Update Contact Information
                </h3>

                <p>
                    You can update your email address and phone number.
                    Academic details are managed by the college.
                </p>


                <form method="POST"
                      class="edit-form">


                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php
                            echo htmlspecialchars(
                                $student["email"]
                            );
                            ?>"
                            required>

                    </div>


                    <div class="form-group">

                        <label for="phone">
                            Phone Number
                        </label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="<?php
                            echo htmlspecialchars(
                                $student["phone"]
                            );
                            ?>"
                            maxlength="10"
                            pattern="[0-9]{10}"
                            required>

                    </div>


                    <div class="edit-buttons">

                        <button
                            type="submit"
                            class="save-profile-btn">

                            💾 Save Changes

                        </button>

                        <button
                            type="reset"
                            class="cancel-profile-btn">

                            Cancel

                        </button>

                    </div>


                </form>

            </div>

        </div>


        <!-- =========================
             ACADEMIC + ACCOUNT
             ========================= -->

        <div class="dashboard-grid"
             style="margin-top:20px;">


            <div class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            Academic Information
                        </h2>

                        <p>
                            Current academic details
                        </p>

                    </div>

                    <span class="panel-icon">
                        🎓
                    </span>

                </div>


                <div class="fee-summary">

                    <div>

                        <span>
                            Department
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["department"]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Year
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["year"]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Section
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $student["section"]
                            );
                            ?>
                        </strong>

                    </div>

                </div>

            </div>


            <div class="dashboard-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            Account Status
                        </h2>

                        <p>
                            SmartCampus account
                        </p>

                    </div>

                    <span class="panel-icon">
                        🔐
                    </span>

                </div>


                <div class="fee-summary">

                    <div>

                        <span>
                            Role
                        </span>

                        <strong>
                            STUDENT
                        </strong>

                    </div>


                    <div>

                        <span>
                            Account
                        </span>

                        <strong class="success-text">
                            ACTIVE
                        </strong>

                    </div>

                </div>

            </div>

        </div>


        <footer class="dashboard-footer">

            © 2026 SmartCampus · Student Management Portal

        </footer>


    </main>

</div>


<script>

/* =========================
   THEME
   ========================= */

const themeButton =
    document.getElementById("profileThemeToggle");

const savedTheme =
    localStorage.getItem("smartcampus-theme");

if (savedTheme === "dark") {

    document.body.classList.add("dark-theme");

    themeButton.textContent = "☀️";

}


themeButton.addEventListener("click", function () {

    document.body.classList.toggle("dark-theme");

    const isDark =
        document.body.classList.contains("dark-theme");

    localStorage.setItem(
        "smartcampus-theme",
        isDark ? "dark" : "light"
    );

    themeButton.textContent =
        isDark ? "☀️" : "🌙";

});

</script>


</body>

</html>