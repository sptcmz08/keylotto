-- =============================================
-- แก้หมวดหมู่อิงตามกำหนดการออก
-- หวยรายวัน = ออกทุกวัน (ลาว+ฮานอย+หุ้นVIP = 39 ตัว)
-- หวยหุ้น = จันทร์-ศุกร์ (หุ้นปกติ+ลาวพัฒนา+12ราศี = 20 ตัว)
-- หวยไทย = งวด 1,16 (3 ตัว)
-- รัน: mysql -u lotto -p lotto < fix_schedule_categories.sql
-- =============================================

-- 1. ย้ายหวยต่างประเทศ (cat 3) → หวยรายวัน (cat 4)
UPDATE lottery_types SET category_id = 4 WHERE category_id = 3;

-- 2. ย้าย หุ้น VIP (cat 8) → หวยรายวัน (cat 4)
UPDATE lottery_types SET category_id = 4 WHERE category_id = 8;

-- 3. ย้าย ลาวพัฒนา + หวย 12 ราศี → หวยหุ้น (cat 5) เพราะเป็นจันทร์-ศุกร์
UPDATE lottery_types SET category_id = 5 WHERE name IN ('ลาวพัฒนา', 'หวย 12 ราศี');

-- 4. ปิดหมวดที่ว่าง
UPDATE lottery_categories SET is_active = 0 WHERE id IN (3, 8);

-- 5. ตรวจสอบ
SELECT lc.name AS category, COUNT(*) AS count
FROM lottery_types lt
JOIN lottery_categories lc ON lt.category_id = lc.id
GROUP BY lc.name ORDER BY lc.sort_order;

SELECT lt.id, lt.name, lc.name AS category
FROM lottery_types lt
JOIN lottery_categories lc ON lt.category_id = lc.id
ORDER BY lt.id;
