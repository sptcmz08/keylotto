-- ลบ ดาวโจนส์อเมริกา (ซ้ำกับ หุ้นดาวโจนส์)
DELETE FROM result_links WHERE name = 'ดาวโจนส์อเมริกา';
DELETE FROM pay_rates WHERE lottery_type_id = (SELECT id FROM lottery_types WHERE name = 'ดาวโจนส์อเมริกา');
DELETE FROM lottery_types WHERE name = 'ดาวโจนส์อเมริกา';

-- ตรวจสอบ
SELECT name FROM lottery_types WHERE name LIKE '%ดาวโจนส์%';
SELECT name FROM result_links WHERE name LIKE '%ดาวโจนส์%';
