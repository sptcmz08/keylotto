-- =============================================
-- Lottery Key Website Database Schema
-- =============================================



-- =============================================
-- 1. Users (Admin Login)
-- =============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('admin','user') DEFAULT 'admin',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: admin / 123456
INSERT INTO `users` (`username`, `password`, `name`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ดูแลระบบ', 'admin')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- =============================================
-- 2. Lottery Categories (หมวดหมู่หวย)
-- =============================================
CREATE TABLE IF NOT EXISTS `lottery_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lottery_categories` (`id`, `name`, `slug`, `sort_order`) VALUES
(1, 'หวยชุด', 'huay-chud', 1),
(2, 'หวยไทย', 'huay-thai', 2),
(3, 'หวยต่างประเทศ', 'huay-tangprathet', 3),
(4, 'หวยรายวัน', 'huay-raiwan', 4),
(5, 'หวยหุ้น', 'huay-hun', 5),
(6, 'หวย One', 'huay-one', 6),
(7, 'หวยสากล', 'huay-sakon', 7)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- =============================================
-- 3. Lottery Types (ประเภทหวย)
-- =============================================
CREATE TABLE IF NOT EXISTS `lottery_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `flag_emoji` VARCHAR(10) DEFAULT '🏳️',
  `flag_image` VARCHAR(255) DEFAULT NULL,
  `close_time` TIME DEFAULT NULL,
  `result_time` TIME DEFAULT NULL,
  `result_url` VARCHAR(500) DEFAULT NULL,
  `result_label` VARCHAR(100) DEFAULT NULL,
  `draw_date` DATE DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  UNIQUE KEY `name_category` (`name`, `category_id`),
  CONSTRAINT `fk_lottery_category` FOREIGN KEY (`category_id`) REFERENCES `lottery_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample lottery types
INSERT INTO `lottery_types` (`id`, `category_id`, `name`, `flag_emoji`, `close_time`, `result_time`, `result_url`, `result_label`, `draw_date`, `sort_order`) VALUES
-- หวยชุด
(1, 1, 'หวยไทยชุด', '🇹🇭', '15:00:00', '15:30:00', 'https://www.glo.or.th', 'www.glo.or.th', CURDATE(), 1),
(2, 1, 'หวยไทย JACKPOT', '🇹🇭', '15:00:00', '15:30:00', 'https://www.glo.or.th', 'www.glo.or.th', CURDATE(), 2),
(3, 1, 'หวยชุด ฮานอย', '🇻🇳', '18:10:00', '18:30:00', '#', 'หวยฮานอย', CURDATE(), 3),
(4, 1, 'ฮานอย JACKPOT', '🇻🇳', '18:10:00', '18:30:00', '#', 'หวยฮานอย', CURDATE(), 4),
(5, 1, 'หวยลาวชุด', '🇱🇦', '20:20:00', '20:30:00', '#', 'หวยลาวพัฒนา', CURDATE(), 5),
(6, 1, 'ลาวพัฒนา JACKPOT', '🇱🇦', '20:20:00', '20:30:00', '#', 'ลาวพัฒนา', CURDATE(), 6),
-- หวยต่างประเทศ
(7, 3, 'ดาวโจนส์อเมริกา', '🇺🇸', '23:59:00', '00:10:00', '#', 'ดาวโจนส์', CURDATE(), 1),
(8, 3, 'ฮานอยพิเศษ', '🇻🇳', '03:05:00', '03:30:00', '#', 'ฮานอยพิเศษ', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2),
(9, 3, 'ฮานอยปกติ', '🇻🇳', '03:05:00', '03:30:00', '#', 'ฮานอยปกติ', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 3),
(10, 3, 'ฮานอย VIP', '🇻🇳', '03:05:00', '03:30:00', '#', 'ฮานอย VIP', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 4),
(11, 3, 'หวยฮ่องกง', '🇭🇰', '03:05:00', '03:30:00', '#', 'หวยฮ่องกง', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 5),
(12, 3, 'ลาว VIP', '🇱🇦', '03:05:00', '03:30:00', '#', 'ลาว VIP', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 6),
-- หวยรายวัน
(13, 4, 'เยอรมัน VIP', '🇩🇪', '22:45:00', '23:00:00', '#', 'เยอรมัน VIP', CURDATE(), 1),
(14, 4, 'ลาวกาชาด', '🇱🇦', '23:25:00', '23:30:00', '#', 'ลาวกาชาด', CURDATE(), 2),
(15, 4, 'รัสเซีย VIP', '🇷🇺', '23:45:00', '00:00:00', '#', 'รัสเซีย VIP', CURDATE(), 3),
(16, 4, 'ดาวโจนส์ VIP', '🇺🇸', '00:10:00', '00:30:00', '#', 'ดาวโจนส์ VIP', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 4),
(17, 4, 'ดาวโจนส์ STAR', '🇺🇸', '01:05:00', '01:30:00', '#', 'ดาวโจนส์ STAR', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 5),
(18, 4, 'ลาวประตูชัย', '🇱🇦', '05:00:00', '05:30:00', '#', 'ลาวประตูชัย', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 6),
(19, 4, 'ลาวสันติภาพ', '🇱🇦', '05:00:00', '05:30:00', '#', 'ลาวสันติภาพ', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 7),
(20, 4, 'ประชาชนลาว', '🇱🇦', '05:00:00', '05:30:00', '#', 'ประชาชนลาว', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 8),
(21, 4, 'ลาว Extra', '🇱🇦', '05:00:00', '05:30:00', '#', 'ลาว Extra', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 9),
-- หวยหุ้น
(22, 5, 'หุ้นดาวโจนส์', '🇺🇸', '22:00:00', '22:30:00', '#', 'หุ้นดาวโจนส์', CURDATE(), 1),
(23, 5, 'หุ้นรัสเซีย', '🇷🇺', '21:47:00', '22:00:00', '#', 'หุ้นรัสเซีย', CURDATE(), 2),
(24, 5, 'หุ้นเยอรมัน', '🇩🇪', '21:47:00', '22:00:00', '#', 'หุ้นเยอรมัน', CURDATE(), 3),
(25, 5, 'หุ้นอังกฤษ', '🇬🇧', '21:47:00', '22:00:00', '#', 'หุ้นอังกฤษ', CURDATE(), 4)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- =============================================
-- 4. Pay Rates (อัตราจ่าย)
-- =============================================
CREATE TABLE IF NOT EXISTS `pay_rates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lottery_type_id` INT(11) NOT NULL,
  `bet_type` VARCHAR(20) NOT NULL COMMENT '3top,3tod,2top,2bot,run_top,run_bot',
  `rate_label` VARCHAR(50) DEFAULT NULL,
  `pay_rate` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `discount` DECIMAL(5,2) DEFAULT 0,
  `min_bet` INT(11) DEFAULT 1,
  `max_bet` INT(11) DEFAULT 500,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lotto_bet` (`lottery_type_id`, `bet_type`),
  CONSTRAINT `fk_rate_lottery` FOREIGN KEY (`lottery_type_id`) REFERENCES `lottery_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 5. Bets (บิลเดิมพัน)
-- =============================================
CREATE TABLE IF NOT EXISTS `bets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bet_number` VARCHAR(20) DEFAULT NULL,
  `lottery_type_id` INT(11) NOT NULL,
  `draw_date` DATE NOT NULL,
  `total_items` INT(11) DEFAULT 0,
  `total_amount` DECIMAL(12,2) DEFAULT 0,
  `discount_amount` DECIMAL(12,2) DEFAULT 0,
  `net_amount` DECIMAL(12,2) DEFAULT 0,
  `note` TEXT DEFAULT NULL,
  `status` ENUM('pending','won','lost','cancelled') DEFAULT 'pending',
  `win_amount` DECIMAL(12,2) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bet_number` (`bet_number`),
  KEY `lottery_type_id` (`lottery_type_id`),
  KEY `draw_date` (`draw_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_bet_lottery` FOREIGN KEY (`lottery_type_id`) REFERENCES `lottery_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 6. Bet Items (รายละเอียดเดิมพัน)
-- =============================================
CREATE TABLE IF NOT EXISTS `bet_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bet_id` INT(11) NOT NULL,
  `number` VARCHAR(10) NOT NULL,
  `bet_type` VARCHAR(20) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `is_reversed` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bet_id` (`bet_id`),
  KEY `check_results` (`bet_type`, `number`),
  CONSTRAINT `fk_item_bet` FOREIGN KEY (`bet_id`) REFERENCES `bets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 7. Results (ผลรางวัล)
-- =============================================
CREATE TABLE IF NOT EXISTS `results` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lottery_type_id` INT(11) NOT NULL,
  `draw_date` DATE NOT NULL,
  `three_top` VARCHAR(10) DEFAULT NULL,
  `three_tod` VARCHAR(255) DEFAULT NULL,
  `two_top` VARCHAR(10) DEFAULT NULL,
  `two_bot` VARCHAR(10) DEFAULT NULL,
  `run_top` VARCHAR(10) DEFAULT NULL,
  `run_bot` VARCHAR(10) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lotto_date` (`lottery_type_id`, `draw_date`),
  CONSTRAINT `fk_result_lottery` FOREIGN KEY (`lottery_type_id`) REFERENCES `lottery_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 8. Result Links (ลิงค์ดูผลหวย)
-- =============================================
CREATE TABLE IF NOT EXISTS `result_links` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `flag_emoji` VARCHAR(10) DEFAULT '🏳️',
  `close_time` TIME DEFAULT NULL,
  `result_time` TIME DEFAULT NULL,
  `result_url` VARCHAR(500) DEFAULT NULL,
  `result_label` VARCHAR(100) DEFAULT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_category` (`name`, `category_id`),
  CONSTRAINT `fk_link_category` FOREIGN KEY (`category_id`) REFERENCES `lottery_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample result links
INSERT INTO `result_links` (`id`, `category_id`, `name`, `flag_emoji`, `close_time`, `result_time`, `result_url`, `result_label`, `sort_order`) VALUES
(1, 1, 'หวยไทยชุด', '🇹🇭', '15:00:00', '15:30:00', 'https://www.glo.or.th', 'www.glo.or.th', 1),
(2, 1, 'หวยไทย JACKPOT', '🇹🇭', '15:00:00', '15:30:00', 'https://www.glo.or.th', 'www.glo.or.th', 2),
(3, 1, 'หวยชุด ฮานอย', '🇻🇳', '18:10:00', '18:30:00', '#', 'หวยฮานอย', 3),
(4, 1, 'ฮานอย JACKPOT', '🇻🇳', '18:10:00', '18:30:00', '#', 'หวยฮานอย', 4),
(5, 1, 'หวยลาวชุด', '🇱🇦', '20:20:00', '20:30:00', '#', 'หวยลาวพัฒนา', 5),
(6, 1, 'ลาวพัฒนา JACKPOT', '🇱🇦', '20:20:00', '20:30:00', '#', 'ลาวพัฒนา', 6)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Sample pay rates for ดาวโจนส์อเมริกา (id=7)
INSERT INTO `pay_rates` (`lottery_type_id`, `bet_type`, `rate_label`, `pay_rate`, `discount`, `min_bet`, `max_bet`) VALUES
(7, '3top', '3 ตัวบน', 800.00, 5.00, 1, 500),
(7, '3tod', '3 ตัวโต๊ด', 125.00, 5.00, 1, 500),
(7, '2top', '2 ตัวบน', 100.00, 0.00, 1, 500),
(7, '2bot', '2 ตัวล่าง', 100.00, 0.00, 1, 500),
(7, 'run_top', 'วิ่งบน', 3.00, 12.00, 1, 5000),
(7, 'run_bot', 'วิ่งล่าง', 4.00, 12.00, 1, 5000)
ON DUPLICATE KEY UPDATE `pay_rate` = VALUES(`pay_rate`);

-- Sample results
INSERT INTO `results` (`lottery_type_id`, `draw_date`, `three_top`, `two_top`, `two_bot`) VALUES
(7, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '400', '40', '07'),
(7, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '043', '04', '54'),
(7, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '486', '48', '30'),
(7, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '864', '86', '25'),
(7, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '582', '58', '43')
ON DUPLICATE KEY UPDATE `three_top` = VALUES(`three_top`);
