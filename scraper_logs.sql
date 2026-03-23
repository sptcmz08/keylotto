-- =============================================
-- Scraper Logs (บันทึกการดึงผลอัตโนมัติ)
-- =============================================
-- เพิ่มตารางนี้ลง database เพื่อ track ผลการ scrape
-- รัน SQL นี้ใน phpMyAdmin หรือ Plesk DB Manager

CREATE TABLE IF NOT EXISTS `scraper_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `lottery_name` VARCHAR(100) DEFAULT NULL,
  `source` VARCHAR(50) DEFAULT NULL COMMENT 'manycai, raakaadee, stockvip, hanoi, laovip, laosamakki',
  `status` ENUM('success','failed','skipped') DEFAULT 'success',
  `message` TEXT DEFAULT NULL,
  `draw_date` DATE DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source_status` (`source`, `status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
