<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "EXAM_CELL") {
    header("Location: ../auth/exam-login.php");
    exit();
}

require_once "../config/database.php";

$message = "";
$message_type = "";

$user_id = $_SESSION["user_id"];


/* Exam Cell User */
$stmt = $conn->prepare("
    SELECT username
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$exam_user = $result->fetch_assoc();

$stmt->close();


/* Generate Hall Ticket */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate_ticket"])) {

    $student_id = (int)($_POST["student_id"] ?? 0);
    $exam_id = (int)($_POST["exam_id"] ?? 0);

    if ($student_id <= 0 || $exam_id <= 0) {

        $message = "Please select both student and examination.";
        $message_type = "error";

    } else {

        /* Check student */
        $stmt = $conn->prepare("
            SELECT
                student_id,
                roll_number,
                full_name
            FROM students
            WHERE student_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $student_id);
        $stmt->execute();

        $student_result = $stmt->get_result();
        $student = $student_result->fetch_assoc();

        $stmt->close();


        /* Check exam */
        $stmt = $conn->prepare("
            SELECT
                e.exam_id,
                e.exam_name,
                e.exam_date,
                e.start_time,
                e.end_time,
                e.room_number,
                e.status,
                s.subject_code,
                s.subject_name
            FROM exam_details e
            INNER JOIN subjects s
                ON e.subject_id = s.subject_id
            WHERE e.exam_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $exam_id);
        $stmt->execute();

        $exam_result = $stmt->get_result();
        $exam = $exam_result->fetch_assoc();

        $stmt->close();


        if (!$student) {

            $message = "Selected student was not found.";
            $message_type = "error";

        } elseif (!$exam) {

            $message = "Selected examination was not found.";
            $message_type = "error";

        } else {

            /* Check duplicate ticket */
            $stmt = $conn->prepare("
                SELECT hall_ticket_id
                FROM hall_tickets
                WHERE student_id = ?
                  AND exam_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $student_id,
                $exam_id
            );

            $stmt->execute();

            $duplicate_result = $stmt->get_result();

            if ($duplicate_result->num_rows > 0) {

                $message = "Hall ticket already generated for this student and exam.";
                $message_type = "error";

            } else {

                /*
                 * Generate unique hall ticket number
                 * Example: HT2026000123
                 */
                do {

                    $hall_ticket_number =
                        "HT"
                        . date("Y")
                        . strtoupper(
                            substr(
                                md5(
                                    uniqid(
                                        (string)$student_id,
                                        true
                                    )
                                ),
                                0,
                                8
                            )
                        );

                    $check_ticket = $conn->prepare("
                        SELECT hall_ticket_id
                        FROM hall_tickets
                        WHERE hall_ticket_number = ?
                        LIMIT 1
                    ");

                    $check_ticket->bind_param(
                        "s",
                        $hall_ticket_number
                    );

                    $check_ticket->execute();

                    $ticket_result =
                        $check_ticket->get_result();

                    $exists =
                        $ticket_result->num_rows > 0;

                    $check_ticket->close();

                } while ($exists);


                $issue_date = date("Y-m-d");

                $status = "ACTIVE";


                $insert = $conn->prepare("
                    INSERT INTO hall_tickets
                    (
                        student_id,
                        exam_id,
                        hall_ticket_number,
                        issue_date,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $insert->bind_param(
                    "iisss",
                    $student_id,
                    $exam_id,
                    $hall_ticket_number,
                    $issue_date,
                    $status
                );


                if ($insert->execute()) {

                    $message =
                        "Hall ticket generated successfully: "
                        . $hall_ticket_number;

                    $message_type = "success";

                } else {

                    $message =
                        "Failed to generate hall ticket: "
                        . $insert->error;

                    $message_type = "error";
                }

                $insert->close();
            }

            $stmt->close();
        }
    }
}


/* Delete Hall Ticket */
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_ticket"])
) {

    $hall_ticket_id =
        (int)($_POST["hall_ticket_id"] ?? 0);


    if ($hall_ticket_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM hall_tickets
            WHERE hall_ticket_id = ?
        ");

        $stmt->bind_param(
            "i",
            $hall_ticket_id
        );


        if ($stmt->execute()) {

            $message =
                "Hall ticket deleted successfully.";

            $message_type = "success";

        } else {

            $message =
                "Unable to delete hall ticket.";

            $message_type = "error";
        }

        $stmt->close();
    }
}


/* Load Students */
$students = [];

$result = $conn->query("
    SELECT
        student_id,
        roll_number,
        full_name,
        department,
        year,
        section
    FROM students
    ORDER BY roll_number
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}


/* Load Exams */
$exams = [];

$result = $conn->query("
    SELECT
        e.exam_id,
        e.exam_name,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_number,
        e.status,
        s.subject_code,
        s.subject_name
    FROM exam_details e
    INNER JOIN subjects s
        ON e.subject_id = s.subject_id
    ORDER BY e.exam_date ASC, e.start_time ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
}


/* Load Hall Tickets */
$tickets = [];

$result = $conn->query("
    SELECT
        h.hall_ticket_id,
        h.hall_ticket_number,
        h.issue_date,
        h.status,

        st.roll_number,
        st.full_name,
        st.department,
        st.year,
        st.section,

        e.exam_name,
        e.exam_type,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room_number,

        s.subject_code,
        s.subject_name

    FROM hall_tickets h

    INNER JOIN students st
        ON h.student_id = st.student_id

    INNER JOIN exam_details e
        ON h.exam_id = e.exam_id

    INNER JOIN subjects s
        ON e.subject_id = s.subject_id

    ORDER BY e.exam_date ASC, st.roll_number ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}


/* Statistics */
$total_tickets = count($tickets);

$active_tickets = 0;
$inactive_tickets = 0;

foreach ($tickets as $ticket) {

    if (
        strtoupper(trim($ticket["status"])) === "ACTIVE"
    ) {
        $active_tickets++;
    } else {
        $inactive_tickets++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Hall Tickets | SmartCampus</title>

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

                <span>Exam Cell Portal</span>

            </div>

        </div>


        <nav class="sidebar-menu">

            <a href="dashboard.php">
                <span>🏠</span>
                Dashboard
            </a>


            <a href="exams.php">
                <span>📝</span>
                Exam Schedule
            </a>


            <a href="marks.php">
                <span>📊</span>
                Marks Management
            </a>


            <a href="hall-tickets.php"
               class="active">

                <span>🎫</span>
                Hall Tickets

            </a>


            <a href="notifications.php">

                <span>🔔</span>
                Notifications

            </a>


            <a href="reports.php">

                <span>📄</span>
                Exam Reports

            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../auth/logout.php">

                <span>🚪</span>
                Logout

            </a>

        </div>

    </aside>


    <!-- MAIN -->

    <main class="dashboard-content">


        <!-- HEADER -->

        <header class="dashboard-header">

            <div class="welcome-text">

                <h1>Hall Tickets</h1>

                <p>
                    Generate and manage student hall tickets
                </p>

            </div>


            <div class="dashboard-header-right">

                <div class="student-meta">

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $exam_user["username"]
                            ?? "Exam Cell"
                        );
                        ?>

                    </strong>

                    <span>
                        Examination Cell
                    </span>

                </div>


                <button
                    class="dashboard-theme-toggle"
                    id="themeToggle"
                    type="button">

                    🌙

                </button>

            </div>

        </header>


        <!-- MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="exam-hall-message
                        <?php echo $message_type; ?>">

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <section class="exam-hall-summary">


            <div class="exam-hall-summary-card">

                <div class="exam-hall-summary-icon">
                    🎫
                </div>

                <div>

                    <span>Total Tickets</span>

                    <strong>
                        <?php echo $total_tickets; ?>
                    </strong>

                </div>

            </div>


            <div class="exam-hall-summary-card">

                <div class="exam-hall-summary-icon">
                    ✅
                </div>

                <div>

                    <span>Active</span>

                    <strong>
                        <?php echo $active_tickets; ?>
                    </strong>

                </div>

            </div>


            <div class="exam-hall-summary-card">

                <div class="exam-hall-summary-icon">
                    ⏸️
                </div>

                <div>

                    <span>Inactive</span>

                    <strong>
                        <?php echo $inactive_tickets; ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- GENERATE -->

        <section class="exam-hall-form-section">


            <div class="exam-hall-section-header">

                <div>

                    <h2>
                        Generate Hall Ticket
                    </h2>

                    <p>
                        Select a student and examination
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="hall-tickets.php"
                class="exam-hall-form">


                <input
                    type="hidden"
                    name="generate_ticket"
                    value="1"
                >


                <div class="exam-hall-form-group">

                    <label for="student_id">
                        Student *
                    </label>

                    <select
                        name="student_id"
                        id="student_id"
                        required>

                        <option value="">
                            Select Student
                        </option>


                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?php
                                echo (int)$student["student_id"];
                                ?>">

                                <?php

                                echo htmlspecialchars(
                                    $student["roll_number"]
                                    . " - "
                                    . $student["full_name"]
                                    . " ("
                                    . $student["department"]
                                    . ")"
                                );

                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="exam-hall-form-group">

                    <label for="exam_id">
                        Examination *
                    </label>

                    <select
                        name="exam_id"
                        id="exam_id"
                        required>

                        <option value="">
                            Select Examination
                        </option>


                        <?php foreach ($exams as $exam): ?>

                            <option
                                value="<?php
                                echo (int)$exam["exam_id"];
                                ?>">

                                <?php

                                echo htmlspecialchars(
                                    $exam["subject_code"]
                                    . " - "
                                    . $exam["exam_name"]
                                    . " - "
                                    . date(
                                        "d M Y",
                                        strtotime(
                                            $exam["exam_date"]
                                        )
                                    )
                                );

                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="exam-hall-form-actions">

                    <button
                        type="submit"
                        class="exam-hall-generate-btn">

                        🎫 Generate Hall Ticket

                    </button>

                </div>

            </form>

        </section>


        <!-- TICKETS -->

        <section class="exam-hall-list-section">


            <div class="exam-hall-section-header">

                <div>

                    <h2>
                        Generated Hall Tickets
                    </h2>

                    <p>
                        All generated examination hall tickets
                    </p>

                </div>

            </div>


            <?php if (count($tickets) > 0): ?>


                <div class="exam-hall-table-wrapper">

                    <table class="exam-hall-table">

                        <thead>

                            <tr>

                                <th>Hall Ticket</th>

                                <th>Student</th>

                                <th>Subject</th>

                                <th>Examination</th>

                                <th>Date</th>

                                <th>Time</th>

                                <th>Room</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($tickets as $ticket): ?>


                                <tr>


                                    <td>

                                        <strong
                                            class="exam-hall-ticket-number">

                                            <?php
                                            echo htmlspecialchars(
                                                $ticket[
                                                    "hall_ticket_number"
                                                ]
                                            );
                                            ?>

                                        </strong>

                                        <small>

                                            Issued:

                                            <?php
                                            echo date(
                                                "d M Y",
                                                strtotime(
                                                    $ticket[
                                                        "issue_date"
                                                    ]
                                                )
                                            );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $ticket[
                                                    "roll_number"
                                                ]
                                            );
                                            ?>

                                        </strong>

                                        <small>

                                            <?php
                                            echo htmlspecialchars(
                                                $ticket[
                                                    "full_name"
                                                ]
                                            );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $ticket[
                                                    "subject_code"
                                                ]
                                            );
                                            ?>

                                        </strong>

                                        <small>

                                            <?php
                                            echo htmlspecialchars(
                                                $ticket[
                                                    "subject_name"
                                                ]
                                            );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $ticket[
                                                    "exam_name"
                                                ]
                                            );
                                            ?>

                                        </strong>

                                        <small>

                                            <?php
                                            echo htmlspecialchars(
                                                $ticket[
                                                    "exam_type"
                                                ]
                                            );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime(
                                                $ticket[
                                                    "exam_date"
                                                ]
                                            )
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo date(
                                            "h:i A",
                                            strtotime(
                                                $ticket[
                                                    "start_time"
                                                ]
                                            )
                                        );
                                        ?>

                                        <br>

                                        <small>

                                            to

                                            <?php
                                            echo date(
                                                "h:i A",
                                                strtotime(
                                                    $ticket[
                                                        "end_time"
                                                    ]
                                                )
                                            );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $ticket[
                                                "room_number"
                                            ]
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        $ticket_status =
                                            strtoupper(
                                                trim(
                                                    $ticket["status"]
                                                )
                                            );

                                        $status_class =
                                            $ticket_status === "ACTIVE"
                                            ? "active"
                                            : "inactive";

                                        ?>

                                        <span
                                            class="exam-hall-status
                                                   <?php
                                                   echo $status_class;
                                                   ?>">

                                            <?php
                                            echo htmlspecialchars(
                                                $ticket_status
                                            );
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <form
                                            method="POST"
                                            action="hall-tickets.php"
                                            onsubmit="return confirm('Delete this hall ticket?');">

                                            <input
                                                type="hidden"
                                                name="delete_ticket"
                                                value="1"
                                            >

                                            <input
                                                type="hidden"
                                                name="hall_ticket_id"
                                                value="<?php
                                                echo (int)$ticket[
                                                    "hall_ticket_id"
                                                ];
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="exam-hall-delete-btn">

                                                🗑

                                            </button>

                                        </form>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="exam-hall-empty">

                    <div>
                        🎫
                    </div>

                    <h3>
                        No Hall Tickets Generated
                    </h3>

                    <p>
                        Select a student and examination above
                        to generate a hall ticket.
                    </p>

                </div>


            <?php endif; ?>


        </section>

    </main>

</div>


<script>

/* Theme */

const themeToggle =
    document.getElementById("themeToggle");

const savedTheme =
    localStorage.getItem("theme");


if (savedTheme === "dark") {

    document.body.classList.add("dark-theme");

    if (themeToggle) {
        themeToggle.textContent = "☀️";
    }

}


if (themeToggle) {

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

            localStorage.setItem(
                "theme",
                isDark ? "dark" : "light"
            );

            themeToggle.textContent =
                isDark ? "☀️" : "🌙";

        }
    );

}

</script>

</body>
</html>