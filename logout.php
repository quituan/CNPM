<?php
session_start();

// Xóa toàn bộ biến Session
$_SESSION = array();

// Xóa Cookie Session nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy Session
session_destroy();

// Chuyển hướng về lại trang đăng nhập
header("Location: login.php");
exit();
?>