-- =============================================
-- ปิดหมวดหมู่ที่ไม่ใช้ (เหลือแค่ 5 หมวด)
-- =============================================
UPDATE lottery_categories SET is_active = 0 WHERE name IN ('หวยชุด', 'หวย One', 'หวยสากล');
UPDATE lottery_categories SET is_active = 1 WHERE name IN ('หวยไทย', 'หวยต่างประเทศ', 'หวยรายวัน', 'หวยหุ้น', 'หุ้น VIP');
