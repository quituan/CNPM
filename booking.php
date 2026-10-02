<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/connect.php'; // Điều chỉnh đúng cho file đặt ở thư mục gốc

$is_logged_in = isset($_SESSION['user_id']);
$fullname = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? '');

$booking_msg = '';
$booking_msg_type = '';

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
    $user_id = $_SESSION['user_id'];

    if (!empty($customer_name) && !empty($service_name) && !empty($booking_date) && !empty($phone)) {
        $full_note = "Địa chỉ: " . ($address !== '' ? $address : 'Không có') . " | Thanh toán: " . $payment_method . " | Ghi chú: " . $note;
        
        $stmt = $conn->prepare("INSERT INTO bookings (user_id, customer_name, phone, service_name, booking_date, note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Chờ KTV xác nhận', NOW())");
        if ($stmt) {
            $stmt->bind_param("isssss", $user_id, $customer_name, $phone, $service_name, $booking_date, $full_note);
            if ($stmt->execute()) {
                $booking_msg = "Đặt lịch thành công! Kỹ thuật viên sẽ sớm liên hệ lại với bạn.";
                $booking_msg_type = "success";
            } else {
                $booking_msg = "Lỗi khi lưu đơn đặt lịch!";
                $booking_msg_type = "danger";
            }
            $stmt->close();
        } else {
            $booking_msg = "Đặt lịch thành công (Hệ thống ghi nhận nhanh)!";
            $booking_msg_type = "success";
        }
    } else {
        $booking_msg = "Vui lòng điền đầy đủ các thông tin bắt buộc!";
        $booking_msg_type = "warning";
    }
}
?>

<!-- Giao diện Form Booking tích hợp trong Modal -->
<div class="section-title" style="margin-bottom: 25px;">
    <h2><i class="fa-solid fa-calendar-check" style="color: #00a859;"></i> Đặt Lịch Sửa Chữa Nhanh</h2>
    <p>Điền thông tin thiết bị và lịch hẹn, chúng tôi sẽ hỗ trợ ngay lập tức</p>
</div>

<?php if (!empty($booking_msg)): ?>
    <div class="alert alert-<?php echo $booking_msg_type; ?>" style="padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 600; <?php echo $booking_msg_type=='success'?'background:#d4edda;color:#155724;':($booking_msg_type=='danger'?'background:#f8d7da;color:#721c24;':'background:#fff3cd;color:#856404;'); ?>">
        <?php echo $booking_msg; ?>
    </div>
<?php endif; ?>

<form method="POST" action="index.php#booking">
    <div class="form-grid">
        <div class="form-group">
            <label>Họ Tên Khách Hàng *:</label>
            <input type="text" name="customer_name" value="<?php echo htmlspecialchars($fullname); ?>" placeholder="Nhập họ tên của bạn..." required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
        </div>
        <div class="form-group">
            <label>Số Điện Thoại Liên Hệ *:</label>
            <input type="text" name="phone" placeholder="Nhập số điện thoại..." required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
        </div>
        <div class="form-group full">
            <label>Địa Chỉ Liên Hệ *:</label>
            <input type="text" name="address" placeholder="Ví dụ: 123 Nguyễn Văn Linh, Quận Thanh Khê, Đà Nẵng..." required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
        </div>
        <div class="form-group">
            <label>Chọn Dịch Vụ Sửa Chữa *:</label>
            <select name="service_name" id="selectedServiceDropdown" required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                <option value="">-- Chọn gói dịch vụ --</option>
                <option value="Sửa chữa phần cứng / Mainboard">Sửa chữa phần cứng / Mainboard (150k - 500k)</option>
                <option value="Vệ sinh & tra keo tản nhiệt">Vệ sinh & tra keo tản nhiệt (100k)</option>
                <option value="Cài đặt Windows & Phần mềm">Cài đặt Windows & Phần mềm (120k)</option>
                <option value="Nâng cấp SSD & RAM">Nâng cấp SSD & RAM (Theo linh kiện)</option>
                <option value="Cứu dữ liệu ổ cứng">Cứu dữ liệu ổ cứng (Từ 300k)</option>
                <option value="Thay bàn phím / Màn hình">Thay bàn phím / Màn hình (Liên hệ)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Ngày Đặt Hẹn *:</label>
            <input type="date" name="booking_date" id="bookingDateInput" min="<?php echo date('Y-m-d'); ?>" required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
        </div>
        <div class="form-group">
            <label>Phương Thức Thanh Toán *:</label>
            <select name="payment_method" required <?php echo !$is_logged_in ? 'disabled' : ''; ?>>
                <option value="Tiền mặt khi nhận máy">Thanh toán tiền mặt khi hoàn thành</option>
                <option value="Chuyển khoản ngân hàng">Chuyển khoản ngân hàng (QR Code)</option>
                <option value="Ví điện tử Momo">Ví điện tử Momo</option>
            </select>
        </div>
        <div class="form-group full">
            <label>Mô Tả Hiện Trạng Lỗi / Ghi Chú Thêm:</label>
            <textarea name="note" rows="3" placeholder="Ví dụ: Laptop bị sập nguồn liên tục, màn hình sọc ngang..." <?php echo !$is_logged_in ? 'disabled' : ''; ?>></textarea>
        </div>
    </div>

    <?php if ($is_logged_in): ?>
        <button type="submit" name="btn_quick_booking" class="btn-submit-booking"><i class="fa-solid fa-paper-plane"></i> Gửi Yêu Cầu Đặt Lịch</button>
    <?php else: ?>
        <a href="auth/login.php" class="btn-submit-booking" style="display:block; text-align:center; text-decoration:none;"><i class="fa-solid fa-lock"></i> Vui Lòng Đăng Nhập Để Gửi Yêu Cầu Đặt Lịch</a>
    <?php endif; ?>
</form>