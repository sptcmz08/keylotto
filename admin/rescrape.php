<?php
/**
 * Admin API: ลบผลรางวัลแล้วดึงใหม่จาก cron_scrape.php
 * Usage: POST { result_id: 123 }
 */
require_once __DIR__ . '/../auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$resultId = intval($_POST['result_id'] ?? 0);
if (!$resultId) {
    echo json_encode(['error' => 'Missing result_id']);
    exit;
}

// Get result info before delete
$stmt = $pdo->prepare("SELECT r.*, lt.name as lottery_name FROM results r JOIN lottery_types lt ON r.lottery_type_id = lt.id WHERE r.id = ?");
$stmt->execute([$resultId]);
$result = $stmt->fetch();

if (!$result) {
    echo json_encode(['error' => 'ไม่พบผลรางวัลนี้']);
    exit;
}

$lotteryName = $result['lottery_name'];
$drawDate = $result['draw_date'];

// Delete the result
$pdo->prepare("DELETE FROM results WHERE id = ?")->execute([$resultId]);

// Reset any bets that were calculated from the wrong result back to pending
$resetStmt = $pdo->prepare("
    UPDATE bets SET status = 'pending', win_amount = 0 
    WHERE lottery_type_id = ? AND draw_date = ? AND status IN ('won', 'lost')
");
$resetStmt->execute([$result['lottery_type_id'], $drawDate]);
$resetCount = $resetStmt->rowCount();

// Trigger cron_scrape to re-fetch
$cronPath = realpath(__DIR__ . '/../cron_scrape.php');
$output = [];
$exitCode = 0;
exec("php \"{$cronPath}\" all 2>&1", $output, $exitCode);
$cronOutput = implode("\n", $output);

// Check if result was re-fetched
$checkStmt = $pdo->prepare("SELECT * FROM results WHERE lottery_type_id = ? AND draw_date = ?");
$checkStmt->execute([$result['lottery_type_id'], $drawDate]);
$newResult = $checkStmt->fetch();

echo json_encode([
    'success' => true,
    'deleted' => $lotteryName . ' (' . $drawDate . ')',
    'bets_reset' => $resetCount,
    'refetched' => $newResult ? true : false,
    'new_result' => $newResult ? [
        'three_top' => $newResult['three_top'],
        'two_bot' => $newResult['two_bot'],
    ] : null,
    'cron_output' => substr($cronOutput, -2000),
], JSON_UNESCAPED_UNICODE);
