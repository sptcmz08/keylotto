<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'win_lose';
$adminTitle = 'คาดคะเน ได้-เสีย';

// Filters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedLottery = $_GET['lottery'] ?? '';
$sortBy = $_GET['sort'] ?? 'default'; // default, amount_desc, payout_desc
$sortDir = $_GET['dir'] ?? 'desc';
$showLimit = intval($_GET['limit'] ?? 50);
$showMode = $_GET['mode'] ?? 'win_lose'; // win_lose, amount

// Get lottery types grouped by category
$lotteryTypes = $pdo->query("SELECT lt.id, lt.name, lc.name as cat_name, lc.id as cat_id FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id WHERE lt.is_active = 1 ORDER BY lt.category_id, lt.sort_order, lt.name")->fetchAll();

// Get available draw dates for dropdown (last 30 days that have bets)
$dateStmt = $pdo->query("SELECT DISTINCT draw_date FROM bets WHERE status != 'cancelled' AND draw_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY draw_date DESC LIMIT 20");
$availableDates = $dateStmt->fetchAll(PDO::FETCH_COLUMN);

// Auto-select first lottery if none selected
if (empty($selectedLottery) && !empty($lotteryTypes)) {
    // Try to find the first lottery that has bets for this date
    $firstStmt = $pdo->prepare("SELECT DISTINCT b.lottery_type_id FROM bets b WHERE b.draw_date = ? AND b.status != 'cancelled' LIMIT 1");
    $firstStmt->execute([$selectedDate]);
    $firstLottery = $firstStmt->fetchColumn();
    $selectedLottery = $firstLottery ?: $lotteryTypes[0]['id'];
}

// Build WHERE clause
$where = "WHERE b.draw_date = ? AND b.status != 'cancelled' AND b.lottery_type_id = ?";
$params = [$selectedDate, $selectedLottery];

// Get pay rates for this lottery
$rates = [];
$rateStmt = $pdo->prepare("SELECT bet_type, pay_rate FROM pay_rates WHERE lottery_type_id = ?");
$rateStmt->execute([$selectedLottery]);
foreach ($rateStmt->fetchAll() as $r) {
    $rates[$r['bet_type']] = floatval($r['pay_rate']);
}

// Get discount rates
$discountRate = 0; // TODO: if you have discount config, pull it here

// Per-number breakdown
$numberStmt = $pdo->prepare("
    SELECT bi.number, bi.bet_type, SUM(bi.amount) as total_amount, COUNT(*) as total_count
    FROM bet_items bi JOIN bets b ON bi.bet_id = b.id $where
    GROUP BY bi.number, bi.bet_type ORDER BY bi.number
");
$numberStmt->execute($params);

$numberMap = [];
foreach ($numberStmt->fetchAll() as $r) {
    $num = $r['number'];
    if (!isset($numberMap[$num])) $numberMap[$num] = [];
    $numberMap[$num][$r['bet_type']] = ['amount' => floatval($r['total_amount']), 'count' => intval($r['total_count'])];
}

$betTypes = ['3top', '3tod', '2top', '2bot', 'run_top', 'run_bot'];
$betTypeLabels = ['3top' => '3 ตัวบน', '3tod' => '3 ตัวโต๊ด', '2top' => '2 ตัวบน', '2bot' => '2 ตัวล่าง', 'run_top' => 'วิ่งบน', 'run_bot' => 'วิ่งล่าง'];

// Calculate summary totals
$summary = [];
foreach ($betTypes as $bt) {
    $summary[$bt] = ['buy' => 0, 'commission' => 0, 'net' => 0, 'payout' => 0];
}
foreach ($numberMap as $num => $types) {
    foreach ($betTypes as $bt) {
        if (isset($types[$bt])) {
            $amt = $types[$bt]['amount'];
            $rate = $rates[$bt] ?? 0;
            $summary[$bt]['buy'] += $amt;
            $summary[$bt]['payout'] += $amt * $rate;
        }
    }
}

$grandBuy = array_sum(array_column($summary, 'buy'));
$grandCommission = 0;
foreach ($betTypes as $bt) {
    $summary[$bt]['commission'] = $summary[$bt]['buy'] * $discountRate;
    $grandCommission += $summary[$bt]['commission'];
}
$grandNet = $grandBuy - abs($grandCommission);
$grandPayout = array_sum(array_column($summary, 'payout'));

// Build number rows for table
$numberRows = [];
foreach ($numberMap as $num => $types) {
    $row = ['number' => $num, 'cols' => []];
    $rowPayout = 0;
    foreach ($betTypes as $bt) {
        $amt = $types[$bt]['amount'] ?? 0;
        $rate = $rates[$bt] ?? 0;
        $pay = $amt * $rate;
        $row['cols'][$bt] = ['buy' => $amt, 'payout' => $pay];
        $rowPayout += $pay;
    }
    $row['total_payout'] = $rowPayout;
    $numberRows[] = $row;
}

// Sort
if ($sortBy === 'amount_desc') {
    usort($numberRows, function($a, $b) {
        $sumA = array_sum(array_column($a['cols'], 'buy'));
        $sumB = array_sum(array_column($b['cols'], 'buy'));
        return $sumB <=> $sumA;
    });
} elseif ($sortBy === 'payout_desc') {
    usort($numberRows, fn($a, $b) => $b['total_payout'] <=> $a['total_payout']);
}

// Total count
$totalBetCount = 0;
foreach ($numberMap as $types) {
    foreach ($types as $t) $totalBetCount += $t['count'];
}

// Selected lottery info
$selLotteryName = '';
$selCatName = '';
foreach ($lotteryTypes as $lt) {
    if ($lt['id'] == $selectedLottery) {
        $selLotteryName = $lt['name'];
        $selCatName = $lt['cat_name'];
        break;
    }
}

// Group lottery types by category for the selector
$lotteryByCategory = [];
foreach ($lotteryTypes as $lt) {
    $lotteryByCategory[$lt['cat_name']][] = $lt;
}

require_once 'includes/header.php';
?>

<style>
    .wl-table { border-collapse: collapse; width: 100%; font-size: 11px; font-family: Tahoma, Arial, sans-serif; }
    .wl-table th, .wl-table td { border: 1px solid #a5d6a7; padding: 2px 4px; }
    .wl-table thead th { background: #00a65a; color: #fff; font-weight: bold; text-align: center; padding: 4px 4px; white-space: nowrap; }
    .wl-summary { background: #f0fff0; }
    .wl-summary td { font-weight: bold; white-space: nowrap; }
    .wl-summary .label-cell { background: #e8f5e9; text-align: left; padding-left: 8px; font-size: 12px; color: #2e7d32; }
    .wl-row-even { background: #fff; }
    .wl-row-odd { background: #f9fff9; }
    .wl-row-highlight { background: #fff9c4 !important; }
    .neg { color: #d32f2f; }
    .pos { color: #1b5e20; }
    .num-cell { text-align: right; font-family: 'Courier New', monospace; font-size: 11px; }
    .number-badge { display: inline-block; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 2px; padding: 0 4px; font-family: monospace; font-weight: bold; font-size: 12px; min-width: 28px; text-align: center; }
    .filter-bar { background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; padding: 4px 8px; margin-bottom: 6px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 11px; }
    .filter-bar select, .filter-bar input { border: 1px solid #ccc; border-radius: 3px; padding: 2px 4px; font-size: 11px; outline: none; }
    .filter-bar label { color: #666; white-space: nowrap; }
    .btn-refresh { background: #fff; border: 1px solid #00a65a; color: #00a65a; padding: 2px 10px; border-radius: 3px; font-size: 11px; cursor: pointer; }
    .btn-refresh:hover { background: #e8f5e9; }
    .color-legend { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; padding: 2px 6px; border-radius: 2px; cursor: pointer; }
    .fight-input { width: 60px; text-align: center; border: 1px solid #ccc; border-radius: 2px; padding: 1px 2px; font-size: 11px; }
    .btn-save-fight { background: #00a65a; color: #fff; border: none; padding: 2px 10px; border-radius: 2px; font-size: 11px; cursor: pointer; font-weight: bold; }
</style>

<div class="mb-1">
    <!-- Title + Alert -->
    <div style="font-size:14px; font-weight:bold; color:#333; margin-bottom:4px;">คาดคะเน ได้-เสีย</div>
    
    <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:4px; padding:6px 12px; margin-bottom:6px; font-size:12px; display:flex; justify-content:space-between; align-items:center;">
        <span><span style="color:#d32f2f;">⚠</span> <b>เฉพาะงวด</b> [<?= htmlspecialchars($selCatName) ?>] <b><?= htmlspecialchars($selLotteryName) ?></b> วันที่ <b><?= date('d/m/', strtotime($selectedDate)) . (intval(date('Y', strtotime($selectedDate))) + 543) ?></b> <span style="color:#999;">(เปลี่ยนได้ที่แถบเมนูด้านบน)</span></span>
        <button class="btn-refresh" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh (<?= $totalBetCount ?>)</button>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <label>แสดง</label>
        <select name="mode">
            <option value="win_lose" <?= $showMode === 'win_lose' ? 'selected' : '' ?>>คาดคะเน ได้-เสีย ▼</option>
            <option value="amount" <?= $showMode === 'amount' ? 'selected' : '' ?>>ยอดแทง ▼</option>
        </select>
        <label>เรียงลำดับ</label>
        <select name="sort">
            <option value="default" <?= $sortBy === 'default' ? 'selected' : '' ?>>คาดคะเน ยอดแล้ว ▼</option>
            <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>ยอดซื้อ ▼</option>
            <option value="payout_desc" <?= $sortBy === 'payout_desc' ? 'selected' : '' ?>>ยอดจ่าย ▼</option>
        </select>
        <label>เรียงจาก</label>
        <select name="dir">
            <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>มาก > น้อย ▼</option>
            <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>น้อย > มาก ▼</option>
        </select>
        <label>จำนวนแสดง</label>
        <select name="limit">
            <?php foreach ([50, 100, 200, 500, 9999] as $lv): ?>
            <option value="<?= $lv ?>" <?= $showLimit == $lv ? 'selected' : '' ?>><?= $lv == 9999 ? 'ทั้งหมด' : $lv ?> ▼</option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="date" value="<?= $selectedDate ?>">
        <input type="hidden" name="lottery" value="<?= $selectedLottery ?>">
        <button type="submit" style="background:#00a65a; color:#fff; border:none; padding:3px 10px; border-radius:3px; font-size:11px; cursor:pointer;"><i class="fas fa-search"></i></button>
    </form>

    <!-- Color Legend -->
    <div style="margin-bottom:6px; display:flex; gap:6px; flex-wrap:wrap;">
        <span class="color-legend" style="background:#c8e6c9; border:1px solid #a5d6a7;">พื้นหลังสีเขียว = เติมแต้ม</span>
        <span class="color-legend" style="background:#fff9c4; border:1px solid #fdd835;">พื้นหลังสีเหลือง = คุณจดหลังอ = ดูคาดะเอิ้ง</span>
        <span class="color-legend" style="background:#eee; border:1px solid #ccc;">☐ Ctrl+F (ค้นหาเลข)</span>
    </div>

    <!-- Lottery Selector (horizontal tabs) -->
    <div style="background:#fff; border:1px solid #ddd; border-radius:4px; padding:6px; margin-bottom:6px;">
        <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:4px;">
            <?php 
            $dateOptions = '';
            foreach ($availableDates as $d) {
                $sel = ($d === $selectedDate) ? 'selected' : '';
                $dFmt = date('d-m-', strtotime($d)) . (intval(date('Y', strtotime($d))) + 543);
                $dateOptions .= "<option value='{$d}' {$sel}>{$dFmt}</option>";
            }
            ?>
            <select onchange="location.href='?date='+this.value+'&lottery=<?= $selectedLottery ?>'" style="border:1px solid #00a65a; border-radius:3px; padding:3px 8px; font-size:11px; background:#e8f5e9; font-weight:bold; color:#2e7d32;">
                <?= $dateOptions ?>
            </select>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:3px;">
            <?php foreach ($lotteryByCategory as $catName => $lts): ?>
                <?php foreach ($lts as $lt): ?>
                <a href="?date=<?= $selectedDate ?>&lottery=<?= $lt['id'] ?>&sort=<?= $sortBy ?>&limit=<?= $showLimit ?>" 
                   style="display:inline-block; padding:2px 6px; font-size:10px; border-radius:2px; text-decoration:none; white-space:nowrap;
                   <?= $lt['id'] == $selectedLottery ? 'background:#00a65a; color:#fff; font-weight:bold;' : 'background:#f5f5f5; color:#333; border:1px solid #ddd;' ?>">
                    <?= htmlspecialchars($lt['name']) ?>
                </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main Data Table -->
    <div style="overflow-x:auto;">
        <table class="wl-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width:30px;"></th>
                    <th rowspan="2" style="min-width:40px;">รวม</th>
                    <?php foreach ($betTypes as $bt): ?>
                    <th colspan="2"><?= $betTypeLabels[$bt] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <!-- ซื้อ (Buy) -->
                <tr class="wl-summary">
                    <td class="label-cell">ซื้อ</td>
                    <td class="num-cell pos" style="font-size:12px;"><?= number_format($grandBuy, 2) ?></td>
                    <?php foreach ($betTypes as $bt): ?>
                    <td class="num-cell" colspan="2"><?= number_format($summary[$bt]['buy'], 2) ?></td>
                    <?php endforeach; ?>
                </tr>
                <!-- คอมฯ (Commission) -->
                <tr class="wl-summary">
                    <td class="label-cell">คอมฯ</td>
                    <td class="num-cell neg"><?= $grandCommission != 0 ? number_format(-abs($grandCommission), 2) : '0.00' ?></td>
                    <?php foreach ($betTypes as $bt): ?>
                    <td class="num-cell" colspan="2"><?= $summary[$bt]['commission'] != 0 ? number_format(-abs($summary[$bt]['commission']), 2) : '0.00' ?></td>
                    <?php endforeach; ?>
                </tr>
                <!-- รับ (Net Received) -->
                <tr class="wl-summary">
                    <td class="label-cell">รับ</td>
                    <td class="num-cell pos" style="font-size:12px;"><?= number_format($grandNet, 2) ?></td>
                    <?php foreach ($betTypes as $bt): ?>
                    <td class="num-cell" colspan="2"><?= number_format($summary[$bt]['buy'] - abs($summary[$bt]['commission']), 2) ?></td>
                    <?php endforeach; ?>
                </tr>
                <!-- จ่าย (Max Payout) -->
                <tr class="wl-summary">
                    <td class="label-cell" style="color:#d32f2f;">จ่าย</td>
                    <td class="num-cell neg" style="font-size:12px;"><?= number_format(-$grandPayout, 2) ?></td>
                    <?php foreach ($betTypes as $bt): ?>
                    <td class="num-cell neg" colspan="2"><?= $summary[$bt]['payout'] > 0 ? number_format(-$summary[$bt]['payout'], 2) : '0.00' ?></td>
                    <?php endforeach; ?>
                </tr>
                <!-- ตั้งสู้ (Fight Limits) -->
                <tr class="wl-summary" style="background:#e8f5e9;">
                    <td class="label-cell">ตั้งสู้</td>
                    <td class="num-cell"><button class="btn-save-fight" onclick="alert('บันทึกแล้ว')">บันทึก</button></td>
                    <?php foreach ($betTypes as $i => $bt): ?>
                    <td colspan="2" style="text-align:center;"><input type="text" class="fight-input" value="<?= [0, 500, 1000, 1000, 2000, 2000][$i] ?? 0 ?>" id="fight_<?= $bt ?>"></td>
                    <?php endforeach; ?>
                </tr>
                
                <!-- Separator -->
                <tr style="height:4px; background:#00a65a;"><td colspan="<?= 2 + count($betTypes) * 2 ?>"></td></tr>

                <!-- Per-number rows -->
                <?php if (empty($numberRows)): ?>
                <tr><td colspan="<?= 2 + count($betTypes) * 2 ?>" style="padding:20px; text-align:center; color:#999;">ไม่มีข้อมูลในงวดนี้</td></tr>
                <?php else: 
                    $displayed = 0;
                    foreach ($numberRows as $i => $row):
                        if ($displayed >= $showLimit) break;
                        $displayed++;
                        $rowClass = $i % 2 === 0 ? 'wl-row-even' : 'wl-row-odd';
                        $rowBuy = array_sum(array_column($row['cols'], 'buy'));
                ?>
                <tr class="<?= $rowClass ?>" style="cursor:pointer;" onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background=''">
                    <td style="text-align:center; color:#999; font-size:10px;"><?= $displayed ?></td>
                    <td style="text-align:center;"><span class="number-badge"><?= htmlspecialchars($row['number']) ?></span></td>
                    <?php foreach ($betTypes as $bt):
                        $buy = $row['cols'][$bt]['buy'];
                        $pay = $row['cols'][$bt]['payout'];
                    ?>
                    <td class="num-cell"><?= $buy > 0 ? number_format($buy, 2) : '0.00' ?></td>
                    <td class="num-cell <?= $pay > 0 ? 'neg' : '' ?>"><?= $pay > 0 ? number_format(-$pay, 2) : '0.00' ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($displayed) && $displayed < count($numberRows)): ?>
    <div style="text-align:center; padding:6px; font-size:11px; color:#666;">
        แสดง <?= $displayed ?> จาก <?= count($numberRows) ?> เลข — 
        <a href="?date=<?= $selectedDate ?>&lottery=<?= $selectedLottery ?>&sort=<?= $sortBy ?>&limit=9999" style="color:#00a65a;">ดูทั้งหมด</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
