-- =============================================
-- Blocked Numbers (เลขอั้น)
-- Admin กำหนดเลขอั้นพร้อมอัตราจ่ายพิเศษ
-- =============================================
CREATE TABLE IF NOT EXISTS `blocked_numbers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lottery_type_id` INT(11) NOT NULL,
  `number` VARCHAR(10) NOT NULL,
  `bet_type` ENUM('2top','2bot','3top','3tod','run_top','run_bot') NOT NULL DEFAULT '2top',
  `custom_pay_rate` DECIMAL(10,2) DEFAULT NULL COMMENT 'อัตราจ่ายพิเศษ (NULL = ใช้ค่าปกติ)',
  `is_blocked` TINYINT(1) DEFAULT 0 COMMENT '1=ปิดรับเลขนี้เลย',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lottery_type_id` (`lottery_type_id`),
  UNIQUE KEY `unique_blocked` (`lottery_type_id`, `number`, `bet_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
