<?php
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');

$lotteryId = intval($_GET['lottery'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

// Auto-detect lottery if not specified
if (!$lotteryId) {
    $s = $pdo->prepare("SELECT DISTINCT lottery_type_id FROM bets WHERE draw_date = ? AND status != 'cancelled' LIMIT 1");
    $s->execute([$date]);
    $lotteryId = $s->fetchColumn() ?: 0;
}

$lt = $pdo->prepare("SELECT * FROM lottery_types WHERE id = ?");
$lt->execute([$lotteryId]);
$lottery = $lt->fetch();

echo "<h2>Debug: {$lottery['name']} - {$date}</h2>";

// 1. Bills overview
echo "<h3>1. Bills (bets table)</h3>";
$s = $pdo->prepare("
    SELECT b.id, b.bet_number, b.draw_date, b.status, b.total_items, b.total_amount, b.net_amount, b.note, b.created_at
    FROM bets b WHERE b.lottery_type_id = ? AND b.draw_date = ? ORDER BY b.created_at
");
$s->execute([$lotteryId, $date]);
$bills = $s->fetchAll();
echo "<table border='1' cellpadding='4'><tr><th>ID</th><th>BetNo</th><th>Status</th><th>Items</th><th>Total</th><th>Net</th><th>Note</th><th>Created</th></tr>";
$grandTotal = 0;
$grandNet = 0;
$grandItems = 0;
foreach ($bills as $b) {
    $grandTotal += $b['total_amount'];
    $grandNet += $b['net_amount'];
    $grandItems += $b['total_items'];
    echo "<tr><td>{$b['id']}</td><td>{$b['bet_number']}</td><td>{$b['status']}</td><td>{$b['total_items']}</td><td>{$b['total_amount']}</td><td>{$b['net_amount']}</td><td>{$b['note']}</td><td>{$b['created_at']}</td></tr>";
}
echo "<tr style='background:#e8f5e9;font-weight:bold'><td colspan='3'>TOTAL (non-cancelled)</td><td>{$grandItems}</td><td>{$grandTotal}</td><td>{$grandNet}</td><td colspan='2'></td></tr>";
echo "</table>";

// 2. Bet items breakdown by bet_type
echo "<h3>2. Bet Items by Type (bet_items table)</h3>";
$s = $pdo->prepare("
    SELECT bi.bet_type, COUNT(*) as cnt, SUM(bi.amount) as total
    FROM bet_items bi JOIN bets b ON bi.bet_id = b.id
    WHERE b.lottery_type_id = ? AND b.draw_date = ? AND b.status != 'cancelled'
    GROUP BY bi.bet_type ORDER BY bi.bet_type
");
$s->execute([$lotteryId, $date]);
echo "<table border='1' cellpadding='4'><tr><th>Bet Type</th><th>Count</th><th>Total Amount</th></tr>";
$sumItems = 0;
$sumAmount = 0;
foreach ($s->fetchAll() as $r) {
    $sumItems += $r['cnt'];
    $sumAmount += $r['total'];
    echo "<tr><td>{$r['bet_type']}</td><td>{$r['cnt']}</td><td>{$r['total']}</td></tr>";
}
echo "<tr style='background:#e8f5e9;font-weight:bold'><td>TOTAL</td><td>{$sumItems}</td><td>{$sumAmount}</td></tr>";
echo "</table>";

// 3. Per-number breakdown (what win_lose.php calculates)
echo "<h3>3. Per-number aggregation (what win_lose uses)</h3>";
$s = $pdo->prepare("
    SELECT bi.number, bi.bet_type, SUM(bi.amount) as total_amount, COUNT(*) as cnt
    FROM bet_items bi JOIN bets b ON bi.bet_id = b.id
    WHERE b.lottery_type_id = ? AND b.draw_date = ? AND b.status != 'cancelled'
    GROUP BY bi.number, bi.bet_type ORDER BY SUM(bi.amount) DESC
    LIMIT 30
");
$s->execute([$lotteryId, $date]);
echo "<table border='1' cellpadding='4'><tr><th>Number</th><th>Bet Type</th><th>Count</th><th>Total Amount</th></tr>";
foreach ($s->fetchAll() as $r) {
    echo "<tr><td>{$r['number']}</td><td>{$r['bet_type']}</td><td>{$r['cnt']}</td><td>{$r['total_amount']}</td></tr>";
}
echo "</table>";

// 4. Check bet_items vs bets total match
echo "<h3>4. Integrity check: bet_items sum vs bets.total_amount</h3>";
$s = $pdo->prepare("
    SELECT b.id, b.bet_number, b.total_amount, b.net_amount, b.note,
           COALESCE(SUM(bi.amount), 0) as items_sum
    FROM bets b LEFT JOIN bet_items bi ON bi.bet_id = b.id
    WHERE b.lottery_type_id = ? AND b.draw_date = ? AND b.status != 'cancelled'
    GROUP BY b.id
");
$s->execute([$lotteryId, $date]);
echo "<table border='1' cellpadding='4'><tr><th>BetNo</th><th>Note</th><th>bets.total_amount</th><th>SUM(bet_items)</th><th>Match?</th></tr>";
foreach ($s->fetchAll() as $r) {
    $match = (abs($r['total_amount'] - $r['items_sum']) < 0.01) ? '✅' : '❌ diff=' . ($r['items_sum'] - $r['total_amount']);
    $color = (abs($r['total_amount'] - $r['items_sum']) < 0.01) ? '' : 'background:#ffebee';
    echo "<tr style='{$color}'><td>{$r['bet_number']}</td><td>{$r['note']}</td><td>{$r['total_amount']}</td><td>{$r['items_sum']}</td><td>{$match}</td></tr>";
}
echo "</table>";

// 5. Pay rates
echo "<h3>5. Pay rates for this lottery</h3>";
$s = $pdo->prepare("SELECT bet_type, pay_rate FROM pay_rates WHERE lottery_type_id = ?");
$s->execute([$lotteryId]);
echo "<table border='1' cellpadding='4'><tr><th>Bet Type</th><th>Pay Rate</th></tr>";
foreach ($s->fetchAll() as $r) {
    echo "<tr><td>{$r['bet_type']}</td><td>{$r['pay_rate']}</td></tr>";
}
echo "</table>";
echo "<p><small>Script ran at: " . date('Y-m-d H:i:s') . "</small></p>";
