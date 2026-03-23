-- =============================================
-- ตาราง site_settings สำหรับตั้งค่าระบบ
-- รัน: mysql -u root -p lotto < add_rate_settings.sql
-- =============================================

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ค่าเริ่มต้น
INSERT INTO site_settings (setting_key, setting_value, description) VALUES
('over_limit_threshold', '50', 'จำนวนรายการที่เกินแล้วลดอัตราจ่าย (ต่อประเภท)'),
('over_limit_2top_rate', '95', 'อัตราจ่าย 2 ตัวบน เมื่อเกิน threshold'),
('over_limit_2bot_rate', '95', 'อัตราจ่าย 2 ตัวล่าง เมื่อเกิน threshold'),
('over_limit_3top_rate', '800', 'อัตราจ่าย 3 ตัวบน เมื่อเกิน threshold'),
('over_limit_3tod_rate', '125', 'อัตราจ่าย 3 ตัวโต๊ด เมื่อเกิน threshold'),
('over_limit_run_top_rate', '3', 'อัตราจ่าย วิ่งบน เมื่อเกิน threshold'),
('over_limit_run_bot_rate', '4', 'อัตราจ่าย วิ่งล่าง เมื่อเกิน threshold')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
