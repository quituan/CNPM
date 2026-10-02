<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/connect.php';
// KIỂM TRA VÀ TẠO BẢNG SERVICES NẾU CHƯA CÓ TRONG CSDL
$conn->query("CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_key VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    price_text VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    keyword VARCHAR(255)
)");
// NẠP DỮ LIỆU MẶC ĐỊNH NẾU BẢNG TRỐNG
$check_empty = $conn->query("SELECT COUNT(*) as total FROM services");
$row_count = $check_empty->fetch_assoc()['total'];
if ($row_count == 0) {
    $default_services = [
        ['service-1', 'Sửa chữa phần cứng / Mainboard', '150.000đ - 500.000đ', 'Khắc phục lỗi chập cháy, máy không lên nguồn, mất hình, lỗi chip VGA trên PC & Laptop.', 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?auto=format&fit=crop&w=600&q=80', 'sua chua phan cung mainboard'],
        ['service-2', 'Vệ sinh & tra keo tản nhiệt', '100.000đ', 'Làm sạch bụi bẩn toàn diện, tra keo tản nhiệt cao cấp MX-4 giúp máy luôn mát mẻ, bền bỉ.', 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?auto=format&fit=crop&w=600&q=80', 've sinh tra keo tan nhiet laptop pc'],
        ['service-3', 'Cài đặt Windows & Phần mềm', '120.000đ', 'Cài đặt Windows 10/11 bản quyền, Office, Photoshop, AutoCAD, Premiere và diệt virus.', 'https://images.unsplash.com/photo-1531482615713-2afd69097998?auto=format&fit=crop&w=600&q=80', 'cai dat windows win pham mem office'],
        ['service-4', 'Nâng cấp SSD & RAM', 'Theo linh kiện', 'Nâng cấp ổ cứng SSD NVMe, tăng dung lượng RAM giúp máy khởi động và chạy đa nhiệm mượt mà.', 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?auto=format&fit=crop&w=600&q=80', 'nang cap ssd ram toc do cao'],
        ['service-5', 'Cứu dữ liệu ổ cứng', 'Từ 300.000đ', 'Khôi phục dữ liệu bị xóa nhầm, format, ổ cứng kêu lạch cạch hoặc không nhận diện trong máy.', 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=600&q=80', 'cuu du lieu o cung mat file'],
        ['service-6', 'Thay bàn phím / Màn hình', 'Liên hệ báo giá', 'Thay thế bàn phím chính hãng, màn hình bị sọc, đốm mờ, nứt vỡ lấy ngay trong ngày.', 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=600&q=80', 'thay ban phim man hinh laptop']
    ];
    foreach ($default_services as $srv) {
        $stmt_ins = $conn->prepare("INSERT IGNORE INTO services (service_key, title, price_text, description, image_url, keyword) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("ssssss", $srv[0], $srv[1], $srv[2], $srv[3], $srv[4], $srv[5]);
        $stmt_ins->execute();
        $stmt_ins->close();
    }
}
// Kiểm tra trạng thái đăng nhập
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? '';
$fullname = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');
$success_popup = false;
$error_message = '';
$show_toast = false;
// XỬ LÝ ADMIN CẬP NHẬT ĐƠN GIÁ DỊCH VỤ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_update_price'])) {
    if ($user_role === 'admin') {
        $srv_id = intval($_POST['service_db_id'] ?? 0);
        $new_price = trim($_POST['new_price'] ?? '');
        if ($srv_id > 0 && !empty($new_price)) {
            $stmt_up = $conn->prepare("UPDATE services SET price_text = ? WHERE id = ?");
            $stmt_up->bind_param("si", $new_price, $srv_id);
            if ($stmt_up->execute()) {
                $show_toast = true; // Kích hoạt hiện thông báo nhỏ
            }
            $stmt_up->close();
        }
    }
}
// Xử lý gửi đơn đặt lịch từ trang chủ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_quick_booking'])) {
    if (!$is_logged_in) {
        header('Location: auth/login.php');
        exit();
    }
    $customer_name = trim($_POST['customer_name'] ?? $fullname);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $service_name = trim($_POST['service_name'] ?? '');
    $booking_date = trim($_POST['booking_date'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'Tiền mặt');
    $payment_verified = isset($_POST['payment_verified']) ? $_POST['payment_verified'] : '0';
    $user_id = $_SESSION['user_id'];
    
    if (!empty($customer_name) && !empty($service_name) && !empty($booking_date) && !empty($phone)) {
        if ($payment_method === 'Chuyển khoản ngân hàng' && $payment_verified !== '1') {
            $error_message = 'Lỗi: Quý khách chưa xác nhận thanh toán qua mã QR! Vui lòng quét mã và bấm nút "Xác Nhận Đã Thanh Toán Thành Công" trước khi gửi đơn.';
        } else {
            $status_text = ($payment_method === 'Chuyển khoản ngân hàng') ? 'Đã chuyển khoản & Chờ KTV' : 'Thanh toán tiền mặt & Chờ KTV';
            $full_note = "Địa chỉ: " . ($address !== '' ? $address : 'Không có') . " | Hình thức thanh toán: " . $payment_method . " | Ghi chú: " . $note;
            
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, customer_name, phone, service_name, booking_date, note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("issssss", $user_id, $customer_name, $phone, $service_name, $booking_date, $full_note, $status_text);
                $stmt->execute();
                $stmt->close();
            }
            $success_popup = true;
        }
    }
}
// LẤY DANH SÁCH DỊCH VỤ TỪ DATABASE
$services_list = [];
$result_srv = $conn->query("SELECT * FROM services ORDER BY id ASC");
if ($result_srv) {
    while ($row = $result_srv->fetch_assoc()) {
        $services_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC 24/7 - Dịch Vụ Sửa Chữa & Bảo Trì Máy Tính Chuyên Nghiệp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* THÔNG BÁO NHỎ DẠNG TOAST GÓC MÀN HÌNH */
        .toast-notification {
            position: fixed;
            top: 90px;
            right: 30px;
            background: #27ae60;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 4000;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(120%);
            transition: transform 0.4s ease-in-out;
        }
        .toast-notification.show {
            transform: translateX(0);
        }
        .floating-booking-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #00a859;
            color: white;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 26px;
            box-shadow: 0 4px 20px rgba(0, 168, 89, 0.4);
            cursor: pointer;
            z-index: 999;
            transition: transform 0.3s, background 0.3s;
            text-decoration: none;
        }
        .floating-booking-btn:hover {
            transform: scale(1.1);
            background: #008d4a;
            color: white;
        }
        .floating-booking-btn .tooltip-text {
            position: absolute;
            right: 75px;
            background: #212529;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            font-weight: 500;
        }
        .floating-booking-btn:hover .tooltip-text {
            opacity: 1;
        }
        .booking-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: <?php echo (!empty($error_message)) ? 'flex' : 'none'; ?>;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 20px;
        }
        .booking-modal-content {
            background: white;
            width: 100%;
            max-width: 800px;
            border-radius: 16px;
            padding: 30px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: modalSlideUp 0.3s ease;
        }
        @keyframes modalSlideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .btn-close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #f1f1f1;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            transition: background 0.3s;
        }
        .btn-close-modal:hover {
            background: #e2e8f0;
            color: #dc3545;
        }
        .error-alert-box {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success-alert-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: <?php echo $success_popup ? 'flex' : 'none'; ?>;
            justify-content: center;
            align-items: center;
            z-index: 3000;
            padding: 20px;
        }
        .success-alert-box {
            background: white;
            width: 100%;
            max-width: 450px;
            border-radius: 16px;
            padding: 35px 25px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
        }
        .success-alert-icon {
            font-size: 60px;
            color: #00a859;
            margin-bottom: 20px;
        }
        .success-alert-box h3 {
            font-size: 22px;
            color: #1a252f;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .success-alert-box p {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .btn-success-action {
            background: #00a859;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn-success-action:hover {
            background: #008d4a;
            color: white;
        }
        #qrPaymentSection {
            display: none;
            background: #f8f9fa;
            border: 1px dashed #00a859;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 18px;
        }
        #qrPaymentSection img {
            width: 180px;
            height: 180px;
            object-fit: contain;
            margin-top: 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: #fff;
            padding: 5px;
        }
        .btn-verify-payment {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.3s;
        }
        .btn-verify-payment:hover {
            background: #0056b3;
        }
        .verified-badge-text {
            color: #00a859;
            font-weight: 600;
            font-size: 13px;
            margin-top: 8px;
            display: none;
        }
        
        html { scroll-behavior: smooth; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #f8f9fa; color: #333; line-height: 1.6; }
        
        .top-bar { background: #0f2027; color: #fff; padding: 8px 5%; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
        .top-bar a { color: #00a859; text-decoration: none; font-weight: 600; }
        
        header { background: #ffffff; box-shadow: 0 2px 10px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000; }
        .navbar { max-width: 1350px; margin: 0 auto; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: nowrap; gap: 15px; }
        .logo { font-size: 22px; font-weight: 700; color: #00a859; text-decoration: none; display: flex; align-items: center; gap: 8px; white-space: nowrap; flex-shrink: 0; }
        
        .nav-links { display: flex; list-style: none; gap: 20px; align-items: center; margin: 0; padding: 0; flex-shrink: 1; }
        .nav-links a { color: #495057; text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.3s; white-space: nowrap; }
        .nav-links a:hover { color: #00a859; }
        
        .user-panel { display: flex; align-items: center; gap: 10px; background: #eef8f3; padding: 5px 12px; border-radius: 30px; border: 1px solid #c8e6d5; white-space: nowrap; flex-shrink: 0; }
        .user-panel span { font-size: 13px; color: #333; }
        .role-badge { font-size: 11px; padding: 2px 7px; border-radius: 10px; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
        .badge-admin { background: #dc3545; color: #fff; }
        .badge-ktv { background: #ffc107; color: #000; }
        .badge-cust { background: #00a859; color: #fff; }
        
        .btn-portal { background: #00a859; color: white !important; padding: 5px 12px; border-radius: 20px; font-weight: 600 !important; font-size: 12px !important; white-space: nowrap; text-decoration: none; }
        .btn-logout { background: #dc3545 !important; color: #fff !important; font-size: 12px !important; font-weight: 600 !important; padding: 5px 10px !important; border-radius: 6px !important; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; white-space: nowrap; }
        .btn-login { background: #00a859; color: white !important; padding: 7px 16px; border-radius: 8px; font-weight: 600 !important; font-size: 13px; white-space: nowrap; text-decoration: none; }
        
        .hero { background: linear-gradient(rgba(15, 32, 39, 0.85), rgba(32, 58, 67, 0.85)), url('https://images.unsplash.com/photo-1588702547919-26089e690ecc?auto=format&fit=crop&w=1600&q=80') no-repeat center center/cover; color: white; padding: 90px 20px; text-align: center; }
        .hero h1 { font-size: 38px; margin-bottom: 15px; font-weight: 700; }
        .hero p { font-size: 16px; color: #e2e8f0; max-width: 700px; margin: 0 auto 30px; }
        
        .search-container { max-width: 600px; margin: 0 auto 40px auto; position: relative; }
        .search-box-wrapper { display: flex; background: white; border-radius: 30px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border: 2px solid #00a859; }
        .search-box-wrapper input { flex: 1; padding: 14px 20px; border: none; outline: none; font-size: 15px; }
        .search-box-wrapper button { background: #00a859; color: white; border: none; padding: 0 25px; font-weight: 600; cursor: pointer; }
        .search-suggestions { position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 0 0 12px 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.12); margin-top: 5px; z-index: 99; max-height: 250px; overflow-y: auto; display: none; text-align: left; }
        .suggestion-item { padding: 10px 20px; font-size: 14px; color: #333; cursor: pointer; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; }
        .suggestion-item:hover { background: #eef8f3; color: #00a859; }
        
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .section-title { text-align: center; margin-bottom: 40px; }
        .section-title h2 { font-size: 28px; color: #1a252f; font-weight: 700; margin-bottom: 8px; }
        .section-title p { color: #6c757d; font-size: 14px; }
        
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 60px; }
        .service-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; border: 1px solid #f0f0f0; display: flex; flex-direction: column; justify-content: space-between; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,168,89,0.12); }
        .service-img { height: 160px; width: 100%; background-size: cover; background-position: center; }
        .service-body { padding: 20px; text-align: left; flex-grow: 1; }
        .service-body h3 { font-size: 16px; color: #2c3e50; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .service-body h3 i { color: #00a859; }
        .service-body p { font-size: 13px; color: #6c757d; line-height: 1.5; margin-bottom: 15px; }
        .service-footer-card { padding: 15px 20px; background: #fafbfc; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .service-price { font-size: 15px; font-weight: 700; color: #e74c3c; }
        .btn-book-this { background: #00a859; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.3s; }
        .btn-book-this:hover { background: #008d4a; }
        
        /* NÚT VÀ FORM SỬA GIÁ DÀNH CHO ADMIN */
        .admin-edit-price-btn { background: #f39c12; color: #fff; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; margin-left: 6px; }
        .admin-edit-price-btn:hover { background: #d35400; }
        .inline-edit-box { display: none; background: #fff8e1; border: 1px solid #ffe082; padding: 8px; border-radius: 6px; margin-top: 10px; }
        .inline-edit-box input { width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; margin-bottom: 6px; }
        .inline-edit-box button { background: #27ae60; color: #fff; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: 600; }
        .inline-edit-box .btn-cancel-edit { background: #7f8c8d; margin-left: 4px; }
        .techs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 25px; margin-bottom: 60px; }
        .tech-card { background: white; border-radius: 12px; padding: 30px 20px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; }
        .tech-avatar { width: 100px; height: 100px; border-radius: 50%; margin: 0 auto 15px; background-size: cover; background-position: center; border: 3px solid #00a859; }
        .tech-card h3 { font-size: 18px; color: #212529; margin-bottom: 4px; }
        .tech-role { font-size: 13px; color: #00a859; font-weight: 600; margin-bottom: 10px; }
        .tech-exp { font-size: 12px; color: #495057; background: #eef8f3; padding: 4px 12px; border-radius: 15px; display: inline-block; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media(max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 18px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ced4da; border-radius: 8px; font-size: 13px; outline: none; transition: border-color 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #00a859; box-shadow: 0 0 0 3px rgba(0, 168, 89, 0.12); }
        
        .btn-submit-booking { background: #00a859; color: white; border: none; width: 100%; padding: 14px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.3s; }
        .btn-submit-booking:hover { background: #008d4a; }
        
        footer { background: #0f2027; color: #a0a0a0; text-align: center; padding: 30px 20px; font-size: 13px; margin-top: 60px; }
    </style>
</head>
<body>
    <!-- THÔNG BÁO NHỎ TOAST -->
    <div class="toast-notification" id="toastNotification">
        <i class="fa-solid fa-circle-check" style="font-size: 18px;"></i>
        <span>Bạn đã sửa giá thành công!</span>
    </div>
    <!-- TOP BAR -->
    <div class="top-bar">
        <div><i class="fa-solid fa-clock"></i> Giờ làm việc: 08:00 - 20:00 (Cả T7 & CN)</div>
        <div><i class="fa-solid fa-phone"></i> Hotline Kỹ Thuật: <a href="tel:0123456789">0123.456.789</a></div>
    </div>
    
    <!-- HEADER / NAVBAR -->
    <header>
        <div class="navbar">
            <a href="index.php" class="logo"><i class="fa-solid fa-desktop"></i> PC 24/7</a>
            <ul class="nav-links">
                <li><a href="index.php">Trang Chủ</a></li>
                <li><a href="#services">Dịch Vụ & Bảng Giá</a></li>
                <li><a href="#technicians">Kỹ Thuật Viên</a></li>
                <li><a href="javascript:void(0);" onclick="openBookingModal()">Đặt Lịch Ngay</a></li>
            </ul>
            <div>
                <?php if ($is_logged_in): ?>
                    <div class="user-panel">
                        <span><i class="fa-solid fa-user"></i> <strong><?php echo htmlspecialchars($fullname); ?></strong></span>
                        <?php if ($user_role === 'admin'): ?>
                            <span class="role-badge badge-admin">Admin</span>
                            <a href="pages/admin.php" class="btn-portal"><i class="fa-solid fa-gauge-high"></i> Quản Trị</a>
                        <?php elseif ($user_role === 'kythuat'): ?>
                            <span class="role-badge badge-ktv">KTV</span>
                            <a href="pages/kythuat.php" class="btn-portal"><i class="fa-solid fa-wrench"></i> Việc Của Tôi</a>
                        <?php else: ?>
                            <span class="role-badge badge-cust">Khách Hàng</span>
                            <a href="pages/khachhang.php" class="btn-portal"><i class="fa-solid fa-clock-rotate-left"></i> Lịch Sử Đơn</a>
                        <?php endif; ?>
                        <a href="auth/logout.php" class="btn-logout" title="Đăng xuất"><i class="fa-solid fa-right-from-bracket"></i> Đăng Xuất</a>
                    </div>
                <?php else: ?>
                    <a href="auth/login.php" class="btn-login"><i class="fa-solid fa-right-to-bracket"></i> Đăng Nhập / Đăng Ký</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- HERO BANNER -->
    <section class="hero">
        <h1>TRUNG TÂM SỬA CHỮA & BẢO TRÌ MÁY TÍNH 24/7</h1>
        <p>Chuyên khắc phục nhanh mọi sự cố phần cứng, phần mềm PC & Laptop. Đặt lịch linh hoạt, thanh toán tiện lợi.</p>
        <div class="search-container">
            <div class="search-box-wrapper">
                <input type="text" id="searchInput" placeholder="Nhập tên dịch vụ bạn cần tìm (vd: vệ sinh, mainboard, ram, cứu dữ liệu)..." autocomplete="off">
                <button type="button"><i class="fa-solid fa-magnifying-glass"></i> Tìm</button>
            </div>
            <div class="search-suggestions" id="searchSuggestions"></div>
        </div>
    </section>
    
    <div class="container">
        <!-- Ô VUÔNG THÊM ĐƠN MỚI DÀNH RIÊNG CHO ADMIN -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div style="display: flex; justify-content: center; margin: 30px 0;">
                <a href="pages/admin.php?tab=new-order" style="
                    width: 280px; 
                    height: 180px; 
                    border: 2px dashed #00a859; 
                    border-radius: 12px; 
                    display: flex; 
                    flex-direction: column; 
                    align-items: center; 
                    justify-content: center; 
                    text-decoration: none; 
                    background: #f0fdf4; 
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
                " onmouseover="this.style.background='#dcfce7'; this.style.transform='translateY(-3px)';" onmouseout="this.style.background='#f0fdf4'; this.style.transform='translateY(0)';">
                    <div style="
                        width: 55px; 
                        height: 55px; 
                        background: #00a859; 
                        color: white; 
                        border-radius: 50%; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        font-size: 26px; 
                        margin-bottom: 12px;
                        box-shadow: 0 3px 8px rgba(0,168,89,0.3);
                    ">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <span style="font-size: 15px; font-weight: 700; color: #00a859; text-transform: uppercase;">Thêm đơn mới</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- DỊCH VỤ SỬA CHỮA & BẢNG GIÁ -->
        <div class="section-title" id="services">
            <h2>Dịch Vụ Sửa Chữa & Bảng Giá Niêm Yết</h2>
            <p>Cam kết chi phí minh bạch, không phát sinh chi phí ẩn</p>
        </div>
        <div class="services-grid" id="servicesGrid">
            <?php foreach ($services_list as $srv): ?>
                <div class="service-card" id="<?php echo htmlspecialchars($srv['service_key']); ?>">
                    <div class="service-img" style="background-image: url('<?php echo htmlspecialchars($srv['image_url']); ?>');"></div>
                    <div class="service-body">
                        <h3>
                            <i class="fa-solid fa-screwdriver-wrench"></i> <?php echo htmlspecialchars($srv['title']); ?>
                            <?php if ($user_role === 'admin'): ?>
                                <button type="button" class="admin-edit-price-btn" onclick="toggleEditPrice('edit-box-<?php echo $srv['id']; ?>')">
                                    <i class="fa-solid fa-pen-to-square"></i> Sửa Giá
                                </button>
                            <?php endif; ?>
                        </h3>
                        <p><?php echo htmlspecialchars($srv['description']); ?></p>
                        <!-- FORM SỬA GIÁ NHỎ GỌN DÀNH CHO ADMIN -->
                        <?php if ($user_role === 'admin'): ?>
                            <div class="inline-edit-box" id="edit-box-<?php echo $srv['id']; ?>">
                                <form method="POST" action="index.php#services">
                                    <input type="hidden" name="service_db_id" value="<?php echo $srv['id']; ?>">
                                    <label style="font-size:11px; font-weight:600; color:#b78103; margin-bottom:3px; display:block;">Nhập giá mới:</label>
                                    <input type="text" name="new_price" value="<?php echo htmlspecialchars($srv['price_text']); ?>" required>
                                    <div>
                                        <button type="submit" name="btn_update_price"><i class="fa-solid fa-check"></i> Lưu</button>
                                        <button type="button" class="btn-cancel-edit" onclick="toggleEditPrice('edit-box-<?php echo $srv['id']; ?>')">Hủy</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="service-footer-card">
                        <span class="service-price"><?php echo htmlspecialchars($srv['price_text']); ?></span>
                        <a href="javascript:void(0);" class="btn-book-this" onclick="selectAndOpenBooking('<?php echo htmlspecialchars($srv['title'], ENT_QUOTES); ?>')"><i class="fa-solid fa-calendar-plus"></i> Đặt Sửa Ngay</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- ĐỘI NGŨ KỸ THUẬT VIÊN -->
        <div class="section-title" id="technicians">
            <h2>Đội Ngũ Kỹ Thuật Viên Chuyên Ngành</h2>
            <p>Những chuyên gia tay nghề cao, tận tâm và giàu kinh nghiệm</p>
        </div>
        <div class="techs-grid">
            <div class="tech-card">
                <div class="tech-avatar" style="background-image: url('https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=80');"></div>
                <h3>Trần Văn Minh</h3>
                <div class="tech-role">Chuyên Viên Phần Cứng & Mainboard</div>
                <div class="tech-exp"><i class="fa-solid fa-award"></i> 8 năm kinh nghiệm</div>
            </div>
            <div class="tech-card">
                <div class="tech-avatar" style="background-image: url('https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80');"></div>
                <h3>Lê Hoàng Nam</h3>
                <div class="tech-role">Chuyên Viên Phần Mềm & Hệ Thống</div>
                <div class="tech-exp"><i class="fa-solid fa-award"></i> 6 năm kinh nghiệm</div>
            </div>
            <div class="tech-card">
                <div class="tech-avatar" style="background-image: url('https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&q=80');"></div>
                <h3>Phạm Đức Anh</h3>
                <div class="tech-role">Chuyên Gia Cứu Dữ Liệu & Mạng</div>
                <div class="tech-exp"><i class="fa-solid fa-award"></i> 10 năm kinh nghiệm</div>
            </div>
        </div>
    </div>
    
    <!-- BONG BÓNG ĐẶT LỊCH NỔI -->
    <a href="javascript:void(0);" onclick="openBookingModal()" class="floating-booking-btn" title="Đặt lịch sửa chữa">
        <i class="fa-solid fa-calendar-days"></i>
        <span class="tooltip-text">Đặt lịch sửa ngay!</span>
    </a>
    
    <!-- MODAL ĐẶT LỊCH VÀ XÁC THỰC THANH TOÁN -->
    <div class="booking-modal-overlay" id="bookingModalOverlay">
        <div class="booking-modal-content">
            <button type="button" class="btn-close-modal" onclick="closeBookingModal()"><i class="fa-solid fa-xmark"></i></button>
            
            <div class="section-title" style="margin-bottom: 20px;">
                <h2><i class="fa-solid fa-calendar-check" style="color: #00a859;"></i> Đặt Lịch & Thanh Toán Trực Tuyến</h2>
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="error-alert-box">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px;"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Họ Tên Khách Hàng *:</label>
                        <input type="text" name="customer_name" value="<?php echo htmlspecialchars($fullname); ?>" placeholder="Nhập họ tên..." required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label>Số Điện Thoại *:</label>
                        <input type="text" name="phone" placeholder="Nhập số điện thoại..." required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-group full">
                        <label>Địa Chỉ Nhận Máy / Sửa Chữa Tại Nhà *:</label>
                        <input type="text" name="address" placeholder="Ví dụ: 123 Nguyễn Văn Linh, Đà Nẵng..." required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label>Chọn Dịch Vụ Sửa Chữa *:</label>
                        <select name="service_name" id="selectedServiceDropdown" required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                            <option value="">-- Chọn gói dịch vụ --</option>
                            <?php foreach ($services_list as $srv): ?>
                                <option value="<?php echo htmlspecialchars($srv['title']); ?>"><?php echo htmlspecialchars($srv['title']); ?> (<?php echo htmlspecialchars($srv['price_text']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ngày Đặt Hẹn *:</label>
                        <input type="date" name="booking_date" id="bookingDateInput" min="<?php echo date('Y-m-d'); ?>" required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="form-group full">
                        <label>Hình Thức Thanh Toán *:</label>
                        <select name="payment_method" id="paymentMethodSelect" onchange="togglePaymentSection()" required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                            <option value="Tiền mặt">Thanh toán bằng Tiền mặt (Khi nhận máy / Hoàn tất dịch vụ)</option>
                            <option value="Chuyển khoản ngân hàng">Chuyển khoản ngân hàng (Bắt buộc quét mã QR thanh toán)</option>
                        </select>
                    </div>
                    
                    <div class="form-group full" id="qrPaymentSection">
                        <strong><i class="fa-solid fa-qrcode" style="color: #00a859;"></i> Quét mã QR chuyển khoản trước khi đặt lịch</strong>
                        <p style="font-size: 12px; color: #dc3545; margin-top: 4px;"></p>
                        <img src="assets/VCBTuan.jpg" alt="Mã QR thanh toán">
                        <div>
                            <button type="button" class="btn-verify-payment" id="btnVerifyPayment" onclick="verifyPaymentSuccess()">
                                <i class="fa-solid fa-check-circle"></i> Xác Nhận Đã Thanh Toán Thành Công
                            </button>
                        </div>
                        <div class="verified-badge-text" id="verifiedBadgeText">
                            <i class="fa-solid fa-circle-check"></i> Đã xác thực thanh toán chuyển khoản thành công!
                        </div>
                        <input type="hidden" name="payment_verified" id="paymentVerifiedInput" value="1">
                    </div>
                    <div class="form-group full">
                        <label>Ghi Chú Thêm:</label>
                        <textarea name="note" rows="2" placeholder="Mô tả chi tiết tình trạng lỗi của máy..." <?php echo !$is_logged_in ? 'disabled' : ''; ?>></textarea>
                    </div>
                </div>
                <?php if ($is_logged_in): ?>
                    <button type="submit" name="btn_quick_booking" class="btn-submit-booking"><i class="fa-solid fa-paper-plane"></i> Gửi Yêu Cầu Đặt Lịch</button>
                <?php else: ?>
                    <a href="auth/login.php" class="btn-submit-booking" style="display:block; text-align:center; text-decoration:none;"><i class="fa-solid fa-lock"></i> Vui Lòng Đăng Nhập Để Đặt Lịch</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <!-- MODAL THÔNG BÁO THÀNH CÔNG ĐẶT LỊCH -->
    <div class="success-alert-overlay" id="successAlertOverlay">
        <div class="success-alert-box">
            <div class="success-alert-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h3>Giao Dịch & Đặt Lịch Thành Công!</h3>
            <p>Hệ thống đã ghi nhận lịch hẹn và xác thực thanh toán chuyển khoản của bạn. Kỹ thuật viên sẽ sớm liên hệ hỗ trợ.</p>
            <a href="pages/khachhang.php" class="btn-success-action">
                <i class="fa-solid fa-clock-rotate-left"></i> Chuyển Đến Lịch Sử Đơn Hàng
            </a>
        </div>
    </div>
    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 PC 24/7. Hotline Hỗ Trợ: <strong>0123.456.789</strong></p>
    </footer>
    <!-- SCRIPT -->
    <script>
        // Kích hoạt hiển thị thông báo Toast nếu PHP trả về trạng thái update thành công
        <?php if ($show_toast): ?>
        window.addEventListener('DOMContentLoaded', (event) => {
            const toast = document.getElementById('toastNotification');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        });
        <?php endif; ?>
        const servicesData = [
            <?php foreach ($services_list as $srv): ?>
            { id: "<?php echo $srv['service_key']; ?>", title: "<?php echo addslashes($srv['title']); ?>", price: "<?php echo addslashes($srv['price_text']); ?>", keyword: "<?php echo addslashes($srv['keyword']); ?>" },
            <?php endforeach; ?>
        ];
        
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            searchSuggestions.innerHTML = '';
            
            if (query === '') {
                searchSuggestions.style.display = 'none';
                return;
            }
            const matchedServices = servicesData.filter(service => 
                service.title.toLowerCase().includes(query) || service.keyword.toLowerCase().includes(query)
            );
            if (matchedServices.length > 0) {
                searchSuggestions.style.display = 'block';
                matchedServices.forEach(service => {
                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.innerHTML = `<span><i class="fa-solid fa-wrench" style="color: #00a859; margin-right: 8px;"></i> ${service.title}</span> <strong style="color: #e74c3c;">${service.price}</strong>`;
                    
                    item.addEventListener('click', function() {
                        searchInput.value = service.title;
                        searchSuggestions.style.display = 'none';
                        
                        const targetCard = document.getElementById(service.id);
                        if (targetCard) {
                            targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            targetCard.style.transition = 'all 0.5s';
                            targetCard.style.boxShadow = '0 0 20px rgba(0, 168, 89, 0.6)';
                            targetCard.style.borderColor = '#00a859';
                            setTimeout(() => {
                                targetCard.style.boxShadow = '';
                                targetCard.style.borderColor = '#f0f0f0';
                            }, 2000);
                        }
                    });
                    searchSuggestions.appendChild(item);
                });
            } else {
                searchSuggestions.style.display = 'block';
                const noItem = document.createElement('div');
                noItem.className = 'suggestion-item';
                noItem.style.color = '#6c757d';
                noItem.innerHTML = 'Không tìm thấy dịch vụ phù hợp...';
                searchSuggestions.appendChild(noItem);
            }
        });
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                searchSuggestions.style.display = 'none';
            }
        });
        function toggleEditPrice(boxId) {
            const box = document.getElementById(boxId);
            if (box) {
                box.style.display = (box.style.display === 'block') ? 'none' : 'block';
            }
        }
        function openBookingModal() {
            const modal = document.getElementById('bookingModalOverlay');
            if (modal) modal.style.display = 'flex';
        }
        function closeBookingModal() {
            const modal = document.getElementById('bookingModalOverlay');
            if (modal) modal.style.display = 'none';
        }
        function selectAndOpenBooking(serviceName) {
            const dropdown = document.getElementById('selectedServiceDropdown');
            if (dropdown) {
                for (let i = 0; i < dropdown.options.length; i++) {
                    if (dropdown.options[i].value === serviceName) {
                        dropdown.selectedIndex = i;
                        break;
                    }
                }
            }
            openBookingModal();
        }
        function togglePaymentSection() {
            const paymentSelect = document.getElementById('paymentMethodSelect');
            const qrSection = document.getElementById('qrPaymentSection');
            const verifiedInput = document.getElementById('paymentVerifiedInput');
            const verifiedBadge = document.getElementById('verifiedBadgeText');
            const btnVerify = document.getElementById('btnVerifyPayment');
            
            if (paymentSelect.value === 'Chuyển khoản ngân hàng') {
                qrSection.style.display = 'block';
                verifiedInput.value = '0';
                btnVerify.style.display = 'inline-block';
                btnVerify.disabled = false;
                verifiedBadge.style.display = 'none';
            } else {
                qrSection.style.display = 'none';
                verifiedInput.value = '1';
                verifiedBadge.style.display = 'none';
            }
        }
        function verifyPaymentSuccess() {
            const verifiedInput = document.getElementById('paymentVerifiedInput');
            const verifiedBadge = document.getElementById('verifiedBadgeText');
            const btnVerify = document.getElementById('btnVerifyPayment');
            
            verifiedInput.value = '1';
            verifiedBadge.style.display = 'block';
            btnVerify.style.display = 'none';
            alert('Xác nhận thành công! Hệ thống đã ghi nhận thanh toán qua QR. Bạn có thể tiếp tục gửi đơn đặt lịch.');
        }
        window.addEventListener('click', function(e) {
            const modalOverlay = document.getElementById('bookingModalOverlay');
            if (e.target === modalOverlay) {
                closeBookingModal();
            }
        });
    </script>
</body>
</html>