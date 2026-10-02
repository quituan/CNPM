<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/connect.php';
// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
$admin_name = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Quản Trị Viên');
$success_message = '';

// Kiểm tra và tạo bảng bookings nếu chưa tồn tại
$conn->query("CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    service_name VARCHAR(255) NOT NULL,
    customer_name VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    gia VARCHAR(100) DEFAULT '0',
    status VARCHAR(50) DEFAULT 'Đang xử lý',
    booking_date DATE DEFAULT CURRENT_DATE,
    note TEXT DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT 'Chuyển khoản'
)");

// Kiểm tra và tạo bảng services (dịch vụ hiển thị ngoài trang chủ index.php) nếu chưa tồn tại
$conn->query("CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(255) NOT NULL,
    gia VARCHAR(100) DEFAULT '0',
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Xử lý Thêm Dịch Vụ Mới (Hiển thị đồng bộ cho hệ thống/index.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_new_service'])) {
    $service_name = trim($_POST['service_name'] ?? '');
    $gia = trim($_POST['gia'] ?? '0');
    $description = trim($_POST['description'] ?? '');

    if (!empty($service_name)) {
        // Thêm vào bảng dịch vụ chung để index.php có thể gọi ra hiển thị
        $stmt_serv = $conn->prepare("INSERT INTO services (service_name, gia, description) VALUES (?, ?, ?)");
        if ($stmt_serv) {
            $stmt_serv->bind_param("sss", $service_name, $gia, $description);
            $stmt_serv->execute();
            $stmt_serv->close();
        }

        // Đồng thời thêm vào danh sách quản lý chung nếu cần
        $stmt_add_ord = $conn->prepare("INSERT INTO bookings (service_name, customer_name, phone, gia, status, booking_date, note) VALUES (?, 'Hệ thống / Dịch vụ mới', '', ?, 'Sẵn sàng', CURDATE(), ?)");
        if ($stmt_add_ord) {
            $stmt_add_ord->bind_param("sss", $service_name, $gia, $description);
            $stmt_add_ord->execute();
            $stmt_add_ord->close();
        }

        $success_message = "Thêm dịch vụ mới thành công và đã cập nhật vào hệ thống!";
    }
}

// Xử lý tạo tài khoản KTV mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_ktv'])) {
    $new_user = trim($_POST['ktv_username'] ?? '');
    $new_pass = trim($_POST['ktv_password'] ?? '');
    $new_name = trim($_POST['ktv_fullname'] ?? '');
    if (!empty($new_user) && !empty($new_pass)) {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $new_user);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows == 0) {
            $stmt_check->close();
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $role = 'kythuat';
            $status = 'hoatdong';
            $stmt_ins = $conn->prepare("INSERT INTO users (username, password, fullname, role, trang_thai) VALUES (?, ?, ?, ?, ?)");
            $stmt_ins->bind_param("sssss", $new_user, $hashed_pass, $new_name, $role, $status);
            $stmt_ins->execute();
            $stmt_ins->close();
            $success_message = "Tạo tài khoản kỹ thuật viên thành công!";
        } else {
            $stmt_check->close();
        }
    }
}

// Xử lý khóa/mở khóa tài khoản KTV
if (isset($_GET['toggle_id']) && isset($_GET['current_status'])) {
    $target_id = intval($_GET['toggle_id']);
    $current_st = trim($_GET['current_status']);
    $new_st = ($current_st === 'khoa') ? 'hoatdong' : 'khoa';
    $stmt_tg = $conn->prepare("UPDATE users SET trang_thai = ? WHERE id = ? AND role = 'kythuat'");
    $stmt_tg->bind_param("si", $new_st, $target_id);
    $stmt_tg->execute();
    $stmt_tg->close();
    header("Location: admin.php");
    exit();
}

// Xử lý thêm linh kiện kho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_component'])) {
    $comp_name = trim($_POST['comp_name'] ?? '');
    $comp_qty = intval($_POST['comp_qty'] ?? 0);
    $comp_price_raw = trim($_POST['comp_price'] ?? '0');
    $comp_image = trim($_POST['comp_image'] ?? '../assets/VCBTuan.jpg');
    if (!empty($comp_name)) {
        $stmt_ins = $conn->prepare("INSERT INTO linh_kien (ten_linh_kien, so_luong, gia_nhap, hinh_anh) VALUES (?, ?, ?, ?)");
        if ($stmt_ins) {
            $stmt_ins->bind_param("siss", $comp_name, $comp_qty, $comp_price_raw, $comp_image);
            $stmt_ins->execute();
            $stmt_ins->close();
            $success_message = "Đã thêm linh kiện mới vào kho thành công!";
        }
    }
}

// Xử lý Cập nhật trạng thái đơn hàng cũ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_order_status'])) {
    $update_order_id = intval($_POST['order_id'] ?? 0);
    $new_order_status = trim($_POST['order_status'] ?? '');
    if ($update_order_id > 0 && !empty($new_order_status)) {
        $stmt_up_ord = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        if ($stmt_up_ord) {
            $stmt_up_ord->bind_param("si", $new_order_status, $update_order_id);
            $stmt_up_ord->execute();
            $stmt_up_ord->close();
            $success_message = "Cập nhật trạng thái đơn hàng #{$update_order_id} thành công!";
        }
    }
}

// Lấy dữ liệu đơn hàng và phân nhóm theo tháng
$query_bookings = "SELECT b.*, u.fullname, u.role FROM bookings b LEFT JOIN users u ON b.user_id = u.id ORDER BY b.id DESC";
$res_bookings = $conn->query($query_bookings);
$total_orders = 0;
$total_revenue = 0;
$transfer_revenue = 0;
$cash_revenue = 0;
$completed_count = 0;
$cancelled_count = 0;
$monthly_stats = [];
$all_orders = [];

if ($res_bookings && $res_bookings->num_rows > 0) {
    while ($row = $res_bookings->fetch_assoc()) {
        $all_orders[] = $row;
        $total_orders++;
        $st = trim($row['status'] ?? '');
        $price = floatval($row['gia'] ?? 0);
        $note = $row['note'] ?? '';
        $booking_date = $row['booking_date'] ?? date('Y-m-d');
        $month_key = date('Y-m', strtotime($booking_date));
        if ($st === 'Đã hoàn thành') {
            $completed_count++;
        } elseif ($st === 'Đã hủy') {
            $cancelled_count++;
        }
        if (!isset($monthly_stats[$month_key])) {
            $monthly_stats[$month_key] = [
                'orders' => 0,
                'completed' => 0,
                'revenue' => 0,
                'transfer' => 0,
                'cash' => 0,
                'list_orders' => []
            ];
        }
        $monthly_stats[$month_key]['list_orders'][] = $row;
        if ($st !== 'Đã hủy') {
            $total_revenue += $price;
            $monthly_stats[$month_key]['revenue'] += $price;
            $monthly_stats[$month_key]['orders']++;
            if ($st === 'Đã hoàn thành') {
                $monthly_stats[$month_key]['completed']++;
            }
            $is_cash = (stripos($note, 'Tiền mặt') !== false || (isset($row['payment_method']) && trim($row['payment_method']) === 'Tiền mặt'));
            if ($is_cash) {
                $cash_revenue += $price;
                $monthly_stats[$month_key]['cash'] += $price;
            } else {
                $transfer_revenue += $price;
                $monthly_stats[$month_key]['transfer'] += $price;
            }
        }
    }
}
krsort($monthly_stats);

// Lấy danh sách linh kiện kho
$components = [];
$low_stock_components = [];
$check_lk = $conn->query("SHOW TABLES LIKE 'linh_kien'");
if ($check_lk && $check_lk->num_rows > 0) {
    $res_lk = $conn->query("SELECT * FROM linh_kien ORDER BY id DESC");
    if ($res_lk) {
        while ($lk = $res_lk->fetch_assoc()) {
            $components[] = $lk;
            if (intval($lk['so_luong']) < 10) {
                $low_stock_components[] = $lk;
            }
        }
    }
}

// Lấy danh sách Kỹ thuật viên
$technicians = [];
$res_ktv = $conn->query("SELECT * FROM users WHERE role = 'kythuat'");
if ($res_ktv) {
    while ($ktv = $res_ktv->fetch_assoc()) {
        $technicians[] = $ktv;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Quản Trị Admin - PC 24/7</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #f4f7f6; color: #1e293b; }
        .navbar { background: #0f2027; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar-brand { font-size: 18px; font-weight: 700; color: white; display: flex; align-items: center; gap: 10px; }
        .navbar-actions { display: flex; gap: 15px; align-items: center; }
        .badge-admin { background: #dc2626; color: white; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .btn-home { background: rgba(255,255,255,0.15); color: white; text-decoration: none; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px; }
        .btn-home:hover { background: rgba(255,255,255,0.3); }
        .btn-logout { background: #ef4444; color: white; text-decoration: none; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px; }
        .btn-logout:hover { background: #b91c1c; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .alert-success-box { background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .dashboard-header { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .header-top-box { background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); color: white; border-radius: 10px; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header-top-box h2 { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header-top-box p { font-size: 13px; opacity: 0.9; margin-top: 4px; }
        
        .stats-boxes { display: flex; gap: 12px; flex-wrap: wrap; }
        .stat-box { background: rgba(255,255,255,0.15); padding: 10px 14px; border-radius: 8px; text-align: center; min-width: 110px; }
        .stat-box span { display: block; font-size: 16px; font-weight: 700; }
        .stat-box small { font-size: 11px; opacity: 0.9; text-transform: uppercase; }
        
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 25px; background: white; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03); flex-wrap: wrap; }
        .tab-btn { padding: 9px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; background: #f1f5f9; color: #475569; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .tab-btn.active, .tab-btn:hover { background: #0f2027; color: white; }
        .section-content { display: none; }
        .section-content.active { display: block; }
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        @media(max-width: 992px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width: 576px) { .metrics-grid { grid-template-columns: 1fr; } }
        
        .metric-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .metric-card .metric-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .metric-card .metric-value { font-size: 20px; font-weight: 700; color: #0f172a; }
        .metric-card .metric-sub { font-size: 11px; color: #00a859; margin-top: 5px; font-weight: 600; }
        
        .card-box { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .card-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 700; color: #334155; }
        tr:hover { background: #f8fafc; }
        
        .month-row { cursor: pointer; transition: background 0.2s; }
        .month-row:hover { background: #f1f5f9 !important; }
        .month-row i.fa-chevron-right { transition: transform 0.3s ease; color: #64748b; font-size: 11px; margin-right: 6px; }
        .month-row.expanded i.fa-chevron-right { transform: rotate(90deg); }
        .sub-orders-row { display: none; background: #f8fafc; }
        .sub-orders-row.active { display: table-row; }
        .sub-table-container { padding: 15px 20px; }
        .sub-table { width: 100%; background: white; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; }
        .sub-table th { background: #edf2f7; font-size: 12px; }
        .sub-table td { font-size: 12px; }
        
        .badge-status { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-success { background: #d1fae5; color: #047857; }
        
        .form-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1.5fr auto; gap: 12px; align-items: end; }
        .form-grid-ktv { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end; margin-bottom: 20px; }
        @media(max-width: 992px) { .form-grid, .form-grid-ktv { grid-template-columns: 1fr; } }
        
        .form-group label { font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; outline: none; background: #fff; }
        .btn-submit { background: #00a859; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; transition: background 0.3s; height: 39px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-submit:hover { background: #008f4c; }
        .component-thumb { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; border: 1px solid #cbd5e1; }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="navbar-brand">
            <i class="fa-solid fa-user-shield"></i> HỆ THỐNG QUẢN TRỊ ADMIN - PC 24/7
        </div>
        <div class="navbar-actions">
            <a href="../index.php" class="btn-home"><i class="fa-solid fa-house"></i> Trang Chủ</a>
            <span class="badge-admin"><i class="fa-solid fa-crown"></i> <?php echo htmlspecialchars($admin_name); ?></span>
            <a href="../auth/logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng Xuất</a>
        </div>
    </header>
    <div class="container">
        <?php if (!empty($success_message)): ?>
            <div class="alert-success-box">
                <i class="fa-solid fa-circle-check" style="font-size: 18px;"></i>
                <span><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-header">
            <div class="header-top-box">
                <div>
                    <h2><i class="fa-solid fa-chart-pie"></i> Bảng Điều Hành Quản Trị Viên</h2>
                    <p>Quản lý doanh thu tài chính, thêm mới dịch vụ hệ thống, kho linh kiện và phân quyền nhân sự kỹ thuật.</p>
                </div>
                <div class="stats-boxes">
                    <div class="stat-box"><span><?php echo $total_orders; ?></span><small>Tổng Đơn</small></div>
                    <div class="stat-box"><span><?php echo count($low_stock_components); ?></span><small>Sắp Hết</small></div>
                    <div class="stat-box"><span><?php echo count($technicians); ?></span><small>KTV Quản Lý</small></div>
                </div>
            </div>
        </div>

        <!-- Các Tab Chức Năng Admin -->
        <div class="filter-tabs">
            <button class="tab-btn active" onclick="switchTab('tab-revenue', event)"><i class="fa-solid fa-sack-dollar"></i> Báo Cáo Doanh Thu</button>
            <button class="tab-btn" onclick="switchTab('tab-new-order', event)"><i class="fa-solid fa-square-plus" style="color: #00a859;"></i> Thêm Dịch Vụ Mới</button>
            <button class="tab-btn" onclick="switchTab('tab-update-order', event)"><i class="fa-solid fa-pen-to-square"></i> Sửa Trạng Thái Đơn</button>
            <button class="tab-btn" onclick="switchTab('tab-components', event)"><i class="fa-solid fa-microchip"></i> Thêm Linh Kiện</button>
            <button class="tab-btn" onclick="switchTab('tab-lowstock', event)"><i class="fa-solid fa-triangle-exclamation" style="color: #d97706;"></i> Sắp Hết (<?php echo count($low_stock_components); ?>)</button>
            <button class="tab-btn" onclick="switchTab('tab-technicians', event)"><i class="fa-solid fa-users-gear"></i> Quản Lý & Lương KTV</button>
        </div>

        <!-- TAB 1: BÁO CÁO DOANH THU & XEM CHI TIẾT ĐƠN HÀNG -->
        <div id="tab-revenue" class="section-content active">
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-label"><i class="fa-solid fa-file-invoice" style="color: #2563eb;"></i> Tổng Số Đơn</div>
                    <div class="metric-value"><?php echo $total_orders; ?></div>
                    <div class="metric-sub">Hoàn thành: <?php echo $completed_count; ?> đơn</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label"><i class="fa-solid fa-wallet" style="color: #16a34a;"></i> Tổng Doanh Thu</div>
                    <div class="metric-value" style="color: #16a34a;"><?php echo number_format($total_revenue, 0, ',', '.'); ?>đ</div>
                    <div class="metric-sub"><i class="fa-solid fa-arrow-trend-up"></i> Toàn hệ thống</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label"><i class="fa-solid fa-qrcode" style="color: #4f46e5;"></i> Chuyển Khoản</div>
                    <div class="metric-value" style="color: #4f46e5;"><?php echo number_format($transfer_revenue, 0, ',', '.'); ?>đ</div>
                    <div class="metric-sub">Thanh toán trực tuyến</div>
                </div>
                <div class="metric-card">
                    <div class="metric-label"><i class="fa-solid fa-money-bill-wave" style="color: #d97706;"></i> Tiền Mặt</div>
                    <div class="metric-value" style="color: #d97706;"><?php echo number_format($cash_revenue, 0, ',', '.'); ?>đ</div>
                    <div class="metric-sub">Thu tại cửa hàng</div>
                </div>
            </div>
            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-chart-column"></i> Báo Cáo Tài Chính & Lịch Sử Đơn Hàng Theo Tháng (Click dòng để xem chi tiết)</div>
                <table>
                    <thead>
                        <tr>
                            <th>Tháng (Năm-Tháng)</th>
                            <th>Tổng Đơn Đạt</th>
                            <th>Đơn Hoàn Thành</th>
                            <th>Doanh Thu Chuyển Khoản</th>
                            <th>Doanh Thu Tiền Mặt</th>
                            <th>Tổng Doanh Thu Tháng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($monthly_stats)): ?>
                            <?php $index = 0; foreach ($monthly_stats as $m => $st): $index++; ?>
                                <tr class="month-row" onclick="toggleSubOrders('sub-orders-<?php echo $index; ?>', this)">
                                    <td><i class="fa-solid fa-chevron-right"></i> <b>Tháng <?php echo $m; ?></b></td>
                                    <td><?php echo $st['orders']; ?> đơn</td>
                                    <td><span class="badge-status badge-success"><?php echo $st['completed']; ?> đơn</span></td>
                                    <td><?php echo number_format($st['transfer'], 0, ',', '.'); ?> VNĐ</td>
                                    <td><?php echo number_format($st['cash'], 0, ',', '.'); ?> VNĐ</td>
                                    <td><b style="color: #16a34a; font-size: 14px;"><?php echo number_format($st['revenue'], 0, ',', '.'); ?> VNĐ</b></td>
                                </tr>
                                <tr id="sub-orders-<?php echo $index; ?>" class="sub-orders-row">
                                    <td colspan="6" style="padding: 0;">
                                        <div class="sub-table-container">
                                            <p style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 10px;">
                                                <i class="fa-solid fa-list-check" style="color: #00a859;"></i> Chi tiết lịch sử đơn hàng trong Tháng <?php echo $m; ?>:
                                            </p>
                                            <table class="sub-table">
                                                <thead>
                                                    <tr>
                                                        <th>ID Đơn</th>
                                                        <th>Khách Hàng</th>
                                                        <th>Dịch Vụ</th>
                                                        <th>Ngày Đặt</th>
                                                        <th>Trạng Thái</th>
                                                        <th>Hình Thức</th>
                                                        <th>Tổng Tiền</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($st['list_orders'])): ?>
                                                        <?php foreach ($st['list_orders'] as $ord): 
                                                            $sub_st = trim($ord['status'] ?? '');
                                                            $sub_badge = 'badge-warning';
                                                            if ($sub_st === 'Đã hoàn thành') $sub_badge = 'badge-success';
                                                            elseif ($sub_st === 'Đã hủy') $sub_badge = 'badge-danger';
                                                            
                                                            $sub_note = $ord['note'] ?? '';
                                                            $sub_pay = (stripos($sub_note, 'Tiền mặt') !== false || (isset($ord['payment_method']) && trim($ord['payment_method']) === 'Tiền mặt')) ? 'Tiền mặt' : 'Chuyển khoản';
                                                        ?>
                                                            <tr>
                                                                <td><b>#<?php echo $ord['id']; ?></b></td>
                                                                <td><?php echo htmlspecialchars($ord['fullname'] ?? $ord['customer_name'] ?? 'Khách lẻ'); ?></td>
                                                                <td><?php echo htmlspecialchars($ord['service_name']); ?></td>
                                                                <td><?php echo date('d/m/Y', strtotime($ord['booking_date'])); ?></td>
                                                                <td><span class="badge-status <?php echo $sub_badge; ?>"><?php echo htmlspecialchars($sub_st); ?></span></td>
                                                                <td><?php echo $sub_pay; ?></td>
                                                                <td><b><?php echo number_format(floatval($ord['gia'] ?? 0), 0, ',', '.'); ?> VNĐ</b></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr><td colspan="7" style="text-align: center; color: #64748b;">Không có đơn hàng nào.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b;">Chưa có dữ liệu báo cáo tài chính.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB: THÊM DỊCH VỤ MỚI -->
        <div id="tab-new-order" class="section-content">
            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-square-plus" style="color: #00a859;"></i> Thêm Dịch Vụ Mới Cung Cấp (Hiển thị đồng bộ lên trang chủ / index.php)</div>
                <form method="POST" action="">
                    <input type="hidden" name="action_add_new_service" value="1">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label>Tên Dịch Vụ Sửa Chữa / Cài Đặt</label>
                            <input type="text" name="service_name" placeholder="Ví dụ: Vệ sinh laptop chuyên nghiệp & Bôi keo tản nhiệt..." required>
                        </div>
                        <div class="form-group">
                            <label>Giá Dịch Vụ (VNĐ)</label>
                            <input type="text" name="gia" placeholder="Ví dụ: 150000 hoặc 150k" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Mô Tả Chi Tiết Dịch Vụ</label>
                        <textarea name="description" rows="3" placeholder="Nhập mô tả chi tiết quy trình, bảo hành hoặc thông tin dịch vụ để hiển thị lên hệ thống..."></textarea>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Lưu & Đăng Dịch Vụ Mới</button>
                </form>
            </div>
        </div>

        <!-- TAB CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG CŨ -->
        <div id="tab-update-order" class="section-content">
            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-pen-to-square"></i> Cập Nhật Trạng Thái Đơn Hàng Nhanh</div>
                <form method="POST" action="" style="max-width: 600px; margin-bottom: 25px;">
                    <input type="hidden" name="action_update_order_status" value="1">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Chọn Mã Đơn Hàng</label>
                        <select name="order_id" required>
                            <option value="">-- Chọn đơn hàng cần cập nhật --</option>
                            <?php if (!empty($all_orders)): ?>
                                <?php foreach ($all_orders as $ord): ?>
                                    <option value="<?php echo $ord['id']; ?>">Đơn #<?php echo $ord['id']; ?> - <?php echo htmlspecialchars($ord['service_name']); ?> (KH: <?php echo htmlspecialchars($ord['fullname'] ?? $ord['customer_name'] ?? 'Khách lẻ'); ?> - Trạng thái: <?php echo $ord['status']; ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Trạng Thái Mới</label>
                        <select name="order_status" required>
                            <option value="Đang xử lý">Đang xử lý</option>
                            <option value="Đang sửa chữa">Đang sửa chữa</option>
                            <option value="Đã hoàn thành">Đã hoàn thành</option>
                            <option value="Đã hủy">Đã hủy</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Lưu Thay Đổi Trạng Thái</button>
                </form>
            </div>
        </div>

        <!-- TAB 2: CẬP NHẬT LINH KIỆN -->
        <div id="tab-components" class="section-content">
            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-plus-circle"></i> Thêm / Cập Nhật Kho Linh Kiện & Ảnh Minh Họa</div>
                <form method="POST" action="" class="form-grid">
                    <input type="hidden" name="action_component" value="1">
                    <div class="form-group">
                        <label>Tên Linh Kiện</label>
                        <input type="text" name="comp_name" placeholder="Ví dụ: RAM 8GB DDR4..." required>
                    </div>
                    <div class="form-group">
                        <label>Số Lượng</label>
                        <input type="number" name="comp_qty" placeholder="Ví dụ: 10" required min="0">
                    </div>
                    <div class="form-group">
                        <label>Đơn Giá</label>
                        <input type="text" name="comp_price" placeholder="Ví dụ: 650k" required>
                    </div>
                    <div class="form-group">
                        <label>Đường Dẫn Ảnh</label>
                        <input type="text" name="comp_image" placeholder="../assets/ram.jpg">
                    </div>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Lưu Kho</button>
                </form>
            </div>
            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-boxes-stacked"></i> Danh Sách Kho Linh Kiện Hiện Tại</div>
                <table>
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>ID</th>
                            <th>Tên Linh Kiện</th>
                            <th>Số Lượng Tồn</th>
                            <th>Đơn Giá</th>
                            <th>Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($components)): ?>
                            <?php foreach ($components as $lk): 
                                $qty = intval($lk['so_luong']);
                                $st_badge = 'badge-success';
                                $st_text = 'Đủ hàng';
                                if ($qty <= 0) {
                                    $st_badge = 'badge-danger';
                                    $st_text = 'Hết hàng';
                                } elseif ($qty < 10) {
                                    $st_badge = 'badge-warning';
                                    $st_text = 'Sắp hết';
                                }
                                $img_path = !empty($lk['hinh_anh']) ? $lk['hinh_anh'] : '../assets/VCBTuan.jpg';
                                $raw_price = $lk['gia_nhap'];
                                $display_price = is_numeric($raw_price) ? number_format(floatval($raw_price), 0, ',', '.') . ' VNĐ' : htmlspecialchars($raw_price);
                            ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($img_path); ?>" alt="Linh kiện" class="component-thumb" onerror="this.src='../assets/VCBTuan.jpg'"></td>
                                    <td>#<?php echo $lk['id']; ?></td>
                                    <td><b><?php echo htmlspecialchars($lk['ten_linh_kien']); ?></b></td>
                                    <td><?php echo $qty; ?> cái</td>
                                    <td><?php echo $display_price; ?></td>
                                    <td><span class="badge-status <?php echo $st_badge; ?>"><?php echo $st_text; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #64748b;">Chưa có linh kiện nào trong kho.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: LINH KIỆN SẮP HẾT -->
        <div id="tab-lowstock" class="section-content">
            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-triangle-exclamation" style="color: #d97706;"></i> Danh Sách Linh Kiện Sắp Hết (Dưới 10 cái)</div>
                <table>
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>ID</th>
                            <th>Tên Linh Kiện</th>
                            <th>Số Lượng Còn Lại</th>
                            <th>Đơn Giá</th>
                            <th>Hành Động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($low_stock_components)): ?>
                            <?php foreach ($low_stock_components as $ls): 
                                $img_path = !empty($ls['hinh_anh']) ? $ls['hinh_anh'] : '../assets/VCBTuan.jpg';
                                $raw_price = $ls['gia_nhap'];
                                $display_price = is_numeric($raw_price) ? number_format(floatval($raw_price), 0, ',', '.') . ' VNĐ' : htmlspecialchars($raw_price);
                            ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($img_path); ?>" alt="Linh kiện" class="component-thumb" onerror="this.src='../assets/VCBTuan.jpg'"></td>
                                    <td>#<?php echo $ls['id']; ?></td>
                                    <td><b><?php echo htmlspecialchars($ls['ten_linh_kien']); ?></b></td>
                                    <td><span class="badge-status badge-warning"><?php echo $ls['so_luong']; ?> cái</span></td>
                                    <td><?php echo $display_price; ?></td>
                                    <td>
                                        <button class="btn-submit" style="padding: 5px 12px; font-size: 11px;" onclick="alert('Vui lòng nhập thêm linh kiện!')">
                                            <i class="fa-solid fa-cart-shopping"></i> Nhập Thêm
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #16a34a; font-weight: 600;">Tuyệt vời! Hiện tại không có linh kiện nào dưới 10 cái.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: QUẢN LÝ KTV -->
        <div id="tab-technicians" class="section-content">
            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-user-plus"></i> Tạo Tài Khoản Kỹ Thuật Viên Mới</div>
                <form method="POST" action="" class="form-grid-ktv">
                    <input type="hidden" name="action_create_ktv" value="1">
                    <div class="form-group">
                        <label>Họ và Tên KTV</label>
                        <input type="text" name="ktv_fullname" placeholder="Ví dụ: Nguyễn Văn A" required>
                    </div>
                    <div class="form-group">
                        <label>Tên Đăng Nhập</label>
                        <input type="text" name="ktv_username" placeholder="Ví dụ: ktv_nguyena" required>
                    </div>
                    <div class="form-group">
                        <label>Mật Khẩu</label>
                        <input type="password" name="ktv_password" placeholder="Nhập mật khẩu..." required>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-user-check"></i> Tạo Tài Khoản</button>
                </form>
            </div>
            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-users-gear"></i> Danh Sách Kỹ Thuật Viên & Lương Tháng</div>
                <table>
                    <thead>
                        <tr>
                            <th>ID KTV</th>
                            <th>Họ và Tên</th>
                            <th>Tên Đăng Nhập</th>
                            <th>Tháng Thống Kê</th>
                            <th>Đơn Hoàn Thành</th>
                            <th>Tổng Lương Tạm Tính (VNĐ)</th>
                            <th>Trạng Thái / Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($technicians)): ?>
                            <?php foreach ($technicians as $ktv): 
                                $ktv_id = $ktv['id'];
                                $ktv_status = $ktv['trang_thai'] ?? 'hoatdong';
                            ?>
                                <tr>
                                    <td>#<?php echo $ktv_id; ?></td>
                                    <td><b><?php echo htmlspecialchars($ktv['fullname'] ?? $ktv['username']); ?></b></td>
                                    <td><?php echo htmlspecialchars($ktv['username']); ?></td>
                                    <td><?php echo date('m/Y'); ?></td>
                                    <td><span class="badge-status badge-success">0 đơn</span></td>
                                    <td><b style="color: #16a34a;">5.000.000 VNĐ</b></td>
                                    <td>
                                        <?php if ($ktv_status === 'khoa'): ?>
                                            <span class="badge-status badge-danger" style="margin-bottom: 5px; display: block;">Đã khóa</span>
                                            <a href="admin.php?toggle_id=<?php echo $ktv_id; ?>&current_status=khoa" class="btn-submit" style="background: #10b981; padding: 4px 10px; font-size: 11px;"><i class="fa-solid fa-lock-open"></i> Kích hoạt lại</a>
                                        <?php else: ?>
                                            <span class="badge-status badge-success" style="margin-bottom: 5px; display: block;">Hoạt động</span>
                                            <a href="admin.php?toggle_id=<?php echo $ktv_id; ?>&current_status=hoatdong" class="btn-submit" style="background: #ef4444; padding: 4px 10px; font-size: 11px;" onclick="return confirm('Bạn có chắc chắn muốn khóa tài khoản này không?');"><i class="fa-solid fa-ban"></i> Khóa</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; color: #64748b;">Chưa có kỹ thuật viên nào trong hệ thống.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function switchTab(tabId, event) {
            const contents = document.querySelectorAll('.section-content');
            contents.forEach(c => c.classList.remove('active'));
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
        }
        function toggleSubOrders(rowId, headerElem) {
            const subRow = document.getElementById(rowId);
            if (subRow.classList.contains('active')) {
                subRow.classList.remove('active');
                headerElem.classList.remove('expanded');
            } else {
                subRow.classList.add('active');
                headerElem.classList.add('expanded');
            }
        }
    </script>
</body>
</html>