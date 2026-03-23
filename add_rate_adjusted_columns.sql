-- Add auto-rate adjustment columns
ALTER TABLE bets ADD COLUMN IF NOT EXISTS rate_adjusted TINYINT(1) DEFAULT 0;
ALTER TABLE bet_items ADD COLUMN IF NOT EXISTS adjusted_pay_rate DECIMAL(10,2) DEFAULT NULL;
