-- =============================================
-- Sync result_links.category_id ให้ตรงกับ lottery_types.category_id
-- =============================================
UPDATE result_links rl
JOIN lottery_types lt ON rl.name = lt.name
SET rl.category_id = lt.category_id
WHERE rl.category_id != lt.category_id;

-- ตรวจสอบผลลัพธ์
SELECT lc.name as category, COUNT(*) as total, GROUP_CONCAT(rl.name ORDER BY rl.sort_order SEPARATOR ', ') as lotteries
FROM result_links rl
JOIN lottery_categories lc ON rl.category_id = lc.id
GROUP BY lc.name
ORDER BY lc.id;
