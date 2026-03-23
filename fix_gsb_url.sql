-- อัปเดต ออมสิน ให้ใช้ URL salak-1year-100
UPDATE result_links 
SET result_url = 'https://www.gsb.or.th/personal/resultsalak/?type=salak-1year-100',
    result_label = 'สลากออมสิน 1 ปี',
    scraper_url = 'https://psc.gsb.or.th/resultsalak/salak-1year-100/'
WHERE name = 'ออมสิน';

SELECT name, result_url, scraper_url FROM result_links WHERE name = 'ออมสิน';
