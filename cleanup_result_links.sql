-- ลบ result_links ที่ไม่มีใน lottery_types
DELETE FROM result_links 
WHERE name NOT IN (SELECT name FROM lottery_types WHERE is_active = 1);

-- ตรวจสอบ: หวยที่เหลืออยู่
SELECT rl.name, lc.name as category
FROM result_links rl
JOIN lottery_categories lc ON rl.category_id = lc.id
ORDER BY lc.sort_order, rl.sort_order;

-- ตรวจสอบ: หวยที่มีใน lottery_types แต่ไม่มีใน result_links
SELECT lt.name as 'หวยที่ขาดใน result_links', lc.name as category
FROM lottery_types lt
JOIN lottery_categories lc ON lt.category_id = lc.id
WHERE lt.is_active = 1 AND lt.name NOT IN (SELECT name FROM result_links)
ORDER BY lc.sort_order, lt.sort_order;
