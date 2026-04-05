<?php

$host = "localhost";
$user = "root";
$password = "adminrpa@13579";
$dbname = "cv_sorting";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

?>