-- =============================================
-- Populate result_links with real lottery data
-- ดึง URL จาก scraper sources
-- รัน: mysql -u root -p lotto < populate_result_links.sql
-- =============================================

-- ล้างข้อมูลเก่า
DELETE FROM result_links;

-- Reset auto increment
ALTER TABLE result_links AUTO_INCREMENT = 1;

-- =============================================
-- หวยไทย (cat 2)
-- =============================================
INSERT INTO result_links (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, scraper_url, sort_order, is_active) VALUES
(2, 'รัฐบาลไทย', '🇹🇭', '15:00', '15:30', 'https://www.glo.or.th', 'www.glo.or.th', 'https://www.raakaadee.com/', 1, 1),
(2, 'ออมสิน', '🇹🇭', '15:00', '15:30', 'https://www.glo.or.th', 'www.glo.or.th', 'https://www.raakaadee.com/', 2, 1),
(2, 'ธกส', '🇹🇭', '15:00', '15:30', 'https://www.glo.or.th', 'www.glo.or.th', 'https://www.raakaadee.com/', 3, 1);

-- =============================================
-- หวยรายวัน (cat 4)
-- =============================================
INSERT INTO result_links (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, scraper_url, sort_order, is_active) VALUES
(4, 'ลาวพัฒนา', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวพัฒนา/', 'หวยลาวพัฒนา', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวพัฒนา/', 1, 1),
(4, 'หวย 12 ราศี', '🇹🇭', '15:00', '15:30', 'https://ponhuay24.com/app/lottoother', '12 ราศี', 'https://ponhuay24.com/app/lottoother', 2, 1);

-- =============================================
-- หวยต่างประเทศ - ฮานอย (cat 3)
-- =============================================
INSERT INTO result_links (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, scraper_url, sort_order, is_active) VALUES
(3, 'ฮานอยปกติ', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'หวยฮานอย', 'https://exphuay.com/backward/minhngoc', 1, 1),
(3, 'ฮานอย VIP', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอย/', 'ฮานอย VIP', 'https://exphuay.com/backward/mlnhngo', 2, 1),
(3, 'ฮานอยพิเศษ', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยพิเศษ/', 'ฮานอยพิเศษ', 'https://exphuay.com/backward/xsthm', 3, 1),
(3, 'ฮานอยสามัคคี', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'ฮานอยสามัคคี', 'https://exphuay.com/backward/xosounion', 4, 1),
(3, 'ฮานอยพัฒนา', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'ฮานอยพัฒนา', 'https://exphuay.com/backward/xosodevelop', 5, 1),
(3, 'ฮานอย EXTRA', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'ฮานอย EXTRA', 'https://exphuay.com/backward/xosoextra', 6, 1),
(3, 'ฮานอยกาชาด', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'ฮานอยกาชาด', 'https://exphuay.com/backward/xosoredcross', 7, 1),
(3, 'ฮานอยอาเซียน', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'ฮานอยอาเซียน', 'https://exphuay.com/backward/hanoiasean', 8, 1),
(3, 'ฮานอย HD', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'ฮานอย HD', 'https://exphuay.com/backward/xosohd', 9, 1),
(3, 'ฮานอยสตาร์', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'ฮานอยสตาร์', 'https://exphuay.com/backward/minhngocstar', 10, 1),
(3, 'ฮานอย TV', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/', 'ฮานอย TV', 'https://exphuay.com/backward/minhngoctv', 11, 1),
(3, 'ฮานอยตรุษจีน', '🇻🇳', '18:10', '18:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยตรุษจีน/', 'ฮานอยตรุษจีน', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยตรุษจีน/', 12, 1);

-- =============================================
-- หวยต่างประเทศ - ลาว (cat 3)
-- =============================================
INSERT INTO result_links (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, scraper_url, sort_order, is_active) VALUES
(3, 'ลาวประตูชัย', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวประตูชัย/', 'ลาวประตูชัย', 'https://exphuay.com/backward/laopatuxay', 20, 1),
(3, 'ลาวสันติภาพ', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวสันติภาพ/', 'ลาวสันติภาพ', 'https://exphuay.com/backward/laosantipap', 21, 1),
(3, 'ประชาชนลาว', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยประชาชนลาว/', 'ประชาชนลาว', 'https://exphuay.com/backward/laocitizen', 22, 1),
(3, 'ลาว EXTRA', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว-Extra/', 'ลาว EXTRA', 'https://exphuay.com/backward/laoextra', 23, 1),
(3, 'ลาว TV', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวทีวี/', 'ลาว TV', 'https://exphuay.com/backward/laotv', 24, 1),
(3, 'ลาว HD', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว/', 'ลาว HD', 'https://exphuay.com/backward/laoshd', 25, 1),
(3, 'ลาวสตาร์', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวสตาร์/', 'ลาวสตาร์', 'https://exphuay.com/backward/laostars', 26, 1),
(3, 'ลาวใต้', '🇱🇦', '20:20', '20:30', 'https://ponhuay24.com/app/lottolaos', 'ลาวใต้', 'https://ponhuay24.com/app/lottolaos', 27, 1),
(3, 'ลาวสามัคคี', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวสามัคคี/', 'ลาวสามัคคี', 'https://exphuay.com/backward/laounion', 28, 1),
(3, 'ลาวอาเซียน', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว/', 'ลาวอาเซียน', 'https://exphuay.com/backward/laosasean', 29, 1),
(3, 'ลาว VIP', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว-VIP/', 'ลาว VIP', 'https://exphuay.com/backward/laosvip', 30, 1),
(3, 'ลาวสามัคคี VIP', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว/', 'ลาวสามัคคี VIP', 'https://exphuay.com/backward/laounionvip', 31, 1),
(3, 'ลาวกาชาด', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวกาชาด/', 'ลาวกาชาด', 'https://exphuay.com/backward/laoredcross', 32, 1);

-- =============================================
-- หวยหุ้น (cat 5) — จันทร์-ศุกร์
-- =============================================
INSERT INTO result_links (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, scraper_url, sort_order, is_active) VALUES
(5, 'นิเคอิ - เช้า', '🇯🇵', '10:30', '12:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นนิเคอิ/', 'หุ้นนิเคอิ', 'https://exphuay.com/backward/nikkei-morning', 1, 1),
(5, 'หุ้นจีน - เช้า', '🇨🇳', '11:20', '11:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นจีน/', 'หุ้นจีน', 'https://exphuay.com/backward/szse-morning', 2, 1),
(5, 'ฮั่งเส็ง - เช้า', '🇭🇰', '11:50', '12:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นฮั่งเส็ง/', 'หุ้นฮั่งเส็ง', 'https://exphuay.com/backward/hsi-morning', 3, 1),
(5, 'หุ้นไต้หวัน', '🇹🇼', '13:20', '13:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นไต้หวัน/', 'หุ้นไต้หวัน', 'https://exphuay.com/backward/twse', 4, 1),
(5, 'หุ้นเกาหลี', '🇰🇷', '14:50', '15:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นเกาหลี/', 'หุ้นเกาหลี', 'https://exphuay.com/backward/ktop30', 5, 1),
(5, 'นิเคอิ - บ่าย', '🇯🇵', '14:20', '15:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นนิเคอิ/', 'หุ้นนิเคอิ', 'https://exphuay.com/backward/nikkei-afternoon', 6, 1),
(5, 'หุ้นจีน - บ่าย', '🇨🇳', '14:50', '15:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นจีน/', 'หุ้นจีน', 'https://exphuay.com/backward/szse-afternoon', 7, 1),
(5, 'ฮั่งเส็ง - บ่าย', '🇭🇰', '15:50', '16:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นฮั่งเส็ง/', 'หุ้นฮั่งเส็ง', 'https://exphuay.com/backward/hsi-afternoon', 8, 1),
(5, 'หุ้นสิงคโปร์', '🇸🇬', '16:40', '17:10', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นสิงคโปร์/', 'หุ้นสิงคโปร์', 'https://exphuay.com/backward/sgx', 9, 1),
(5, 'หุ้นไทย - เย็น', '🇹🇭', '16:20', '16:40', 'https://www.set.or.th/', 'หุ้นไทย SET', 'https://exphuay.com/backward/set', 10, 1),
(5, 'หุ้นอินเดีย', '🇮🇳', '16:00', '16:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นอินเดีย/', 'หุ้นอินเดีย', 'https://exphuay.com/backward/bsesn', 11, 1),
(5, 'หุ้นอียิปต์', '🇪🇬', '17:00', '17:10', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นอียิปต์/', 'หุ้นอียิปต์', 'https://exphuay.com/backward/egx30', 12, 1),
(5, 'หุ้นอังกฤษ', '🇬🇧', '22:00', '23:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นอังกฤษ/', 'หุ้นอังกฤษ', 'https://exphuay.com/backward/ftse100', 13, 1),
(5, 'หุ้นเยอรมัน', '🇩🇪', '22:00', '23:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นเยอรมัน/', 'หุ้นเยอรมัน', 'https://exphuay.com/backward/gdaxi', 14, 1),
(5, 'หุ้นรัสเซีย', '🇷🇺', '23:30', '00:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นรัสเซีย/', 'หุ้นรัสเซีย', 'https://exphuay.com/backward/moexbc', 15, 1),
(5, 'หุ้นดาวโจนส์', '🇺🇸', '04:00', '05:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นดาวโจนส์/', 'หุ้นดาวโจนส์', 'https://exphuay.com/backward/dji', 16, 1),
(5, 'ดาวโจนส์ STAR', '🇺🇸', '04:00', '05:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นดาวโจนส์สตาร์/', 'ดาวโจนส์ STAR', 'https://exphuay.com/backward/dowjonestar', 17, 1),
(5, 'ดาวโจนส์อเมริกา', '🇺🇸', '04:00', '05:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นดาวโจนส์/', 'ดาวโจนส์อเมริกา', 'https://exphuay.com/backward/dji', 18, 1);

-- =============================================
-- หุ้น VIP (cat 8)
-- =============================================
INSERT INTO result_links (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, scraper_url, sort_order, is_active) VALUES
(8, 'นิเคอิเช้า VIP', '🇯🇵', '10:30', '12:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/nikkei-vip-morning', 1, 1),
(8, 'จีนเช้า VIP', '🇨🇳', '11:20', '11:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/szse-vip-morning', 2, 1),
(8, 'ฮั่งเส็งเช้า VIP', '🇭🇰', '11:50', '12:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/hsi-vip-morning', 3, 1),
(8, 'ไต้หวัน VIP', '🇹🇼', '13:20', '13:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/twse-vip', 4, 1),
(8, 'เกาหลี VIP', '🇰🇷', '14:50', '15:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/ktop30-vip', 5, 1),
(8, 'นิเคอิบ่าย VIP', '🇯🇵', '14:20', '15:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/nikkei-vip-afternoon', 6, 1),
(8, 'จีนบ่าย VIP', '🇨🇳', '14:50', '15:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/szse-vip-afternoon', 7, 1),
(8, 'ฮั่งเส็งบ่าย VIP', '🇭🇰', '15:50', '16:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/hsi-vip-afternoon', 8, 1),
(8, 'สิงคโปร์ VIP', '🇸🇬', '16:40', '17:10', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/sgx-vip', 9, 1),
(8, 'อังกฤษ VIP', '🇬🇧', '22:00', '23:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/england-vip', 10, 1),
(8, 'เยอรมัน VIP', '🇩🇪', '22:00', '23:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/germany-vip', 11, 1),
(8, 'รัสเซีย VIP', '🇷🇺', '23:30', '00:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/russia-vip', 12, 1),
(8, 'ดาวโจนส์ VIP', '🇺🇸', '04:00', '05:00', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/dowjones-vip', 13, 1),
(8, 'ลาวสตาร์ VIP', '🇱🇦', '20:20', '20:30', 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/', 'หุ้น VIP', 'https://exphuay.com/backward/laostarsvip', 14, 1);

-- =============================================
-- ตรวจสอบผลลัพธ์
-- =============================================
SELECT COUNT(*) as total_links FROM result_links;
SELECT rl.name, lc.name as category, rl.scraper_url
FROM result_links rl
JOIN lottery_categories lc ON rl.category_id = lc.id
ORDER BY lc.sort_order, rl.sort_order;
