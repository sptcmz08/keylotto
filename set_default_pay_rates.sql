-- =============================================
-- Default Pay Rates by Category
-- ทุกหวย: บาทละ 100 ยกเว้น หวยไทย: บาทละ 95
-- =============================================

-- ลบอัตราจ่ายเก่าทั้งหมดก่อน
DELETE FROM pay_rates;

-- หวยไทย (category_id=1): อัตราจ่าย บาทละ 95
INSERT INTO pay_rates (lottery_type_id, bet_type, rate_label, pay_rate, discount, min_bet, max_bet)
SELECT lt.id, b.bet_type, b.rate_label, b.pay_rate, b.discount, b.min_bet, b.max_bet
FROM lottery_types lt
CROSS JOIN (
    SELECT '3top' as bet_type, '3 ตัวบน' as rate_label, 750.00 as pay_rate, 10.00 as discount, 1 as min_bet, 500 as max_bet
    UNION ALL SELECT '3tod', '3 ตัวโต๊ด', 125.00, 10.00, 1, 500
    UNION ALL SELECT '2top', '2 ตัวบน', 95.00, 5.00, 1, 500
    UNION ALL SELECT '2bot', '2 ตัวล่าง', 95.00, 5.00, 1, 500
    UNION ALL SELECT 'run_top', 'วิ่งบน', 3.00, 12.00, 1, 5000
    UNION ALL SELECT 'run_bot', 'วิ่งล่าง', 4.00, 12.00, 1, 5000
) b
WHERE lt.category_id = 1;

-- หวยอื่นๆ ทั้งหมด (category_id != 1): อัตราจ่าย บาทละ 100
INSERT INTO pay_rates (lottery_type_id, bet_type, rate_label, pay_rate, discount, min_bet, max_bet)
SELECT lt.id, b.bet_type, b.rate_label, b.pay_rate, b.discount, b.min_bet, b.max_bet
FROM lottery_types lt
CROSS JOIN (
    SELECT '3top' as bet_type, '3 ตัวบน' as rate_label, 800.00 as pay_rate, 5.00 as discount, 1 as min_bet, 500 as max_bet
    UNION ALL SELECT '3tod', '3 ตัวโต๊ด', 125.00, 5.00, 1, 500
    UNION ALL SELECT '2top', '2 ตัวบน', 100.00, 0.00, 1, 500
    UNION ALL SELECT '2bot', '2 ตัวล่าง', 100.00, 0.00, 1, 500
    UNION ALL SELECT 'run_top', 'วิ่งบน', 3.00, 12.00, 1, 5000
    UNION ALL SELECT 'run_bot', 'วิ่งล่าง', 4.00, 12.00, 1, 5000
) b
WHERE lt.category_id != 1;
