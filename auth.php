<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
}

function checkRole($allowed_roles = []) {
    checkLogin();
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        echo "<h3 style='color:red;'>Bạn không có quyền truy cập vào trang này!</h3>";
        echo "<a href='../dashboard.php'>Quay lại Dashboard</a>";
        exit();
    }
}
?>