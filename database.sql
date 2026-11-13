-- ========================================================
-- TẠO CƠ SỞ DỮ LIỆU HỆ THỐNG PC 24/7
-- ========================================================
CREATE DATABASE IF NOT EXISTS `suachua_maytinh` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `suachua_maytinh`;

-- --------------------------------------------------------
-- 1. BẢNG QUẢN LÝ TÀI KHOẢN (users)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','khachhang') NOT NULL DEFAULT 'khachhang',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu mẫu bảng users (Mật khẩu mặc định là: 123 - đã băm BCRYPT)
INSERT INTO `users` (`id`, `fullname`, `phone`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Quản trị viên Hệ thống', '0905000000', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW()),
(2, 'Nguyễn Văn Kỹ Thuật', '0905111111', 'ktv_an', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', NOW()),
(3, 'Lưu Quí Tuấn', '0905123456', 'khachhang1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'khachhang', NOW());

-- --------------------------------------------------------
-- 2. BẢNG QUẢN LÝ DANH MỤC DỊCH VỤ (services)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(150) NOT NULL,
  `price` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu mẫu bảng services
INSERT INTO `services` (`id`, `service_name`, `price`, `description`, `image`, `is_locked`) VALUES
(5, 'Cứu dữ liệu ổ cứng', 'Từ 300.000đ', 'Phục hồi dữ liệu ổ cứng bị format, bad sector, xóa nhầm.', 'cuhdd.jpg', 0),
(6, 'Thay bàn phím / Màn hình', 'Liên hệ báo giá', 'Thay thế linh kiện chính hãng cho laptop và PC.', 'keyboard.jpg', 0),
(7, 'Sửa chữa phần cứng / Mainboard', '150.000đ - 500.000đ', 'Khắc phục lỗi nguồn, chập cháy mainboard, không lên hình.', 'mainboard.jpg', 0);

-- --------------------------------------------------------
-- 3. BẢNG QUẢN LÝ LỊCH HẸN & ĐƠN ĐẶT HÀNG (bookings)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `service_name` varchar(150) NOT NULL,
  `booking_date` date NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `note` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Chờ KTV xác nhận',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_bookings_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu mẫu bảng bookings
INSERT INTO `bookings` (`id`, `user_id`, `customer_name`, `phone`, `address`, `service_name`, `booking_date`, `payment_method`, `note`, `status`, `created_at`) VALUES
(101, 3, 'Lưu Quí Tuấn', '0905123456', '123 Nguyễn Văn Linh, Đà Nẵng', 'Cứu dữ liệu ổ cứng', '2026-06-15', 'Tiền mặt khi hoàn thành', 'Ổ cứng bị kêu lạch cạch', 'Chờ KTV xác nhận', NOW()),
(102, 3, 'Nguyễn Văn A', '0912345678', '45 Lê Duẩn, Đà Nẵng', 'Vệ sinh & tra keo tản nhiệt', '2026-06-16', 'Chuyển khoản ngân hàng (QR)', 'Máy hay bị nóng tắt nguồn', 'Đang thực hiện', NOW());

-- --------------------------------------------------------
-- 4. BẢNG KHO LINH KIỆN (linh_kien / parts)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `linh_kien`;
CREATE TABLE `linh_kien` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ten_linh_kien` varchar(150) NOT NULL,
  `so_luong` int(11) NOT NULL DEFAULT 0,
  `gia_nhap` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `linh_kien` (`id`, `ten_linh_kien`, `so_luong`, `gia_nhap`) VALUES
(1, 'Keo tản nhiệt Arctic MX-4', 50, 120000.00);