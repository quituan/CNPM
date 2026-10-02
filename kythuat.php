<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/connect.php';

// Kiểm tra quyền Kỹ thuật viên (hoặc Admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'kythuat' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../auth/login.php");
    exit();
}

$ktv_name = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Kỹ Thuật Viên');
$base_asset_url = "../assets/VCBTuan.jpg"; 

$auto_open_qr_id = 0;
$auto_open_qr_price = 0;

// Xử lý cập nhật nhanh giá tiền và phương thức thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_update_price_id'])) {
    $booking_id = intval($_POST['quick_update_price_id']);
    $new_total_cost = floatval($_POST['quick_total_cost'] ?? 0);
    $payment_method = trim($_POST['quick_payment_method'] ?? 'Chuyển khoản');

    $check_st = $conn->prepare("SELECT status, note FROM bookings WHERE id = ?");
    $check_st->bind_param("i", $booking_id);
    $check_st->execute();
    $res_st = $check_st->get_result();
    $row_st = $res_st->fetch_assoc();
    $check_st->close();

    if ($row_st) {
        $st_val = trim($row_st['status']);
        if ($st_val !== 'Đã hủy' && $st_val !== 'Đã hoàn thành') {
            $time_stamp = date('d/m/Y H:i');
            $old_note = $row_st['note'] ?? '';
            $updated_note = $old_note . " | [Thanh toán: $payment_method - $time_stamp]";

            $stmt_p = $conn->prepare("UPDATE bookings SET gia = ?, note = ? WHERE id = ?");
            if ($stmt_p) {
                $stmt_p->bind_param("dsi", $new_total_cost, $updated_note, $booking_id);
                if ($stmt_p->execute()) {
                    $auto_open_qr_id = $booking_id;
                    $auto_open_qr_price = $new_total_cost;
                }
                $stmt_p->close();
            }
        }
    }
}

// Xử lý cập nhật đầy đủ từ form chi tiết bên dưới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_id'])) {
    $booking_id = intval($_POST['update_booking_id']);
    
    $stmt_old = $conn->prepare("SELECT status, note FROM bookings WHERE id = ?");
    $stmt_old->bind_param("i", $booking_id); 
    $stmt_old->execute();
    $res_old = $stmt_old->get_result();
    
    if ($row_old = $res_old->fetch_assoc()) {
        $old_status = trim($row_old['status'] ?? '');
        
        if ($old_status !== 'Đã hủy' && $old_status !== 'Đã hoàn thành') {
            $new_status = trim($_POST['new_status']);
            $feedback = trim($_POST['feedback_note']);
            $total_cost = floatval($_POST['total_cost'] ?? 0);
            $payment_method = trim($_POST['payment_method'] ?? 'Chuyển khoản');
            $old_note = $row_old['note'] ?? '';
            $updated_note = $old_note;
            $time_stamp = date('d/m/Y H:i');
            
            if ($old_status !== $new_status) {
                $updated_note .= " | [Chuyển trạng thái: $new_status vào $time_stamp]";
            }
            
            $updated_note .= " | [Hình thức: $payment_method]";

            if (!empty($feedback)) {
                $prefix = ($new_status === 'Đã hủy') ? " | [LÝ DO HỦY ĐƠN]: " : " | KTV Phản Hồi: ";
                $updated_note .= $prefix . $feedback;
            }
            
            $stmt_up = $conn->prepare("UPDATE bookings SET status = ?, note = ?, gia = ? WHERE id = ?");
            if ($stmt_up) {
                $stmt_up->bind_param("ssdi", $new_status, $updated_note, $total_cost, $booking_id);
                $stmt_up->execute();
                $stmt_up->close();
            }
        }
    }
    $stmt_old->close();
}

// Lấy danh sách toàn bộ đơn sửa chữa
$query = "SELECT b.*, u.fullname FROM bookings b LEFT JOIN users u ON b.user_id = u.id ORDER BY b.id ASC";
$result = $conn->query($query);

$total_orders = 0;
$processing_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;
$grand_total_revenue = 0;
$transfer_revenue = 0;
$cash_revenue = 0;
$transfer_count = 0;
$cash_count = 0;
$all_bookings = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $st = trim($row['status'] ?? '');
        $p_val = floatval($row['gia'] ?? 0);
        
        $note_content = $row['note'] ?? '';
        $p_method = 'Chuyển khoản';
        if (stripos($note_content, 'Tiền mặt') !== false || (isset($row['payment_method']) && trim($row['payment_method']) === 'Tiền mặt')) {
            $p_method = 'Tiền mặt';
        }
        $row['parsed_payment_method'] = $p_method;

        $all_bookings[] = $row;
        $total_orders++;

        if ($st !== 'Đã hủy') {
            $grand_total_revenue += $p_val;
            if ($p_method === 'Tiền mặt') {
                $cash_revenue += $p_val;
                $cash_count++;
            } else {
                $transfer_revenue += $p_val;
                $transfer_count++;
            }
        }

        if ($st === 'Đang sửa chữa' || $st === 'Đang nhận máy') {
            $processing_orders++;
        } elseif ($st === 'Đã hoàn thành') {
            $completed_orders++;
        } elseif ($st === 'Đã hủy') {
            $cancelled_orders++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Điều Hành Kỹ Thuật Viên - PC 24/7</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #f4f7f6; color: #1e293b; }
        .navbar { background: #0f2027; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar-brand { font-size: 18px; font-weight: 700; color: white; display: flex; align-items: center; gap: 10px; }
        .navbar-actions { display: flex; gap: 15px; align-items: center; }
        .badge-ktv { background: #00a859; color: white; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .btn-logout { background: #ef4444; color: white; text-decoration: none; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px; }
        .btn-logout:hover { background: #dc2626; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .dashboard-header { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .header-top-box { background: #00a859; color: white; border-radius: 10px; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .header-top-box h2 { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header-top-box p { font-size: 13px; opacity: 0.9; margin-top: 4px; }
        
        .header-right-actions { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .stats-boxes { display: flex; gap: 10px; flex-wrap: wrap; }
        .stat-box { background: rgba(255,255,255,0.15); padding: 10px 12px; border-radius: 8px; text-align: center; min-width: 70px; }
        .stat-box.highlight { background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.3); min-width: 115px; }
        .stat-box span { display: block; font-size: 16px; font-weight: 700; }
        .stat-box small { font-size: 11px; opacity: 0.9; text-transform: uppercase; }
        
        .btn-reset { background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.3s; text-decoration: none; }
        .btn-reset:hover { background: rgba(255,255,255,0.35); }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 25px; background: white; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03); flex-wrap: wrap; }
        .tab-btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; background: #f1f5f9; color: #475569; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .tab-btn.active, .tab-btn:hover { background: #00a859; color: white; }
        
        .order-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px; transition: all 0.3s; }
        .order-card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
        .order-title { font-size: 16px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .order-meta { display: flex; gap: 20px; font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 500; flex-wrap: wrap; }
        
        .header-right-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .order-price-tag { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; padding: 5px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 4px; white-space: nowrap; }
        
        .quick-price-form { display: inline-flex; align-items: center; gap: 3px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 2px 5px; border-radius: 8px; }
        .quick-price-form select, .quick-price-form input { border: none; background: transparent; font-size: 11px; font-weight: 600; outline: none; color: #1e293b; padding: 2px; }
        .quick-price-form select { background: #e2e8f0; border-radius: 4px; margin-right: 2px; }
        .quick-price-form input { width: 75px; }
        .quick-price-btn { background: #00a859; color: white; border: none; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; transition: background 0.3s; white-space: nowrap; }
        .quick-price-btn:hover { background: #008f4c; }

        .btn-qr-trigger { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 5px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: background 0.3s; white-space: nowrap; }
        .btn-qr-trigger:hover { background: #dbeafe; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; white-space: nowrap; }
        .status-cho { background: #fef3c7; color: #b45309; }
        .status-dangsua { background: #e0f2fe; color: #0369a1; }
        .status-hoanthanh { background: #d1fae5; color: #047857; }
        .status-huy { background: #fee2e2; color: #991b1b; }
        
        .method-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .method-transfer { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .method-cash { background: #fef9c3; color: #854d0e; border: 1px solid #fef08a; }

        .order-body { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        @media(max-width: 850px) { .order-body { grid-template-columns: 1fr; } }
        .info-box { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; position: relative; }
        .info-box label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px; }
        .info-box p { font-size: 13px; font-weight: 600; color: #1e293b; }
        
        .lock-note-box { background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 10px; border-radius: 6px; font-size: 12px; margin-top: 8px; }
        .btn-bell-chat { position: absolute; top: 12px; right: 12px; background: #eef8f3; color: #00a859; border: 1px solid #c8e6d5; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .btn-bell-chat:hover { background: #00a859; color: white; }
        .form-update-box { display: flex; flex-direction: column; gap: 10px; }
        .form-update-box label { font-size: 11px; font-weight: 700; color: #334155; }
        .form-update-box select, .form-update-box input, .form-update-box textarea { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; outline: none; }
        .form-update-box textarea { resize: vertical; height: 55px; }
        
        .payment-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .btn-submit-update { background: #00a859; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; transition: background 0.3s; margin-top: 4px; }
        .btn-submit-update:hover { background: #008f4c; }
        
        .order-card.locked-disabled { background-color: #fafafa; opacity: 0.95; }
        .order-card.locked-disabled input, 
        .order-card.locked-disabled select, 
        .order-card.locked-disabled textarea { background-color: #f1f5f9; color: #64748b; cursor: not-allowed; }
        .order-card.locked-disabled .quick-price-btn,
        .order-card.locked-disabled .btn-submit-update { background: #94a3b8; cursor: not-allowed; pointer-events: none; }

        .empty-orders { text-align: center; padding: 40px; color: #64748b; font-weight: 600; }
        
        .chat-modal, .qr-modal { display: none; position: fixed; bottom: 25px; right: 25px; width: 350px; background: white; border-radius: 12px; border: 1px solid #cbd5e1; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 1000; flex-direction: column; overflow: hidden; animation: slideUp 0.3s ease; }
        .chat-modal.active, .qr-modal.active { display: flex; }
        .qr-modal { width: 320px; align-items: center; text-align: center; padding-bottom: 15px; right: auto; left: 50%; top: 50%; transform: translate(-50%, -50%); bottom: auto; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .chat-header, .qr-header { background: #0f2027; color: white; padding: 12px 15px; font-size: 13px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .chat-close, .qr-close { background: none; border: none; color: white; font-size: 16px; cursor: pointer; }
        .chat-messages { flex: 1; padding: 12px; overflow-y: auto; background: #f8fafc; display: flex; flex-direction: column; gap: 8px; height: 300px; width: 100%; }
        .chat-msg { max-width: 80%; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 500; }
        .chat-msg.customer { background: #e2e8f0; color: #1e293b; align-self: flex-start; }
        .chat-msg.ktv { background: #00a859; color: white; align-self: flex-end; }
        .chat-input { padding: 10px; background: white; border-top: 1px solid #e2e8f0; display: flex; gap: 6px; width: 100%; }
        .chat-input input { flex: 1; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; outline: none; }
        .chat-input button { background: #00a859; color: white; border: none; padding: 0 12px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        
        .qr-body { padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .qr-body img { width: 220px; height: 220px; border-radius: 8px; border: 1px solid #e2e8f0; object-fit: cover; }
        .qr-body p { font-size: 13px; font-weight: 600; color: #334155; }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="navbar-brand">
            <i class="fa-solid fa-screwdriver-wrench"></i> HỆ THỐNG ĐIỀU HÀNH KỸ THUẬT VIÊN - PC 24/7
        </div>
        <div class="navbar-actions">
            <span class="badge-ktv"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($ktv_name); ?></span>
            <a href="../auth/logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng Xuất</a>
        </div>
    </header>
    <div class="container">
        <div class="dashboard-header">
            <div class="header-top-box">
                <div>
                    <h2><i class="fa-solid fa-chart-line"></i> Bảng Điều Hành Kỹ Thuật Viên</h2>
                    <p>Theo dõi sát sao lịch đặt sửa chữa, phân loại đơn chuyển khoản/tiền mặt và cập nhật giá trực tiếp.</p>
                </div>
                <div class="header-right-actions">
                    <div class="stats-boxes">
                        <div class="stat-box"><span><?php echo $total_orders; ?></span><small>Tổng đơn</small></div>
                        <div class="stat-box highlight" title="Doanh thu chuyển khoản">
                            <span><?php echo number_format($transfer_revenue, 0, ',', '.'); ?>đ</span>
                            <small>CK (<?php echo $transfer_count; ?>)</small>
                        </div>
                        <div class="stat-box highlight" title="Doanh thu tiền mặt">
                            <span><?php echo number_format($cash_revenue, 0, ',', '.'); ?>đ</span>
                            <small>Tiền mặt (<?php echo $cash_count; ?>)</small>
                        </div>
                        <div class="stat-box highlight" title="Tổng doanh thu toàn hệ thống">
                            <span><?php echo number_format($grand_total_revenue, 0, ',', '.'); ?>đ</span>
                            <small>Tổng tiền</small>
                        </div>
                    </div>
                    <button type="button" class="btn-reset" onclick="window.location.reload();">
                        <i class="fa-solid fa-rotate-right"></i> Làm Mới
                    </button>
                </div>
            </div>
        </div>

        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterOrders('all', event)"><i class="fa-solid fa-list"></i> Tất Cả Đơn</button>
            <button class="tab-btn" onclick="filterOrders('transfer', event)"><i class="fa-solid fa-qrcode"></i> Đơn Chuyển Khoản</button>
            <button class="tab-btn" onclick="filterOrders('cash', event)"><i class="fa-solid fa-money-bill-wave"></i> Đơn Tiền Mặt</button>
            <button class="tab-btn" onclick="filterOrders('moi', event)"><i class="fa-solid fa-calendar-plus"></i> Đơn Mới</button>
            <button class="tab-btn" onclick="filterOrders('lichsu', event)"><i class="fa-solid fa-clock-rotate-left"></i> Đang Sửa</button>
            <button class="tab-btn" onclick="filterOrders('hoanthanh', event)"><i class="fa-solid fa-circle-check"></i> Hoàn Thành</button>
            <button class="tab-btn" onclick="filterOrders('dahuy', event)"><i class="fa-solid fa-ban"></i> Đã Hủy</button>
        </div>

        <div id="ordersContainer">
            <?php if (!empty($all_bookings)): ?>
                <?php foreach ($all_bookings as $order): 
                    $status = trim($order['status'] ?? 'Chờ KTV xác nhận');
                    $badge_class = 'status-cho';
                    $category_type = 'moi';
                    $is_locked = ($status === 'Đã hủy' || $status === 'Đã hoàn thành');
                    
                    if ($status === 'Đang sửa chữa' || $status === 'Đang nhận máy') {
                        $badge_class = 'status-dangsua';
                        $category_type = 'lichsu';
                    } elseif ($status === 'Đã hoàn thành') {
                        $badge_class = 'status-hoanthanh';
                        $category_type = 'hoanthanh';
                    } elseif ($status === 'Đã hủy') {
                        $badge_class = 'status-huy';
                        $category_type = 'dahuy';
                    }
                    
                    $price_val = isset($order['gia']) ? floatval($order['gia']) : 0;
                    $price_display = ($price_val > 0) ? number_format($price_val, 0, ',', '.') . ' VNĐ' : 'Chưa có giá';
                    
                    $payment_method = $order['parsed_payment_method'] ?? 'Chuyển khoản';
                    $payment_filter_class = ($payment_method === 'Tiền mặt') ? 'cash' : 'transfer';
                ?>
                    <div class="order-card <?php echo $is_locked ? 'locked-disabled' : ''; ?>" data-category="<?php echo $category_type; ?>" data-payment="<?php echo $payment_filter_class; ?>" data-status="<?php echo $status; ?>">
                        <div class="order-card-header">
                            <div>
                                <div class="order-title">
                                    <i class="fa-solid fa-folder-open" style="color: #00a859;"></i> Đơn Sửa Chữa #<?php echo $order['id']; ?>
                                    <?php if ($payment_method === 'Tiền mặt'): ?>
                                        <span class="method-badge method-cash"><i class="fa-solid fa-money-bill-wave"></i> Tiền mặt</span>
                                    <?php else: ?>
                                        <span class="method-badge method-transfer"><i class="fa-solid fa-qrcode"></i> Chuyển khoản</span>
                                    <?php endif; ?>
                                </div>
                                <div class="order-meta">
                                    <span><i class="fa-regular fa-calendar"></i> Ngày đặt: <?php echo date('d/m/Y', strtotime($order['booking_date'])); ?></span>
                                    <span><i class="fa-solid fa-desktop"></i> Yêu cầu: <?php echo htmlspecialchars($order['service_name']); ?></span>
                                </div>
                            </div>
                            <div class="header-right-group">
                                <button type="button" class="btn-qr-trigger" onclick="openQR(<?php echo $order['id']; ?>, <?php echo $price_val; ?>, 'Thanh toan don #<?php echo $order['id']; ?>', '<?php echo $base_asset_url; ?>')" title="Tạo mã QR chuyển khoản">
                                    <i class="fa-solid fa-qrcode"></i> QR
                                </button>
                                
                                <div class="order-price-tag" title="Tổng tiền dịch vụ">
                                    <i class="fa-solid fa-tag"></i> <?php echo $price_display; ?>
                                </div>

                                <form method="POST" action="" class="quick-price-form" onsubmit="saveScrollPosition()">
                                    <input type="hidden" name="quick_update_price_id" value="<?php echo $order['id']; ?>">
                                    <select name="quick_payment_method" title="Chọn hình thức thanh toán" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        <option value="Chuyển khoản" <?php if($payment_method == 'Chuyển khoản') echo 'selected'; ?>>CK</option>
                                        <option value="Tiền mặt" <?php if($payment_method == 'Tiền mặt') echo 'selected'; ?>>Tiền mặt</option>
                                    </select>
                                    <input type="number" name="quick_total_cost" placeholder="Nhập giá..." value="<?php echo $price_val > 0 ? intval($price_val) : ''; ?>" <?php echo $is_locked ? 'disabled' : 'required'; ?>>
                                    <button type="submit" class="quick-price-btn" title="Cập nhật nhanh giá tiền" <?php echo $is_locked ? 'disabled' : ''; ?>><i class="fa-solid fa-check"></i></button>
                                </form>

                                <span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                            </div>
                        </div>
                        <div class="order-body">
                            <div style="display: flex; flex-direction: column; gap: 15px;">
                                <div class="info-box">
                                    <button type="button" class="btn-bell-chat" onclick="openChat(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['fullname'] ?? $order['customer_name'] ?? 'Khách hàng'); ?>')" title="Mở chat với khách">
                                        <i class="fa-solid fa-bell"></i>
                                    </button>
                                    <label>Khách Hàng</label>
                                    <p><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($order['fullname'] ?? $order['customer_name'] ?? 'Khách lẻ'); ?></p>
                                </div>
                                <div class="info-box">
                                    <label>Số Điện Thoại</label>
                                    <p><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($order['phone']); ?></p>
                                </div>
                                <div class="info-box">
                                    <label>Ghi chú / Lịch sử đơn hàng</label>
                                    <p style="color: #475569; font-weight: 500; font-size: 12px; line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars($order['note'])); ?>
                                    </p>
                                    <?php if ($is_locked): ?>
                                        <div class="lock-note-box">
                                            <i class="fa-solid fa-lock"></i> Đơn hàng này đã <?php echo ($status === 'Đã hoàn thành') ? 'hoàn thành' : 'bị hủy'; ?>, mọi thao tác chỉnh sửa đã bị khóa.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-box">
                                <form method="POST" action="" class="form-update-box" onsubmit="saveScrollPosition()">
                                    <input type="hidden" name="update_booking_id" value="<?php echo $order['id']; ?>">
                                    <div>
                                        <label>Cập nhật trạng thái mới:</label>
                                        <select name="new_status" <?php echo $is_locked ? 'disabled' : 'required'; ?>>
                                            <option value="Chờ KTV xác nhận" <?php if($status == 'Chờ KTV xác nhận') echo 'selected'; ?>>Chờ KTV xác nhận</option>
                                            <option value="Đang nhận máy" <?php if($status == 'Đang nhận máy') echo 'selected'; ?>>Đang nhận máy</option>
                                            <option value="Đang sửa chữa" <?php if($status == 'Đang sửa chữa') echo 'selected'; ?>>Đang sửa chữa</option>
                                            <option value="Đã hoàn thành" <?php if($status == 'Đã hoàn thành') echo 'selected'; ?>>Đã hoàn thành</option>
                                            <option value="Đã hủy" <?php if($status == 'Đã hủy') echo 'selected'; ?>>Đã hủy</option>
                                        </select>
                                    </div>
                                    <div class="payment-row">
                                        <div>
                                            <label>Hình Thức Thanh Toán:</label>
                                            <select name="payment_method" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                <option value="Chuyển khoản" <?php if($payment_method == 'Chuyển khoản') echo 'selected'; ?>>Chuyển khoản</option>
                                                <option value="Tiền mặt" <?php if($payment_method == 'Tiền mặt') echo 'selected'; ?>>Tiền mặt</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label>Tổng Tiền (VNĐ):</label>
                                            <input type="number" name="total_cost" placeholder="Ví dụ: 350000" value="<?php echo $price_val > 0 ? intval($price_val) : ''; ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>
                                    <div>
                                        <label>Phản hồi hoặc Lý do hủy đơn (gửi khách):</label>
                                        <textarea name="feedback_note" placeholder="Nhập lý do hủy đơn hoặc nội dung trao đổi..." <?php echo $is_locked ? 'disabled' : ''; ?>></textarea>
                                    </div>
                                    <button type="submit" class="btn-submit-update" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        <i class="fa-solid fa-circle-check"></i> Cập Nhật Đơn Hàng
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="order-card empty-orders">
                    <i class="fa-solid fa-inbox" style="font-size: 40px; margin-bottom: 10px; color: #cbd5e1;"></i>
                    <p>Hiện chưa có đơn sửa chữa nào trong hệ thống.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="qr-modal" id="qrModal">
        <div class="qr-header">
            <span id="qrModalTitle"><i class="fa-solid fa-qrcode"></i> Mã QR Thanh Toán</span>
            <button class="qr-close" onclick="closeQR()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="qr-body">
            <img id="qrImage" src="" alt="Mã QR thanh toán">
            <p id="qrInfoText">Quét mã QR để thanh toán đơn hàng</p>
        </div>
    </div>

    <div class="chat-modal" id="chatModal">
        <div class="chat-header">
            <span id="chatTitle"><i class="fa-solid fa-comments"></i> Trò chuyện với khách</span>
            <button class="chat-close" onclick="closeChat()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="chat-messages" id="chatMessages">
            <div class="chat-msg customer">Xin chào KTV, máy của tôi tiến độ thế nào rồi?</div>
            <div class="chat-msg ktv">Chào bạn, KTV đang tiến hành kiểm tra máy cho bạn nhé.</div>
        </div>
        <div class="chat-input">
            <input type="text" id="chatInputText" placeholder="Nhập tin nhắn..." onkeypress="if(event.key === 'Enter') sendMsg();">
            <button type="button" onclick="sendMsg()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </div>

    <script>
        // Lưu vị trí cuộn trang trước khi submit form
        function saveScrollPosition() {
            localStorage.setItem('scrollPosition', window.scrollY);
        }

        // Tự động cuộn lại đúng vị trí cũ khi tải lại trang
        window.addEventListener('DOMContentLoaded', () => {
            const scrollPos = localStorage.getItem('scrollPosition');
            if (scrollPos !== null) {
                window.scrollTo(0, parseInt(scrollPos));
                localStorage.removeItem('scrollPosition');
            }

            const cards = document.querySelectorAll('.order-card');
            cards.forEach(card => {
                const status = card.getAttribute('data-status');
                if (status === 'Đã hoàn thành' || status === 'Đã hủy') {
                    card.style.display = 'none';
                }
            });
        });

        <?php if ($auto_open_qr_id > 0): ?>
            window.addEventListener('DOMContentLoaded', () => {
                openQR(<?php echo $auto_open_qr_id; ?>, <?php echo$auto_open_qr_price; ?>, 'Thanh toan don #<?php echo $auto_open_qr_id; ?>', '<?php echo$base_asset_url; ?>');
            });
        <?php endif; ?>

        function filterOrders(type, event) {
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
            
            const cards = document.querySelectorAll('.order-card');
            cards.forEach(card => {
                const status = card.getAttribute('data-status');
                const isFinishedOrCancelled = (status === 'Đã hoàn thành' || status === 'Đã hủy');

                if (type === 'all') {
                    card.style.display = isFinishedOrCancelled ? 'none' : 'block';
                } else if (type === 'transfer') {
                    const pay = card.getAttribute('data-payment');
                    card.style.display = (pay === 'transfer' && !isFinishedOrCancelled) ? 'block' : 'none';
                } else if (type === 'cash') {
                    const pay = card.getAttribute('data-payment');
                    card.style.display = (pay === 'cash' && !isFinishedOrCancelled) ? 'block' : 'none';
                } else {
                    const cat = card.getAttribute('data-category');
                    card.style.display = (cat === type) ? 'block' : 'none';
                }
            });
        }

        function openQR(orderId, amount, note, imgUrl) {
            const modal = document.getElementById('qrModal');
            const qrImg = document.getElementById('qrImage');
            const title = document.getElementById('qrModalTitle');
            const infoText = document.getElementById('qrInfoText');
            
            title.innerHTML = `<i class="fa-solid fa-qrcode"></i> QR Đơn Hàng #${orderId}`;
            qrImg.src = imgUrl; 
            
            const formattedAmount = amount > 0 ? amount.toLocaleString('vi-VN') + ' VNĐ' : 'Chưa cập nhật giá';
            infoText.innerHTML = `Số tiền: <b>${formattedAmount}</b><br>Nội dung: ${note}`;
            
            modal.classList.add('active');
        }

        function closeQR() {
            document.getElementById('qrModal').classList.remove('active');
        }

        function openChat(orderId, customerName) {
            const modal = document.getElementById('chatModal');
            document.getElementById('chatTitle').innerHTML = `<i class="fa-solid fa-comments"></i> Chat: ${customerName} (#${orderId})`;
            modal.classList.add('active');
            document.getElementById('chatInputText').focus();
        }

        function closeChat() {
            document.getElementById('chatModal').classList.remove('active');
        }

        function sendMsg() {
            const input = document.getElementById('chatInputText');
            const container = document.getElementById('chatMessages');
            const text = input.value.trim();
            if (!text) return;
            const msgDiv = document.createElement('div');
            msgDiv.className = 'chat-msg ktv';
            msgDiv.innerText = text;
            container.appendChild(msgDiv);
            input.value = '';
            container.scrollTop = container.scrollHeight;
        }
    </script>
</body>
</html>