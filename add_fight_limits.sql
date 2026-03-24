-- =============================================
-- ตาราง fight_limits สำหรับตั้งสู้ แยกแต่ละหวย
-- รัน: mysql -u root -p lotto < add_fight_limits.sql
-- =============================================

CREATE TABLE IF NOT EXISTS `fight_limits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lottery_type_id` INT NOT NULL,
  `bet_type` VARCHAR(20) NOT NULL COMMENT '3top, 3tod, 2top, 2bot, run_top, run_bot',
  `max_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'จำนวนเงินสูงสุดที่รับได้ (ต่อเลข)',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_lottery_bet` (`lottery_type_id`, `bet_type`),
  FOREIGN KEY (`lottery_type_id`) REFERENCES `lottery_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ค่าเริ่มต้นสำหรับทุกหวย: ใส่ค่า default 0 (ไม่จำกัด) จะ insert ตอน save ครั้งแรก
