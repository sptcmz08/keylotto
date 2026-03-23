-- =============================================
-- ตั้งเวลาเปิด/ปิดรับ + ตารางออกผล มาตรฐาน
-- อ้างอิงจากเว็บหวยออนไลน์ทั่วไป
--
-- หลักการ:
--   open_time  = เปิดรับแทง (ปกติเปิดหลังผลออก ~5-10 นาที หรือ 06:00)
--   close_time = ปิดรับ (ก่อนผลออก 5-15 นาที)
--   result_time = เวลาผลออก
--   draw_schedule = ตารางออกผล:
--     'daily'               = ทุกวัน (ลาว, ฮานอย, VIP)
--     'mon,tue,wed,thu,fri' = จันทร์-ศุกร์ (หุ้นจริง)
--     'sun,mon,tue,wed,thu' = อาทิตย์-พฤหัส (หุ้นอียิปต์)
--     '1,16'                = วันที่ 1 และ 16 ของเดือน (หวยไทย)
-- =============================================

-- =============================================
-- 🇱🇦 ลาว — ออกทุกวัน (daily)
-- =============================================
UPDATE lottery_types SET open_time='00:01:00', close_time='05:30:00', result_time='06:00:00', draw_schedule='daily' WHERE name='ลาวประตูชัย';
UPDATE lottery_types SET open_time='06:05:00', close_time='06:30:00', result_time='07:00:00', draw_schedule='daily' WHERE name='ลาวสันติภาพ';
UPDATE lottery_types SET open_time='07:05:00', close_time='07:30:00', result_time='08:00:00', draw_schedule='daily' WHERE name='ประชาชนลาว';
UPDATE lottery_types SET open_time='08:05:00', close_time='08:15:00', result_time='08:30:00', draw_schedule='daily' WHERE name='ลาว EXTRA';
UPDATE lottery_types SET open_time='08:35:00', close_time='08:45:00', result_time='09:00:00', draw_schedule='daily' WHERE name='ลาวใต้';
UPDATE lottery_types SET open_time='09:05:00', close_time='10:15:00', result_time='10:30:00', draw_schedule='daily' WHERE name='ลาว TV';
UPDATE lottery_types SET open_time='10:35:00', close_time='13:15:00', result_time='13:30:00', draw_schedule='daily' WHERE name='ลาว HD';
UPDATE lottery_types SET open_time='13:35:00', close_time='15:15:00', result_time='15:30:00', draw_schedule='daily' WHERE name='ลาวสตาร์';
UPDATE lottery_types SET open_time='06:00:00', close_time='17:15:00', result_time='17:30:00', draw_schedule='daily' WHERE name='ลาวสามัคคี';
UPDATE lottery_types SET open_time='06:00:00', close_time='19:45:00', result_time='20:00:00', draw_schedule='daily' WHERE name='ลาวอาเซียน';
UPDATE lottery_types SET open_time='06:00:00', close_time='20:15:00', result_time='20:30:00', draw_schedule='daily' WHERE name='ลาวพัฒนา';
UPDATE lottery_types SET open_time='06:00:00', close_time='20:45:00', result_time='21:00:00', draw_schedule='daily' WHERE name='ลาว VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='21:15:00', result_time='21:30:00', draw_schedule='daily' WHERE name='ลาวสามัคคี VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='21:45:00', result_time='22:00:00', draw_schedule='daily' WHERE name='ลาวสตาร์ VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='23:15:00', result_time='23:30:00', draw_schedule='daily' WHERE name='ลาวกาชาด';

-- =============================================
-- 🇻🇳 ฮานอย — ออกทุกวัน (daily)
-- =============================================
UPDATE lottery_types SET open_time='06:00:00', close_time='09:15:00', result_time='09:30:00', draw_schedule='daily' WHERE name='ฮานอยอาเซียน';
UPDATE lottery_types SET open_time='06:00:00', close_time='11:15:00', result_time='11:30:00', draw_schedule='daily' WHERE name='ฮานอย HD';
UPDATE lottery_types SET open_time='06:00:00', close_time='12:15:00', result_time='12:30:00', draw_schedule='daily' WHERE name='ฮานอยสตาร์';
UPDATE lottery_types SET open_time='06:00:00', close_time='14:15:00', result_time='14:30:00', draw_schedule='daily' WHERE name='ฮานอย TV';
UPDATE lottery_types SET open_time='06:00:00', close_time='16:15:00', result_time='16:30:00', draw_schedule='daily' WHERE name='ฮานอยกาชาด';
UPDATE lottery_types SET open_time='06:00:00', close_time='17:15:00', result_time='17:30:00', draw_schedule='daily' WHERE name='ฮานอยพิเศษ';
UPDATE lottery_types SET open_time='06:00:00', close_time='17:15:00', result_time='17:30:00', draw_schedule='daily' WHERE name='ฮานอยสามัคคี';
UPDATE lottery_types SET open_time='06:00:00', close_time='18:05:00', result_time='18:30:00', draw_schedule='daily' WHERE name='ฮานอยปกติ';
UPDATE lottery_types SET open_time='06:00:00', close_time='18:05:00', result_time='18:30:00', draw_schedule='daily' WHERE name='ฮานอยตรุษจีน';
UPDATE lottery_types SET open_time='06:00:00', close_time='19:15:00', result_time='19:30:00', draw_schedule='daily' WHERE name='ฮานอย VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='19:15:00', result_time='19:30:00', draw_schedule='daily' WHERE name='ฮานอยพัฒนา';
UPDATE lottery_types SET open_time='06:00:00', close_time='22:15:00', result_time='22:30:00', draw_schedule='daily' WHERE name='ฮานอย EXTRA';

-- =============================================
-- 🎰 หวย VIP — ออกทุกวัน (daily)
-- =============================================
UPDATE lottery_types SET open_time='06:00:00', close_time='08:45:00', result_time='09:00:00', draw_schedule='daily' WHERE name='นิเคอิเช้า VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='09:45:00', result_time='10:00:00', draw_schedule='daily' WHERE name='จีนเช้า VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='10:15:00', result_time='10:30:00', draw_schedule='daily' WHERE name='ฮั่งเส็งเช้า VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='11:15:00', result_time='11:30:00', draw_schedule='daily' WHERE name='ไต้หวัน VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='12:15:00', result_time='12:30:00', draw_schedule='daily' WHERE name='เกาหลี VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='13:15:00', result_time='13:30:00', draw_schedule='daily' WHERE name='นิเคอิบ่าย VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='14:15:00', result_time='14:30:00', draw_schedule='daily' WHERE name='จีนบ่าย VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='15:15:00', result_time='15:30:00', draw_schedule='daily' WHERE name='ฮั่งเส็งบ่าย VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='16:45:00', result_time='17:00:00', draw_schedule='daily' WHERE name='สิงคโปร์ VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='21:45:00', result_time='22:00:00', draw_schedule='daily' WHERE name='อังกฤษ VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='22:45:00', result_time='23:00:00', draw_schedule='daily' WHERE name='เยอรมัน VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='23:45:00', result_time='00:00:00', draw_schedule='daily' WHERE name='รัสเซีย VIP';
UPDATE lottery_types SET open_time='06:00:00', close_time='00:15:00', result_time='00:30:00', draw_schedule='daily' WHERE name='ดาวโจนส์ VIP';

-- =============================================
-- 📈 หุ้นจริง — จันทร์-ศุกร์ (mon,tue,wed,thu,fri)
-- =============================================
UPDATE lottery_types SET open_time='06:00:00', close_time='08:45:00', result_time='09:05:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นนิเคอิเช้า';
UPDATE lottery_types SET open_time='06:00:00', close_time='09:45:00', result_time='10:05:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นจีนเช้า';
UPDATE lottery_types SET open_time='06:00:00', close_time='10:15:00', result_time='10:35:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นฮั่งเส็งเช้า';
UPDATE lottery_types SET open_time='06:00:00', close_time='11:15:00', result_time='11:35:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นไต้หวัน';
UPDATE lottery_types SET open_time='06:00:00', close_time='12:15:00', result_time='12:35:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นเกาหลี';
UPDATE lottery_types SET open_time='06:00:00', close_time='13:00:00', result_time='13:25:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นนิเคอิบ่าย';
UPDATE lottery_types SET open_time='06:00:00', close_time='14:00:00', result_time='14:25:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นจีนบ่าย';
UPDATE lottery_types SET open_time='06:00:00', close_time='15:00:00', result_time='15:25:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นฮั่งเส็งบ่าย';
UPDATE lottery_types SET open_time='06:00:00', close_time='16:15:00', result_time='16:35:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นสิงคโปร์';
UPDATE lottery_types SET open_time='06:00:00', close_time='16:15:00', result_time='16:35:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นไทย';
UPDATE lottery_types SET open_time='06:00:00', close_time='16:45:00', result_time='17:05:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นอินเดีย';
UPDATE lottery_types SET open_time='06:00:00', close_time='19:45:00', result_time='20:00:00', draw_schedule='sun,mon,tue,wed,thu' WHERE name='หุ้นอียิปต์';
UPDATE lottery_types SET open_time='06:00:00', close_time='22:45:00', result_time='23:00:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นอังกฤษ';
UPDATE lottery_types SET open_time='06:00:00', close_time='22:45:00', result_time='23:00:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นเยอรมัน';
UPDATE lottery_types SET open_time='06:00:00', close_time='22:45:00', result_time='23:00:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นรัสเซีย';
UPDATE lottery_types SET open_time='06:00:00', close_time='23:45:00', result_time='00:10:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='ดาวโจนส์อเมริกา';
UPDATE lottery_types SET open_time='06:00:00', close_time='01:00:00', result_time='01:30:00', draw_schedule='daily' WHERE name='ดาวโจนส์ STAR';
UPDATE lottery_types SET open_time='06:00:00', close_time='02:50:00', result_time='03:20:00', draw_schedule='mon,tue,wed,thu,fri' WHERE name='หุ้นดาวโจนส์';
UPDATE lottery_types SET open_time='06:00:00', close_time='14:45:00', result_time='15:00:00', draw_schedule='daily' WHERE name='หวย 12 ราศี';

-- =============================================
-- 🇹🇭 หวยไทย — วันที่ 1 และ 16 ของเดือน
-- =============================================
UPDATE lottery_types SET open_time='06:00:00', close_time='14:45:00', result_time='15:30:00', draw_schedule='1,16' WHERE name='รัฐบาลไทย';
UPDATE lottery_types SET open_time='06:00:00', close_time='08:30:00', result_time='09:00:00', draw_schedule='1,16' WHERE name='ออมสิน';
UPDATE lottery_types SET open_time='06:00:00', close_time='08:30:00', result_time='09:00:00', draw_schedule='1,16' WHERE name='ธกส';
