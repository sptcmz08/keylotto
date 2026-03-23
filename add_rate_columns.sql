-- เพิ่มคอลัมน์ rate_adjusted ในตาราง bets
-- และ adjusted_pay_rate ในตาราง bet_items
-- รัน: mysql -u lotto -p lotto < add_rate_columns.sql

ALTER TABLE bets ADD COLUMN rate_adjusted TINYINT(1) DEFAULT 0 AFTER note;
ALTER TABLE bet_items ADD COLUMN adjusted_pay_rate DECIMAL(10,2) DEFAULT NULL AFTER amount;

-- ตรวจสอบ
DESCRIBE bets;
DESCRIBE bet_items;
