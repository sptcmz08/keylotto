-- Add bet_closed column for manual close control
ALTER TABLE `lottery_types` ADD COLUMN `bet_closed` TINYINT(1) DEFAULT 0 AFTER `is_active`;
