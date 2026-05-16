<?php
/**
 * Stock Lottery Monitor
 *
 * Runs separately from cron_scrape.php and keeps polling lotteries that have
 * passed result_time but still have no usable result.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cron_scrape.php';

date_default_timezone_set('Asia/Bangkok');

$now = time();
$today = date('Y-m-d', $now - 4 * 3600);
$todayReal = date('Y-m-d');

$NAME_TO_EXPHUAY_SLUG = [];
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

$KEY_TO_RAAKAADEE_URL = [
    'lao-pattana' => 'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวพัฒนา/',
];

$KEY_TO_PONHUAY24_SLUG = [
    'lao-pattana' => 'lao-pattana',
    'lao-tai' => 'lao-tai',
    'lao-pratuchai' => 'lao-pratuchai',
    'lao-santiphap' => 'lao-santiphap',
    'lao-redcross' => 'lao-redcross',
    'lao-extra' => 'lao-extra',
    'lao-tv' => 'lao-tv',
    'lao-hd' => 'lao-hd',
    'lao-star' => 'lao-star',
    'lao-star-vip' => 'lao-star-vip',
    'lao-samakki' => 'lao-samakki',
    'lao-samakki-vip' => 'lao-samakki-vip',
    'lao-vip' => 'lao-vip',
    'lao-asean' => 'lao-asean',
    'lao-prachachon' => 'lao-prachachon',
];

$EXP_RESULT_PAGE_UNSUPPORTED = [
    'lao-pattana' => true,
];

function getMonitorExpectedDrawDate(array $lottery, int $nowTs, string $todayReal) {
    $schedule = $lottery['draw_schedule'] ?? 'daily';
    $openTime = $lottery['open_time'] ?? '06:00:00';
    $resultTime = $lottery['result_time'] ?? '00:00:00';

    $openHour = intval(substr($openTime, 0, 2));
    $resultHour = intval(substr($resultTime, 0, 2));
    $isCrossMidnight = !lotteryUsesActualResultDate($lottery) && $resultHour < $openHour && $resultHour < 6;

    $referenceDate = $todayReal;
    if ($isCrossMidnight && date('H:i:s', $nowTs) < $openTime) {
        $referenceDate = date('Y-m-d', strtotime($todayReal . ' -1 day'));
    }

    if (lotteryUsesActualResultDate($lottery)) {
        return $todayReal;
    }

    return getCurrentDrawDateForLottery($schedule, $referenceDate, $lottery);
}

function buildMonitorResultDates(string $drawDate, string $today, string $todayReal) {
    $dates = [$drawDate, $today, $todayReal, date('Y-m-d', strtotime($todayReal . ' -1 day'))];
    return array_values(array_unique($dates));
}

foreach ($SLUG_TO_LOTTERY_NAME as $keySlug => $thaiName) {
    if (isset($KEY_TO_EXPHUAY[$keySlug])) {
        $NAME_TO_EXPHUAY_SLUG[$thaiName] = $KEY_TO_EXPHUAY[$keySlug];
    }
}

$stmt = $pdo->query("
    SELECT lt.id, lt.name, lt.result_time, lt.close_time, lt.open_time, lt.draw_schedule
    FROM lottery_types lt
    WHERE lt.is_active = 1
      AND lt.result_time IS NOT NULL
    ORDER BY lt.result_time ASC
");

$dueLotteries = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $lt) {
    $expectedDate = getMonitorExpectedDrawDate($lt, $now, $todayReal);
    if ($expectedDate !== $today && $expectedDate !== $todayReal) {
        $openTime = $lt['open_time'] ?? '06:00:00';
        $resultTime = $lt['result_time'] ?? '00:00:00';
        $openHour = intval(substr($openTime, 0, 2));
        $resultHour = intval(substr($resultTime, 0, 2));
        $isCrossMidnight = !lotteryUsesActualResultDate($lt) && $resultHour < $openHour && $resultHour < 6;
        if (!$isCrossMidnight) {
            continue;
        }
    }

    if (findUsableResultForDates($pdo, $lt['id'], buildMonitorResultDates($expectedDate, $today, $todayReal))) {
        continue;
    }

    $drawDate = $expectedDate;
    $resultTs = strtotime($drawDate . ' ' . $lt['result_time']);

    $openHour = intval(substr($lt['open_time'] ?? '06:00:00', 0, 2));
    $resultHour = intval(substr($lt['result_time'], 0, 2));
    if (!lotteryUsesActualResultDate($lt) && $resultHour < $openHour && $resultHour < 6) {
        $resultTs = strtotime(date('Y-m-d', strtotime($drawDate . ' +1 day')) . ' ' . $lt['result_time']);
    }

    if ($now < $resultTs) {
        continue;
    }

    $elapsedMin = round(($now - $resultTs) / 60);
    $withMeta = array_merge($lt, ['elapsed' => $elapsedMin, 'draw_date' => $drawDate]);
    $dueLotteries[] = $withMeta;
}

if (empty($dueLotteries)) {
    exit(0);
}

echo "[Monitor] " . date('H:i:s') . " — รอผล " . count($dueLotteries) . " หวย:\n";
foreach ($dueLotteries as $lt) {
    echo "  • {$lt['name']} (ออก {$lt['result_time']}, รอมา {$lt['elapsed']} นาที)\n";
}

echo "\n[Monitor] 🌐 ดึง ExpHuay /result page...\n";
$stderrFile = tempnam(sys_get_temp_dir(), 'monitor_');
$output = [];
$exitCode = 0;
exec("{$PYTHON_PATH} \"{$SCRIPT_DIR}/scrape_exphuay.py\" --date={$todayReal} 2>{$stderrFile}", $output, $exitCode);
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

$stillMissing = [];
foreach ($dueLotteries as $lt) {
    if (!findUsableResultForDates($pdo, $lt['id'], buildMonitorResultDates($lt['draw_date'], $today, $todayReal))) {
        $stillMissing[] = ['id' => $lt['id'], 'name' => $lt['name'], 'draw_date' => $lt['draw_date']];
    }
}

if (!empty($stillMissing)) {
    echo "\n[Monitor] 🔍 ยังขาด " . count($stillMissing) . " ตัว → ลองหน้าผลเฉพาะ:\n";

    foreach ($stillMissing as $missing) {
        $keySlug = array_search($missing['name'], $SLUG_TO_LOTTERY_NAME, true);
        $keySlug = $keySlug !== false ? $keySlug : null;
        $expSlug = $NAME_TO_EXPHUAY_SLUG[$missing['name']] ?? null;
        $saved = false;

        if ($expSlug && empty($EXP_RESULT_PAGE_UNSUPPORTED[$keySlug])) {
            echo "  🌐 {$missing['name']} → exphuay.com/result/{$expSlug}...\n";

            $pageUrl = "https://exphuay.com/result/{$expSlug}";
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0\r\n"
                        . "Accept: text/html\r\n",
                ],
            ]);
            $html = @file_get_contents($pageUrl, false, $ctx);

            if ($html && strlen($html) >= 100) {
                $threeTop = null;
                $twoBot = null;
                $resultDate = null;
                $thaiMonths = [
                    'มกราคม' => 1, 'กุมภาพันธ์' => 2, 'มีนาคม' => 3, 'เมษายน' => 4,
                    'พฤษภาคม' => 5, 'มิถุนายน' => 6, 'กรกฎาคม' => 7, 'สิงหาคม' => 8,
                    'กันยายน' => 9, 'ตุลาคม' => 10, 'พฤศจิกายน' => 11, 'ธันวาคม' => 12,
                ];

                foreach ($thaiMonths as $monthName => $monthNum) {
                    if (preg_match('/(\d{1,2})\s+' . preg_quote($monthName, '/') . '\s+(\d{4})/', $html, $dm)) {
                        $day = intval($dm[1]);
                        $year = intval($dm[2]);
                        if ($year > 2500) {
                            $year -= 543;
                        }
                        $resultDate = sprintf('%04d-%02d-%02d', $year, $monthNum, $day);
                        break;
                    }
                }

                if ($resultDate && $resultDate !== $today && $resultDate !== $todayReal) {
                    echo "  ⏭️ ผลเป็นของ {$resultDate} (ไม่ใช่วันนี้) → ลองแหล่งสำรอง\n";
                } else {
                    if (preg_match_all('/>\s*(\d{3})\s*</', $html, $threeMatches)) {
                        $threeTop = $threeMatches[1][0] ?? null;
                    }
                    if (preg_match_all('/>\s*(\d{2})\s*</', $html, $twoMatches)) {
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

                    if ($threeTop && $twoBot && $keySlug) {
                        echo "  ✅ พบผล: {$threeTop}/{$twoBot} ({$resultDate})\n";
                        $singleResult = [[
                            'slug' => $keySlug,
                            'three_top' => $threeTop,
                            'two_top' => substr($threeTop, -2),
                            'two_bottom' => $twoBot,
                            'draw_date' => $resultDate ?: $today,
                            'source' => 'exphuay.com/result/' . $expSlug,
                        ]];
                        $singleStats = processResults($pdo, $singleResult, 'monitor-single');
                        if ($singleStats['success'] > 0 || findUsableResultForDates($pdo, $missing['id'], buildMonitorResultDates($missing['draw_date'], $today, $todayReal))) {
                            $saved = true;
                            echo "  💾 บันทึกแล้ว!\n";
                        }
                    } else {
                        echo "  ⏳ ยังไม่มีผล → ลองแหล่งสำรอง\n";
                    }
                }
            } else {
                echo "  ❌ ไม่สามารถดึงหน้าได้ → ลองแหล่งสำรอง\n";
            }
        }

        if ($saved) {
            continue;
        }

        if ($keySlug && isset($KEY_TO_PONHUAY24_SLUG[$keySlug])) {
            $ponhuaySlug = $KEY_TO_PONHUAY24_SLUG[$keySlug];
            echo "  ðŸŒ {$missing['name']} â†’ Ponhuay24 fallback...\n";

            $stderrFile = tempnam(sys_get_temp_dir(), 'monitor_ponhuay24_');
            $output = [];
            $exitCode = 0;
            $command = escapeshellarg($PYTHON_PATH)
                . ' ' . escapeshellarg($SCRIPT_DIR . '/scrape_ponhuay24.py')
                . ' --slug ' . escapeshellarg($ponhuaySlug)
                . " 2>{$stderrFile}";
            exec($command, $output, $exitCode);
            @unlink($stderrFile);

            $json = implode("\n", $output);
            $data = json_decode($json, true);
            $results = $data['results'] ?? [];

            if ($exitCode === 0 && !empty($data['success']) && !empty($results)) {
                $fallbackStats = processResults($pdo, $results, 'monitor-ponhuay24');
                if ($fallbackStats['success'] > 0 || findUsableResultForDates($pdo, $missing['id'], buildMonitorResultDates($missing['draw_date'], $today, $todayReal))) {
                    echo "  ðŸ’¾ à¸šà¸±à¸™à¸—à¸¶à¸à¸ˆà¸²à¸ Ponhuay24 à¹à¸¥à¹‰à¸§!\n";
                    continue;
                }

                echo "  â³ Ponhuay24 à¸žà¸šà¸œà¸¥ à¹à¸•à¹ˆà¸¢à¸±à¸‡à¸šà¸±à¸™à¸—à¸¶à¸à¹„à¸¡à¹ˆà¹„à¸”à¹‰à¹ƒà¸™à¸£à¸­à¸šà¸™à¸µà¹‰\n";
            } else {
                echo "  â³ Ponhuay24 à¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¸¡à¸µà¸œà¸¥ â†’ à¸¥à¸­à¸‡à¹à¸«à¸¥à¹ˆà¸‡à¸ªà¸³à¸£à¸­à¸‡à¸–à¸±à¸”à¹„à¸›\n";
            }
        }

        if ($keySlug && isset($KEY_TO_RAAKAADEE_URL[$keySlug])) {
            $raakaadeeUrl = $KEY_TO_RAAKAADEE_URL[$keySlug];
            echo "  🌐 {$missing['name']} → Raakaadee fallback...\n";

            $stderrFile = tempnam(sys_get_temp_dir(), 'monitor_raakaadee_');
            $output = [];
            $exitCode = 0;
            $command = escapeshellarg($PYTHON_PATH)
                . ' ' . escapeshellarg($SCRIPT_DIR . '/scrape_raakaadee.py')
                . ' --slug ' . escapeshellarg($keySlug)
                . ' --url ' . escapeshellarg($raakaadeeUrl)
                . " 2>{$stderrFile}";
            exec($command, $output, $exitCode);
            @unlink($stderrFile);

            $json = implode("\n", $output);
            $data = json_decode($json, true);
            $results = $data['results'] ?? [];

            if ($exitCode === 0 && !empty($data['success']) && !empty($results)) {
                $fallbackStats = processResults($pdo, $results, 'monitor-raakaadee');
                if ($fallbackStats['success'] > 0 || findUsableResultForDates($pdo, $missing['id'], buildMonitorResultDates($missing['draw_date'], $today, $todayReal))) {
                    echo "  💾 บันทึกจาก Raakaadee แล้ว!\n";
                } else {
                    echo "  ⏳ Raakaadee พบผล แต่ยังบันทึกไม่ได้ในรอบนี้\n";
                }
            } else {
                echo "  ⏳ Raakaadee ยังไม่มีผล → รอรอบถัดไป\n";
            }

            continue;
        }

        if (!$expSlug) {
            echo "  ⚠️ {$missing['name']}: ไม่มี source สำรองที่รองรับ\n";
        }
    }
}

echo "\n[Monitor] " . date('H:i:s') . " Done ✅\n";
