-- เพิ่มคอลัมน์ max_per_number ในตาราง pay_rates
-- ใช้กำหนดยอดรับสูงสุดรวมต่อเลข ต่อประเภท ต่อหวย (รวมทุกบิล)
-- 0 = ไม่จำกัด
ALTER TABLE pay_rates ADD COLUMN max_per_number INT NOT NULL DEFAULT 0 COMMENT 'ยอดรับสูงสุดรวมต่อเลข (0=ไม่จำกัด)' AFTER max_bet;
