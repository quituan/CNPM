<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "suachua_maytinh"; // Sửa lại thành suachua_maytinh cho khớp với phpMyAdmin

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>