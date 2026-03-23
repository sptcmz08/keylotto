-- Add open_time column to lottery_types
ALTER TABLE `lottery_types` ADD COLUMN `open_time` TIME DEFAULT NULL AFTER `close_time`;

-- Set default open_time = 5 hours before close_time (reasonable default)
-- Admin can adjust specific lotteries via admin panel
UPDATE `lottery_types` SET `open_time` = SUBTIME(`close_time`, '05:00:00') WHERE `close_time` IS NOT NULL;
