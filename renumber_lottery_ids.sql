-- =============================================
-- Renumber lottery_types ID 1-62 + fix categories
-- รัน: mysql -u lotto -p lotto < renumber_lottery_ids.sql
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- Step 1: สร้าง temp table เก็บ ID mapping
-- =============================================
DROP TABLE IF EXISTS _id_map;
CREATE TABLE _id_map (
    old_id INT NOT NULL,
    new_id INT NOT NULL,
    PRIMARY KEY (old_id)
);

-- กำหนด new_id ตามลำดับจริง
-- เรียงตาม: หวยไทย → หวยต่างประเทศ(ฮานอย→ลาว) → หวยหุ้น → หุ้น VIP → หวยรายวัน
SET @row = 0;
INSERT INTO _id_map (old_id, new_id)
SELECT id, (@row := @row + 1) AS new_id
FROM lottery_types
ORDER BY
    CASE category_id
        WHEN 2 THEN 1  -- หวยไทย
        WHEN 3 THEN 2  -- หวยต่างประเทศ
        WHEN 5 THEN 3  -- หวยหุ้น
        WHEN 8 THEN 4  -- หุ้น VIP
        WHEN 4 THEN 5  -- หวยรายวัน
        ELSE 9
    END,
    sort_order,
    name;

-- =============================================
-- Step 2: ย้าย ID เป็นค่าสูงๆ ก่อน (ป้องกัน conflict)
-- =============================================
UPDATE lottery_types lt
JOIN _id_map m ON lt.id = m.old_id
SET lt.id = m.new_id + 10000;

UPDATE bets b
JOIN _id_map m ON b.lottery_type_id = m.old_id
SET b.lottery_type_id = m.new_id + 10000;

UPDATE pay_rates pr
JOIN _id_map m ON pr.lottery_type_id = m.old_id
SET pr.lottery_type_id = m.new_id + 10000;

UPDATE results r
JOIN _id_map m ON r.lottery_type_id = m.old_id
SET r.lottery_type_id = m.new_id + 10000;

-- =============================================
-- Step 3: ย้ายกลับเป็น new_id จริง
-- =============================================
UPDATE lottery_types SET id = id - 10000 WHERE id >= 10000;
UPDATE bets SET lottery_type_id = lottery_type_id - 10000 WHERE lottery_type_id >= 10000;
UPDATE pay_rates SET lottery_type_id = lottery_type_id - 10000 WHERE lottery_type_id >= 10000;
UPDATE results SET lottery_type_id = lottery_type_id - 10000 WHERE lottery_type_id >= 10000;

-- Reset auto_increment
ALTER TABLE lottery_types AUTO_INCREMENT = 63;

-- =============================================
-- Step 4: อัปเดต sort_order ให้ตรงกับ ID ใหม่
-- =============================================
UPDATE lottery_types SET sort_order = id;

-- =============================================
-- Cleanup
-- =============================================
DROP TABLE IF EXISTS _id_map;
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- ตรวจสอบ
-- =============================================
SELECT lt.id, lt.name, lc.name AS category
FROM lottery_types lt
JOIN lottery_categories lc ON lt.category_id = lc.id
ORDER BY lt.id;
