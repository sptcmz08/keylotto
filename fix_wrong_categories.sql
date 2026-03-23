-- =============================================
-- แก้หมวดหมู่ผิด + ลบ duplicate
-- รัน: mysql -u lotto -p lotto < fix_wrong_categories.sql
-- =============================================

-- 1. ลาวกาชาด ซ้ำ 2 ตัว → ลบตัวที่อยู่ในหวยรายวัน (cat 4) ออก เหลือแค่ตัวที่อยู่ในหวยต่างประเทศ
DELETE FROM lottery_types WHERE name = 'ลาวกาชาด' AND category_id = 4;

-- 2. ลาว 4 ตัวอยู่ผิดหมวด (หวยรายวัน → หวยต่างประเทศ)
UPDATE lottery_types SET category_id = 3 WHERE name IN ('ลาวประตูชัย', 'ลาวสันติภาพ', 'ประชาชนลาว') AND category_id = 4;

-- 3. ลาว Extra → rename + ย้ายเข้าหวยต่างประเทศ
UPDATE lottery_types SET name = 'ลาว EXTRA', category_id = 3 WHERE name IN ('ลาว Extra', 'ลาว EXTRA') AND category_id = 4;

-- 4. เยอรมัน/รัสเซีย/ดาวโจนส์ VIP อยู่ผิดหมวด (หวยรายวัน → หุ้น VIP)
UPDATE lottery_types SET category_id = 8 WHERE name IN ('เยอรมัน VIP', 'รัสเซีย VIP', 'ดาวโจนส์ VIP') AND category_id = 4;

-- 5. ดาวโจนส์ STAR อยู่ผิดหมวด (หวยรายวัน → หวยหุ้น)
UPDATE lottery_types SET category_id = 5 WHERE name = 'ดาวโจนส์ STAR' AND category_id = 4;

-- 6. ดาวโจนส์อเมริกา อยู่ผิดหมวด (หวยต่างประเทศ → หวยหุ้น)
UPDATE lottery_types SET category_id = 5 WHERE name = 'ดาวโจนส์อเมริกา' AND category_id = 3;

-- 7. ลาวพัฒนา ย้ายเข้าหวยรายวัน (cat 4)
UPDATE lottery_types SET category_id = 4 WHERE name = 'ลาวพัฒนา' AND category_id = 3;

-- =============================================
-- ตรวจสอบผลลัพธ์
-- =============================================
SELECT COUNT(*) AS total FROM lottery_types;

SELECT lc.name AS category, GROUP_CONCAT(lt.name ORDER BY lt.sort_order SEPARATOR ', ') AS lotteries, COUNT(*) AS count
FROM lottery_types lt
JOIN lottery_categories lc ON lt.category_id = lc.id
GROUP BY lc.name
ORDER BY lc.sort_order;
