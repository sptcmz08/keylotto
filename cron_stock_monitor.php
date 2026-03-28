<?php
/**
 * =============================================
 * Stock Lottery Monitor — ดึงผลหวยหุ้นแบบ Targeted
 * =============================================
 * เมื่อถึง result_time ของหวยตัวใด → เริ่มดึงผลทุก 1 นาที
 * จนกว่าจะเจอ หรือเกิน 2 ชั่วโมง → งดออกผล + ยกเลิกโพย
 *
 * Crontab: * * * * * php /path/to/cron_stock_monitor.php >> /var/log/stock_monitor.log 2>&1
 *
 * ทำงานแยกจาก cron_scrape.php (smart mode)
 * - smart mode: ดึงผลรวมจาก ExpHuay/Raakaadee (หน้ารวม)
 * - monitor: ดึงเฉพาะหวยที่ "ถึงเวลาออกผลแล้วแต่ยังไม่มีผล"
 *            โดยเข้าหน้าผลของหวยตัวนั้นโดยตรง
 */

require_once __DIR__ . '/config.php';

// Include cron_scrape เพื่อใช้ processResults, logScrape, etc.
// Guard: cron_scrape.php ไม่รัน main code เมื่อถูก require
require_once __DIR__ . '/cron_scrape.php';

date_default_timezone_set('Asia/Bangkok');

$TIMEOUT_MINUTES = 120; // 2 ชม. → งดออกผล
$now = time();
$today = date('Y-m-d', $now - 4 * 3600);
$todayReal = date('Y-m-d');

// =============================================
// ExpHuay slug mapping: lottery_types.name → exphuay slug
// =============================================
$NAME_TO_EXPHUAY_SLUG = [];
// Reverse map: Key slug → ExpHuay slug
$KEY_TO_EXPHUAY = [
    'dowjones' => 'dji', 'nikkei-morning' => 'nikkei-morning', 'nikkei-afternoon' => 'nikkei-afternoon',
    'china-morning' => 'szse-morning', 'china-afternoon' => 'szse-afternoon',
    'hangseng-morning' => 'hsi-morning', 'hangseng-afternoon' => 'hsi-afternoon',
    'taiwan' => 'twse', 'korea' => 'ktop30', 'singapore' => 'sgx',
    'india' => 'bsesn', 'egypt' => 'egx30', 'germany' => 'gdaxi',
    'russia' => 'moexbc', 'uk' => 'ftse100', 'thai-stock' => 'set',
    'dowjones-vip' => 'dowjones-vip', 'dowjones-star' => 'dowjonestar',
    'nikkei-morning-vip' => 'nikkei-vip-morning', 'nikkei-afternoon-vip' => 'nikkei-vip-afternoon',
    'china-morning-vip' => 'szse-vip-morning', 'china-afternoon-vip' => 'szse-vip-afternoon',
    'hangseng-morning-vip' => 'hsi-vip-morning', 'hangseng-afternoon-vip' => 'hsi-vip-afternoon',
    'taiwan-vip' => 'twse-vip', 'korea-vip' => 'ktop30-vip', 'singapore-vip' => 'sgx-vip',
    'uk-vip' => 'england-vip', 'germany-vip' => 'germany-vip', 'russia-vip' => 'russia-vip',
    'hanoi' => 'minhngoc', 'hanoi-special' => 'xsthm', 'hanoi-vip' => 'mlnhngo',
    'hanoi-redcross' => 'xosoredcross', 'hanoi-asean' => 'hanoiasean',
    'hanoi-hd' => 'xosohd', 'hanoi-tv' => 'minhngoctv', 'hanoi-star' => 'minhngocstar',
    'hanoi-samakki' => 'xosounion', 'hanoi-pattana' => 'xosodevelop', 'hanoi-extra' => 'xosoextra',
    'lao-vip' => 'laosvip', 'lao-star' => 'laostars', 'lao-star-vip' => 'laostarsvip',
    'lao-samakki' => 'laounion', 'lao-samakki-vip' => 'laounionvip',
    'lao-pratuchai' => 'laopatuxay', 'lao-santiphap' => 'laosantipap',
    'lao-prachachon' => 'laocitizen', 'lao-extra' => 'laoextra',
    'lao-tv' => 'laotv', 'lao-hd' => 'laoshd', 'lao-asean' => 'laosasean',
    'lao-redcross' => 'laoredcross', 'lao-pattana' => 'lao-pattana',
];

// Build Name → ExpHuay slug map (via $SLUG_TO_LOTTERY_NAME)
foreach ($SLUG_TO_LOTTERY_NAME as $keySlug => $thaiName) {
    if (isset($KEY_TO_EXPHUAY[$keySlug])) {
        $NAME_TO_EXPHUAY_SLUG[$thaiName] = $KEY_TO_EXPHUAY[$keySlug];
    }
}

// =============================================
// 1. หาหวยที่ถึงเวลาออกผลแล้ว แต่ยังไม่มีผล
// =============================================
$stmt = $pdo->query("
    SELECT lt.id, lt.name, lt.result_time, lt.close_time, lt.open_time, lt.draw_schedule
    FROM lottery_types lt
    WHERE lt.is_active = 1
    AND lt.result_time IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM results r 
        WHERE r.lottery_type_id = lt.id 
        AND (r.draw_date = '{$today}' OR r.draw_date = '{$todayReal}')
    )
    ORDER BY lt.result_time ASC
");

$dueLotteries = [];
$timedOutLotteries = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $lt) {
    // เช็คว่าวันนี้เป็นวันออกผลไหม
    $expectedDate = getCurrentDrawDate($lt['draw_schedule'] ?? 'daily');
    if ($expectedDate !== $today && $expectedDate !== $todayReal) continue;
    
    // คำนวณ result_time timestamp
    $drawDate = $expectedDate;
    $resultTs = strtotime($drawDate . ' ' . $lt['result_time']);
    
    // หวยข้ามเที่ยงคืน: result_time < open_time → result เป็นของวันถัดไป
    $openHour = intval(substr($lt['open_time'] ?? '06:00:00', 0, 2));
    $resultHour = intval(substr($lt['result_time'], 0, 2));
    if ($resultHour < $openHour && $resultHour < 6) {
        $resultTs = strtotime(date('Y-m-d', strtotime($drawDate . ' +1 day')) . ' ' . $lt['result_time']);
    }
    
    // ยังไม่ถึงเวลาออกผล → ข้าม
    if ($now < $resultTs) continue;
    
    $elapsedMin = round(($now - $resultTs) / 60);
    
    if ($elapsedMin > $TIMEOUT_MINUTES) {
        $timedOutLotteries[] = array_merge($lt, ['elapsed' => $elapsedMin, 'draw_date' => $drawDate]);
    } else {
        $dueLotteries[] = array_merge($lt, ['elapsed' => $elapsedMin, 'draw_date' => $drawDate]);
    }
}

// =============================================
// 2. หวยที่เกิน 2 ชม. → งดออกผล + ยกเลิกโพย
// =============================================
foreach ($timedOutLotteries as $lt) {
    // บันทึก log ว่างดออกผล (บันทึกแค่ครั้งเดียวต่อวัน)
    $logCheck = $pdo->prepare("
        SELECT COUNT(*) FROM scraper_logs 
        WHERE lottery_name = ? AND status = 'no_draw' AND draw_date = ?
    ");
    $logCheck->execute([$lt['name'], $lt['draw_date']]);
    
    if ((int)$logCheck->fetchColumn() === 0) {
        logScrape($pdo, $lt['name'], 'monitor', 'no_draw', 
            "เกิน {$TIMEOUT_MINUTES} นาที ({$lt['elapsed']} min) → งดออกผล", $lt['draw_date']);
        
        // ยกเลิกโพย pending
        $cancelStmt = $pdo->prepare("
            UPDATE bets SET status = 'cancelled', win_amount = 0,
            cancel_approved_by = 'auto_timeout', cancel_approved_at = NOW()
            WHERE lottery_type_id = ? AND (draw_date = ? OR draw_date = ?) AND status = 'pending'
        ");
        $cancelStmt->execute([$lt['id'], $today, $todayReal]);
        $cancelled = $cancelStmt->rowCount();
        
        if ($cancelled > 0) {
            echo "[Monitor] ⏰ {$lt['name']}: งดออกผล (รอ {$lt['elapsed']} นาที) — ยกเลิก {$cancelled} โพย\n";
        } else {
            echo "[Monitor] ⏰ {$lt['name']}: งดออกผล (รอ {$lt['elapsed']} นาที)\n";
        }
    }
}

// =============================================
// 3. หวยที่ยังอยู่ใน window → ดึงผลจาก ExpHuay
// =============================================
if (empty($dueLotteries)) {
    exit(0); // ไม่มีหวยรอผล
}

echo "[Monitor] " . date('H:i:s') . " — รอผล " . count($dueLotteries) . " หวย:\n";
foreach ($dueLotteries as $lt) {
    echo "  • {$lt['name']} (ออก {$lt['result_time']}, รอมา {$lt['elapsed']} นาที)\n";
}

// =============================================
// 3a. ดึงจาก ExpHuay /result page (1 request ได้ทุกตัว)
// =============================================
echo "\n[Monitor] 🌐 ดึง ExpHuay /result page...\n";
$stderrFile = tempnam(sys_get_temp_dir(), 'monitor_');
$output = [];
$exitCode = 0;
exec("{$PYTHON_PATH} \"{$SCRIPT_DIR}/scrape_exphuay.py\" --date={$today} 2>{$stderrFile}", $output, $exitCode);
@unlink($stderrFile);

$json = implode("\n", $output);
$data = json_decode($json, true);
$foundFromResult = 0;

if ($data && !empty($data['success'])) {
    $results = $data['results'] ?? [];
    echo "[Monitor] ExpHuay /result: " . count($results) . " ผลพบ\n";
    $stats = processResults($pdo, $results, 'monitor');
    $foundFromResult = $stats['success'];
    if ($foundFromResult > 0) {
        echo "[Monitor] ✅ บันทึก {$foundFromResult} ผลใหม่จาก /result\n";
    }
}

// =============================================
// 3b. สำหรับหวยที่ยังไม่เจอ → ลองเข้าหน้าผลเฉพาะตัว
//     เช่น exphuay.com/result/gdaxi (หน้าเฉพาะหุ้นเยอรมัน)
// =============================================

// เช็คว่ายังมีหวยไหนไม่เจอจาก /result page
$stillMissingStmt = $pdo->prepare("
    SELECT lt.id, lt.name
    FROM lottery_types lt
    WHERE lt.id IN (" . implode(',', array_column($dueLotteries, 'id')) . ")
    AND NOT EXISTS (
        SELECT 1 FROM results r 
        WHERE r.lottery_type_id = lt.id 
        AND (r.draw_date = ? OR r.draw_date = ?)
    )
");
$stillMissingStmt->execute([$today, $todayReal]);
$stillMissing = $stillMissingStmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($stillMissing)) {
    echo "\n[Monitor] 🔍 ยังขาด " . count($stillMissing) . " ตัว → ลองเข้าหน้าเฉพาะ:\n";
    
    foreach ($stillMissing as $missing) {
        $expSlug = $NAME_TO_EXPHUAY_SLUG[$missing['name']] ?? null;
        if (!$expSlug) {
            echo "  ⚠️ {$missing['name']}: ไม่มี ExpHuay slug → ข้าม\n";
            continue;
        }
        
        echo "  🌐 {$missing['name']} → exphuay.com/result/{$expSlug}...\n";
        
        // ดึงหน้าผลเฉพาะตัว
        $pageUrl = "https://exphuay.com/result/{$expSlug}";
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0\r\n" .
                            "Accept: text/html\r\n",
            ]
        ]);
        $html = @file_get_contents($pageUrl, false, $ctx);
        
        if (!$html || strlen($html) < 100) {
            echo "  ❌ ไม่สามารถดึงหน้าได้\n";
            continue;
        }
        
        // Parse: หาวันที่วันนี้ + ตัวเลข 3 ตัวบน / 2 ตัวล่าง
        // ExpHuay /result/{slug} แสดงผลล่าสุดพร้อมวันที่
        $threeTop = null;
        $twoBot = null;
        $resultDate = null;
        
        // หาวันที่ในหน้า — format: "วันที่ DD เดือน YYYY" (Thai)
        $thaiMonths = [
            'มกราคม'=>1,'กุมภาพันธ์'=>2,'มีนาคม'=>3,'เมษายน'=>4,'พฤษภาคม'=>5,'มิถุนายน'=>6,
            'กรกฎาคม'=>7,'สิงหาคม'=>8,'กันยายน'=>9,'ตุลาคม'=>10,'พฤศจิกายน'=>11,'ธันวาคม'=>12
        ];
        foreach ($thaiMonths as $mName => $mNum) {
            if (preg_match('/(\d{1,2})\s+' . preg_quote($mName) . '\s+(\d{4})/', $html, $dm)) {
                $day = intval($dm[1]);
                $year = intval($dm[2]);
                if ($year > 2500) $year -= 543;
                $resultDate = sprintf('%04d-%02d-%02d', $year, $mNum, $day);
                break;
            }
        }
        
        // เช็คว่าเป็นผลของวันนี้ไหม
        if ($resultDate && $resultDate !== $today && $resultDate !== $todayReal) {
            echo "  ⏭️ ผลเป็นของ {$resultDate} (ไม่ใช่วันนี้) → ยังไม่อัพเดท\n";
            continue;
        }
        
        // หาเลข 3 ตัวบน / 2 ตัวล่าง จาก HTML
        // Pattern: ตัวเลข 3 หลัก และ 2 หลัก ใน tag
        if (preg_match_all('/>\s*(\d{3})\s*</', $html, $threeMatches)) {
            $threeTop = $threeMatches[1][0] ?? null;
        }
        if (preg_match_all('/>\s*(\d{2})\s*</', $html, $twoMatches)) {
            // เอา 2 ตัวล่าง (ไม่ใช่ 2 ตัวบน)
            // ปกติ 2 ตัวล่างจะอยู่หลัง 3 ตัวบน
            foreach ($twoMatches[1] as $candidate) {
                if ($threeTop && substr($threeTop, -2) !== $candidate) {
                    $twoBot = $candidate;
                    break;
                }
            }
            if (!$twoBot && !empty($twoMatches[1])) {
                $twoBot = end($twoMatches[1]);
            }
        }
        
        if ($threeTop && $twoBot) {
            echo "  ✅ พบผล: {$threeTop}/{$twoBot} ({$resultDate})\n";
            
            // Map กลับเป็น key slug
            $keySlug = null;
            foreach ($KEY_TO_EXPHUAY as $ks => $es) {
                if ($es === $expSlug) { $keySlug = $ks; break; }
            }
            
            if ($keySlug) {
                $singleResult = [[
                    'slug' => $keySlug,
                    'three_top' => $threeTop,
                    'two_top' => substr($threeTop, -2),
                    'two_bottom' => $twoBot,
                    'draw_date' => $resultDate ?: $today,
                    'source' => 'exphuay.com/result/' . $expSlug,
                ]];
                $sStats = processResults($pdo, $singleResult, 'monitor-single');
                if ($sStats['success'] > 0) {
                    echo "  💾 บันทึกแล้ว!\n";
                }
            }
        } else {
            echo "  ⏳ ยังไม่มีผล → รอรอบถัดไป\n";
        }
    }
}

echo "\n[Monitor] " . date('H:i:s') . " Done ✅\n";
