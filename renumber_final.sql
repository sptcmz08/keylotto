-- =============================================
-- Renumber ID 1-62 ใหม่ เรียงตาม sort_order ภายในหมวด
-- รัน: mysql -u lotto -p lotto < renumber_final.sql
-- =============================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS _id_map;
CREATE TABLE _id_map (old_id INT NOT NULL, new_id INT NOT NULL, PRIMARY KEY (old_id));

SET @row = 0;
INSERT INTO _id_map (old_id, new_id)
SELECT id, (@row := @row + 1) AS new_id
FROM lottery_types
ORDER BY
    CASE category_id
        WHEN 2 THEN 1
        WHEN 4 THEN 2
        WHEN 5 THEN 3
        ELSE 9
    END,
    sort_order,
    name;

-- Pass 1: shift to high IDs
UPDATE lottery_types lt JOIN _id_map m ON lt.id = m.old_id SET lt.id = m.new_id + 10000;
UPDATE bets b JOIN _id_map m ON b.lottery_type_id = m.old_id SET b.lottery_type_id = m.new_id + 10000;
UPDATE pay_rates pr JOIN _id_map m ON pr.lottery_type_id = m.old_id SET pr.lottery_type_id = m.new_id + 10000;
UPDATE results r JOIN _id_map m ON r.lottery_type_id = m.old_id SET r.lottery_type_id = m.new_id + 10000;

-- Pass 2: shift back to real IDs
UPDATE lottery_types SET id = id - 10000 WHERE id >= 10000;
UPDATE bets SET lottery_type_id = lottery_type_id - 10000 WHERE lottery_type_id >= 10000;
UPDATE pay_rates SET lottery_type_id = lottery_type_id - 10000 WHERE lottery_type_id >= 10000;
UPDATE results SET lottery_type_id = lottery_type_id - 10000 WHERE lottery_type_id >= 10000;

ALTER TABLE lottery_types AUTO_INCREMENT = 63;
UPDATE lottery_types SET sort_order = id;

DROP TABLE IF EXISTS _id_map;
SET FOREIGN_KEY_CHECKS = 1;

-- ตรวจ
SELECT lt.id, lt.name, lc.name AS category
FROM lottery_types lt
JOIN lottery_categories lc ON lt.category_id = lc.id
ORDER BY lt.id;
