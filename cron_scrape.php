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
    'hanoi-star'         => 'ฮานอยสตาร์',
    'hanoi-samakki'      => 'ฮานอยสามัคคี',
    'hanoi-chinese-ny'   => 'ฮานอยตรุษจีน',
    'hanoi-pattana'      => 'ฮานอยพัฒนา',
    'hanoi-extra'        => 'ฮานอย EXTRA',
    'lao-vip'            => 'ลาว VIP',
    'lao-star'           => 'ลาวสตาร์',
    'lao-star-vip'       => 'ลาวสตาร์ VIP',
    'lao-samakki'        => 'ลาวสามัคคี',
    'lao-samakki-vip'    => 'ลาวสามัคคี VIP',
    'lao-pattana'        => 'ลาวพัฒนา',
    'lao-pratuchai'      => 'ลาวประตูชัย',
    'lao-santiphap'      => 'ลาวสันติภาพ',
    'lao-prachachon'     => 'ประชาชนลาว',
    'lao-extra'          => 'ลาว EXTRA',
    'lao-tv'             => 'ลาว TV',
    'lao-hd'             => 'ลาว HD',
    'lao-tai'            => 'ลาวใต้',
    'lao-asean'          => 'ลาวอาเซียน',
    'lao-redcross'       => 'ลาวกาชาด',

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
    'hangseng-afternoon-vip' => 'ฮั่งเส็งบ่าย VIP',
    'taiwan-vip'             => 'ไต้หวัน VIP',
    'singapore-vip'          => 'สิงคโปร์ VIP',
    'uk-vip'                 => 'อังกฤษ VIP',
    'korea-vip'              => 'เกาหลี VIP',

    // === หุ้นปกติ (จาก Raakaadee — แทน ManyCai) ===
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
    'thai'               => 'รัฐบาลไทย',
    'baac'               => 'ธกส',
    'gsb'                => 'ออมสิน',
    'gsb-1'              => 'ออมสิน',
    'gsb-2'              => 'ออมสิน',

    // === อื่นๆ ===
    'rasi-12'            => 'หวย 12 ราศี',
];

// =============================================
// Smart Pre-check: นับผลที่ยังขาดวันนี้
// ถ้าครบแล้ว → ข้าม scraping (ประหยัด CPU/RAM)
// =============================================
function countMissingResults($pdo, $expectedCount = 62) {
    $today = date('Y-m-d');
    
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
// Process results → INSERT into Key DB
// =============================================
function processResults($pdo, $results, $source) {
    global $SLUG_TO_LOTTERY_NAME;

    $successCount = 0;
    $skippedCount = 0;
    $failedCount = 0;
    $today = date('Y-m-d');

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
            // Try each mapping
            foreach ($SLUG_TO_LOTTERY_NAME as $s => $name) {
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

        // Reject future dates
        if ($drawDate > $today) {
            echo "⚠️  {$keyLotteryName}: วันที่ {$drawDate} เป็นอนาคต → ข้าม\n";
            $skippedCount++;
            continue;
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
            // Update if result changed (safety net)
            $needsUpdate = false;
            $updates = [];

            if ($threeTop && $existing['three_top'] !== $threeTop) {
                $updates['three_top'] = $threeTop;
                $updates['two_top'] = $twoTop;
                $updates['run_top'] = $runTop;
                $needsUpdate = true;
            }
            if ($twoBot && $existing['two_bot'] !== $twoBot) {
                $updates['two_bot'] = $twoBot;
                $updates['run_bot'] = $runBot;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $setParts = [];
                $params = [];
                foreach ($updates as $col => $val) {
                    $setParts[] = "{$col} = ?";
                    $params[] = $val;
                }
                $setParts[] = "updated_at = NOW()";
                $params[] = $existing['id'];

                $pdo->prepare("UPDATE results SET " . implode(', ', $setParts) . " WHERE id = ?")->execute($params);
                echo "🔄 {$keyLotteryName}: อัพเดตผล → {$threeTop}/{$twoTop}/{$twoBot}\n";
                logScrape($pdo, $keyLotteryName, $source, 'success', "Updated: {$threeTop}/{$twoBot}", $drawDate);
            } else {
                echo "⏭️  {$keyLotteryName}: มีผลอยู่แล้ว ({$existing['three_top']}/{$existing['two_bot']})\n";
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
        if (($rn['id'] ?? '') === 'runningNumberLastTwo') {
            $twoBottom = $rn['number'][0] ?? '';
            break;
        }
    }

    if (empty($firstPrize)) {
        echo "❌ Rayriffy: ไม่พบรางวัลที่ 1\n";
        logScrape($pdo, 'รัฐบาลไทย', 'rayriffy', 'failed', 'No first prize found');
        return;
    }

    // Convert draw date (YYYY-MM-DD format from API)
    if ($drawDate && strlen($drawDate) === 10) {
        // Already YYYY-MM-DD
    } elseif ($drawDate) {
        // Try to parse
        $drawDate = date('Y-m-d', strtotime($drawDate));
    } else {
        $drawDate = date('Y-m-d');
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
    global $SCRIPT_DIR, $NODE_PATH;

    echo "🏦 GSB Scraper (สลากออมสิน)...\n";
    $data = runScript("{$NODE_PATH} \"{$SCRIPT_DIR}/gsb_scrape.js\"", 'gsb');

    if (!$data || empty($data['success'])) {
        echo "❌ GSB: " . ($data['error'] ?? 'No data') . "\n";
        logScrape($pdo, 'ออมสิน', 'gsb', 'failed', $data['error'] ?? 'No data');
        return;
    }

    $results = $data['results'] ?? [];
    echo "📊 GSB: " . count($results) . " results found\n";

    // Map gsb-1/gsb-2 slugs
    foreach ($results as &$r) {
        $r['slug'] = $r['slug'] ?? 'gsb';
    }
    unset($r);

    $stats = processResults($pdo, $results, 'gsb');
    echo "\n📊 GSB Done! ✅ {$stats['success']} ใหม่, ⏭️ {$stats['skipped']} ข้าม, ❌ {$stats['failed']} ล้มเหลว\n";
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

    $today = date('Y-m-d');

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
// Main
// =============================================
$scraper = $argv[1] ?? 'all';
$startTime = microtime(true);

echo "═══════════════════════════════════════\n";
echo "🎰 Lottery Scraper — " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════\n\n";

switch ($scraper) {
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
        scrapeRaakaadee($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapeStockVip($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapePonhuay24($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapeExphuay($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapeHanoi($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapeLaoVip($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapeLaoSamakki($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapeThaiRayriffy($pdo);
        echo "\n───────────────────────────────────────\n\n";
        scrapeGSB($pdo);
        break;
    default:
        echo "❌ Unknown scraper: {$scraper}\n";
        echo "Usage: php cron_scrape.php [raakaadee|ponhuay24|exphuay|rayriffy|gsb|all]\n";
        exit(1);
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "\n═══════════════════════════════════════\n";
echo "⏱️  เสร็จใน {$elapsed} วินาที\n";
echo "═══════════════════════════════════════\n";

