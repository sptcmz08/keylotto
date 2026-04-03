<?php
/**
 * One-off repair: cancel overdue bets for lotteries with no usable result.
 *
 * Usage:
 *   php repair_overdue_no_draw.php
 *   php repair_overdue_no_draw.php --draw-date=2026-04-03
 *   php repair_overdue_no_draw.php --lottery-id=6
 *   php repair_overdue_no_draw.php --lottery-name="หุ้นไต้หวัน"
 *   php repair_overdue_no_draw.php --dry-run
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cron_scrape.php';

date_default_timezone_set('Asia/Bangkok');

$args = $argv;
array_shift($args);

$options = [
    'draw-date' => null,
    'lottery-id' => null,
    'lottery-name' => null,
    'dry-run' => false,
];

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $options['dry-run'] = true;
        continue;
    }

    if (preg_match('/^--([^=]+)=(.*)$/', $arg, $m)) {
        $key = $m[1];
        $value = $m[2];
        if (array_key_exists($key, $options)) {
            $options[$key] = $value;
        }
    }
}

$referenceDate = $options['draw-date'] ?: date('Y-m-d');
$todayShifted = date('Y-m-d', strtotime($referenceDate . ' -4 hours'));
$todayReal = $referenceDate;
$now = time();

$where = [
    'lt.is_active = 1',
    'lt.result_time IS NOT NULL',
];
$params = [];

if (!empty($options['lottery-id'])) {
    $where[] = 'lt.id = ?';
    $params[] = (int)$options['lottery-id'];
}

if (!empty($options['lottery-name'])) {
    $where[] = 'lt.name = ?';
    $params[] = $options['lottery-name'];
}

$sql = "
    SELECT lt.id, lt.name, lt.result_time, lt.close_time, lt.open_time, lt.draw_schedule
    FROM lottery_types lt
    WHERE " . implode(' AND ', $where) . "
    ORDER BY lt.result_time ASC, lt.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lotteries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($lotteries)) {
    echo "ไม่พบหวยที่ตรงเงื่อนไข\n";
    exit(0);
}

$scanned = 0;
$matched = 0;
$updatedTotal = 0;

echo "═══════════════════════════════════════\n";
echo "Repair Overdue No-Draw — " . date('Y-m-d H:i:s') . "\n";
echo "Reference date: {$referenceDate}\n";
echo $options['dry-run'] ? "Mode: DRY RUN\n" : "Mode: APPLY\n";
echo "═══════════════════════════════════════\n\n";

foreach ($lotteries as $lt) {
    $scanned++;

    $expectedDate = getCurrentDrawDate($lt['draw_schedule'] ?? 'daily', $referenceDate);
    if (!empty($options['draw-date'])) {
        $expectedDate = $referenceDate;
    }

    $drawDates = array_values(array_unique(array_filter([$expectedDate, $todayShifted, $todayReal])));
    if (findUsableResultForDates($pdo, $lt['id'], $drawDates)) {
        continue;
    }

    $resultTimeStr = $expectedDate . ' ' . $lt['result_time'];
    $resultTimestamp = strtotime($resultTimeStr);

    $openHour = intval(substr($lt['open_time'] ?? '06:00:00', 0, 2));
    $resultHour = intval(substr($lt['result_time'], 0, 2));
    if ($resultHour < $openHour && $resultHour < 6) {
        $resultTimeStr = date('Y-m-d', strtotime($expectedDate . ' +1 day')) . ' ' . $lt['result_time'];
        $resultTimestamp = strtotime($resultTimeStr);
    }

    if (!$resultTimestamp) {
        echo "⚠️  {$lt['name']}: แปลง result_time ไม่สำเร็จ ({$lt['result_time']})\n";
        continue;
    }

    $hoursPast = ($now - $resultTimestamp) / 3600;
    if ($hoursPast <= 2) {
        continue;
    }

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM bets
        WHERE lottery_type_id = ?
          AND draw_date IN (" . implode(',', array_fill(0, count($drawDates), '?')) . ")
          AND status IN ('pending', 'lost')
    ");
    $countStmt->execute(array_merge([$lt['id']], $drawDates));
    $repairable = (int)$countStmt->fetchColumn();

    if ($repairable <= 0) {
        continue;
    }

    $matched++;

    if ($options['dry-run']) {
        echo "🧪 {$lt['name']} [" . implode(', ', $drawDates) . "] -> จะปรับ {$repairable} โพยเป็น cancelled\n";
        continue;
    }

    $updated = cancelBetsBecauseNoResult($pdo, $lt['id'], $drawDates, 'repair_timeout');
    if ($updated > 0) {
        echo "✅ {$lt['name']} [" . implode(', ', $drawDates) . "] -> ปรับ {$updated} โพยเป็น cancelled\n";
        logScrape($pdo, $lt['name'], 'repair_no_draw', 'success', "Repair cancelled {$updated} overdue no-draw bets", $expectedDate);
        $updatedTotal += $updated;
    }
}

echo "\n───────────────────────────────────────\n";
echo "Scanned: {$scanned}\n";
echo "Matched: {$matched}\n";
echo "Updated: {$updatedTotal}\n";

