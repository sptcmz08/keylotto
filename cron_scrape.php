<?php
/**
 * =============================================
 * Lottery Auto-Scrape — PHP Wrapper for crontab
 * =============================================
 * เรียกจาก crontab เพื่อดึงผลหวยอัตโนมัติ
 * ใช้ Raakaadee + Ponhuay24 เป็นแหล่งหลัก
 *
 * Usage:
 *   php cron_scrape.php raakaadee        ← ดึงจาก Raakaadee (แหล่งหลัก)
 *   php cron_scrape.php ponhuay24        ← ดึงจาก Ponhuay24 (สำรอง)
 *   php cron_scrape.php rayriffy         ← ดึงรัฐบาลไทย
 *   php cron_scrape.php gsb             ← ดึงออมสิน
 *   php cron_scrape.php all              ← ดึงทุกแหล่ง (ทีละตัว)
 */

// =============================================
// Config
// =============================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/line/common.php';

date_default_timezone_set('Asia/Bangkok');

$SCRIPT_DIR = __DIR__ . '/scripts';

// Node.js path — แก้ตาม server
$NODE_PATH = '/usr/bin/node';
// Python path — ใช้ venv ถ้ามี
$PYTHON_PATH = file_exists(__DIR__ . '/.venv/bin/python')
    ? __DIR__ . '/.venv/bin/python'
    : 'python3';

// =============================================
// Slug → lottery_types.name Mapping
// =============================================
// Map scraper slug → ชื่อหวยใน lottery_types ของ Key DB
// *** แก้ไขให้ตรงกับ lottery_types.name ที่มีอยู่ใน DB ***
$SLUG_TO_LOTTERY_NAME = [
    // === หวยต่างประเทศ ===
    'hanoi-special'      => 'ฮานอยพิเศษ',
    'hanoi'              => 'ฮานอยปกติ',
    'hanoi-vip'          => 'ฮานอย VIP',
    'hanoi-redcross'     => 'ฮานอยกาชาด',
    'hanoi-asean'        => 'ฮานอยอาเซียน',
    'hanoi-hd'           => 'ฮานอย HD',
    'hanoi-tv'           => 'ฮานอย TV',
    'hanoi-star'         => 'ฮานอย สตาร์',
    'hanoi-samakki'      => 'ฮานอยสามัคคี',
    'hanoi-pattana'      => 'ฮานอยพัฒนา',
    'hanoi-extra'        => 'ฮานอย Extra',
    'lao-vip'            => 'ลาว VIP',
    'lao-star'           => 'ลาวสตาร์',
    'lao-star-vip'       => 'ลาวสตาร์ VIP',
    'lao-samakki'        => 'ลาวสามัคคี',
    'lao-samakki-vip'    => 'ลาวสามัคคี VIP',
    'lao-pattana'        => 'ลาวพัฒนา',
    'lao-pratuchai'      => 'ลาวประตูชัย',
    'lao-santiphap'      => 'ลาวสันติภาพ',
    'lao-prachachon'     => 'ประชาชนลาว',
    'lao-extra'          => 'ลาว Extra',
    'lao-tv'             => 'ลาว TV',
    'lao-hd'             => 'ลาว HD',
    'lao-tai'            => 'ลาวใต้',
    'lao-asean'          => 'ลาวอาเซียน',
    'lao-redcross'       => 'ลาวกาชาด',
    'malaysia'           => 'หวยมาเลเซีย',
    'malay'              => 'หวยมาเลเซีย',

    // === หุ้น VIP (จาก Raakaadee) ===
    'dowjones-vip'           => 'ดาวโจนส์ VIP',
    'dowjones-star'          => 'ดาวโจนส์ STAR',
    'germany-vip'            => 'เยอรมัน VIP',
    'russia-vip'             => 'รัสเซีย VIP',
    'nikkei-morning-vip'     => 'นิเคอิเช้า VIP',
    'nikkei-afternoon-vip'   => 'นิเคอิบ่าย VIP',
    'china-morning-vip'      => 'จีนเช้า VIP',
    'china-afternoon-vip'    => 'จีนบ่าย VIP',
    'hangseng-morning-vip'   => 'ฮั่งเส็งเช้า VIP',
    'hangseng-afternoon-vip' => 'อั่งเส็งบ่าย VIP',
    'taiwan-vip'             => 'ไต้หวัน VIP',
    'singapore-vip'          => 'สิงคโปร์ VIP',
    'uk-vip'                 => 'อังกฤษ VIP',
    'korea-vip'              => 'เกาหลี VIP',

    // === หุ้นปกติ (จาก Raakaadee) ===
    'nikkei-morning'     => 'นิเคอิ - เช้า',
    'nikkei-afternoon'   => 'นิเคอิ - บ่าย',
    'china-morning'      => 'หุ้นจีน - เช้า',
    'china-afternoon'    => 'หุ้นจีน - บ่าย',
    'hangseng-morning'   => 'ฮั่งเส็ง - เช้า',
    'hangseng-afternoon' => 'ฮั่งเส็ง - บ่าย',
    'taiwan'             => 'หุ้นไต้หวัน',
    'korea'              => 'หุ้นเกาหลี',
    'singapore'          => 'หุ้นสิงคโปร์',
    'india'              => 'หุ้นอินเดีย',
    'egypt'              => 'หุ้นอียิปต์',
    'uk'                 => 'หุ้นอังกฤษ',
    'germany'            => 'หุ้นเยอรมัน',
    'russia'             => 'หุ้นรัสเซีย',
    'dowjones'           => 'หุ้นดาวโจนส์',
    'thai-stock'         => 'หุ้นไทย - เย็น',

    // === หวยไทย ===
    'thai'               => 'หวยรัฐบาลไทย',
    'baac'               => 'หวย ธกส.',
    'gsb'                => 'หวยออมสิน',
    'gsb-1'              => 'หวยออมสิน',
    'gsb-2'              => 'หวยออมสิน',
];

// =============================================
// Smart Pre-check: นับผลที่ยังขาดวันนี้
// ถ้าครบแล้ว → ข้าม scraping (ประหยัด CPU/RAM)
// =============================================
function countMissingResults($pdo, $expectedCount = 62) {
    $today = date('Y-m-d', time() - 4 * 3600);
    
    // นับหวยที่ active ทั้งหมด
    $totalActive = $pdo->query("SELECT COUNT(*) FROM lottery_types WHERE is_active = 1")->fetchColumn();
    
    // นับผลที่มีอยู่แล้ววันนี้
    $todayResults = $pdo->prepare("
        SELECT COUNT(*) FROM results r
        JOIN lottery_types lt ON r.lottery_type_id = lt.id
        WHERE r.draw_date = ? AND lt.is_active = 1
    ");
    $todayResults->execute([$today]);
    $foundToday = $todayResults->fetchColumn();
    
    $missing = max(0, $expectedCount - $foundToday);
    
    return [
        'total_active' => $totalActive,
        'found_today'  => $foundToday,
        'expected'     => $expectedCount,
        'missing'      => $missing,
    ];
}

// =============================================
// Lookup: lottery name → lottery_types.id (cached)
// Smart matching: exact → normalized fuzzy
// =============================================
$LOTTERY_ID_CACHE = [];
$ALL_LOTTERY_NAMES = null; // lazy loaded

/**
 * Normalize ชื่อหวยให้เหลือแค่แก่นคำ
 * 'หุ้นนิเคอิเช้า' → 'นิเคอิเช้า'
 * 'นิเคอิ - เช้า'  → 'นิเคอิเช้า'
 * 'หุ้นจีน - บ่าย' → 'จีนบ่าย'
 */
function normalizeLotteryName($name) {
    $n = $name;
    $n = str_replace(['หุ้น', ' ', '-', '–', '—'], '', $n);
    $n = mb_strtolower($n, 'UTF-8');
    return $n;
}

function getLotteryTypeId($pdo, $lotteryName) {
    global $LOTTERY_ID_CACHE, $ALL_LOTTERY_NAMES;

    if (isset($LOTTERY_ID_CACHE[$lotteryName])) {
        return $LOTTERY_ID_CACHE[$lotteryName];
    }

    // 1) Exact match
    $stmt = $pdo->prepare("SELECT id FROM lottery_types WHERE name = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$lotteryName]);
    $row = $stmt->fetch();

    if ($row) {
        $LOTTERY_ID_CACHE[$lotteryName] = (int)$row['id'];
        return (int)$row['id'];
    }

    // 2) Fuzzy match — normalize แล้วเทียบ
    if ($ALL_LOTTERY_NAMES === null) {
        $ALL_LOTTERY_NAMES = [];
        $all = $pdo->query("SELECT id, name FROM lottery_types WHERE is_active = 1")->fetchAll();
        foreach ($all as $lt) {
            $ALL_LOTTERY_NAMES[] = ['id' => (int)$lt['id'], 'name' => $lt['name'], 'norm' => normalizeLotteryName($lt['name'])];
        }
    }

    $inputNorm = normalizeLotteryName($lotteryName);
    foreach ($ALL_LOTTERY_NAMES as $lt) {
        if ($lt['norm'] === $inputNorm) {
            $LOTTERY_ID_CACHE[$lotteryName] = $lt['id'];
            echo "  ℹ️  Fuzzy matched: \"{$lotteryName}\" → \"{$lt['name']}\" (id:{$lt['id']})\n";
            return $lt['id'];
        }
    }

    return null;
}

// =============================================
// Log helper
// =============================================
function logScrape($pdo, $lotteryName, $source, $status, $message = '', $drawDate = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO scraper_logs (lottery_name, source, status, message, draw_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$lotteryName, $source, $status, $message, $drawDate]);
    } catch (Exception $e) {
        // Silently ignore if table doesn't exist yet
    }
}

// =============================================
// Run scraper script
// =============================================
function runScript($command, $source) {
    $output = shell_exec($command . ' 2>&1');

    if (empty($output)) {
        echo "❌ [{$source}] No output from script\n";
        return null;
    }

    // Find JSON in output
    $jsonStart = strpos($output, '{');
    if ($jsonStart === false) {
        // Try array format
        $jsonStart = strpos($output, '[');
    }

    if ($jsonStart === false) {
        echo "❌ [{$source}] No JSON found in output\n";
        echo substr($output, 0, 500) . "\n";
        return null;
    }

    $json = substr($output, $jsonStart);
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ [{$source}] Invalid JSON: " . json_last_error_msg() . "\n";
        return null;
    }

    return $data;
}

// =============================================
// Auto Payout Calculation — คำนวณผลรางวัลอัตโนมัติ
// =============================================
function processBetPayouts($pdo, $lotteryTypeId, $drawDate) {
    // 1) Get result for this lottery + date
    $stmt = $pdo->prepare("SELECT * FROM results WHERE lottery_type_id = ? AND draw_date = ?");
    $stmt->execute([$lotteryTypeId, $drawDate]);
    $result = $stmt->fetch();
    if (!$result || empty($result['three_top'])) return 0;

    // 2) Get pay rates for this lottery
    $rateStmt = $pdo->prepare("SELECT bet_type, pay_rate FROM pay_rates WHERE lottery_type_id = ?");
    $rateStmt->execute([$lotteryTypeId]);
    $rateMap = [];
    foreach ($rateStmt->fetchAll() as $r) {
        $rateMap[$r['bet_type']] = floatval($r['pay_rate']);
    }

    // 3) Get all pending bets
    $betStmt = $pdo->prepare("SELECT id FROM bets WHERE lottery_type_id = ? AND draw_date = ? AND status = 'pending'");
    $betStmt->execute([$lotteryTypeId, $drawDate]);
    $pendingBets = $betStmt->fetchAll();
    if (empty($pendingBets)) return 0;

    // Helper: sort string for 3tod matching
    $sortStr = function($s) { $a = str_split($s); sort($a); return implode('', $a); };

    $processed = 0;
    foreach ($pendingBets as $bet) {
        $betId = $bet['id'];

        // Get bet items
        $itemStmt = $pdo->prepare("SELECT * FROM bet_items WHERE bet_id = ?");
        $itemStmt->execute([$betId]);
        $items = $itemStmt->fetchAll();

        $totalWin = 0;
        $hasWin = false;

        foreach ($items as $item) {
            $num = $item['number'];
            $type = $item['bet_type'];
            $amount = floatval($item['amount']);
            $isWinner = false;

            // Check if this number wins
            switch ($type) {
                case '3top':
                    $isWinner = ($result['three_top'] === $num);
                    break;
                case '3tod':
                    // 3tod wins if digits are same permutation but NOT exact match (3top)
                    if ($result['three_top'] && strlen($num) === 3) {
                        $isWinner = ($sortStr($num) === $sortStr($result['three_top'])) && ($num !== $result['three_top']);
                    }
                    break;
                case '2top':
                    $isWinner = ($result['two_top'] === $num);
                    break;
                case '2bot':
                    $isWinner = ($result['two_bot'] === $num);
                    break;
                case 'run_top':
                    $isWinner = ($result['run_top'] !== null && strpos($result['three_top'], $num) !== false);
                    break;
                case 'run_bot':
                    $isWinner = ($result['run_bot'] !== null && strpos($result['two_bot'], $num) !== false);
                    break;
            }

            if ($isWinner) {
                $hasWin = true;
                // ใช้ pay_rate ที่บันทึกตอนแทงก่อน (รวม over-rate + จ่ายครึ่ง)
                if (!empty($item['pay_rate']) && floatval($item['pay_rate']) > 0) {
                    $payRate = floatval($item['pay_rate']);
                } elseif (!empty($item['adjusted_pay_rate'])) {
                    $payRate = floatval($item['adjusted_pay_rate']);
                } else {
                    $payRate = $rateMap[$type] ?? 0;
                }
                $winAmount = $amount * $payRate;
                $totalWin += $winAmount;
            }
        }

        // Update bet status
        $newStatus = $hasWin ? 'won' : 'lost';
        $updateStmt = $pdo->prepare("UPDATE bets SET status = ?, win_amount = ? WHERE id = ? AND status = 'pending'");
        $updateStmt->execute([$newStatus, $totalWin, $betId]);
        $processed++;
    }

    return $processed;
}

// =============================================
// Process results → INSERT into Key DB
// =============================================
function processResults($pdo, $results, $source) {
    if (empty($results)) return ['success' => 0, 'failed' => 0, 'skipped' => 0];

    global $SLUG_TO_LOTTERY_NAME;

    $successCount = 0;
    $skippedCount = 0;
    $failedCount = 0;
    $today = date('Y-m-d', time() - 4 * 3600);
    $todayReal = date('Y-m-d'); // วันที่จริง (ไม่ shift)

    // หวยข้ามเที่ยงคืน (close < open) → ใช้วันที่จริงไม่ shift
    $crossMidnightIds = [];
    $cmStmt = $pdo->query("SELECT id FROM lottery_types WHERE CAST(SUBSTRING(close_time,1,2) AS UNSIGNED) < CAST(SUBSTRING(open_time,1,2) AS UNSIGNED) AND CAST(SUBSTRING(close_time,1,2) AS UNSIGNED) < 5");
    while ($row = $cmStmt->fetch()) {
        $crossMidnightIds[$row['id']] = true;
    }

    foreach ($results as $r) {
        // Get slug from result
        $slug = $r['slug'] ?? null;
        $lotteryName = $r['lottery_name'] ?? '';
        $threeTop = $r['three_top'] ?? $r['first_prize'] ?? '';
        $twoTop = $r['two_top'] ?? '';
        $twoBot = $r['two_bottom'] ?? $r['two_bot'] ?? '';
        $drawDate = $r['draw_date'] ?? $today;

        // Map slug → lottery name in Key DB
        $keyLotteryName = null;
        if ($slug && isset($SLUG_TO_LOTTERY_NAME[$slug])) {
            $keyLotteryName = $SLUG_TO_LOTTERY_NAME[$slug];
        }

        // Fallback: try matching by ManyCai lottery_name → slug → Key name
        if (!$keyLotteryName && !$slug) {
            // Direct ManyCai results have lottery_name but no slug
            $isVipText = stripos($lotteryName, 'vip') !== false || stripos($lotteryName, 'พิเศษ') !== false;
            
            foreach ($SLUG_TO_LOTTERY_NAME as $s => $name) {
                $isVipKey = stripos($name, 'vip') !== false || stripos($name, 'พิเศษ') !== false;
                
                // Prevent VIP text matching Normal key, and vice versa
                if ($isVipText !== $isVipKey) {
                    continue;
                }
                
                if (stripos($lotteryName, $name) !== false || stripos($name, $lotteryName) !== false) {
                    $keyLotteryName = $name;
                    $slug = $s;
                    break;
                }
            }
        }

        if (!$keyLotteryName) {
            if ($slug) {
                echo "⚠️  No mapping for slug '{$slug}' ({$lotteryName})\n";
            }
            $skippedCount++;
            continue;
        }

        // Find lottery_type_id
        $lotteryTypeId = getLotteryTypeId($pdo, $keyLotteryName);
        if (!$lotteryTypeId) {
            echo "⚠️  '{$keyLotteryName}' ไม่มีในตาราง lottery_types — ข้าม\n";
            $skippedCount++;
            continue;
        }

        // หวยข้ามเที่ยงคืน (เช่น ดาวโจนส์ VIP ปิด 00:10, STAR ปิด 01:05)
        // draw_date ของหวยพวกนี้เป็นของ "เมื่อวาน" เสมอ (เช่น ดาวโจนส์วันที่ 25 ออกผลหลัง 00:00 วันที่ 26)
        $isCrossMidnight = isset($crossMidnightIds[$lotteryTypeId]);
        if ($isCrossMidnight) {
            // ถ้า scraper ส่ง draw_date มาตรงกับเมื่อวาน (shift -4h) → ใช้ตามนั้น (ถูกแล้ว)
            // ถ้าไม่ → ใช้เมื่อวาน (วันซื้อขาย/ออกผลจริง)
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($drawDate === $todayReal) {
                // scraper ไม่มี draw_date หรือใส่วันนี้ (ผิด) → แก้เป็นเมื่อวาน
                $drawDate = $yesterday;
            }
            // ถ้า scraper ใส่ $today (shift -4h) = เมื่อวาน → ถูกแล้ว ไม่ต้องแก้
        }

        // === ⛔ SAFEGUARD: ห้ามบันทึกผลก่อนถึง result_time ===
        // ป้องกันการดึงผลเก่ามาบันทึกเป็นของวันนี้
        $ltStmt = $pdo->prepare("SELECT result_time FROM lottery_types WHERE id = ?");
        $ltStmt->execute([$lotteryTypeId]);
        $ltInfo = $ltStmt->fetch();
        if ($ltInfo && !empty($ltInfo['result_time'])) {
            $resultTimeStr = $drawDate . ' ' . $ltInfo['result_time'];
            // หวยข้ามเที่ยงคืน: result_time เป็นของวันถัดไป (เช่น 03:20 ของพรุ่งนี้)
            if ($isCrossMidnight) {
                // ถ้า result_time < 05:00 แสดงว่าเป็นเวลาหลังเที่ยงคืน
                $resultHour = intval(substr($ltInfo['result_time'], 0, 2));
                if ($resultHour < 5) {
                    // result_time เป็นของวันถัดไป (drawDate + 1 วัน)
                    $resultTimeStr = date('Y-m-d', strtotime($drawDate . ' +1 day')) . ' ' . $ltInfo['result_time'];
                }
            }
            $resultTimestamp = strtotime($resultTimeStr);
            if ($resultTimestamp && time() < $resultTimestamp) {
                echo "⏳ {$keyLotteryName}: ยังไม่ถึงเวลาออกผล (" . $ltInfo['result_time'] . ") → ข้าม\n";
                $skippedCount++;
                continue;
            }
        }

        // Reject future dates
        $maxDate = max($today, $todayReal);
        if ($drawDate > $maxDate) {
            echo "⚠️  {$keyLotteryName}: วันที่ {$drawDate} เป็นอนาคต → ข้าม\n";
            $skippedCount++;
            continue;
        }

        // === ⛔ SAFEGUARD 2: ตรวจสอบว่าวันที่ตรงกับงวดที่ควรออก ===
        // เช่น ลาวพัฒนา (จ/พ/ศ) จะไม่บันทึกผลในวันอังคาร
        $scheduleStmt = $pdo->prepare("SELECT draw_schedule FROM lottery_types WHERE id = ?");
        $scheduleStmt->execute([$lotteryTypeId]);
        $scheduleInfo = $scheduleStmt->fetch();
        if ($scheduleInfo && !empty($scheduleInfo['draw_schedule'])) {
            $expectedDrawDate = getCurrentDrawDate($scheduleInfo['draw_schedule'], $drawDate);
            if ($expectedDrawDate !== $drawDate) {
                echo "📅 {$keyLotteryName}: วันที่ {$drawDate} ไม่ตรงงวด (ควรเป็น {$expectedDrawDate}) → ข้าม\n";
                $skippedCount++;
                continue;
            }
        }

        // Ensure three_top is max 3 digits (truncate from right if longer)
        if (strlen($threeTop) > 3) {
            $threeTop = substr($threeTop, -3);
        }

        // Derive two_top if missing
        if (empty($twoTop) && !empty($threeTop)) {
            $twoTop = substr($threeTop, -2);
        }

        // Derive run_top and run_bot
        $runTop = !empty($threeTop) ? substr($threeTop, -1) : '';
        $runBot = !empty($twoBot) ? substr($twoBot, -1) : '';

        // Check if already exists
        $stmt = $pdo->prepare("SELECT id, three_top, two_bot FROM results WHERE lottery_type_id = ? AND draw_date = ?");
        $stmt->execute([$lotteryTypeId, $drawDate]);
        $existing = $stmt->fetch();

        if ($existing) {
            // ถ้ามีอยู่แล้ว เช็คว่าตรงกันไหม
            $existingThree = $existing['three_top'] ?? '';
            $existingBot = $existing['two_bot'] ?? '';
            
            $isMatch = true;
            if ($threeTop && $existingThree && $existingThree !== $threeTop) $isMatch = false;
            if ($twoBot && $existingBot && $existingBot !== $twoBot) $isMatch = false;
            
            if ($isMatch) {
                echo "⏭️  {$keyLotteryName}: มีผลอยู่แล้ว ({$existingThree}/{$existingBot})\n";
                // ✅ ถึงผลมีแล้ว ก็ยังเช็คว่ามี pending bets ค้างอยู่ไหม → คำนวณจ่าย
                $payoutCount = processBetPayouts($pdo, $lotteryTypeId, $drawDate);
                if ($payoutCount > 0) echo "💰 {$keyLotteryName}: คำนวณผลค้าง {$payoutCount} โพย\n";
            } else {
                // ⚠️ CONFLICT: ผลไม่ตรงกัน → log แจ้งเตือน ไม่ overwrite อัตโนมัติ
                echo "⚠️  CONFLICT {$keyLotteryName}: DB=[{$existingThree}/{$existingBot}] vs {$source}=[{$threeTop}/{$twoBot}] — ไม่ overwrite\n";
                logScrape($pdo, $keyLotteryName, $source, 'conflict', 
                    "DB: {$existingThree}/{$existingBot} vs {$source}: {$threeTop}/{$twoBot}", $drawDate);
            }
            $skippedCount++;
            continue;
        }

        // INSERT new result
        try {
            
            $stmt = $pdo->prepare("
                INSERT INTO results (lottery_type_id, draw_date, three_top, two_top, two_bot, run_top, run_bot)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$lotteryTypeId, $drawDate, $threeTop, $twoTop, $twoBot, $runTop, $runBot]);

            echo "✅ {$keyLotteryName}: {$threeTop} / {$twoTop} / {$twoBot} ({$drawDate}) [ใหม่]\n";
            logScrape($pdo, $keyLotteryName, $source, 'success', "New: {$threeTop}/{$twoBot}", $drawDate);
            // Auto-calculate payouts
            $payoutCount = processBetPayouts($pdo, $lotteryTypeId, $drawDate);
            if ($payoutCount > 0) echo "💰 {$keyLotteryName}: คำนวณผล {$payoutCount} โพย\n";
            try {
                $lineStats = lineSendResultNotification($pdo, $lotteryTypeId, $drawDate);
                if (!empty($lineStats['sent'])) {
                    $lineMode = !empty($lineStats['renderer']) ? 'image:' . $lineStats['renderer'] : 'image';
                    echo "ðŸ“£ {$keyLotteryName}: à¸ªà¹ˆà¸‡ LINE {$lineStats['sent']} à¸à¸¥à¸¸à¹ˆà¸¡ ({$lineMode})\n";
                } elseif (($lineStats['reason'] ?? '') === 'image_generation_failed') {
                    echo "âš ï¸  {$keyLotteryName}: à¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¸ªà¹ˆà¸‡ LINE à¹€à¸žà¸£à¸²à¸°à¸ªà¸£à¹‰à¸²à¸‡à¸£à¸¹à¸›à¹„à¸¡à¹ˆà¸ªà¸³à¹€à¸£à¹‡à¸ˆ\n";
                }
            } catch (Exception $lineError) {
                echo "âš ï¸  LINE notify failed for {$keyLotteryName}: " . $lineError->getMessage() . "\n";
                lineLog('Auto notify failed: ' . $lineError->getMessage());
            }

            $successCount++;
        } catch (Exception $e) {
            echo "❌ {$keyLotteryName}: " . $e->getMessage() . "\n";
            logScrape($pdo, $keyLotteryName, $source, 'failed', $e->getMessage(), $drawDate);
            $failedCount++;
        }
    }

    return [
        'success' => $successCount,
        'skipped' => $skippedCount,
        'failed'  => $failedCount,
    ];
}

// =============================================
// Scraper Runners
// =============================================

// ManyCai removed — ใช้ Raakaadee + Ponhuay24 แทนทั้งหมด

function scrapeRaakaadee($pdo) {
    global $SCRIPT_DIR, $PYTHON_PATH;

    echo "🌐 Raakaadee Scraper (Camoufox)...\n";

    // Smart pre-check: ถ้าผลวันนี้ครบแล้ว ข้าม
    $check = countMissingResults($pdo, 62);
    if ($check['missing'] <= 0) {
        echo "✅ ผลวันนี้ครบแล้ว ({$check['found_today']}/{$check['expected']}) → ข้าม Raakaadee\n";
        return;
    }
    echo "📋 ยังขาด {$check['missing']} ผล ({$check['found_today']}/{$check['expected']}) → เริ่มดึง...\n";

    $stderrFile = tempnam(sys_get_temp_dir(), 'raakaadee_');
    $output = [];
    $exitCode = 0;
    exec("{$PYTHON_PATH} \"{$SCRIPT_DIR}/scrape_raakaadee.py\" 2>{$stderrFile}", $output, $exitCode);
    @unlink($stderrFile);

    $jsonOutput = implode("\n", $output);
    $data = json_decode($jsonOutput, true);

    if (!$data || empty($data['success'])) {
        echo "❌ Raakaadee: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ALL', 'raakaadee', 'failed', $data['error'] ?? 'No data');
        return;
    }

    $results = $data['results'] ?? [];
    echo "📊 Raakaadee: " . count($results) . " results found\n";

    // Raakaadee results already have slug → processResults handles mapping
    $stats = processResults($pdo, $results, 'raakaadee');
    echo "\n📊 Raakaadee Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

function scrapeStockVip($pdo) {
    global $SCRIPT_DIR, $NODE_PATH;

    echo "📈 Stock VIP Scraper (Puppeteer)...\n";
    $data = runScript("{$NODE_PATH} \"{$SCRIPT_DIR}/stocks_vip_draw.js\"", 'stockvip');

    if (!$data || empty($data['success'])) {
        echo "❌ Stock VIP: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ALL', 'stockvip', 'failed', $data['error'] ?? 'No data');
        return;
    }

    $results = $data['results'] ?? [];
    echo "📊 Stock VIP: " . count($results) . " results found\n";

    $stats = processResults($pdo, $results, 'stockvip');
    echo "\n📊 Stock VIP Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

function scrapeHanoi($pdo) {
    global $SCRIPT_DIR, $NODE_PATH;

    echo "🇻🇳 Hanoi Normal Scraper (XSMB API)...\n";
    $data = runScript("{$NODE_PATH} \"{$SCRIPT_DIR}/hanoi_normal_api.js\"", 'hanoi');

    if (!$data || empty($data['success'])) {
        echo "❌ Hanoi: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ฮานอยปกติ', 'hanoi', 'failed', $data['error'] ?? 'No data');
        return;
    }

    // hanoi_normal_api.js returns single result
    $results = [];
    if (isset($data['result'])) {
        $results[] = array_merge($data['result'], ['slug' => 'hanoi']);
    } elseif (isset($data['results'])) {
        foreach ($data['results'] as $r) {
            $r['slug'] = $r['slug'] ?? 'hanoi';
            $results[] = $r;
        }
    }

    $stats = processResults($pdo, $results, 'hanoi');
    echo "\n📊 Hanoi Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

function scrapeLaoVip($pdo) {
    global $SCRIPT_DIR, $NODE_PATH;

    echo "🇱🇦 Lao VIP Scraper...\n";
    $data = runScript("{$NODE_PATH} \"{$SCRIPT_DIR}/laos_vip_draw.js\"", 'laovip');

    if (!$data || empty($data['success'])) {
        // Fallback to API
        echo "⚠️  Draw failed, trying API...\n";
        $data = runScript("{$NODE_PATH} \"{$SCRIPT_DIR}/laos_vip_api.js\"", 'laovip');
    }

    if (!$data || empty($data['success'])) {
        echo "❌ Lao VIP: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ลาว VIP', 'laovip', 'failed', $data['error'] ?? 'No data');
        return;
    }

    $results = [];
    if (isset($data['result'])) {
        $results[] = array_merge($data['result'], ['slug' => 'lao-vip']);
    } elseif (isset($data['results'])) {
        foreach ($data['results'] as $r) {
            $r['slug'] = $r['slug'] ?? 'lao-vip';
            $results[] = $r;
        }
    }

    $stats = processResults($pdo, $results, 'laovip');
    echo "\n📊 Lao VIP Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

function scrapeLaoSamakki($pdo) {
    global $SCRIPT_DIR, $NODE_PATH;

    echo "🇱🇦 Lao Samakki Scraper...\n";
    $data = runScript("{$NODE_PATH} \"{$SCRIPT_DIR}/laounion_draw.js\"", 'laosamakki');

    if (!$data || empty($data['success'])) {
        echo "⚠️  Draw failed, trying API...\n";
        $data = runScript("{$NODE_PATH} \"{$SCRIPT_DIR}/laounion_api.js\"", 'laosamakki');
    }

    if (!$data || empty($data['success'])) {
        echo "❌ Lao Samakki: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ลาวสามัคคี', 'laosamakki', 'failed', $data['error'] ?? 'No data');
        return;
    }

    $results = [];
    if (isset($data['result'])) {
        $results[] = array_merge($data['result'], ['slug' => 'lao-samakki']);
    } elseif (isset($data['results'])) {
        foreach ($data['results'] as $r) {
            $r['slug'] = $r['slug'] ?? 'lao-samakki';
            $results[] = $r;
        }
    }

    $stats = processResults($pdo, $results, 'laosamakki');
    echo "\n📊 Lao Samakki Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

function scrapeThaiRayriffy($pdo) {
    echo "🇹🇭 Thai Lottery Scraper (Rayriffy API)...\n";

    $url = 'https://lotto.api.rayriffy.com/latest';
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => "Accept: application/json\r\n"
        ]
    ]);

    $json = @file_get_contents($url, false, $context);
    if (!$json) {
        echo "❌ Rayriffy API: เชื่อมต่อไม่ได้\n";
        logScrape($pdo, 'รัฐบาลไทย', 'rayriffy', 'failed', 'Connection failed');
        return;
    }

    $data = json_decode($json, true);
    if (!$data || ($data['status'] ?? '') !== 'success') {
        echo "❌ Rayriffy API: status not success\n";
        logScrape($pdo, 'รัฐบาลไทย', 'rayriffy', 'failed', 'API status: ' . ($data['status'] ?? 'unknown'));
        return;
    }

    $response = $data['response'] ?? [];
    $drawDate = $response['date'] ?? null;
    $firstPrize = '';
    $threeTop = '';
    $twoBottom = '';

    // Extract first prize
    $prizes = $response['prizes'] ?? [];
    foreach ($prizes as $prize) {
        if (($prize['id'] ?? '') === 'prizeFirst') {
            $firstPrize = $prize['number'][0] ?? '';
            $threeTop = substr($firstPrize, -3);
            break;
        }
    }

    // Extract 2 ตัวล่าง
    $runningNumbers = $response['runningNumbers'] ?? [];
    foreach ($runningNumbers as $rn) {
        if (($rn['id'] ?? '') === 'runningNumberBackTwo') {
            $twoBottom = $rn['number'][0] ?? '';
            break;
        }
    }

    if (empty($firstPrize)) {
        echo "❌ Rayriffy: ไม่พบรางวัลที่ 1\n";
        logScrape($pdo, 'รัฐบาลไทย', 'rayriffy', 'failed', 'No first prize found');
        return;
    }

    // Convert draw date — API returns Thai format: "16 มีนาคม 2569"
    if ($drawDate) {
        $thaiMonths = [
            'มกราคม' => 1, 'กุมภาพันธ์' => 2, 'มีนาคม' => 3,
            'เมษายน' => 4, 'พฤษภาคม' => 5, 'มิถุนายน' => 6,
            'กรกฎาคม' => 7, 'สิงหาคม' => 8, 'กันยายน' => 9,
            'ตุลาคม' => 10, 'พฤศจิกายน' => 11, 'ธันวาคม' => 12,
        ];
        $parsed = false;
        foreach ($thaiMonths as $monthName => $monthNum) {
            if (preg_match('/(\d{1,2})\s+' . preg_quote($monthName) . '\s+(\d{4})/', $drawDate, $m)) {
                $day = intval($m[1]);
                $year = intval($m[2]);
                if ($year > 2500) $year -= 543; // BE to CE
                $drawDate = sprintf('%04d-%02d-%02d', $year, $monthNum, $day);
                $parsed = true;
                break;
            }
        }
        if (!$parsed) {
            // Fallback: try strtotime
            $ts = strtotime($drawDate);
            $drawDate = $ts ? date('Y-m-d', $ts) : date('Y-m-d', time() - 4 * 3600);
        }
    } else {
        $drawDate = date('Y-m-d', time() - 4 * 3600);
    }

    echo "✅ Rayriffy: รางวัลที่1={$firstPrize} (3บน={$threeTop}, 2ล่าง={$twoBottom}) วันที่={$drawDate}\n";

    $results = [[
        'slug' => 'thai',
        'lottery_name' => 'รัฐบาลไทย',
        'first_prize' => $firstPrize,
        'three_top' => $threeTop,
        'two_top' => substr($threeTop, -2),
        'two_bottom' => $twoBottom,
        'draw_date' => $drawDate,
        'source' => 'rayriffy',
    ]];

    $stats = processResults($pdo, $results, 'rayriffy');
    echo "\n📊 Rayriffy Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

function scrapeGSB($pdo) {
    global $SCRIPT_DIR, $PYTHON_PATH;

    echo "🏦 GSB+BAAC Scraper (สลากออมสิน + ธกส)...\n";
    $data = runScript("{$PYTHON_PATH} \"{$SCRIPT_DIR}/scrape_thai_savings.py\"", 'thai_savings');

    if (!$data || empty($data['success'])) {
        echo "❌ ThaiSavings: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ออมสิน+ธกส', 'thai_savings', 'failed', $data['error'] ?? 'No data');
        return;
    }

    $results = $data['results'] ?? [];
    echo "📊 ThaiSavings: " . count($results) . " results found\n";

    $stats = processResults($pdo, $results, 'thai_savings');
    echo "\n📊 ThaiSavings Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

function scrapePonhuay24($pdo) {
    global $SCRIPT_DIR, $PYTHON_PATH;

    echo "🎯 Ponhuay24 Backup Scraper (ดึงหวยที่ Raakaadee ไม่มี)...\n";

    // Smart pre-check: ถ้าผลวันนี้ครบแล้ว ข้าม
    $check = countMissingResults($pdo, 62);
    if ($check['missing'] <= 0) {
        echo "✅ ผลวันนี้ครบแล้ว ({$check['found_today']}/{$check['expected']}) → ข้าม Ponhuay24\n";
        return;
    }
    echo "📋 ยังขาด {$check['missing']} ผล ({$check['found_today']}/{$check['expected']}) → เริ่มดึง...\n";

    $data = runScript("{$PYTHON_PATH} \"{$SCRIPT_DIR}/scrape_ponhuay24.py\"", 'ponhuay24');

    if (!$data || empty($data['success'])) {
        echo "❌ Ponhuay24: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ALL', 'ponhuay24', 'failed', $data['error'] ?? 'No data');
        return;
    }

    $results = $data['results'] ?? [];
    echo "📊 Ponhuay24: " . count($results) . " results found\n";

    $stats = processResults($pdo, $results, 'ponhuay24');
    echo "\n📊 Ponhuay24 Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

function scrapeExphuay($pdo) {
    global $SCRIPT_DIR, $PYTHON_PATH;

    echo "📈 ExpHuay Scraper (ทุกหวย — HTTP เร็ว)...\n";

    $today = date('Y-m-d', time() - 4 * 3600);

    $stderrFile = tempnam(sys_get_temp_dir(), 'exphuay_');
    $output = [];
    $exitCode = 0;
    exec("{$PYTHON_PATH} \"{$SCRIPT_DIR}/scrape_exphuay.py\" --date={$today} 2>{$stderrFile}", $output, $exitCode);
    @unlink($stderrFile);

    $jsonOutput = implode("\n", $output);
    $data = json_decode($jsonOutput, true);

    if (!$data || empty($data['success'])) {
        echo "❌ ExpHuay: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ALL', 'exphuay', 'failed', $data['error'] ?? 'No data');
        return;
    }

    $results = $data['results'] ?? [];
    echo "📊 ExpHuay: " . count($results) . " results found\n";

    $stats = processResults($pdo, $results, 'exphuay');
    echo "\n📊 ExpHuay Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
}

// =============================================
// Smart Scraper: สลับ raakaadee ↔ exphuay ทุก 1 นาที
// =============================================
function scrapeSmart($pdo) {
    global $SCRIPT_DIR, $PYTHON_PATH;

    $minute = (int)date('i');
    $sourceFirst = ($minute % 2 === 1) ? 'raakaadee' : 'exphuay';
    $sourceSecond = ($sourceFirst === 'raakaadee') ? 'exphuay' : 'raakaadee';

    echo "🔄 Smart Scraper — นาทีที่ {$minute} → ลำดับ: {$sourceFirst} → {$sourceSecond}\n\n";

    // ดึงหวยที่ยังไม่มีผลวันนี้
    $today = date('Y-m-d', time() - 4 * 3600);
    $todayReal = date('Y-m-d');
    
    $missingStmt = $pdo->prepare("
        SELECT lt.id, lt.name, lt.close_time, lt.open_time
        FROM lottery_types lt
        WHERE lt.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM results r 
            WHERE r.lottery_type_id = lt.id 
            AND (r.draw_date = ? OR r.draw_date = ?)
        )
    ");
    $missingStmt->execute([$today, $todayReal]);
    $missingLotteries = $missingStmt->fetchAll(PDO::FETCH_ASSOC);

    $missingCount = count($missingLotteries);
    if ($missingCount === 0) {
        echo "✅ ผลวันนี้ครบแล้ว → ไม่ต้องดึงเพิ่ม\n";
        return;
    }

    echo "📋 ยังขาดผล {$missingCount} หวย\n";

    // ดึงจากแหล่งแรก
    echo "\n─── แหล่งที่ 1: {$sourceFirst} ───\n";
    $stats1 = runSmartSource($pdo, $sourceFirst);
    
    if ($stats1['success'] > 0) {
        echo "✅ {$sourceFirst}: ได้ผลใหม่ {$stats1['success']} รายการ\n";
    }

    // เช็คว่ายังขาดอยู่ไหม
    $missingStmt->execute([$today, $todayReal]);
    $stillMissing = count($missingStmt->fetchAll(PDO::FETCH_ASSOC));

    if ($stillMissing === 0) {
        echo "\n✅ ผลครบแล้วหลังดึงจาก {$sourceFirst}\n";
        return;
    }

    // ดึงจากแหล่งที่ 2
    echo "\n─── แหล่งที่ 2: {$sourceSecond} (ยังขาด {$stillMissing}) ───\n";
    $stats2 = runSmartSource($pdo, $sourceSecond);
    
    if ($stats2['success'] > 0) {
        echo "✅ {$sourceSecond}: ได้ผลใหม่ {$stats2['success']} รายการ\n";
    }

    $totalNew = $stats1['success'] + $stats2['success'];
    $totalConflict = $stats1['conflict'] + $stats2['conflict'];
    echo "\n═══════════════════════════════════════\n";
    echo "📊 Smart Summary: ✅ {$totalNew} ใหม่";
    if ($totalConflict > 0) echo ", ⚠️ {$totalConflict} conflict";
    echo "\n";

    // =============================================
    // Catch-all: คำนวณจ่ายโพย pending ทั้งหมดที่มีผลแล้ว
    // =============================================
    echo "\n─── Catch-all: เช็คโพย pending ที่มีผลแล้ว ───\n";
    $catchAllStmt = $pdo->query("
        SELECT DISTINCT b.lottery_type_id, b.draw_date
        FROM bets b
        JOIN results r ON r.lottery_type_id = b.lottery_type_id AND r.draw_date = b.draw_date
        WHERE b.status = 'pending'
        AND r.three_top IS NOT NULL AND r.three_top != ''
    ");
    $catchAllPairs = $catchAllStmt->fetchAll();
    $catchAllTotal = 0;
    foreach ($catchAllPairs as $pair) {
        $count = processBetPayouts($pdo, $pair['lottery_type_id'], $pair['draw_date']);
        if ($count > 0) {
            $ltName = $pdo->prepare("SELECT name FROM lottery_types WHERE id = ?");
            $ltName->execute([$pair['lottery_type_id']]);
            $name = $ltName->fetchColumn() ?: $pair['lottery_type_id'];
            echo "💰 Catch-all: {$name} ({$pair['draw_date']}): คำนวณผล {$count} โพย\n";
            $catchAllTotal += $count;
        }
    }
    if ($catchAllTotal === 0) {
        echo "✅ ไม่มีโพย pending ค้าง\n";
    } else {
        echo "💰 Catch-all: คำนวณผลรวม {$catchAllTotal} โพย\n";
    }

    // =============================================
    // Auto-cancel: หวยที่เลย result_time > 2 ชม. ยังไม่มีผล → ยกเลิกโพย pending
    // =============================================
    $now = time();
    $overdueStmt = $pdo->prepare("
        SELECT lt.id, lt.name, lt.result_time, lt.close_time, lt.open_time, lt.draw_schedule
        FROM lottery_types lt
        WHERE lt.is_active = 1
        AND lt.result_time IS NOT NULL
        AND EXISTS (
            SELECT 1 FROM bets b 
            WHERE b.lottery_type_id = lt.id 
            AND (b.draw_date = ? OR b.draw_date = ?)
            AND b.status = 'pending'
        )
        AND NOT EXISTS (
            SELECT 1 FROM results r 
            WHERE r.lottery_type_id = lt.id 
            AND (r.draw_date = ? OR r.draw_date = ?)
        )
    ");
    $overdueStmt->execute([$today, $todayReal, $today, $todayReal]);
    $overdueLotteries = $overdueStmt->fetchAll(PDO::FETCH_ASSOC);

    $autoCancelCount = 0;
    foreach ($overdueLotteries as $ol) {
        // คำนวณ result_time สำหรับวันนี้
        $resultTimeStr = $todayReal . ' ' . $ol['result_time'];
        $resultTimestamp = strtotime($resultTimeStr);
        
        // หวยข้ามเที่ยงคืน: result_time < open_time → result เป็นของวันถัดไป
        $openHour = intval(substr($ol['open_time'] ?? '06:00:00', 0, 2));
        $resultHour = intval(substr($ol['result_time'], 0, 2));
        if ($resultHour < $openHour && $resultHour < 6) {
            // result_time หลังเที่ยงคืน → ใช้วันที่ของ draw_date + 1
            $resultTimeStr = date('Y-m-d', strtotime($today . ' +1 day')) . ' ' . $ol['result_time'];
            $resultTimestamp = strtotime($resultTimeStr);
        }
        
        $hoursPast = ($now - $resultTimestamp) / 3600;
        
        if ($hoursPast > 2) {
            // เลย 2 ชม.แล้ว → ยกเลิกโพย pending
            $cancelStmt = $pdo->prepare("
                UPDATE bets SET status = 'cancelled', win_amount = 0, 
                cancel_approved_by = 'auto_timeout', cancel_approved_at = NOW()
                WHERE lottery_type_id = ? AND (draw_date = ? OR draw_date = ?) AND status = 'pending'
            ");
            $cancelStmt->execute([$ol['id'], $today, $todayReal]);
            $cancelled = $cancelStmt->rowCount();
            if ($cancelled > 0) {
                echo "🚫 Auto-cancel: {$ol['name']} — ยกเลิก {$cancelled} โพย (เลย result_time > 2 ชม.)\n";
                logScrape($pdo, $ol['name'], 'auto_cancel', 'success', "Cancelled {$cancelled} bets (overdue > 2hrs)", $today);
                $autoCancelCount += $cancelled;
            }
        }
    }
    if ($autoCancelCount > 0) {
        echo "🚫 Auto-cancel รวม: {$autoCancelCount} โพย\n";
    }
}

function runSmartSource($pdo, $source) {
    global $SCRIPT_DIR, $PYTHON_PATH;
    
    $today = date('Y-m-d', time() - 4 * 3600);
    $stderrFile = tempnam(sys_get_temp_dir(), "smart_{$source}_");
    $output = [];
    $exitCode = 0;

    if ($source === 'raakaadee') {
        exec("{$PYTHON_PATH} \"{$SCRIPT_DIR}/scrape_raakaadee.py\" 2>{$stderrFile}", $output, $exitCode);
    } else {
        exec("{$PYTHON_PATH} \"{$SCRIPT_DIR}/scrape_exphuay.py\" --date={$today} 2>{$stderrFile}", $output, $exitCode);
    }
    @unlink($stderrFile);

    $jsonOutput = implode("\n", $output);
    $data = json_decode($jsonOutput, true);

    if (!$data || empty($data['success'])) {
        echo "❌ {$source}: " . ($data['error'] ?? 'No data / parse error') . "\n";
        return ['success' => 0, 'conflict' => 0];
    }

    $results = $data['results'] ?? [];
    echo "📊 {$source}: " . count($results) . " results found\n";

    $stats = processResults($pdo, $results, $source);
    
    // นับ conflict จาก log
    $conflictCount = 0;
    $cStmt = $pdo->prepare("
        SELECT COUNT(*) FROM scraper_logs 
        WHERE status = 'conflict' AND draw_date = ? AND created_at >= NOW() - INTERVAL 2 MINUTE
    ");
    $cStmt->execute([$today]);
    $conflictCount = (int)$cStmt->fetchColumn();

    return [
        'success' => $stats['success'],
        'conflict' => $conflictCount,
    ];
}

// =============================================
// Main (only runs when called directly, not via require)
// =============================================
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['argv'][0] ?? '')) {
$scraper = $argv[1] ?? 'all';
$startTime = microtime(true);

echo "═══════════════════════════════════════\n";
echo "🎰 Lottery Scraper — " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════\n\n";

switch ($scraper) {
    case 'smart':
        scrapeSmart($pdo);
        break;
    case 'raakaadee':
        scrapeRaakaadee($pdo);
        break;
    case 'ponhuay24':
        scrapePonhuay24($pdo);
        break;
    case 'exphuay':
        scrapeExphuay($pdo);
        break;
    case 'stockvip':
        scrapeStockVip($pdo);
        break;
    case 'hanoi':
        scrapeHanoi($pdo);
        break;
    case 'laovip':
        scrapeLaoVip($pdo);
        break;
    case 'laosamakki':
        scrapeLaoSamakki($pdo);
        break;
    case 'rayriffy':
    case 'thai':
        scrapeThaiRayriffy($pdo);
        break;
    case 'gsb':
        scrapeGSB($pdo);
        break;
    case 'all':
        // ExpHuay เป็นหลัก (HTTP เร็ว, แม่นยำ)
        scrapeExphuay($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapePonhuay24($pdo);
        echo "\n───────────────────────────────────────\n\n";
        // Raakaadee เป็นสำรอง (เก็บผลที่ ExpHuay ไม่มี)
        scrapeRaakaadee($pdo);
        // หวยไทย/ออมสิน — รันเฉพาะวันที่ 1 และ 16 ของเดือน
        $dayOfMonth = intval(date('d'));
        if ($dayOfMonth === 1 || $dayOfMonth === 16) {
            echo "\n───────────────────────────────────────\n\n";
            echo "📅 วันที่ {$dayOfMonth} → รันหวยไทย/ออมสิน\n";
            scrapeThaiRayriffy($pdo);
            echo "\n───────────────────────────────────────\n\n";
            scrapeGSB($pdo);
        }
        break;
    default:
        echo "❌ Unknown scraper: {$scraper}\n";
        echo "Usage: php cron_scrape.php [smart|raakaadee|ponhuay24|exphuay|all]\n";
        exit(1);
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "\n═══════════════════════════════════════\n";
echo "⏱️  เสร็จใน {$elapsed} วินาที\n";
echo "═══════════════════════════════════════\n";

} // end if (main guard)
