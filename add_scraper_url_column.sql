-- Add scraper source URL column to result_links
ALTER TABLE result_links ADD COLUMN scraper_url VARCHAR(500) DEFAULT NULL COMMENT 'URL แหล่งที่มา scraper' AFTER result_label;
