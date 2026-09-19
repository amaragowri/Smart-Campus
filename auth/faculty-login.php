<?php

session_start();
require_once "../config/config.php";
require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";
    if (!empty($csrf_token) && !verify_csrf_token($csrf_token)) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

        $sql = "SELECT user_id, username, password, role, status
                FROM users
                WHERE username = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if ($user["status"] !== "ACTIVE") {

                $error = "Your account is inactive.";

            } elseif ($user["role"] !== "FACULTY") {

                $error = "This login is only for faculty members.";

            } elseif (password_verify($password, $user["password"])) {

                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];

                header("Location: ../faculty/dashboard.php");
                exit();

            } else {

                $error = "Invalid username or password.";
            }

        } else {

            $error = "Invalid username or password.";
        }

        $stmt->close();
    }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Faculty Login | SmartCampus</title>

    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <link rel="stylesheet" href="../assets/css/toast.css">
    <link rel="stylesheet" href="../assets/css/auth.css">

</head>


<body>

<div class="login-page">

    <div class="login-card">


        <!-- THEME TOGGLE -->

        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            title="Change theme">

            🌙

        </button>


        <!-- HEADER -->

        <div class="login-header">

            <div class="login-logo">
                SC
            </div>

            <h1>
                SmartCampus
            </h1>

            <p>
                Faculty Portal
            </p>

        </div>


        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- LOGIN FORM -->

        <form method="POST" action="" data-async-auth data-role="FACULTY">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">


            <!-- USERNAME -->

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="Enter your username"
                    autocomplete="username"
                    required>

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required>

                    <button
                        type="button"
                        class="password-toggle"
                        id="passwordToggle"
                        title="Show password">

                        👁

                    </button>

                </div>

            </div>


            <!-- OPTIONS -->

            <div class="login-options">

                <label class="remember-me">

                    <input
                        type="checkbox"
                        name="remember">

                    <span>
                        Remember me
                    </span>

                </label>


                <a href="forgot-password.php">
                    Forgot Password?
                </a>

            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="login-btn">

                Login

            </button>


        </form>


        <!-- FOOTER -->

        <div class="login-footer">

            <p>
                © <?php echo date("Y"); ?> SmartCampus
            </p>

            <p>
                Faculty Management Portal
            </p>

        </div>


    </div>

</div>


<script>

/* =========================================
   DARK / LIGHT THEME
========================================= */

const themeToggle =
    document.getElementById("themeToggle");

const savedTheme =
    localStorage.getItem("smartcampus-theme");


if (savedTheme === "dark") {

    document.body.classList.add("dark-theme");

    themeToggle.textContent = "☀️";

} else {

    themeToggle.textContent = "🌙";

}


themeToggle.addEventListener("click", function () {

    document.body.classList.toggle("dark-theme");

    const isDark =
        document.body.classList.contains("dark-theme");


    if (isDark) {

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

});


/* =========================================
   SHOW / HIDE PASSWORD
========================================= */

const password =
    document.getElementById("password");

const passwordToggle =
    document.getElementById("passwordToggle");


passwordToggle.addEventListener("click", function () {

    if (password.type === "password") {

        password.type = "text";

        passwordToggle.textContent = "🙈";

        passwordToggle.title = "Hide password";

    } else {

        password.type = "password";

        passwordToggle.textContent = "👁";

        passwordToggle.title = "Show password";

    }

});

</script>

<script src="../assets/js/toast.js"></script>
<script src="../assets/js/app.js"></script>

</body>

</html>