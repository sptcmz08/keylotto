-- อัปเดต ออมสิน ให้ชี้เว็บ GSB (สลาก 1 ปี)
UPDATE result_links 
SET result_url = 'https://www.gsb.or.th/personal/resultsalak/?type=salak-1year',
    result_label = 'สลากออมสิน 1 ปี',
    scraper_url = 'https://psc.gsb.or.th/resultsalak/salak-1year/'
WHERE name = 'ออมสิน';

-- อัปเดต ธกส
UPDATE result_links 
SET result_url = 'https://www.glo.or.th',
    result_label = 'www.glo.or.th',
    scraper_url = 'https://www.raakaadee.com/'
WHERE name = 'ธกส';

-- ตรวจสอบ
SELECT name, result_url, result_label, scraper_url FROM result_links WHERE name IN ('ออมสิน', 'ธกส');
