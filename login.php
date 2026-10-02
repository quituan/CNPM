<?php
session_start();

// Bật hiển thị lỗi
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Kết nối CSDL
require_once '../config/connect.php';

// Đọc thông báo từ SESSION
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$active_tab = isset($_SESSION['active_tab']) ? $_SESSION['active_tab'] : 'login';

// Xóa thông báo sau khi hiển thị
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['active_tab']);

// ==================== XỬ LÝ ĐĂNG NHẬP ====================
// ==================== XỬ LÝ QUÊN MẬT KHẨU (ĐÃ FIX LỖI TÀI KHOẢN KHÔNG TỒN TẠI) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_forgot'])) {
    $forgot_user = trim($_POST['forgot_username']);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if (empty($forgot_user) || empty($new_password) || empty($confirm_new_password)) {
        $_SESSION['error'] = "Vui lòng điền đầy đủ thông tin khôi phục!";
    } elseif ($new_password !== $confirm_new_password) {
        $_SESSION['error'] = "Mật khẩu mới và mật khẩu xác nhận không trùng khớp!";
    } else {
        // KIỂM TRA XEM TÊN ĐĂNG NHẬP CÓ TỒN TẠI TRONG CSDL KHÔNG
        $stmt_fg = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_fg->bind_param("s", $forgot_user);
        $stmt_fg->execute();
        $res_fg = $stmt_fg->get_result();

        if ($res_fg && $res_fg->num_rows > 0) {
            // Tài khoản có tồn tại -> Tiến hành đổi mật khẩu mới
            $hashed_pwd = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt_up = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
            $stmt_up->bind_param("ss", $hashed_pwd, $forgot_user);
            
            if ($stmt_up->execute()) {
                $_SESSION['success'] = "Đặt lại mật khẩu thành công! Hãy đăng nhập bằng mật khẩu mới.";
            } else {
                $_SESSION['error'] = "Lỗi hệ thống khi cập nhật mật khẩu!";
            }
            $stmt_up->close();
        } else {
            // TÀI KHOẢN KHÔNG TỒN TẠI -> BÁO LỖI NGAY, KHÔNG CHO ĐỔI
            $_SESSION['error'] = "Tên đăng nhập này chưa được đăng ký trong hệ thống!";
        }
        $stmt_fg->close();
    }

    $_SESSION['active_tab'] = 'login';
    header("Location: login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!";
        $_SESSION['active_tab'] = 'login';
        header("Location: login.php");
        exit();
    }

    // 1. Kiểm tra tài khoản mặc định trực tiếp (Admin & KTV mật khẩu '123')
    if (($username === 'admin' || $username === 'ktv1' || $username === 'kythuat1') && $password === '123') {
        $_SESSION['user_id'] = ($username === 'admin') ? 1 : 2;
        $_SESSION['username'] = $username;
        $_SESSION['fullname'] = ($username === 'admin') ? 'Quản Trị Viên (Admin)' : 'Kỹ Thuật Viên';
        $_SESSION['role'] = ($username === 'admin') ? 'admin' : 'kythuat';

        if ($_SESSION['role'] === 'admin') {
            header("Location: ../pages/admin.php");
        } else {
            header("Location: ../pages/kythuat.php");
        }
        exit();
    }

    // 2. Kiểm tra tài khoản trong Database
    $stmt = $conn->prepare("SELECT id, username, password, fullname, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Kiểm tra mật khẩu mã hóa (bcrypt), mật khẩu thô, hoặc mật khẩu mặc định '123'
        if (password_verify($password, $user['password']) || $password === $user['password'] || $password === '123') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'] ?? $user['username'];
            $_SESSION['role'] = $user['role'];

            // Điều hướng chính xác theo vai trò
            if ($user['role'] === 'admin') {
                header("Location: ../pages/admin.php");
            } elseif ($user['role'] === 'kythuat') {
                header("Location: ../pages/kythuat.php");
            } else {
                // Khách hàng (role = 'khachhang') chuyển về Trang Chủ
                header("Location: ../index.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Mật khẩu không chính xác!";
        }
    } else {
        $_SESSION['error'] = "Tài khoản không tồn tại trên hệ thống!";
    }

    $stmt->close();
    $_SESSION['active_tab'] = 'login';
    header("Location: login.php");
    exit();
}

// ==================== XỬ LÝ ĐĂNG KÝ ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_register'])) {
    $fullname = trim($_POST['reg_fullname']);
    $phone = trim($_POST['reg_phone']);
    $username = trim($_POST['reg_username']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Mật khẩu xác nhận không khớp!";
        $_SESSION['active_tab'] = 'register';
    } else {
        // Kiểm tra trùng username
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($res_check && $res_check->num_rows > 0) {
            $_SESSION['error'] = "Tên đăng nhập đã tồn tại! Vui lòng chọn tên khác.";
            $_SESSION['active_tab'] = 'register';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $role = 'khachhang'; // Mặc định tài khoản đăng ký là Khách hàng

            $stmt_ins = $conn->prepare("INSERT INTO users (username, password, fullname, phone, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt_ins->bind_param("sssss", $username, $hashed_password, $fullname, $phone, $role);

            if ($stmt_ins->execute()) {
                $_SESSION['success'] = "Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay.";
                $_SESSION['active_tab'] = 'login';
            } else {
                $_SESSION['error'] = "Lỗi hệ thống khi đăng ký: " . $conn->error;
                $_SESSION['active_tab'] = 'register';
            }
            $stmt_ins->close();
        }
        $stmt_check->close();
    }

    header("Location: login.php");
    exit();
}

// ==================== XỬ LÝ QUÊN MẬT KHẨU ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_forgot'])) {
    $forgot_user = trim($_POST['forgot_username']);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if ($new_password !== $confirm_new_password) {
        $_SESSION['error'] = "Mật khẩu mới và mật khẩu xác nhận không trùng khớp!";
    } else {
        $stmt_fg = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_fg->bind_param("s", $forgot_user);
        $stmt_fg->execute();
        $res_fg = $stmt_fg->get_result();

        if ($res_fg && $res_fg->num_rows > 0) {
            $hashed_pwd = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt_up = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
            $stmt_up->bind_param("ss", $hashed_pwd, $forgot_user);
            
            if ($stmt_up->execute()) {
                $_SESSION['success'] = "Đặt lại mật khẩu thành công! Hãy đăng nhập bằng mật khẩu mới.";
            } else {
                $_SESSION['error'] = "Lỗi khi cập nhật mật khẩu!";
            }
            $stmt_up->close();
        } else {
            $_SESSION['error'] = "Không tìm thấy tên đăng nhập trong hệ thống!";
        }
        $stmt_fg->close();
    }

    $_SESSION['active_tab'] = 'login';
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC 24/7 - Đăng nhập & Đăng ký</title>
    <!-- FontAwesome & Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.98);
            width: 100%;
            max-width: 440px;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            padding: 35px 30px;
            position: relative;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .brand-logo h1 {
            font-size: 28px;
            font-weight: 700;
            color: #00a859;
            letter-spacing: 1px;
        }

        .brand-logo p {
            font-size: 13px;
            color: #6c757d;
            margin-top: 4px;
        }

        /* Tab Switcher */
        .tab-header {
            display: flex;
            background: #eef2f5;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 25px;
        }

        .tab-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border: none;
            background: transparent;
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            background: #ffffff;
            color: #00a859;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #333;
            margin-bottom: 6px;
        }

        .input-box {
            position: relative;
        }

        .input-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 15px;
        }

        .input-box input {
            width: 100%;
            padding: 11px 14px 11px 42px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            outline: none;
        }

        .input-box input:focus {
            border-color: #00a859;
            box-shadow: 0 0 0 3px rgba(0, 168, 89, 0.15);
        }

        .actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .forgot-link {
            color: #00a859;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Buttons */
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #00a859;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn-submit:hover {
            background: #008d4a;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #a0a0a0;
            font-size: 12px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }

        .divider span {
            padding: 0 10px;
        }

        .btn-google {
            width: 100%;
            padding: 11px;
            background: #ffffff;
            color: #333;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn-google:hover {
            background: #f8f9fa;
        }

        /* Alert Messages */
        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        /* Modal Quên mật khẩu */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 999;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 18px;
            cursor: pointer;
            color: #6c757d;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <!-- Logo & Thương hiệu -->
        <div class="brand-logo">
            <h1><i class="fa-solid fa-desktop"></i> PC 24/7</h1>
            <p>Hệ thống Đặt lịch & Sửa chữa Máy tính 24/7</p>
        </div>

        <!-- Thông báo Lỗi / Thành công -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Tab chuyển đổi -->
        <div class="tab-header">
            <button class="tab-btn <?php echo ($active_tab == 'login') ? 'active' : ''; ?>" onclick="switchTab('login')">Đăng Nhập</button>
            <button class="tab-btn <?php echo ($active_tab == 'register') ? 'active' : ''; ?>" onclick="switchTab('register')">Đăng Ký Khách</button>
        </div>

        <!-- FORM ĐĂNG NHẬP -->
        <form id="form-login" action="login.php" method="POST" style="display: <?php echo ($active_tab == 'login') ? 'block' : 'none'; ?>;">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <div class="input-box">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="username" placeholder="Nhập tên đăng nhập..." required>
                </div>
            </div>

            <div class="form-group">
                <label>Mật khẩu</label>
                <div class="input-box">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <div class="actions-row">
                <label><input type="checkbox" name="remember"> Ghi nhớ tôi</label>
                <a href="#" class="forgot-link" onclick="openModal()">Quên mật khẩu?</a>
            </div>

            <button type="submit" name="btn_login" class="btn-submit">Đăng Nhập</button>

            <div class="divider">
                <span>HOẶC</span>
            </div>

            <button type="button" class="btn-google" onclick="loginWithGoogle()">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.15C3.26 21.3 7.31 24 12 24z"/><path fill="#FBBC05" d="M5.28 14.24c-.25-.72-.38-1.49-.38-2.24s.13-1.52.38-2.24V6.61H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.39l3.99-3.15z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.61l3.99 3.15c.95-2.85 3.6-4.96 6.72-4.96z"/></svg>
                Đăng nhập bằng Google
            </button>
        </form>

        <!-- FORM ĐĂNG KÝ TÀI KHOẢN KHÁCH -->
        <form id="form-register" action="login.php" method="POST" style="display: <?php echo ($active_tab == 'register') ? 'block' : 'none'; ?>;">
            <div class="form-group">
                <label>Họ và tên khách hàng</label>
                <div class="input-box">
                    <i class="fa-solid fa-id-card"></i>
                    <input type="text" name="reg_fullname" placeholder="Nguyễn Văn A" required>
                </div>
            </div>

            <div class="form-group">
                <label>Số điện thoại</label>
                <div class="input-box">
                    <i class="fa-solid fa-phone"></i>
                    <input type="tel" name="reg_phone" placeholder="0905123456" pattern="[0-9]{10,11}" title="Vui lòng nhập số điện thoại hợp lệ" required>
                </div>
            </div>

            <div class="form-group">
                <label>Tên đăng nhập</label>
                <div class="input-box">
                    <i class="fa-solid fa-user-tag"></i>
                    <input type="text" name="reg_username" placeholder="Tên tài khoản..." required>
                </div>
            </div>

            <div class="form-group">
                <label>Mật khẩu</label>
                <div class="input-box">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="reg_password" placeholder="••••••••" required>
                </div>
            </div>

            <div class="form-group">
                <label>Xác nhận mật khẩu</label>
                <div class="input-box">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="password" name="reg_confirm_password" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" name="btn_register" class="btn-submit">Đăng Ký Tài Khoản Khách</button>
        </form>
    </div>

    <!-- MODAL QUÊN MẬT KHẨU -->
    <div id="modal-forgot" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h3 style="margin-bottom: 10px; color: #00a859;">Khôi Phục Mật Khẩu</h3>
            <p style="font-size: 13px; color: #6c757d; margin-bottom: 15px;">Nhập tên đăng nhập và mật khẩu mới để thiết lập lại tài khoản.</p>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label>Tên đăng nhập</label>
                    <div class="input-box">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="forgot_username" placeholder="Nhập tên đăng nhập..." required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mật khẩu mới</label>
                    <div class="input-box">
                        <i class="fa-solid fa-key"></i>
                        <input type="password" name="new_password" placeholder="Mật khẩu mới..." required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Nhập lại mật khẩu mới</label>
                    <div class="input-box">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="confirm_new_password" placeholder="Nhập lại mật khẩu mới..." required>
                    </div>
                </div>

                <button type="submit" name="btn_forgot" class="btn-submit">Xác Nhận Đặt Lại</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const loginForm = document.getElementById('form-login');
            const regForm = document.getElementById('form-register');
            const tabs = document.querySelectorAll('.tab-btn');

            if (tab === 'login') {
                loginForm.style.display = 'block';
                regForm.style.display = 'none';
                tabs[0].classList.add('active');
                tabs[1].classList.remove('active');
            } else {
                loginForm.style.display = 'none';
                regForm.style.display = 'block';
                tabs[0].classList.remove('active');
                tabs[1].classList.add('active');
            }
        }

        function openModal() {
            document.getElementById('modal-forgot').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('modal-forgot').style.display = 'none';
        }

        function loginWithGoogle() {
            alert("Tính năng Đăng nhập bằng Google sẽ kết nối với tài khoản Google của bạn.");
        }
    </script>
</body>
</html>