-- อัปเดต ธกส ให้ใช้ URL ที่ถูกต้อง
UPDATE result_links 
SET result_url = 'https://www.baac.or.th/salak/content-lotto.php',
    result_label = 'ตรวจผลสลาก ธกส',
    scraper_url = 'https://www.baac.or.th/salak/content-lotto.php'
WHERE name = 'ธกส';

SELECT name, result_url, scraper_url FROM result_links WHERE name = 'ธกส';
