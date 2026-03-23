-- Add over-limit columns to pay_rates table (per-lottery per-type)
ALTER TABLE pay_rates
    ADD COLUMN over_threshold INT NOT NULL DEFAULT 0 COMMENT 'จำนวนรายการที่เกินแล้วลดอัตราจ่าย (0=ไม่จำกัด)' AFTER max_per_number,
    ADD COLUMN over_pay_rate DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'อัตราจ่ายเมื่อเกิน threshold (0=ใช้อัตราปกติ)' AFTER over_threshold;
