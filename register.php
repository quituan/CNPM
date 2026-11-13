<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Kết nối CSDL (cùng cấp thư mục auth/ nên gọi ../config/connect.php)
require_once '../config/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_register'])) {
    $fullname = trim($_POST['reg_fullname']);
    $phone = trim($_POST['reg_phone']);
    $username = trim($_POST['reg_username']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];

    // Mặc định giữ ở tab register nếu có lỗi xảy ra
    $_SESSION['active_tab'] = 'register';

    // 1. Kiểm tra định dạng số điện thoại (10 - 11 chữ số)
    if (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $_SESSION['error'] = "Số điện thoại không hợp lệ! Vui lòng nhập từ 10 đến 11 chữ số.";
    } elseif ($password !== $confirm_password) {
        $_SESSION['error'] = "Mật khẩu xác nhận không khớp!";
    } else {
        // 2. Kiểm tra số điện thoại hoặc tên đăng nhập đã tồn tại chưa
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR phone = ?");
        $stmt_check->bind_param("ss", $username, $phone);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        
        if ($res_check && $res_check->num_rows > 0) {
            $_SESSION['error'] = "Tên đăng nhập hoặc Số điện thoại này đã được sử dụng!";
        } else {
            // 3. Mã hóa mật khẩu BCRYPT & Thêm mới vào CSDL
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $role = 'khachhang';
            
            $stmt_ins = $conn->prepare("INSERT INTO users (username, password, fullname, phone, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt_ins->bind_param("sssss", $username, $hashed_password, $fullname, $phone, $role);
            
            if ($stmt_ins->execute()) {
                $_SESSION['success'] = "Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay.";
                $_SESSION['active_tab'] = 'login'; // Chuyển về tab đăng nhập
            } else {
                $_SESSION['error'] = "Lỗi hệ thống khi đăng ký: " . $conn->error;
            }
            $stmt_ins->close();
        }
        $stmt_check->close();
    }
}

// Luôn quay lại giao diện login.php để hiển thị thông báo
header("Location: login.php");
exit();
?>