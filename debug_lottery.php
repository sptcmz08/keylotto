<?php
/**
 * Debug: simulate index.php logic for ลาวพัฒนา
 */
require_once 'config.php';

echo "=== Simulate index.php for ลาวพัฒนา ===\n\n";

// Same query as index.php
$stmt = $pdo->query("
    SELECT 
        lt.id, lt.name, lt.flag_emoji, lt.draw_date, lt.open_time, lt.close_time,
        lt.result_time, lt.bet_closed, lt.category_id, lt.draw_schedule,
        r.three_top, r.two_bot, r.draw_date as result_date, r.created_at as result_created_at
    FROM lottery_types lt
    LEFT JOIN results r ON r.lottery_type_id = lt.id 
        AND r.draw_date = (SELECT MAX(r2.draw_date) FROM results r2 WHERE r2.lottery_type_id = lt.id)
    WHERE lt.is_active = 1
");
$allLotteries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total lotteries from DB: " . count($allLotteries) . "\n";

// Build lotteryMap same as index.php
$now = time();
$today = date('Y-m-d');
$lotteryMap = [];

foreach ($allLotteries as &$l) {
    $drawSchedule = $l['draw_schedule'] ?? 'daily';
    $currentRoundDate = getCurrentDrawDate($drawSchedule);
    $resultDate = $l['result_date'] ?? null;
    $hasAnyResult = !empty($l['three_top']);
    $hasResultForCurrentRound = $hasAnyResult && $resultDate === $currentRoundDate;
    
    $resultCreatedAt = !empty($l['result_created_at']) ? strtotime($l['result_created_at']) : 0;
    $timeSinceResult = $resultCreatedAt ? ($now - $resultCreatedAt) : PHP_INT_MAX;
    
    if (!$hasResultForCurrentRound && $hasAnyResult && $timeSinceResult < 3600) {
        $hasResultForCurrentRound = true;
        $currentRoundDate = $resultDate;
    }
    
    $l['current_round_date'] = $currentRoundDate;
    $l['has_result_current_round'] = $hasResultForCurrentRound;
    
    $lotteryMap[$l['name']] = $l;
}
unset($l);

echo "Total in lotteryMap: " . count($lotteryMap) . "\n\n";

// Check ลาวพัฒนา
$target = 'ลาวพัฒนา';
if (isset($lotteryMap[$target])) {
    $lt = $lotteryMap[$target];
    echo "✅ ลาวพัฒนา FOUND in lotteryMap!\n";
    echo "   round_date: {$lt['current_round_date']}\n";
    echo "   has_result: " . ($lt['has_result_current_round'] ? 'YES' : 'NO') . "\n";
    echo "   three_top: " . ($lt['three_top'] ?? 'NULL') . "\n";
} else {
    echo "❌ ลาวพัฒนา NOT in lotteryMap!\n";
    echo "Keys containing 'ลาว' or 'พัฒนา':\n";
    foreach (array_keys($lotteryMap) as $k) {
        if (strpos($k, 'ลาว') !== false || strpos($k, 'พัฒนา') !== false) {
            echo "   '$k' (hex: " . bin2hex($k) . ")\n";
        }
    }
}

// Check LOTTERY_GROUPS
echo "\n=== Check display ===\n";
$LOTTERY_GROUPS_STOCK = [
    'นิเคอิ - เช้า', 'นิเคอิ - บ่าย',
    'หุ้นจีน - เช้า', 'หุ้นจีน - บ่าย',
    'ฮั่งเส็ง - เช้า', 'ฮั่งเส็ง - บ่าย',
    'หุ้นไต้หวัน', 'หุ้นเกาหลี', 'หุ้นสิงคโปร์',
    'หุ้นไทย - เย็น', 'หุ้นอินเดีย', 'หุ้นอียิปต์',
    'ลาวพัฒนา', 'หวย 12 ราศี',
    'หุ้นอังกฤษ', 'หุ้นเยอรมัน', 'หุ้นรัสเซีย',
    'ดาวโจนส์ STAR', 'หุ้นดาวโจนส์',
];

echo "หวยหุ้น group check:\n";
foreach ($LOTTERY_GROUPS_STOCK as $name) {
    $lt = $lotteryMap[$name] ?? null;
    $status = $lt ? "✅ ID={$lt['id']} result=" . ($lt['three_top'] ?? 'none') : "❌ NOT IN MAP";
    echo "   {$name} → {$status}\n";
}

echo "\n=== Done ===\n";
