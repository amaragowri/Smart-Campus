<?php

session_start();

$_SESSION = [];

session_destroy();

header("Location: student-login.php");
exit();

?>