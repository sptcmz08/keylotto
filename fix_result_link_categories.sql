-- Fix: ตรวจสอบและเปิดหมวดหมู่ที่ใช้ใน result_links
-- ตาม populate_result_links.sql ใช้ category_id: 2, 3, 4, 5, 8
UPDATE lottery_categories SET is_active = 1 WHERE id IN (2, 3, 4, 5, 8);

-- ตรวจสอบผลลัพธ์
SELECT id, name, slug, is_active FROM lottery_categories ORDER BY sort_order;

-- ตรวจจำนวนลิงค์ต่อหมวด
SELECT lc.id, lc.name, lc.slug, lc.is_active, COUNT(rl.id) as link_count
FROM lottery_categories lc
LEFT JOIN result_links rl ON lc.id = rl.category_id
GROUP BY lc.id
ORDER BY lc.sort_order;
