<?php

require_once "../config/database.php";

/* Faculty login details */
$username = "faculty001";
$password = "Faculty@123";
$role = "FACULTY";
$status = "ACTIVE";

/* Hash password */
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

/* Check whether username already exists */
$check = $conn->prepare(
    "SELECT user_id FROM users WHERE username = ?"
);

$check->bind_param("s", $username);
$check->execute();

$result = $check->get_result();

if ($result->num_rows > 0) {

    echo "<h2>Faculty account already exists.</h2>";

} else {

    /* Insert user */
    $sql = "
        INSERT INTO users
        (username, password, role, status)
        VALUES (?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssss",
        $username,
        $hashed_password,
        $role,
        $status
    );

    if ($stmt->execute()) {

        $user_id = $stmt->insert_id;

        /* Insert faculty profile */
        $faculty_sql = "
            INSERT INTO faculty
            (user_id, employee_id, full_name, email, phone, department, designation, specialization)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $faculty_stmt = $conn->prepare($faculty_sql);

        $employee_id = "FAC001";
        $full_name = "Test Faculty";
        $email = "faculty001@smartcampus.com";
        $phone = "9876543210";
        $department = "CSE";
        $designation = "Assistant Professor";
        $specialization = "Computer Science";

        $faculty_stmt->bind_param(
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

        if ($faculty_stmt->execute()) {

            echo "<h2>Faculty account created successfully!</h2>";

            echo "<p><b>Username:</b> faculty001</p>";
            echo "<p><b>Password:</b> Faculty@123</p>";
            echo "<p><b>Employee ID:</b> FAC001</p>";

        } else {

            echo "Faculty profile creation failed: "
                . $faculty_stmt->error;
        }

        $faculty_stmt->close();

    } else {

        echo "User creation failed: " . $stmt->error;
    }

    $stmt->close();
}

$check->close();
$conn->close();

?>