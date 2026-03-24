<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'win_lose';
$adminTitle = 'คาดคะเน ได้-เสีย';

// ==========================================
// AJAX: Drill-down — รายละเอียดเลข
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'drill_down') {
    header('Content-Type: application/json; charset=utf-8');
    $number = $_GET['number'] ?? '';
    $betType = $_GET['bet_type'] ?? '';
    $lotteryId = intval($_GET['lottery'] ?? 0);
    $drawDate = $_GET['date'] ?? '';
    
    $stmt = $pdo->prepare("
        SELECT bi.number, bi.bet_type, bi.amount, bi.pay_rate AS item_pay_rate,
               b.bet_number, b.created_at, b.note,
               pr.pay_rate
        FROM bet_items bi
        JOIN bets b ON bi.bet_id = b.id
        LEFT JOIN pay_rates pr ON pr.lottery_type_id = b.lottery_type_id AND pr.bet_type = bi.bet_type
        WHERE b.draw_date = ? AND b.lottery_type_id = ? AND b.status != 'cancelled'
              AND bi.number = ? AND bi.bet_type = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$drawDate, $lotteryId, $number, $betType]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['items' => $items]);
    exit;
}

// ==========================================
// AJAX: Save fight limits (ตั้งสู้)
// ==========================================
if (isset($_POST['ajax']) && $_POST['ajax'] === 'save_fight') {
    header('Content-Type: application/json; charset=utf-8');
    $lotteryId = intval($_POST['lottery_id'] ?? 0);
    $limits = json_decode($_POST['limits'] ?? '{}', true);
    
    if (!$lotteryId || empty($limits)) {
        echo json_encode(['success' => false, 'error' => 'missing data']);
        exit;
    }
    
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fight_limits` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lottery_type_id` INT NOT NULL,
        `bet_type` VARCHAR(20) NOT NULL,
        `max_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_lottery_bet` (`lottery_type_id`, `bet_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $stmt = $pdo->prepare("INSERT INTO fight_limits (lottery_type_id, bet_type, max_amount) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE max_amount = VALUES(max_amount)");
    
    foreach ($limits as $betType => $amount) {
        $stmt->execute([$lotteryId, $betType, floatval($amount)]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

// ==========================================
// Filters
// ==========================================
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedLottery = $_GET['lottery'] ?? '';
$sortBy = $_GET['sort'] ?? 'amount_desc'; // Default: sort by highest amount
$showLimit = intval($_GET['limit'] ?? 200);

// Get lottery types
$lotteryTypes = $pdo->query("
    SELECT lt.id, lt.name, lt.flag_emoji, lt.open_time, lt.close_time, lt.bet_closed,
           lc.name as cat_name, lc.id as cat_id
    FROM lottery_types lt 
    JOIN lottery_categories lc ON lt.category_id = lc.id 
    WHERE lt.is_active = 1 
    ORDER BY lc.sort_order, lt.sort_order, lt.name
")->fetchAll();

// Auto-select first lottery with bets for this date
if (empty($selectedLottery) && !empty($lotteryTypes)) {
    $firstStmt = $pdo->prepare("SELECT DISTINCT lottery_type_id FROM bets WHERE draw_date = ? AND status != 'cancelled' LIMIT 1");
    $firstStmt->execute([$selectedDate]);
    $firstLottery = $firstStmt->fetchColumn();
    $selectedLottery = $firstLottery ?: $lotteryTypes[0]['id'];
}

// Available draw dates
$availableDates = $pdo->prepare("
    SELECT DISTINCT draw_date FROM (
        SELECT draw_date FROM results WHERE lottery_type_id = ?
        UNION
        SELECT draw_date FROM bets WHERE lottery_type_id = ? AND status != 'cancelled'
    ) combined
    ORDER BY draw_date DESC
    LIMIT 50
");
$availableDates->execute([$selectedLottery, $selectedLottery]);
$availableDates = $availableDates->fetchAll(PDO::FETCH_COLUMN);

if (!empty($availableDates) && !in_array($selectedDate, $availableDates)) {
    $selectedDate = $availableDates[0];
}

$where = "WHERE b.draw_date = ? AND b.status != 'cancelled' AND b.lottery_type_id = ?";
$params = [$selectedDate, $selectedLottery];

// Pay rates
$rates = [];
$rateStmt = $pdo->prepare("SELECT bet_type, pay_rate FROM pay_rates WHERE lottery_type_id = ?");
$rateStmt->execute([$selectedLottery]);
foreach ($rateStmt->fetchAll() as $r) $rates[$r['bet_type']] = floatval($r['pay_rate']);

// Fight limits (ตั้งสู้)
$fightLimits = ['3top'=>0,'3tod'=>0,'2top'=>0,'2bot'=>0,'run_top'=>0,'run_bot'=>0];
try {
    $flStmt = $pdo->prepare("SELECT bet_type, max_amount FROM fight_limits WHERE lottery_type_id = ?");
    $flStmt->execute([$selectedLottery]);
    foreach ($flStmt->fetchAll() as $fl) $fightLimits[$fl['bet_type']] = floatval($fl['max_amount']);
} catch (Exception $e) {} // table may not exist yet

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
$betTypeLabels = ['3top'=>'3 ตัวบน','3tod'=>'3 ตัวโต๊ด','2top'=>'2 ตัวบน','2bot'=>'2 ตัวล่าง','run_top'=>'วิ่งบน','run_bot'=>'วิ่งล่าง'];

// Summary
$summary = [];
foreach ($betTypes as $bt) $summary[$bt] = ['buy'=>0,'payout'=>0];
foreach ($numberMap as $num => $types) {
    foreach ($betTypes as $bt) {
        if (isset($types[$bt])) {
            $summary[$bt]['buy'] += $types[$bt]['amount'];
            $summary[$bt]['payout'] += $types[$bt]['amount'] * ($rates[$bt] ?? 0);
        }
    }
}
$grandBuy = array_sum(array_column($summary, 'buy'));
$grandPayout = array_sum(array_column($summary, 'payout'));

// Number rows — ตัวเลขคู่กับราคาที่ซื้อ
$numberRows = [];
foreach ($numberMap as $num => $types) {
    $row = ['number' => $num, 'cols' => []];
    $rowBuy = 0;
    $rowPayout = 0;
    foreach ($betTypes as $bt) {
        $amt = $types[$bt]['amount'] ?? 0;
        $pay = $amt * ($rates[$bt] ?? 0);
        $row['cols'][$bt] = ['buy' => $amt, 'payout' => $pay];
        $rowBuy += $amt;
        $rowPayout += $pay;
    }
    $row['total_buy'] = $rowBuy;
    $row['total_payout'] = $rowPayout;
    $numberRows[] = $row;
}

// Sort — default sort by highest total buy amount
if ($sortBy === 'amount_desc') usort($numberRows, fn($a,$b) => $b['total_buy'] <=> $a['total_buy']);
elseif ($sortBy === 'payout_desc') usort($numberRows, fn($a,$b) => $b['total_payout'] <=> $a['total_payout']);
// 'default' = no sort (by number order)

$totalBetCount = 0;
foreach ($numberMap as $types) foreach ($types as $t) $totalBetCount += $t['count'];

// Selected lottery info
$selLotteryName = ''; $selCatName = ''; $selFlagEmoji = '';
foreach ($lotteryTypes as $lt) {
    if ($lt['id'] == $selectedLottery) {
        $selLotteryName = $lt['name'];
        $selCatName = $lt['cat_name'];
        $selFlagEmoji = $lt['flag_emoji'] ?? '';
        break;
    }
}
$selFlagUrl = getFlagForCountry($selFlagEmoji, $selLotteryName);

// Group by category for dropdown
$lotteryByCategory = [];
foreach ($lotteryTypes as $lt) $lotteryByCategory[$lt['cat_name']][] = $lt;

// Date status — simplified 2 levels
$dateStatusMap = [];
foreach ($availableDates as $drawDate) {
    $resChk = $pdo->prepare("SELECT three_top FROM results WHERE lottery_type_id = ? AND draw_date = ? LIMIT 1");
    $resChk->execute([$selectedLottery, $drawDate]);
    $resRow = $resChk->fetch();
    $hasResult = !empty($resRow['three_top']);
    
    if ($hasResult) {
        $dateStatusMap[$drawDate] = ['label' => 'จ่ายเงินแล้ว', 'color' => '#28a745'];
    } else {
        $dateStatusMap[$drawDate] = ['label' => 'รอออกผล', 'color' => '#007bff'];
    }
}

$catColors = ['หวยชุด'=>'#6d0000','หวยไทย'=>'#1565C0','หวยต่างประเทศ'=>'#2E7D32','หวยรายวัน'=>'#C62828','หุ้นหลัก'=>'#4A148C','หวย One'=>'#E65100'];

require_once 'includes/header.php';
?>

<style>
    .wl-page { font-family: Tahoma, Arial, sans-serif; font-size: 11px; }
    .flag-sm { width: 22px; height: 14px; object-fit: cover; border-radius: 2px; border: 1px solid rgba(0,0,0,0.15); vertical-align: middle; }
    .flag-xs { width: 18px; height: 12px; object-fit: cover; border-radius: 2px; border: 1px solid rgba(0,0,0,0.1); vertical-align: middle; }
    .wl-header-bar { background: #00a65a; padding: 6px 12px; display: flex; align-items: center; gap: 12px; border-radius: 4px 4px 0 0; position: relative; }
    .selector-btn { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); color: #fff; padding: 5px 14px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .selector-btn:hover { background: rgba(255,255,255,0.25); }
    .dropdown-panel { display: none; position: absolute; top: 100%; left: 0; z-index: 100; background: #fff; border: 2px solid #00a65a; border-radius: 0 0 6px 6px; box-shadow: 0 4px 16px rgba(0,0,0,0.25); min-width: 720px; max-height: 480px; overflow-y: auto; }
    .dropdown-panel.show { display: block; }
    .cat-hdr { background: #00a65a; color: #fff; font-weight: bold; font-size: 12px; text-align: center; padding: 4px 8px; }
    .cat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; padding: 2px 4px; }
    .lot-item { display: flex; align-items: center; gap: 5px; padding: 4px 8px; font-size: 11px; cursor: pointer; border-radius: 3px; white-space: nowrap; text-decoration: none; color: #333; }
    .lot-item:hover { background: #e8f5e9; }
    .lot-item.active { background: #c8e6c9; font-weight: bold; color: #1b5e20; }
    .date-dropdown { display: none; position: absolute; top: 100%; z-index: 100; background: #fff; border: 2px solid #00a65a; border-radius: 0 0 6px 6px; box-shadow: 0 4px 16px rgba(0,0,0,0.25); min-width: 260px; }
    .date-dropdown.show { display: block; }
    .date-dropdown a { display: flex; justify-content: space-between; align-items: center; padding: 7px 14px; font-size: 12px; color: #333; text-decoration: none; border-bottom: 1px solid #eee; }
    .date-dropdown a:hover { background: #e8f5e9; }
    .date-dropdown a.active { background: #fff9c4; font-weight: bold; }
    .status-badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: bold; color: #fff; }
    .wl-table { border-collapse: collapse; width: 100%; font-size: 11px; font-family: Tahoma, Arial, sans-serif; }
    .wl-table th, .wl-table td { border: 1px solid #a5d6a7; padding: 2px 4px; }
    .wl-table thead th { background: #00a65a; color: #fff; font-weight: bold; text-align: center; padding: 4px; white-space: nowrap; }
    .wl-summary { background: #f0fff0; }
    .wl-summary td { font-weight: bold; white-space: nowrap; }
    .wl-summary .label-cell { background: #e8f5e9; text-align: left; padding-left: 8px; font-size: 12px; color: #2e7d32; }
    .neg { color: #d32f2f; }
    .pos { color: #1b5e20; }
    .num-cell { text-align: right; font-family: 'Courier New', monospace; font-size: 11px; }
    .number-badge { display: inline-block; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 2px; padding: 0 4px; font-family: monospace; font-weight: bold; font-size: 12px; min-width: 28px; text-align: center; cursor: pointer; }
    .number-badge:hover { background: #c8e6c9; }
    .filter-bar { background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; padding: 4px 8px; margin-bottom: 6px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 11px; }
    .filter-bar select { border: 1px solid #ccc; border-radius: 3px; padding: 2px 4px; font-size: 11px; }
    .btn-refresh { background: #fff; border: 1px solid #00a65a; color: #00a65a; padding: 3px 12px; border-radius: 3px; font-size: 11px; cursor: pointer; font-weight: bold; }
    .btn-refresh:hover { background: #e8f5e9; }
    .fight-input { width: 60px; text-align: center; border: 1px solid #ccc; border-radius: 2px; padding: 1px 2px; font-size: 11px; }
    .btn-save-fight { background: #00a65a; color: #fff; border: none; padding: 2px 10px; border-radius: 2px; font-size: 11px; cursor: pointer; font-weight: bold; }
    .wl-row-even { background: #fff; }
    .wl-row-odd { background: #f9fff9; }
    .clickable-amount { cursor: pointer; text-decoration: underline; text-decoration-style: dotted; }
    .clickable-amount:hover { color: #1565c0; font-weight: bold; }
    .exceed-limit { background: #ffebee !important; }
    /* Drill-down modal */
    .drill-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; }
    .drill-content { background: #fff; margin: 5% auto; max-width: 700px; border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); overflow: hidden; max-height: 80vh; display: flex; flex-direction: column; }
    .drill-header { background: #00a65a; color: #fff; padding: 10px 16px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
    .drill-body { overflow-y: auto; padding: 0; flex: 1; }
    .drill-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .drill-table th { background: #e8f5e9; padding: 6px 8px; border: 1px solid #c8e6c9; font-weight: bold; text-align: center; position: sticky; top: 0; }
    .drill-table td { padding: 5px 8px; border: 1px solid #e0e0e0; }
    .drill-table tr:hover { background: #f5f5f5; }
</style>

<div class="wl-page">
    <!-- GREEN HEADER BAR -->
    <div class="wl-header-bar" id="wlHeaderBar">
        <!-- Lottery Selector -->
        <div style="position:relative;">
            <button class="selector-btn" onclick="togglePanel('lotteryPanel'); event.stopPropagation();">
                <img src="<?= $selFlagUrl ?>" class="flag-sm"> <?= htmlspecialchars($selLotteryName) ?> <i class="fas fa-caret-down"></i>
            </button>
            <div class="dropdown-panel" id="lotteryPanel">
                <?php foreach ($lotteryByCategory as $catName => $lts): 
                    $catColor = $catColors[$catName] ?? '#00a65a';
                ?>
                <div class="cat-hdr" style="background:<?= $catColor ?>"><?= htmlspecialchars($catName) ?></div>
                <div class="cat-grid">
                    <?php foreach ($lts as $lt):
                        $fUrl = getFlagForCountry($lt['flag_emoji'] ?? '', $lt['name']);
                    ?>
                    <a href="?date=<?= $selectedDate ?>&lottery=<?= $lt['id'] ?>&sort=<?= $sortBy ?>&limit=<?= $showLimit ?>"
                       class="lot-item <?= $lt['id'] == $selectedLottery ? 'active' : '' ?>">
                        <img src="<?= $fUrl ?>" class="flag-xs"> <?= htmlspecialchars($lt['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Date Selector -->
        <div style="position:relative;">
            <button class="selector-btn" onclick="togglePanel('datePanel'); event.stopPropagation();">
                📅 งวด <?= date('d/m/Y', strtotime($selectedDate)) ?> <i class="fas fa-caret-down"></i>
            </button>
            <div class="date-dropdown" id="datePanel">
                <?php foreach ($availableDates as $d): 
                    $dFmt = date('d/m/Y', strtotime($d));
                    $st = $dateStatusMap[$d] ?? ['label'=>'—','color'=>'#999'];
                ?>
                <a href="?date=<?= $d ?>&lottery=<?= $selectedLottery ?>&sort=<?= $sortBy ?>&limit=<?= $showLimit ?>"
                   class="<?= $d === $selectedDate ? 'active' : '' ?>">
                    <span><?= $dFmt ?></span>
                    <span class="status-badge" style="background:<?= $st['color'] ?>"><?= $st['label'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div style="margin-left:auto;">
            <button class="btn-refresh" style="background:rgba(255,255,255,0.9); border-color:#fff;" onclick="location.reload()">Refresh (<?= $totalBetCount ?>)</button>
        </div>
    </div>

    <!-- TITLE + ALERT -->
    <div style="padding:6px 0;">
        <div style="font-size:14px; font-weight:bold; color:#333; margin-bottom:4px;">คาดคะเน ได้-เสีย</div>
        <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:4px; padding:5px 12px; font-size:11px;">
            <span style="color:#d32f2f;">⚠</span> <b>เฉพาะงวด</b> [<?= htmlspecialchars($selCatName) ?>] <b><?= htmlspecialchars($selLotteryName) ?></b> วันที่ <b><?= date('d/m/Y', strtotime($selectedDate)) ?></b> <span style="color:#999;">(เปลี่ยนได้ที่แถบเมนูด้านบน)</span>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" class="filter-bar">
        <label>เรียงลำดับ</label>
        <select name="sort">
            <option value="amount_desc" <?= $sortBy==='amount_desc'?'selected':'' ?>>ยอดซื้อมากสุด</option>
            <option value="payout_desc" <?= $sortBy==='payout_desc'?'selected':'' ?>>ยอดจ่ายมากสุด</option>
            <option value="default" <?= $sortBy==='default'?'selected':'' ?>>ตามลำดับเลข</option>
        </select>
        <label>จำนวนแสดง</label>
        <select name="limit">
            <?php foreach ([50,100,200,500,9999] as $lv): ?>
            <option value="<?= $lv ?>" <?= $showLimit==$lv?'selected':'' ?>><?= $lv==9999?'ทั้งหมด':$lv ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="date" value="<?= $selectedDate ?>">
        <input type="hidden" name="lottery" value="<?= $selectedLottery ?>">
        <button type="submit" style="background:#00a65a; color:#fff; border:none; padding:3px 10px; border-radius:3px; cursor:pointer;"><i class="fas fa-search"></i></button>
    </form>

    <!-- MAIN TABLE -->
    <div style="overflow-x:auto;">
        <table class="wl-table">
            <thead><tr>
                <th style="width:30px;">#</th><th style="min-width:45px;">เลข</th><th>รวม</th>
                <?php foreach ($betTypes as $bt): ?><th><?= $betTypeLabels[$bt] ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
                <tr class="wl-summary"><td class="label-cell">ซื้อ</td><td class="num-cell">&nbsp;</td><td class="num-cell pos" style="font-size:12px"><?= number_format($grandBuy,2) ?></td><?php foreach ($betTypes as $bt): ?><td class="num-cell"><?= number_format($summary[$bt]['buy'],2) ?></td><?php endforeach; ?></tr>
                <tr class="wl-summary"><td class="label-cell" style="color:#d32f2f">จ่าย</td><td class="num-cell">&nbsp;</td><td class="num-cell neg" style="font-size:12px"><?= number_format(-$grandPayout,2) ?></td><?php foreach ($betTypes as $bt): ?><td class="num-cell neg"><?= $summary[$bt]['payout']>0 ? number_format(-$summary[$bt]['payout'],2) : '0.00' ?></td><?php endforeach; ?></tr>
                <?php 
                    $profit = $grandBuy - $grandPayout;
                    $profitClass = $profit >= 0 ? 'pos' : 'neg';
                ?>
                <tr class="wl-summary" style="background:#e8f5e9"><td class="label-cell" style="font-size:12px">กำไร/ขาดทุน</td><td class="num-cell">&nbsp;</td><td class="num-cell <?= $profitClass ?>" style="font-size:13px; font-weight:bold"><?= number_format($profit,2) ?></td><?php foreach ($betTypes as $bt): ?><?php $p = $summary[$bt]['buy'] - $summary[$bt]['payout']; ?><td class="num-cell <?= $p>=0?'pos':'neg' ?>"><?= number_format($p,2) ?></td><?php endforeach; ?></tr>
                <tr class="wl-summary" style="background:#e8f5e9"><td class="label-cell">ตั้งสู้</td><td class="num-cell"><button class="btn-save-fight" onclick="saveFightLimits()">บันทึก</button></td><td class="num-cell">&nbsp;</td><?php foreach ($betTypes as $bt): ?><td style="text-align:center"><input type="text" class="fight-input" id="fight-<?= $bt ?>" value="<?= number_format($fightLimits[$bt],0,'','') ?>"></td><?php endforeach; ?></tr>
                <tr style="height:3px; background:#00a65a"><td colspan="<?= 3+count($betTypes) ?>"></td></tr>

                <?php if (empty($numberRows)): ?>
                <tr><td colspan="<?= 3+count($betTypes) ?>" style="padding:20px; text-align:center; color:#999;">ไม่มีข้อมูลในงวดนี้</td></tr>
                <?php else:
                    $displayed = 0;
                    $fightLimitsJs = json_encode($fightLimits);
                    foreach ($numberRows as $i => $row):
                        if ($displayed >= $showLimit) break;
                        $displayed++;
                        // Check if any column exceeds fight limit
                        $hasExceed = false;
                        foreach ($betTypes as $bt) {
                            $limit = $fightLimits[$bt];
                            $buy = $row['cols'][$bt]['buy'];
                            if ($limit > 0 && $buy >= $limit) $hasExceed = true;
                        }
                ?>
                <tr class="<?= $hasExceed ? 'exceed-limit' : ($i%2===0?'wl-row-even':'wl-row-odd') ?>" onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background=''">
                    <td style="text-align:center; color:#999; font-size:10px"><?= $displayed ?></td>
                    <td style="text-align:center"><span class="number-badge" onclick="drillDown('<?= htmlspecialchars($row['number']) ?>')" title="คลิกดูรายละเอียด"><?= htmlspecialchars($row['number']) ?></span></td>
                    <td class="num-cell" style="font-weight:bold"><?= number_format($row['total_buy'],2) ?></td>
                    <?php foreach ($betTypes as $bt): 
                        $buy = $row['cols'][$bt]['buy'];
                        $limit = $fightLimits[$bt];
                        $exceeds = ($limit > 0 && $buy >= $limit);
                    ?>
                    <td class="num-cell <?= $exceeds ? 'neg' : '' ?>" <?php if ($buy > 0): ?>onclick="drillDownType('<?= htmlspecialchars($row['number']) ?>','<?= $bt ?>')" class="num-cell clickable-amount <?= $exceeds ? 'neg' : '' ?>" style="cursor:pointer"<?php endif; ?>><?= $buy > 0 ? number_format($buy,2) : '' ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($displayed) && $displayed < count($numberRows)): ?>
    <div style="text-align:center; padding:6px; font-size:11px; color:#666;">
        แสดง <?= $displayed ?> จาก <?= count($numberRows) ?> เลข — <a href="?date=<?= $selectedDate ?>&lottery=<?= $selectedLottery ?>&sort=<?= $sortBy ?>&limit=9999" style="color:#00a65a;">ดูทั้งหมด</a>
    </div>
    <?php endif; ?>
</div>

<!-- Drill-down Modal -->
<div class="drill-modal" id="drillModal" onclick="if(event.target===this)closeDrill()">
    <div class="drill-content">
        <div class="drill-header">
            <span id="drillTitle">รายละเอียด</span>
            <button onclick="closeDrill()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">✕</button>
        </div>
        <div class="drill-body" id="drillBody">
            <div style="padding:30px;text-align:center;color:#999;"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>
        </div>
    </div>
</div>

<script>
const LOTTERY_ID = <?= intval($selectedLottery) ?>;
const DRAW_DATE = '<?= $selectedDate ?>';
const BET_TYPE_LABELS = <?= json_encode($betTypeLabels) ?>;

function togglePanel(id) {
    document.querySelectorAll('.dropdown-panel, .date-dropdown').forEach(p => { if(p.id!==id) p.classList.remove('show'); });
    document.getElementById(id).classList.toggle('show');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#wlHeaderBar')) {
        document.querySelectorAll('.dropdown-panel, .date-dropdown').forEach(p => p.classList.remove('show'));
    }
});

// Save fight limits
async function saveFightLimits() {
    const limits = {};
    ['3top','3tod','2top','2bot','run_top','run_bot'].forEach(bt => {
        limits[bt] = parseFloat(document.getElementById('fight-' + bt).value) || 0;
    });
    
    const fd = new FormData();
    fd.append('ajax', 'save_fight');
    fd.append('lottery_id', LOTTERY_ID);
    fd.append('limits', JSON.stringify(limits));
    
    try {
        const res = await fetch('win_lose.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'บันทึกตั้งสู้เรียบร้อย', timer: 1500, showConfirmButton: false });
            setTimeout(() => location.reload(), 1500);
        } else {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.error });
        }
    } catch(e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message });
    }
}

// Drill-down: show all bet types for a number
function drillDown(number) {
    const modal = document.getElementById('drillModal');
    const body = document.getElementById('drillBody');
    const title = document.getElementById('drillTitle');
    title.textContent = 'รายละเอียดเลข: ' + number;
    body.innerHTML = '<div style="padding:30px;text-align:center;color:#999;"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>';
    modal.style.display = '';
    document.body.style.overflow = 'hidden';
    
    // Fetch all bet types for this number
    const promises = ['3top','3tod','2top','2bot','run_top','run_bot'].map(bt =>
        fetch(`win_lose.php?ajax=drill_down&number=${encodeURIComponent(number)}&bet_type=${bt}&lottery=${LOTTERY_ID}&date=${DRAW_DATE}`)
            .then(r => r.json())
    );
    
    Promise.all(promises).then(results => {
        let html = '';
        const betTypes = ['3top','3tod','2top','2bot','run_top','run_bot'];
        let grandTotal = 0;
        
        betTypes.forEach((bt, idx) => {
            const items = results[idx].items || [];
            if (items.length === 0) return;
            
            let subtotal = 0;
            items.forEach(i => subtotal += parseFloat(i.amount));
            grandTotal += subtotal;
            
            html += `<div style="background:#e8f5e9;padding:6px 12px;font-weight:bold;font-size:12px;border-bottom:1px solid #c8e6c9;">
                ${BET_TYPE_LABELS[bt]} — ${items.length} รายการ — รวม ${subtotal.toLocaleString('en-US',{minimumFractionDigits:2})} บาท
            </div>`;
            html += '<table class="drill-table"><thead><tr>';
            html += '<th>#</th><th>วันที่</th><th>เลขที่โพย</th><th>หมายเลข</th><th>จำนวน</th><th>เรทจ่าย</th><th>หมายเหตุ</th>';
            html += '</tr></thead><tbody>';
            items.forEach((item, i) => {
                const dt = new Date(item.created_at);
                const dateStr = dt.toLocaleDateString('th-TH') + ' ' + dt.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'});
                html += `<tr>
                    <td style="text-align:center">${i+1}</td>
                    <td style="text-align:center;white-space:nowrap">${dateStr}</td>
                    <td style="text-align:center;font-weight:bold">${item.bet_number}</td>
                    <td style="text-align:center;font-weight:bold;font-family:monospace">${item.number}</td>
                    <td style="text-align:right;font-weight:bold">${parseFloat(item.amount).toLocaleString('en-US',{minimumFractionDigits:2})}</td>
                    <td style="text-align:center">${item.pay_rate || '-'}</td>
                    <td>${item.note || ''}</td>
                </tr>`;
            });
            html += '</tbody></table>';
        });
        
        if (!html) {
            html = '<div style="padding:30px;text-align:center;color:#999;">ไม่พบรายการ</div>';
        } else {
            html = `<div style="background:#fff3cd;padding:6px 12px;font-size:13px;font-weight:bold;border-bottom:2px solid #ffc107;">
                เลข: <span style="font-size:16px;color:#d32f2f">${number}</span> — รวมทุกประเภท: <span style="color:#1b5e20">${grandTotal.toLocaleString('en-US',{minimumFractionDigits:2})} บาท</span>
            </div>` + html;
        }
        
        body.innerHTML = html;
    }).catch(e => {
        body.innerHTML = '<div style="padding:30px;text-align:center;color:#d32f2f;">เกิดข้อผิดพลาด: ' + e.message + '</div>';
    });
}

// Drill-down: single bet type
function drillDownType(number, betType) {
    const modal = document.getElementById('drillModal');
    const body = document.getElementById('drillBody');
    const title = document.getElementById('drillTitle');
    title.textContent = `รายละเอียดเลข ${number} — ${BET_TYPE_LABELS[betType]}`;
    body.innerHTML = '<div style="padding:30px;text-align:center;color:#999;"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>';
    modal.style.display = '';
    document.body.style.overflow = 'hidden';
    
    fetch(`win_lose.php?ajax=drill_down&number=${encodeURIComponent(number)}&bet_type=${betType}&lottery=${LOTTERY_ID}&date=${DRAW_DATE}`)
        .then(r => r.json())
        .then(data => {
            const items = data.items || [];
            if (items.length === 0) {
                body.innerHTML = '<div style="padding:30px;text-align:center;color:#999;">ไม่พบรายการ</div>';
                return;
            }
            
            let subtotal = 0;
            items.forEach(i => subtotal += parseFloat(i.amount));
            
            let html = `<div style="background:#fff3cd;padding:6px 12px;font-size:13px;font-weight:bold;border-bottom:2px solid #ffc107;">
                เลข: <span style="font-size:16px;color:#d32f2f">${number}</span> — ${BET_TYPE_LABELS[betType]} — ${items.length} รายการ — รวม: <span style="color:#1b5e20">${subtotal.toLocaleString('en-US',{minimumFractionDigits:2})} บาท</span>
            </div>`;
            html += '<table class="drill-table"><thead><tr>';
            html += '<th>#</th><th>วันที่</th><th>เลขที่โพย</th><th>หมายเลข</th><th>จำนวน</th><th>เรทจ่าย</th><th>หมายเหตุ</th>';
            html += '</tr></thead><tbody>';
            items.forEach((item, i) => {
                const dt = new Date(item.created_at);
                const dateStr = dt.toLocaleDateString('th-TH') + ' ' + dt.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'});
                html += `<tr>
                    <td style="text-align:center">${i+1}</td>
                    <td style="text-align:center;white-space:nowrap">${dateStr}</td>
                    <td style="text-align:center;font-weight:bold">${item.bet_number}</td>
                    <td style="text-align:center;font-weight:bold;font-family:monospace">${item.number}</td>
                    <td style="text-align:right;font-weight:bold">${parseFloat(item.amount).toLocaleString('en-US',{minimumFractionDigits:2})}</td>
                    <td style="text-align:center">${item.pay_rate || '-'}</td>
                    <td>${item.note || ''}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            
            body.innerHTML = html;
        })
        .catch(e => {
            body.innerHTML = '<div style="padding:30px;text-align:center;color:#d32f2f;">เกิดข้อผิดพลาด: ' + e.message + '</div>';
        });
}

function closeDrill() {
    document.getElementById('drillModal').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrill(); });
</script>

<?php require_once 'includes/footer.php'; ?>
