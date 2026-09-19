<?php

session_start();

require_once "../config/database.php";

$error = "";


/* =========================
   ALREADY LOGGED IN
========================= */

if (
    isset($_SESSION["user_id"]) &&
    isset($_SESSION["role"]) &&
    $_SESSION["role"] === "EXAM_CELL"
) {

    header("Location: ../exam/dashboard.php");
    exit();

}


/* =========================
   LOGIN PROCESS
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";


    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT user_id, username, password, role, status
            FROM users
            WHERE username = ?
              AND role = 'EXAM_CELL'
            LIMIT 1
        ");


        if (!$stmt) {

            $error = "System error. Please try again.";

        } else {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();


            if ($result && $result->num_rows === 1) {

                $user = $result->fetch_assoc();


                /* =========================
                   ACCOUNT STATUS
                ========================= */

                if (
                    strtoupper(trim($user["status"])) !== "ACTIVE"
                ) {

                    $error = "Exam Cell account is inactive.";

                } else {

                    $valid_password = false;


                    /* Plain-text password
                       for demo/test accounts */

                    if (
                        hash_equals(
                            (string)$user["password"],
                            $password
                        )
                    ) {

                        $valid_password = true;

                    }


                    /* Hashed password */

                    elseif (
                        password_verify(
                            $password,
                            $user["password"]
                        )
                    ) {

                        $valid_password = true;

                    }


                    if ($valid_password) {

                        session_regenerate_id(true);

                        $_SESSION["user_id"] =
                            $user["user_id"];

                        $_SESSION["username"] =
                            $user["username"];

                        $_SESSION["role"] =
                            "EXAM_CELL";


                        header(
                            "Location: ../exam/dashboard.php"
                        );

                        exit();


                    } else {

                        $error =
                            "Invalid username or password.";

                    }

                }

            } else {

                $error =
                    "Exam Cell account not found.";

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Exam Cell Login | SmartCampus
    </title>


    <!-- COMMON LOGIN CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/auth.css"
    >

</head>


<body>

<div class="login-page">

    <div class="login-card">


        <!-- =========================
             THEME TOGGLE
        ========================== -->

        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            title="Change theme"
        >

            🌙

        </button>


        <!-- =========================
             HEADER
        ========================== -->

        <div class="login-header">

            <div class="login-logo">
                SC
            </div>


            <h1>
                SmartCampus
            </h1>


            <p>
                Examination Cell Portal
            </p>

        </div>


        <!-- =========================
             ERROR
        ========================== -->

        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                ⚠️

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- =========================
             LOGIN FORM
        ========================== -->

        <form
            method="POST"
            action=""
        >


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
                    placeholder="Enter username"
                    value="<?php
                        echo htmlspecialchars(
                            $_POST["username"] ?? ""
                        );
                    ?>"
                    autocomplete="username"
                    required
                >

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
                        placeholder="Enter password"
                        autocomplete="current-password"
                        required
                    >


                    <button
                        type="button"
                        class="password-toggle"
                        id="passwordToggle"
                        title="Show password"
                    >

                        👁

                    </button>

                </div>

            </div>


            <!-- OPTIONS -->

            <div class="login-options">

                <label class="remember-me">

                    <input
                        type="checkbox"
                        name="remember"
                    >

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
                class="login-btn"
            >

                Login to Exam Cell

            </button>


        </form>


        <!-- =========================
             FOOTER
        ========================== -->

        <div class="login-footer">

            <p>
                © <?php echo date("Y"); ?> SmartCampus
            </p>


            <p>
                Examination Cell Management Portal
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


themeToggle.addEventListener(
    "click",
    function () {

        document.body.classList.toggle(
            "dark-theme"
        );


        const isDark =
            document.body.classList.contains(
                "dark-theme"
            );


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

    }
);


/* =========================================
   SHOW / HIDE PASSWORD
========================================= */

const password =
    document.getElementById("password");


const passwordToggle =
    document.getElementById("passwordToggle");


passwordToggle.addEventListener(
    "click",
    function () {

        if (password.type === "password") {

            password.type = "text";

            passwordToggle.textContent = "🙈";

            passwordToggle.title =
                "Hide password";

        } else {

            password.type = "password";

            passwordToggle.textContent = "👁";

            passwordToggle.title =
                "Show password";

        }

    }
);

</script>

</body>

</html>