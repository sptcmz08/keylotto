<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'win_lose';
$adminTitle = 'คาดคะเน ได้-เสีย';

// Filters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedLottery = $_GET['lottery'] ?? '';
$sortBy = $_GET['sort'] ?? 'default';
$sortDir = $_GET['dir'] ?? 'desc';
$showLimit = intval($_GET['limit'] ?? 50);
$showMode = $_GET['mode'] ?? 'win_lose';

// Get lottery types grouped by category
$lotteryTypes = $pdo->query("SELECT lt.id, lt.name, lc.name as cat_name, lc.id as cat_id FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id WHERE lt.is_active = 1 ORDER BY lc.sort_order, lt.sort_order, lt.name")->fetchAll();

// Available draw dates
$dateStmt = $pdo->query("SELECT DISTINCT draw_date FROM bets WHERE status != 'cancelled' AND draw_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY draw_date DESC LIMIT 30");
$availableDates = $dateStmt->fetchAll(PDO::FETCH_COLUMN);

// Auto-select first lottery
if (empty($selectedLottery) && !empty($lotteryTypes)) {
    $firstStmt = $pdo->prepare("SELECT DISTINCT b.lottery_type_id FROM bets b WHERE b.draw_date = ? AND b.status != 'cancelled' LIMIT 1");
    $firstStmt->execute([$selectedDate]);
    $firstLottery = $firstStmt->fetchColumn();
    $selectedLottery = $firstLottery ?: $lotteryTypes[0]['id'];
}

$where = "WHERE b.draw_date = ? AND b.status != 'cancelled' AND b.lottery_type_id = ?";
$params = [$selectedDate, $selectedLottery];

// Pay rates
$rates = [];
$rateStmt = $pdo->prepare("SELECT bet_type, pay_rate FROM pay_rates WHERE lottery_type_id = ?");
$rateStmt->execute([$selectedLottery]);
foreach ($rateStmt->fetchAll() as $r) $rates[$r['bet_type']] = floatval($r['pay_rate']);

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

// Number rows
$numberRows = [];
foreach ($numberMap as $num => $types) {
    $row = ['number' => $num, 'cols' => []];
    $rowPayout = 0;
    foreach ($betTypes as $bt) {
        $amt = $types[$bt]['amount'] ?? 0;
        $pay = $amt * ($rates[$bt] ?? 0);
        $row['cols'][$bt] = ['buy' => $amt, 'payout' => $pay];
        $rowPayout += $pay;
    }
    $row['total_payout'] = $rowPayout;
    $numberRows[] = $row;
}

// Sort
if ($sortBy === 'amount_desc') usort($numberRows, fn($a,$b) => array_sum(array_column($b['cols'],'buy')) <=> array_sum(array_column($a['cols'],'buy')));
elseif ($sortBy === 'payout_desc') usort($numberRows, fn($a,$b) => $b['total_payout'] <=> $a['total_payout']);

$totalBetCount = 0;
foreach ($numberMap as $types) foreach ($types as $t) $totalBetCount += $t['count'];

// Selected lottery info
$selLotteryName = ''; $selCatName = '';
foreach ($lotteryTypes as $lt) {
    if ($lt['id'] == $selectedLottery) { $selLotteryName = $lt['name']; $selCatName = $lt['cat_name']; break; }
}

// Group by category
$lotteryByCategory = [];
foreach ($lotteryTypes as $lt) $lotteryByCategory[$lt['cat_name']][] = $lt;

// Flag mapping
$flagMap = [
    'ไทย'=>'🇹🇭','รัฐบาล'=>'🇹🇭','ออมสิน'=>'🇹🇭','ธกส'=>'🇹🇭',
    'ฮานอย'=>'🇻🇳','เวียดนาม'=>'🇻🇳',
    'ลาว'=>'🇱🇦',
    'มาเลย์'=>'🇲🇾','มาเลเซีย'=>'🇲🇾',
    'จีน'=>'🇨🇳',
    'เกาหลี'=>'🇰🇷',
    'ญี่ปุ่น'=>'🇯🇵','นิเคอิ'=>'🇯🇵',
    'ฮั่งเส็ง'=>'🇭🇰','ฮ่องกง'=>'🇭🇰',
    'ไต้หวัน'=>'🇹🇼',
    'สิงคโปร์'=>'🇸🇬',
    'อินเดีย'=>'🇮🇳',
    'อียิปต์'=>'🇪🇬',
    'อังกฤษ'=>'🇬🇧',
    'เยอรมัน'=>'🇩🇪',
    'ดาวโจนส์'=>'🇺🇸','อเมริกา'=>'🇺🇸',
    'รัสเซีย'=>'🇷🇺',
    'หุ้น'=>'📈',
    'VIP'=>'',
    'One'=>'',
    'JACKPOT'=>'',
    '12 ราศี'=>'♈','ราศี'=>'♈',
];

function getFlag($name) {
    global $flagMap;
    foreach ($flagMap as $key => $flag) {
        if (mb_strpos($name, $key) !== false && !empty($flag)) return $flag;
    }
    return '🎰';
}

// Category color mapping
$catColors = [
    'หวยชุด' => '#8B0000',
    'หวยไทย' => '#1565C0',
    'หวยต่างประเทศ' => '#2E7D32',
    'หวยรายวัน' => '#C62828',
    'หุ้นหลัก' => '#4A148C',
    'หวย One' => '#E65100',
];

// Date status mapping
$dateStatusMap = [];
foreach ($availableDates as $d) {
    $stChk = $pdo->prepare("SELECT 
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status IN ('won','lost') THEN 1 ELSE 0 END) as done
        FROM bets WHERE draw_date = ? AND lottery_type_id = ? AND status != 'cancelled'");
    $stChk->execute([$d, $selectedLottery]);
    $stInfo = $stChk->fetch();
    if ($stInfo['done'] > 0 && $stInfo['pending'] == 0) $dateStatusMap[$d] = 'จ่ายแล้ว';
    elseif ($stInfo['pending'] > 0) $dateStatusMap[$d] = 'กำลังดำเนินการ';
    else $dateStatusMap[$d] = 'กำลังดำเนินการ';
}

require_once 'includes/header.php';
?>

<style>
    .wl-page { font-family: Tahoma, Arial, sans-serif; font-size: 11px; }
    /* Header bar */
    .wl-header-bar { background: #00a65a; padding: 6px 12px; display: flex; align-items: center; gap: 12px; border-radius: 4px 4px 0 0; position: relative; }
    .wl-header-bar .selector-btn { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); color: #fff; padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 6px; }
    .wl-header-bar .selector-btn:hover { background: rgba(255,255,255,0.25); }
    
    /* Dropdown panel */
    .dropdown-panel { display: none; position: absolute; top: 100%; left: 0; z-index: 100; background: #fff; border: 2px solid #00a65a; border-radius: 0 0 6px 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); min-width: 700px; max-height: 500px; overflow-y: auto; }
    .dropdown-panel.show { display: block; }
    .dropdown-panel .cat-header { background: #00a65a; color: #fff; font-weight: bold; font-size: 12px; text-align: center; padding: 4px 8px; margin: 0; }
    .dropdown-panel .cat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; padding: 4px; }
    .dropdown-panel .lot-item { display: flex; align-items: center; gap: 4px; padding: 4px 8px; font-size: 11px; cursor: pointer; border-radius: 3px; white-space: nowrap; text-decoration: none; color: #333; }
    .dropdown-panel .lot-item:hover { background: #e8f5e9; }
    .dropdown-panel .lot-item.active { background: #c8e6c9; font-weight: bold; color: #1b5e20; }
    
    /* Date dropdown */
    .date-dropdown { display: none; position: absolute; top: 100%; z-index: 100; background: #fff; border: 2px solid #00a65a; border-radius: 0 0 6px 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); min-width: 220px; }
    .date-dropdown.show { display: block; }
    .date-dropdown a { display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; font-size: 11px; color: #333; text-decoration: none; border-bottom: 1px solid #eee; }
    .date-dropdown a:hover { background: #e8f5e9; }
    .date-dropdown a.active { background: #c8e6c9; font-weight: bold; }
    .date-dropdown .status-badge { font-size: 9px; padding: 1px 6px; border-radius: 8px; }
    .status-paid { background: #c8e6c9; color: #2e7d32; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-open { background: #bbdefb; color: #1565c0; }
    
    /* Table */
    .wl-table { border-collapse: collapse; width: 100%; font-size: 11px; font-family: Tahoma, Arial, sans-serif; }
    .wl-table th, .wl-table td { border: 1px solid #a5d6a7; padding: 2px 4px; }
    .wl-table thead th { background: #00a65a; color: #fff; font-weight: bold; text-align: center; padding: 4px 4px; white-space: nowrap; }
    .wl-summary { background: #f0fff0; }
    .wl-summary td { font-weight: bold; white-space: nowrap; }
    .wl-summary .label-cell { background: #e8f5e9; text-align: left; padding-left: 8px; font-size: 12px; color: #2e7d32; }
    .wl-row-even { background: #fff; }
    .wl-row-odd { background: #f9fff9; }
    .neg { color: #d32f2f; }
    .pos { color: #1b5e20; }
    .num-cell { text-align: right; font-family: 'Courier New', monospace; font-size: 11px; }
    .number-badge { display: inline-block; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 2px; padding: 0 4px; font-family: monospace; font-weight: bold; font-size: 12px; min-width: 28px; text-align: center; }
    .filter-bar { background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; padding: 4px 8px; margin-bottom: 6px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 11px; }
    .filter-bar select { border: 1px solid #ccc; border-radius: 3px; padding: 2px 4px; font-size: 11px; }
    .filter-bar label { color: #666; white-space: nowrap; }
    .btn-refresh { background: #fff; border: 1px solid #00a65a; color: #00a65a; padding: 2px 10px; border-radius: 3px; font-size: 11px; cursor: pointer; }
    .btn-refresh:hover { background: #e8f5e9; }
    .color-legend { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; padding: 2px 6px; border-radius: 2px; }
    .fight-input { width: 60px; text-align: center; border: 1px solid #ccc; border-radius: 2px; padding: 1px 2px; font-size: 11px; }
    .btn-save-fight { background: #00a65a; color: #fff; border: none; padding: 2px 10px; border-radius: 2px; font-size: 11px; cursor: pointer; font-weight: bold; }
</style>

<div class="wl-page">
    <!-- ===== GREEN HEADER BAR ===== -->
    <div class="wl-header-bar" id="wlHeaderBar">
        <!-- Lottery Selector -->
        <div style="position:relative;">
            <button class="selector-btn" onclick="togglePanel('lotteryPanel')">
                <?= getFlag($selLotteryName) ?> <span><?= htmlspecialchars($selLotteryName) ?></span> <i class="fas fa-caret-down"></i>
            </button>
            <div class="dropdown-panel" id="lotteryPanel">
                <?php foreach ($lotteryByCategory as $catName => $lts): 
                    $catColor = $catColors[$catName] ?? '#00a65a';
                ?>
                <div class="cat-header" style="background:<?= $catColor ?>"><?= htmlspecialchars($catName) ?></div>
                <div class="cat-grid">
                    <?php foreach ($lts as $lt): ?>
                    <a href="?date=<?= $selectedDate ?>&lottery=<?= $lt['id'] ?>&sort=<?= $sortBy ?>&limit=<?= $showLimit ?>"
                       class="lot-item <?= $lt['id'] == $selectedLottery ? 'active' : '' ?>">
                        <?= getFlag($lt['name']) ?> <?= htmlspecialchars($lt['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Date Selector -->
        <div style="position:relative;">
            <button class="selector-btn" onclick="togglePanel('datePanel')">
                📅 <span>งวดวันที่ <?= date('d-m-', strtotime($selectedDate)) . (intval(date('Y', strtotime($selectedDate))) + 543) ?></span> <i class="fas fa-caret-down"></i>
            </button>
            <div class="date-dropdown" id="datePanel">
                <?php foreach ($availableDates as $d): 
                    $dFmt = date('d-m-', strtotime($d)) . (intval(date('Y', strtotime($d))) + 543);
                    $status = $dateStatusMap[$d] ?? 'กำลังดำเนินการ';
                    $statusClass = $status === 'จ่ายแล้ว' ? 'status-paid' : ($status === 'เปิดรับ' ? 'status-open' : 'status-pending');
                ?>
                <a href="?date=<?= $d ?>&lottery=<?= $selectedLottery ?>&sort=<?= $sortBy ?>&limit=<?= $showLimit ?>"
                   class="<?= $d === $selectedDate ? 'active' : '' ?>">
                    <span><?= $dFmt ?></span>
                    <span class="status-badge <?= $statusClass ?>"><?= $status ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Right side: Refresh -->
        <div style="margin-left:auto;">
            <button class="btn-refresh" style="background:rgba(255,255,255,0.9); border-color:#fff;" onclick="location.reload()">Refresh (<?= $totalBetCount ?>)</button>
        </div>
    </div>

    <!-- ===== TITLE + ALERT ===== -->
    <div style="padding:6px 0;">
        <div style="font-size:14px; font-weight:bold; color:#333; margin-bottom:4px;">คาดคะเน ได้-เสีย</div>
        <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:4px; padding:5px 12px; font-size:11px;">
            <span style="color:#d32f2f;">⚠</span> <b>เฉพาะงวด</b> [<?= htmlspecialchars($selCatName) ?>] <b><?= htmlspecialchars($selLotteryName) ?></b> วันที่ <b><?= date('d/m/', strtotime($selectedDate)) . (intval(date('Y', strtotime($selectedDate))) + 543) ?></b> <span style="color:#999;">(เปลี่ยนได้ที่แถบเมนูด้านบน)</span>
        </div>
    </div>

    <!-- ===== FILTER BAR ===== -->
    <form method="GET" class="filter-bar">
        <label>แสดง</label>
        <select name="mode">
            <option value="win_lose" <?= $showMode === 'win_lose' ? 'selected' : '' ?>>คาดคะเน ได้-เสีย</option>
            <option value="amount" <?= $showMode === 'amount' ? 'selected' : '' ?>>ยอดแทง</option>
        </select>
        <label>เรียงลำดับ</label>
        <select name="sort">
            <option value="default" <?= $sortBy === 'default' ? 'selected' : '' ?>>คาดคะเน ยอดแล้ว</option>
            <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>ยอดซื้อ</option>
            <option value="payout_desc" <?= $sortBy === 'payout_desc' ? 'selected' : '' ?>>ยอดจ่าย</option>
        </select>
        <label>เรียงจาก</label>
        <select name="dir">
            <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>มาก > น้อย</option>
            <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>น้อย > มาก</option>
        </select>
        <label>จำนวนแสดง</label>
        <select name="limit">
            <?php foreach ([50,100,200,500,9999] as $lv): ?>
            <option value="<?= $lv ?>" <?= $showLimit == $lv ? 'selected' : '' ?>><?= $lv == 9999 ? 'ทั้งหมด' : $lv ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="date" value="<?= $selectedDate ?>">
        <input type="hidden" name="lottery" value="<?= $selectedLottery ?>">
        <button type="submit" style="background:#00a65a; color:#fff; border:none; padding:3px 10px; border-radius:3px; font-size:11px; cursor:pointer;"><i class="fas fa-search"></i></button>
    </form>

    <!-- ===== COLOR LEGEND ===== -->
    <div style="margin-bottom:6px; display:flex; gap:6px; flex-wrap:wrap;">
        <span class="color-legend" style="background:#c8e6c9; border:1px solid #a5d6a7;">พื้นหลังสีเขียว = เติมแต้ม</span>
        <span class="color-legend" style="background:#fff9c4; border:1px solid #fdd835;">พื้นหลังสีเหลือง = คุณจดหลังอ = ดูคาดะเอิ้ง</span>
        <span class="color-legend" style="background:#eee; border:1px solid #ccc;">☐ Ctrl+F (ค้นหาเลข)</span>
    </div>

    <!-- ===== MAIN DATA TABLE ===== -->
    <div style="overflow-x:auto;">
        <table class="wl-table">
            <thead>
                <tr>
                    <th style="width:30px;"></th>
                    <th style="min-width:45px;">รวม</th>
                    <?php foreach ($betTypes as $bt): ?>
                    <th colspan="2"><?= $betTypeLabels[$bt] ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <!-- ซื้อ -->
                <tr class="wl-summary">
                    <td class="label-cell">ซื้อ</td>
                    <td class="num-cell pos" style="font-size:12px;"><?= number_format($grandBuy, 2) ?></td>
                    <?php foreach ($betTypes as $bt): ?>
                    <td class="num-cell" colspan="2"><?= number_format($summary[$bt]['buy'], 2) ?></td>
                    <?php endforeach; ?>
                </tr>
                <!-- คอมฯ -->
                <tr class="wl-summary">
                    <td class="label-cell">คอมฯ</td>
                    <td class="num-cell">0.00</td>
                    <?php foreach ($betTypes as $bt): ?>
                    <td class="num-cell" colspan="2">0.00</td>
                    <?php endforeach; ?>
                </tr>
                <!-- รับ -->
                <tr class="wl-summary">
                    <td class="label-cell">รับ</td>
                    <td class="num-cell pos" style="font-size:12px;"><?= number_format($grandBuy, 2) ?></td>
                    <?php foreach ($betTypes as $bt): ?>
                    <td class="num-cell" colspan="2"><?= number_format($summary[$bt]['buy'], 2) ?></td>
                    <?php endforeach; ?>
                </tr>
                <!-- จ่าย -->
                <tr class="wl-summary">
                    <td class="label-cell" style="color:#d32f2f;">จ่าย</td>
                    <td class="num-cell neg" style="font-size:12px;"><?= number_format(-$grandPayout, 2) ?></td>
                    <?php foreach ($betTypes as $bt): ?>
                    <td class="num-cell neg" colspan="2"><?= $summary[$bt]['payout'] > 0 ? number_format(-$summary[$bt]['payout'], 2) : '0.00' ?></td>
                    <?php endforeach; ?>
                </tr>
                <!-- ตั้งสู้ -->
                <tr class="wl-summary" style="background:#e8f5e9;">
                    <td class="label-cell">ตั้งสู้</td>
                    <td class="num-cell"><button class="btn-save-fight">บันทึก</button></td>
                    <?php foreach ($betTypes as $i => $bt): ?>
                    <td colspan="2" style="text-align:center;"><input type="text" class="fight-input" value="<?= [0,500,1000,1000,2000,2000][$i] ?? 0 ?>"></td>
                    <?php endforeach; ?>
                </tr>
                
                <tr style="height:3px; background:#00a65a;"><td colspan="<?= 2 + count($betTypes)*2 ?>"></td></tr>

                <!-- Per-number rows -->
                <?php if (empty($numberRows)): ?>
                <tr><td colspan="<?= 2 + count($betTypes)*2 ?>" style="padding:20px; text-align:center; color:#999;">ไม่มีข้อมูลในงวดนี้</td></tr>
                <?php else:
                    $displayed = 0;
                    foreach ($numberRows as $i => $row):
                        if ($displayed >= $showLimit) break;
                        $displayed++;
                        $rowClass = $i % 2 === 0 ? 'wl-row-even' : 'wl-row-odd';
                ?>
                <tr class="<?= $rowClass ?>" onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background=''">
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

<script>
function togglePanel(id) {
    // Close all panels first
    document.querySelectorAll('.dropdown-panel, .date-dropdown').forEach(p => {
        if (p.id !== id) p.classList.remove('show');
    });
    document.getElementById(id).classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.wl-header-bar')) {
        document.querySelectorAll('.dropdown-panel, .date-dropdown').forEach(p => p.classList.remove('show'));
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
