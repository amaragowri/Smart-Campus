<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SmartCampus | College Management System</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="smartcampus-home">

    <!-- NAVBAR -->
    <nav class="home-navbar">

        <div class="home-brand">
            <div class="home-logo">SC</div>

            <div>
                <h2>SmartCampus</h2>
                <span>College Management System</span>
            </div>
        </div>

        <button
            type="button"
            class="home-theme-btn"
            onclick="toggleHomeTheme()"
        >
            🌙
        </button>

    </nav>


    <!-- HERO SECTION -->
    <section class="home-hero">

        <div class="hero-content">

            <div class="hero-badge">
                🎓 Digital Campus Platform
            </div>

            <h1>
                Welcome to
                <span>SmartCampus</span>
            </h1>

            <p>
                A centralized college management platform that connects
                students, faculty, examination cell, office and
                student activity council in one place.
            </p>

        </div>

    </section>


    <!-- LOGIN SECTION -->
    <section class="login-section">

        <div class="section-heading">

            <span class="section-label">
                PORTAL ACCESS
            </span>

            <h2>Choose Your Login</h2>

            <p>
                Select the portal according to your role.
            </p>

        </div>


        <div class="login-grid">


            <!-- STUDENT -->
            <a
                href="auth/student-login.php"
                class="login-card student-card"
            >

                <div class="login-icon">
                    🎓
                </div>

                <div class="login-card-content">

                    <h3>Student</h3>

                    <p>
                        Access attendance, timetable, materials,
                        exams, results, fees and projects.
                    </p>

                    <span class="login-link">
                        Student Login →
                    </span>

                </div>

            </a>


            <!-- FACULTY -->
            <a
                href="auth/faculty-login.php"
                class="login-card faculty-card"
            >

                <div class="login-icon">
                    👨‍🏫
                </div>

                <div class="login-card-content">

                    <h3>Faculty</h3>

                    <p>
                        Manage attendance, timetable, materials,
                        examinations and student results.
                    </p>

                    <span class="login-link">
                        Faculty Login →
                    </span>

                </div>

            </a>


            <!-- EXAM CELL -->
            <a
                href="auth/exam-login.php"
                class="login-card exam-card"
            >

                <div class="login-icon">
                    📝
                </div>

                <div class="login-card-content">

                    <h3>Exam Cell</h3>

                    <p>
                        Manage exams, marks, hall tickets,
                        notifications and examination reports.
                    </p>

                    <span class="login-link">
                        Exam Cell Login →
                    </span>

                </div>

            </a>


            <!-- OFFICE -->
            <a
                href="auth/office-login.php"
                class="login-card office-card"
            >

                <div class="login-icon">
                    🏢
                </div>

                <div class="login-card-content">

                    <h3>Office</h3>

                    <p>
                        Manage student information, fees,
                        payments and official updates.
                    </p>

                    <span class="login-link">
                        Office Login →
                    </span>

                </div>

            </a>


            <!-- SAC -->
            <a
                href="auth/sac-login.php"
                class="login-card sac-card"
            >

                <div class="login-icon">
                    🎯
                </div>

                <div class="login-card-content">

                    <h3>Student Activity Council</h3>

                    <p>
                        Manage clubs, activities, announcements,
                        notifications and student engagement.
                    </p>

                    <span class="login-link">
                        SAC Login →
                    </span>

                </div>

            </a>


            <!-- ADMIN -->
            <a
                href="auth/admin-login.php"
                class="login-card admin-card"
            >

                <div class="login-icon">
                    ⚙️
                </div>

                <div class="login-card-content">

                    <h3>Administrator</h3>

                    <p>
                        Manage the complete SmartCampus system,
                        users, data and platform settings.
                    </p>

                    <span class="login-link">
                        Admin Login →
                    </span>

                </div>

            </a>

        </div>

    </section>


    <!-- FOOTER -->
    <footer class="home-footer">

        <div>
            <strong>SmartCampus</strong>
            <span> | College Management System</span>
        </div>

        <p>
            © <?php echo date("Y"); ?> SmartCampus. All Rights Reserved.
        </p>

    </footer>


    <script>

        function toggleHomeTheme() {

            document.body.classList.toggle("light-theme");

            if (
                document.body.classList.contains("light-theme")
            ) {

                localStorage.setItem(
                    "smartcampus-theme",
                    "light"
                );

            } else {

                localStorage.setItem(
                    "smartcampus-theme",
                    "dark"
                );

            }
        }


        if (
            localStorage.getItem("smartcampus-theme") === "light"
        ) {

            document.body.classList.add("light-theme");

        }

    </script>

</body>
</html>