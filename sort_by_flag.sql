-- =============================================
-- เรียงลำดับ sort_order ตามธง (flag) ภายในแต่ละหมวด
-- รัน: mysql -u lotto -p lotto < sort_by_flag.sql
-- =============================================

-- หวยรายวัน (cat 4): เรียง ลาว → ฮานอย → JP → CN → HK → TW → KR → SG → GB → DE → RU → US
SET @row = 0;
UPDATE lottery_types SET sort_order = (@row := @row + 1)
WHERE category_id = 4
ORDER BY
    CASE
        WHEN name LIKE 'ลาว%' AND name NOT LIKE '%VIP' THEN 1
        WHEN name LIKE 'ฮานอย%' THEN 2
        WHEN name LIKE 'นิเคอิ%VIP' THEN 3
        WHEN name LIKE 'จีน%VIP' THEN 4
        WHEN name LIKE 'ฮั่งเส็ง%VIP' THEN 5
        WHEN name LIKE 'ไต้หวัน VIP' THEN 6
        WHEN name LIKE 'เกาหลี VIP' THEN 7
        WHEN name LIKE 'สิงคโปร์ VIP' THEN 8
        WHEN name LIKE 'อังกฤษ VIP' THEN 9
        WHEN name LIKE 'เยอรมัน VIP' THEN 10
        WHEN name LIKE 'รัสเซีย VIP' THEN 11
        WHEN name LIKE 'ดาวโจนส์ VIP' THEN 12
        WHEN name LIKE 'ลาวสตาร์ VIP' THEN 13
        ELSE 99
    END,
    name;

-- หวยหุ้น (cat 5): เรียง JP → CN → HK → TW → KR → SG → TH → IN → EG → ลาว → 12ราศี → GB → DE → RU → US
SET @row = 0;
UPDATE lottery_types SET sort_order = (@row := @row + 1)
WHERE category_id = 5
ORDER BY
    CASE
        WHEN name LIKE 'นิเคอิ%' THEN 1
        WHEN name LIKE 'หุ้นจีน%' THEN 2
        WHEN name LIKE 'ฮั่งเส็ง%' THEN 3
        WHEN name LIKE 'หุ้นไต้หวัน%' THEN 4
        WHEN name LIKE 'หุ้นเกาหลี%' THEN 5
        WHEN name LIKE 'หุ้นสิงคโปร์%' THEN 6
        WHEN name LIKE 'หุ้นไทย%' THEN 7
        WHEN name LIKE 'หุ้นอินเดีย%' THEN 8
        WHEN name LIKE 'หุ้นอียิปต์%' THEN 9
        WHEN name = 'ลาวพัฒนา' THEN 10
        WHEN name = 'หวย 12 ราศี' THEN 11
        WHEN name LIKE 'หุ้นอังกฤษ%' THEN 12
        WHEN name LIKE 'หุ้นเยอรมัน%' THEN 13
        WHEN name LIKE 'หุ้นรัสเซีย%' THEN 14
        WHEN name LIKE 'ดาวโจนส์%' OR name LIKE 'หุ้นดาวโจนส์%' THEN 15
        ELSE 99
    END,
    name;

-- หวยไทย (cat 2)
UPDATE lottery_types SET sort_order = 1 WHERE name = 'รัฐบาลไทย';
UPDATE lottery_types SET sort_order = 2 WHERE name = 'ออมสิน';
UPDATE lottery_types SET sort_order = 3 WHERE name = 'ธกส';

-- ตรวจสอบ
SELECT lt.id, lt.sort_order, lt.name, lt.flag_emoji, lc.name AS category
FROM lottery_types lt
JOIN lottery_categories lc ON lt.category_id = lc.id
ORDER BY lc.sort_order, lt.sort_order;
