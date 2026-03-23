-- อัปเดต scraper_url ให้ชี้ไปที่เว็บทางการ
UPDATE result_links 
SET scraper_url = 'https://psc.gsb.or.th/resultsalak/salak-1year/'
WHERE name = 'ออมสิน';

UPDATE result_links 
SET scraper_url = 'https://www.baac.or.th/savinglottery/check_manual.php'
WHERE name = 'ธกส';

-- ลบ mapping gsb/baac ออกจาก raakaadee (ไม่ดึงจาก raakaadee อีกต่อไป)
-- หมายเหตุ: ต้องแก้ scrape_raakaadee.py ด้วย (ลบ 'ออมสิน' และ 'ธกส' ออก)

SELECT name, result_url, scraper_url FROM result_links WHERE name IN ('ออมสิน', 'ธกส');
