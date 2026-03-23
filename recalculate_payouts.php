<?php
/**
 * =============================================
 * Recalculate Payouts — คำนวณผลรางวัลย้อนหลัง
 * =============================================
 * ใช้สำหรับคำนวณผลโพยที่ค้างสถานะ "pending" 
 * แต่มีผลหวยออกแล้วใน results table
 *
 * Usage:
 *   php recalculate_payouts.php              ← คำนวณทุกงวดที่มีผล
 *   php recalculate_payouts.php 2026-03-23   ← คำนวณเฉพาะงวดวันที่ระบุ
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cron_scrape.php';

date_default_timezone_set('Asia/Bangkok');

$targetDate = $argv[1] ?? null;

echo "═══════════════════════════════════════\n";
echo "💰 Recalculate Payouts — " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════\n\n";

// Find all pending bets that have results
$sql = "
    SELECT DISTINCT b.lottery_type_id, b.draw_date, lt.name as lottery_name, COUNT(*) as pending_count
    FROM bets b
    JOIN lottery_types lt ON b.lottery_type_id = lt.id
    JOIN results r ON r.lottery_type_id = b.lottery_type_id AND r.draw_date = b.draw_date
    WHERE b.status = 'pending' AND r.three_top IS NOT NULL
";
$params = [];

if ($targetDate) {
    $sql .= " AND b.draw_date = ?";
    $params[] = $targetDate;
    echo "📅 เฉพาะงวด: {$targetDate}\n\n";
} else {
    echo "📅 ทุกงวดที่มีผลแล้ว\n\n";
}

$sql .= " GROUP BY b.lottery_type_id, b.draw_date ORDER BY b.draw_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo "✅ ไม่มีโพยค้าง — ทุกอย่างถูกคำนวณแล้ว!\n";
    exit;
}

$totalProcessed = 0;
echo "📋 พบ " . count($rows) . " หวย/งวด ที่ต้องคำนวณ:\n\n";

foreach ($rows as $row) {
    echo "🎰 {$row['lottery_name']} งวด {$row['draw_date']} ({$row['pending_count']} โพยค้าง)... ";
    
    $count = processBetPayouts($pdo, $row['lottery_type_id'], $row['draw_date']);
    $totalProcessed += $count;
    
    echo "✅ คำนวณ {$count} โพย\n";
}

echo "\n═══════════════════════════════════════\n";
echo "✅ เสร็จ! คำนวณทั้งหมด {$totalProcessed} โพย\n";
echo "═══════════════════════════════════════\n";
