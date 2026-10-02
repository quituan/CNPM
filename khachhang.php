<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Hiển thị thông báo thành công nếu có từ trang chủ chuyển sang
if (isset($_SESSION['success_booking_msg'])) {
    echo '<div class="alert alert-success" style="padding: 15px 20px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 8px; margin: 20px auto; max-width: 1200px; font-size: 14px; font-weight: 500;">
            <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> ' . $_SESSION['success_booking_msg'] . '
          </div>';
    unset($_SESSION['success_booking_msg']); // Xóa ngay sau khi hiển thị để không bị lặp lại khi F5 lại trang
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Khách hàng');

$msg = '';
$msg_type = '';

// Xử lý hủy đơn nếu đơn ở trạng thái chờ
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    $stmt_chk = $conn->prepare("SELECT id, status FROM bookings WHERE id = ? AND user_id = ?");
    $stmt_chk->bind_param("ii", $cancel_id, $user_id);
    $stmt_chk->execute();
    $res_chk = $stmt_chk->get_result();
    
    if ($res_chk && $res_chk->num_rows > 0) {
        $row_chk = $res_chk->fetch_assoc();
        if ($row_chk['status'] === 'Chờ KTV xác nhận') {
            $stmt_up = $conn->prepare("UPDATE bookings SET status = 'Đã hủy' WHERE id = ?");
            $stmt_up->bind_param("i", $cancel_id);
            if ($stmt_up->execute()) {
                $msg = "Đã hủy đơn đặt lịch #$cancel_id thành công!";
                $msg_type = "success";
            }
            $stmt_up->close();
        }
    }
    $stmt_chk->close();
}

// Lấy danh sách đơn đặt lịch của riêng khách hàng này
$stmt_orders = $conn->prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY id DESC");
$stmt_orders->bind_param("i", $user_id);
$stmt_orders->execute();
$orders_result = $stmt_orders->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch Sử Đặt Lịch & Theo Dõi Tình Trạng Máy - PC 24/7</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
        body { background-color: #f4f7f6; color: #1e293b; }
        
        .header-nav { background: #ffffff; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .logo { font-size: 22px; font-weight: 700; color: #00a859; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .nav-actions { display: flex; gap: 12px; align-items: center; }
        .btn-nav { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .btn-home { background: #eef8f3; color: #00a859; border: 1px solid #c8e6d5; }
        .btn-new { background: #00a859; color: white; }
        .btn-logout { background: #dc3545; color: white; }

        .banner-container { max-width: 1250px; margin: 30px auto 20px auto; padding: 0 20px; }
        .banner { background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); border-radius: 14px; color: white; padding: 40px 30px; text-align: center; box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .banner h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .banner p { font-size: 14px; color: #cbd5e1; font-weight: 500; }

        .container { max-width: 1250px; margin: 0 auto 50px auto; padding: 0 20px; }

        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .card h2 { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 600; }
        .alert-success { background: #d1fae5; color: #065f46; }

        /* Bảng danh sách đơn hàng đậm nét */
        .order-table { width: 100%; border-collapse: collapse; }
        .order-table th { background: #f8fafc; color: #334155; font-weight: 700; font-size: 12px; text-transform: uppercase; padding: 12px; border-bottom: 2px solid #e2e8f0; text-align: left; }
        .order-table td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; font-weight: 500; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-primary { background: #ede9fe; color: #6d28d9; }
        .badge-success { background: #d1fae5; color: #047857; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }

        .btn-cancel { background: #fee2e2; color: #b91c1c; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-block; border: none; cursor: pointer; }
        .btn-cancel:hover { background: #fecaca; }

        /* KHUNG CHAT CỐ ĐỊNH GÓC DƯỚI BÊN PHẢI (FLOATING CHAT) */
        .chat-widget-container { position: fixed; bottom: 25px; right: 25px; z-index: 1000; }
        .chat-bubble-btn { background: #00a859; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 6px 20px rgba(0,168,89,0.4); cursor: pointer; border: none; transition: transform 0.2s; }
        .chat-bubble-btn:hover { transform: scale(1.08); }
        
        .chat-box { position: absolute; bottom: 75px; right: 0; width: 360px; background: white; border-radius: 14px; border: 1px solid #cbd5e1; display: none; flex-direction: column; height: 460px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden; animation: slideUp 0.3s ease; }
        .chat-box.active { display: flex; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        .chat-header { background: #0f2027; color: white; padding: 14px 16px; font-size: 14px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
        .chat-close-btn { background: none; border: none; color: white; font-size: 16px; cursor: pointer; opacity: 0.8; }
        .chat-close-btn:hover { opacity: 1; }

        .chat-messages { flex: 1; padding: 15px; overflow-y: auto; background: #f8fafc; display: flex; flex-direction: column; gap: 10px; }
        .chat-msg { max-width: 80%; padding: 10px 14px; border-radius: 10px; font-size: 12px; line-height: 1.4; font-weight: 500; }
        .chat-msg.ktv { background: #e2e8f0; color: #1e293b; align-self: flex-start; }
        .chat-msg.user { background: #00a859; color: white; align-self: flex-end; }
        
        .chat-input-area { padding: 12px; background: white; border-top: 1px solid #e2e8f0; display: flex; gap: 8px; }
        .chat-input-area input { flex: 1; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; outline: none; }
        .chat-input-area button { background: #00a859; color: white; border: none; padding: 0 14px; border-radius: 6px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="header-nav">
        <a href="../index.php" class="logo"><i class="fa-solid fa-desktop"></i> PC 24/7</a>
        <div class="nav-actions">
            <a href="../index.php" class="btn-nav btn-home"><i class="fa-solid fa-house"></i> Trang Chủ</a>
            <a href="../index.php#booking" class="btn-nav btn-new"><i class="fa-solid fa-calendar-plus"></i> Đặt Lịch Mới</a>
            <a href="../auth/logout.php" class="btn-nav btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng Xuất</a>
        </div>
    </header>

    <!-- Banner -->
    <div class="banner-container">
        <div class="banner">
            <h1>Lịch Sử Đặt Lịch & Theo Dõi Tình Trạng Máy</h1>
            <p>Xin chào, <strong><?php echo htmlspecialchars($fullname); ?></strong>. Theo dõi tiến độ sửa chữa và quản lý các đơn đặt lịch của bạn.</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="card">
            <h2><i class="fa-solid fa-list-check" style="color: #00a859;"></i> Danh Sách Đơn Đặt Lịch Của Bạn</h2>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success"><?php echo $msg; ?></div>
            <?php endif; ?>

            <div style="overflow-x: auto;">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Dịch Vụ & Thông Tin</th>
                            <th>Ngày Hẹn</th>
                            <th>Trạng Thái Đơn</th>
                            <th>Ghi Chú</th>
                            <th>Thông Báo Từ KTV</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                            <?php while ($row = $orders_result->fetch_assoc()): 
                                $status = trim($row['status'] ?? 'Chờ KTV xác nhận');
                                $badge_class = 'badge-warning';
                                if ($status === 'Đang nhận máy') $badge_class = 'badge-info';
                                elseif ($status === 'Đang sửa chữa') $badge_class = 'badge-primary';
                                elseif ($status === 'Đã hoàn thành') $badge_class = 'badge-success';
                                elseif ($status === 'Đã hủy') $badge_class = 'badge-danger';

                                // Tách lấy riêng phần ghi chú gốc và phần phản hồi của KTV từ cột note trong DB
                                $raw_note = $row['note'] ?? '';
                                $user_note = $raw_note;
                                $ktv_feedback = '<span style="color: #94a3b8; font-style: italic;">Chưa có thông báo</span>';

                                if (strpos($raw_note, 'KTV Phản Hồi:') !== false) {
                                    $parts = explode('KTV Phản Hồi:', $raw_note);
                                    $user_note = trim($parts[0]); // Phần ghi chú trước đó
                                    $ktv_feedback = '<span style="color: #0f172a; font-weight: 600;">' . htmlspecialchars(trim(end($parts))) . '</span>';
                                }
                            ?>
                                <tr>
                                    <td><strong style="font-size: 14px; color: #0f172a;">#<?php echo $row['id']; ?></strong></td>
                                    <td>
                                        <strong style="color: #00a859;"><?php echo htmlspecialchars($row['service_name']); ?></strong><br>
                                        <small style="color: #475569; font-weight: 600;"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($row['phone']); ?></small>
                                    </td>
                                    <td><strong style="color: #1e293b;"><?php echo date('d/m/Y', strtotime($row['booking_date'])); ?></strong></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                    <td><span style="color: #334155; font-weight: 500;"><?php echo htmlspecialchars($user_note); ?></span></td>
                                    <td><?php echo $ktv_feedback; ?></td>
                                    <td>
                                        <?php if ($status === 'Chờ KTV xác nhận'): ?>
                                            <a href="khachhang.php?cancel_id=<?php echo $row['id']; ?>" class="btn-cancel" onclick="return confirm('Bạn có chắc muốn hủy đơn này không?');">Hủy Đơn</a>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 12px; font-weight: 600;">Không thể hủy</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #64748b; padding: 35px; font-weight: 600;">
                                    Bạn chưa có đơn đặt lịch sửa chữa nào.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- KHUNG CHAT CỐ ĐỊNH GÓC DƯỚI BÊN PHẢI -->
    <div class="chat-widget-container">
        <button class="chat-bubble-btn" onclick="toggleChat()" title="Trò chuyện với KTV">
            <i class="fa-solid fa-headset"></i>
        </button>

        <div class="chat-box" id="chatBox">
            <div class="chat-header">
                <span><i class="fa-solid fa-headset"></i> Trò Chuyện Trực Tuyến Với KTV</span>
                <button class="chat-close-btn" onclick="toggleChat()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="chat-messages" id="chatMessages">
                <div class="chat-msg ktv">
                    Chào bạn! Kỹ thuật viên PC 24/7 luôn sẵn sàng hỗ trợ giải đáp mọi thắc mắc về tình trạng máy của bạn.
                </div>
            </div>
            <div class="chat-input-area">
                <input type="text" id="chatInput" placeholder="Nhập tin nhắn hỏi KTV..." onkeypress="if(event.key === 'Enter') sendChatMessage();">
                <button type="button" onclick="sendChatMessage()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <!-- Script điều khiển Chat -->
    <script>
        function toggleChat() {
            const chatBox = document.getElementById('chatBox');
            chatBox.classList.toggle('active');
            if (chatBox.classList.contains('active')) {
                document.getElementById('chatInput').focus();
            }
        }

        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const messages = document.getElementById('chatMessages');
            const text = input.value.trim();

            if (!text) return;

            // Tin nhắn của Khách
            const userMsg = document.createElement('div');
            userMsg.className = 'chat-msg user';
            userMsg.innerText = text;
            messages.appendChild(userMsg);
            input.value = '';
            messages.scrollTop = messages.scrollHeight;

            // Phản hồi tự động từ hệ thống KTV sau 1 giây
            setTimeout(() => {
                const ktvMsg = document.createElement('div');
                ktvMsg.className = 'chat-msg ktv';
                ktvMsg.innerText = "KTV đã nhận được câu hỏi của bạn và sẽ phản hồi trong giây lát!";
                messages.appendChild(ktvMsg);
                messages.scrollTop = messages.scrollHeight;
            }, 1000);
        }
    </script>
</body>
</html>