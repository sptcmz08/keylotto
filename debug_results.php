<?php
/**
 * Debug: ตรวจสอบ results ใน DB
 * เรียก: php debug_results.php
 * แสดงผลลัพธ์ที่มีอยู่วันนี้ vs close_time ของหวยนั้น
 */
require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Bangkok');

$now = time();
$systemTime = $now - (4 * 3600);
$today = date('Y-m-d', $systemTime);

echo "=== Debug Results ===\n";
echo "Server time: " . date('Y-m-d H:i:s') . "\n";
echo "System date (shifted -4h): $today\n\n";

// ดึงผลของวันนี้ทั้งหมด
$stmt = $pdo->prepare("
    SELECT 
        r.id, r.lottery_type_id, r.draw_date, r.three_top, r.two_bot, r.created_at,
        lt.name, lt.close_time, lt.result_time
    FROM results r
    JOIN lottery_types lt ON r.lottery_type_id = lt.id
    WHERE r.draw_date = ?
    ORDER BY lt.close_time ASC
");
$stmt->execute([$today]);
$results = $stmt->fetchAll();

echo "Results for $today: " . count($results) . " records\n";
echo str_repeat('-', 100) . "\n";
printf("%-4s %-25s %-12s %-10s %-10s %-10s %-10s %-20s\n", 
    "ID", "Lottery", "draw_date", "3top", "2bot", "close", "result_t", "created_at");
echo str_repeat('-', 100) . "\n";

$suspicious = [];
foreach ($results as $r) {
    $closeTimeStr = $today . ' ' . $r['close_time'];
    $closeTs = strtotime($closeTimeStr);
    $isPastClose = $now >= $closeTs;
    $flag = '';
    
    // ถ้ายังไม่ถึงเวลาปิดรับ แต่มีผลแล้ว → น่าสงสัย
    if (!$isPastClose) {
        $flag = ' *** SUSPICIOUS (not past close_time yet)';
        $suspicious[] = $r;
    }
    
    printf("%-4d %-25s %-12s %-10s %-10s %-10s %-10s %-20s%s\n",
        $r['id'], $r['name'], $r['draw_date'], $r['three_top'], $r['two_bot'],
        $r['close_time'], $r['result_time'], $r['created_at'], $flag);
}

echo "\n";
if (!empty($suspicious)) {
    echo "⚠ SUSPICIOUS: " . count($suspicious) . " results exist BEFORE close_time!\n";
    echo "These lotteries haven't closed yet but already have results:\n";
    foreach ($suspicious as $s) {
        echo "  - {$s['name']} (close: {$s['close_time']}, result: {$s['three_top']}/{$s['two_bot']})\n";
    }
    echo "\nTo DELETE suspicious results:\n";
    $ids = implode(',', array_column($suspicious, 'id'));
    echo "  DELETE FROM results WHERE id IN ($ids);\n";
} else {
    echo "✅ All results are after close_time — data looks OK\n";
}
