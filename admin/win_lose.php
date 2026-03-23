<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'win_lose';
$adminTitle = 'คาดคะเน ได้-เสีย';

// Filters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedLottery = $_GET['lottery'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'number'; // number, amount_desc, payout_desc
$showLimit = intval($_GET['limit'] ?? 50);

// Get lottery types for filter
$lotteryTypes = $pdo->query("SELECT lt.id, lt.name, lc.name as cat_name FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id WHERE lt.is_active = 1 ORDER BY lt.category_id, lt.name")->fetchAll();

// Build WHERE clause
$where = "WHERE b.draw_date = ? AND b.status != 'cancelled'";
$params = [$selectedDate];
if ($selectedLottery !== 'all') {
    $where .= " AND b.lottery_type_id = ?";
    $params[] = $selectedLottery;
}

// Get pay rates (per lottery if specific, average if all)
$rates = [];
if ($selectedLottery !== 'all') {
    $rateStmt = $pdo->prepare("SELECT bet_type, pay_rate FROM pay_rates WHERE lottery_type_id = ?");
    $rateStmt->execute([$selectedLottery]);
    foreach ($rateStmt->fetchAll() as $r) {
        $rates[$r['bet_type']] = floatval($r['pay_rate']);
    }
} else {
    foreach ($pdo->query("SELECT bet_type, AVG(pay_rate) as avg_rate FROM pay_rates GROUP BY bet_type")->fetchAll() as $r) {
        $rates[$r['bet_type']] = floatval($r['avg_rate']);
    }
}

// =============================================
// Per-number breakdown (core feature)
// =============================================
$numberStmt = $pdo->prepare("
    SELECT 
        bi.number,
        bi.bet_type,
        SUM(bi.amount) as total_amount,
        COUNT(*) as total_count
    FROM bet_items bi
    JOIN bets b ON bi.bet_id = b.id
    $where
    GROUP BY bi.number, bi.bet_type
    ORDER BY bi.number
");
$numberStmt->execute($params);

// Build per-number map
$numberMap = []; // number => [bet_type => amount]
foreach ($numberStmt->fetchAll() as $r) {
    $num = $r['number'];
    if (!isset($numberMap[$num])) {
        $numberMap[$num] = ['buy' => [], 'count' => []];
    }
    $numberMap[$num]['buy'][$r['bet_type']] = floatval($r['total_amount']);
    $numberMap[$num]['count'][$r['bet_type']] = intval($r['total_count']);
}

// Calculate totals and potential payouts for each number
$betTypes = ['3top', '3tod', '2top', '2bot', 'run_top', 'run_bot'];
$betTypeLabels = ['3top' => '3 ตัวบน', '3tod' => '3 ตัวโต๊ด', '2top' => '2 ตัวบน', '2bot' => '2 ตัวล่าง', 'run_top' => 'วิ่งบน', 'run_bot' => 'วิ่งล่าง'];

$numberRows = [];
$grandTotals = array_fill_keys($betTypes, 0);
$grandPayouts = array_fill_keys($betTypes, 0);
$grandBuy = 0;
$grandPayout = 0;

foreach ($numberMap as $num => $data) {
    $rowBuy = 0;
    $rowPayout = 0;
    $rowCols = [];
    foreach ($betTypes as $bt) {
        $amt = $data['buy'][$bt] ?? 0;
        $rate = $rates[$bt] ?? 0;
        $pay = $amt * $rate;
        $rowCols[$bt] = ['buy' => $amt, 'payout' => $pay];
        $rowBuy += $amt;
        $rowPayout += $pay;
        $grandTotals[$bt] += $amt;
        $grandPayouts[$bt] += $pay;
    }
    $grandBuy += $rowBuy;
    $grandPayout += $rowPayout;
    $numberRows[] = [
        'number' => $num,
        'cols' => $rowCols,
        'total_buy' => $rowBuy,
        'total_payout' => $rowPayout,
        'net' => $rowBuy - $rowPayout, // positive = house wins, negative = house loses
    ];
}

// Sort
if ($sortBy === 'amount_desc') {
    usort($numberRows, fn($a, $b) => $b['total_buy'] <=> $a['total_buy']);
} elseif ($sortBy === 'payout_desc') {
    usort($numberRows, fn($a, $b) => $b['total_payout'] <=> $a['total_payout']);
}

// Summary totals
$summaryStmt = $pdo->prepare("
    SELECT bi.bet_type, SUM(bi.amount) as total_buy, COUNT(*) as total_count
    FROM bet_items bi JOIN bets b ON bi.bet_id = b.id $where
    GROUP BY bi.bet_type
");
$summaryStmt->execute($params);
$summaryByType = [];
foreach ($summaryStmt->fetchAll() as $r) {
    $summaryByType[$r['bet_type']] = $r;
}

$totalBetCount = 0;
$totalSumBuy = 0;
$totalSumPay = 0;
foreach ($betTypes as $bt) {
    $buy = isset($summaryByType[$bt]) ? floatval($summaryByType[$bt]['total_buy']) : 0;
    $totalSumBuy += $buy;
    $totalSumPay += $buy * ($rates[$bt] ?? 0);
    $totalBetCount += isset($summaryByType[$bt]) ? intval($summaryByType[$bt]['total_count']) : 0;
}

// Selected lottery name
$selLotteryName = 'รวมทุกหวย';
if ($selectedLottery !== 'all') {
    foreach ($lotteryTypes as $lt) {
        if ($lt['id'] == $selectedLottery) {
            $selLotteryName = $lt['name'];
            break;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="mb-4">
    <h2 class="text-lg font-bold text-gray-800 mb-3"><i class="fas fa-chart-line text-green-600 mr-2"></i>คาดคะเน ได้-เสีย</h2>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-lg border p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs text-gray-500 block mb-1">วันที่งวด</label>
                <input type="date" name="date" value="<?= $selectedDate ?>" class="border rounded-lg px-3 py-2 text-sm outline-none">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">หวย</label>
                <select name="lottery" class="border rounded-lg px-3 py-2 text-sm outline-none min-w-[200px]">
                    <option value="all">📊 รวมทุกหวย</option>
                    <?php foreach ($lotteryTypes as $lt): ?>
                    <option value="<?= $lt['id'] ?>" <?= $selectedLottery == $lt['id'] ? 'selected' : '' ?>>[<?= $lt['cat_name'] ?>] <?= $lt['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">เรียงตาม</label>
                <select name="sort" class="border rounded-lg px-3 py-2 text-sm outline-none">
                    <option value="number" <?= $sortBy === 'number' ? 'selected' : '' ?>>เลข (น้อย→มาก)</option>
                    <option value="amount_desc" <?= $sortBy === 'amount_desc' ? 'selected' : '' ?>>ยอดซื้อ (มาก→น้อย)</option>
                    <option value="payout_desc" <?= $sortBy === 'payout_desc' ? 'selected' : '' ?>>จ่ายสูงสุด (มาก→น้อย)</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">จำนวนแสดง</label>
                <select name="limit" class="border rounded-lg px-3 py-2 text-sm outline-none">
                    <option value="50" <?= $showLimit == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $showLimit == 100 ? 'selected' : '' ?>>100</option>
                    <option value="200" <?= $showLimit == 200 ? 'selected' : '' ?>>200</option>
                    <option value="9999" <?= $showLimit == 9999 ? 'selected' : '' ?>>ทั้งหมด</option>
                </select>
            </div>
            <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
                <i class="fas fa-search mr-1"></i> ดูรายงาน
            </button>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
        <div class="bg-white rounded-lg border p-4 text-center">
            <p class="text-xs text-gray-400">จำนวนรายการ</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format($totalBetCount) ?></p>
        </div>
        <div class="bg-white rounded-lg border p-4 text-center">
            <p class="text-xs text-gray-400">จำนวนเลข</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format(count($numberRows)) ?></p>
        </div>
        <div class="bg-green-50 rounded-lg border border-green-200 p-4 text-center">
            <p class="text-xs text-green-600">ยอดรับรวม (ซื้อ)</p>
            <p class="text-xl font-bold text-green-700"><?= number_format($totalSumBuy, 2) ?></p>
        </div>
        <div class="bg-red-50 rounded-lg border border-red-200 p-4 text-center">
            <p class="text-xs text-red-500">จ่ายสูงสุด (ถ้าถูกทุกตัว)</p>
            <p class="text-xl font-bold text-red-700"><?= number_format($totalSumPay, 2) ?></p>
        </div>
        <div class="rounded-lg border p-4 text-center <?= ($totalSumBuy - $totalSumPay) >= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?>">
            <p class="text-xs <?= ($totalSumBuy - $totalSumPay) >= 0 ? 'text-green-600' : 'text-red-500' ?>">ได้-เสียสูงสุด</p>
            <p class="text-xl font-bold <?= ($totalSumBuy - $totalSumPay) >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= number_format($totalSumBuy - $totalSumPay, 2) ?></p>
        </div>
    </div>

    <!-- Summary by Bet Type -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-4">
        <div class="bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 font-bold text-sm">
            <i class="fas fa-table mr-1"></i> สรุปตามประเภท — <?= htmlspecialchars($selLotteryName) ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left border text-xs font-bold text-gray-700"></th>
                        <?php foreach ($betTypes as $bt): ?>
                        <th class="px-3 py-2 text-right border text-xs font-bold text-gray-700"><?= $betTypeLabels[$bt] ?></th>
                        <?php endforeach; ?>
                        <th class="px-3 py-2 text-right border text-xs font-bold text-green-700 bg-green-50">รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b">
                        <td class="px-3 py-2 border font-medium text-blue-700">ซื้อ</td>
                        <?php $rowSum = 0; foreach ($betTypes as $bt):
                            $buy = isset($summaryByType[$bt]) ? floatval($summaryByType[$bt]['total_buy']) : 0;
                            $rowSum += $buy;
                        ?>
                        <td class="px-3 py-2 text-right border <?= $buy > 0 ? 'text-blue-600' : 'text-gray-300' ?>"><?= $buy > 0 ? number_format($buy, 2) : '-' ?></td>
                        <?php endforeach; ?>
                        <td class="px-3 py-2 text-right border font-bold text-blue-700 bg-green-50"><?= number_format($rowSum, 2) ?></td>
                    </tr>
                    <tr class="border-b">
                        <td class="px-3 py-2 border font-medium text-red-600">จ่าย (สูงสุด)</td>
                        <?php $paySum = 0; foreach ($betTypes as $bt):
                            $buy = isset($summaryByType[$bt]) ? floatval($summaryByType[$bt]['total_buy']) : 0;
                            $rate = $rates[$bt] ?? 0;
                            $pay = $buy * $rate;
                            $paySum += $pay;
                        ?>
                        <td class="px-3 py-2 text-right border <?= $pay > 0 ? 'text-red-600' : 'text-gray-300' ?>"><?= $pay > 0 ? number_format($pay, 2) : '-' ?></td>
                        <?php endforeach; ?>
                        <td class="px-3 py-2 text-right border font-bold text-red-700 bg-red-50"><?= number_format($paySum, 2) ?></td>
                    </tr>
                    <tr class="bg-green-50 font-bold">
                        <td class="px-3 py-2 border text-green-700">อัตราจ่าย</td>
                        <?php foreach ($betTypes as $bt): ?>
                        <td class="px-3 py-2 text-right border text-gray-600"><?= number_format($rates[$bt] ?? 0, 2) ?></td>
                        <?php endforeach; ?>
                        <td class="px-3 py-2 text-right border text-gray-500 bg-green-50">-</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Per Number Breakdown -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="bg-gradient-to-r from-orange-400 to-orange-500 text-white px-4 py-2 font-bold text-sm flex justify-between items-center">
            <span><i class="fas fa-sort-amount-down mr-1"></i> วิเคราะห์ตามเลข (<?= count($numberRows) ?> เลข)</span>
            <span class="text-xs font-normal opacity-80">ยอดซื้อ / จ่ายถ้าถูก</span>
        </div>
        <div class="overflow-x-auto" style="max-height: 70vh;">
            <table class="w-full text-[12px]">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-2 py-2 border text-center font-bold text-gray-700" rowspan="2">#</th>
                        <th class="px-2 py-2 border text-center font-bold text-gray-700" rowspan="2">เลข</th>
                        <?php foreach ($betTypes as $bt): ?>
                        <th class="px-1 py-1 border text-center font-bold text-gray-700" colspan="1"><?= $betTypeLabels[$bt] ?></th>
                        <?php endforeach; ?>
                        <th class="px-2 py-1 border text-center font-bold text-green-700 bg-green-50" rowspan="2">ยอดซื้อ</th>
                        <th class="px-2 py-1 border text-center font-bold text-red-700 bg-red-50" rowspan="2">จ่ายถ้าถูก</th>
                        <th class="px-2 py-1 border text-center font-bold text-gray-700 bg-yellow-50" rowspan="2">ได้-เสีย</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($numberRows)): ?>
                    <tr><td colspan="<?= 2 + count($betTypes) + 3 ?>" class="px-3 py-8 text-center text-gray-400"><i class="fas fa-inbox text-xl mb-1 block"></i>ไม่มีข้อมูลในวันที่เลือก</td></tr>
                    <?php else:
                        $displayed = 0;
                        foreach ($numberRows as $i => $row):
                            if ($displayed >= $showLimit) break;
                            $displayed++;
                            $isHigh = $row['total_payout'] > $totalSumBuy * 0.05; // highlight if payout > 5% of total buy
                    ?>
                    <tr class="border-b hover:bg-yellow-50 <?= $isHigh ? 'bg-red-50' : '' ?>">
                        <td class="px-2 py-1.5 border text-center text-gray-400 text-[11px]"><?= $displayed ?></td>
                        <td class="px-2 py-1.5 border text-center font-bold text-[13px]">
                            <span class="inline-block bg-blue-100 text-blue-800 px-2 py-0.5 rounded font-mono"><?= htmlspecialchars($row['number']) ?></span>
                        </td>
                        <?php foreach ($betTypes as $bt):
                            $buy = $row['cols'][$bt]['buy'];
                            $pay = $row['cols'][$bt]['payout'];
                        ?>
                        <td class="px-1 py-1 border text-right whitespace-nowrap <?= $buy > 0 ? '' : 'text-gray-300' ?>">
                            <?php if ($buy > 0): ?>
                                <span class="text-blue-600"><?= number_format($buy, 0) ?></span>
                                <br><span class="text-red-500 text-[10px]">-<?= number_format($pay, 0) ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="px-2 py-1.5 border text-right font-bold text-blue-700 bg-green-50"><?= number_format($row['total_buy'], 2) ?></td>
                        <td class="px-2 py-1.5 border text-right font-bold text-red-600 bg-red-50"><?= number_format($row['total_payout'], 2) ?></td>
                        <td class="px-2 py-1.5 border text-right font-bold bg-yellow-50 <?= $row['net'] >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                            <?= $row['net'] >= 0 ? '+' : '' ?><?= number_format($row['net'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Grand Total -->
                    <tr class="bg-green-100 font-bold border-t-2 border-green-500 sticky bottom-0">
                        <td class="px-2 py-2 border" colspan="2">รวม (<?= count($numberRows) ?> เลข)</td>
                        <?php foreach ($betTypes as $bt): ?>
                        <td class="px-1 py-2 border text-right">
                            <span class="text-blue-700"><?= number_format($grandTotals[$bt], 0) ?></span>
                            <br><span class="text-red-600 text-[10px]">-<?= number_format($grandPayouts[$bt], 0) ?></span>
                        </td>
                        <?php endforeach; ?>
                        <td class="px-2 py-2 border text-right text-blue-800 bg-green-200"><?= number_format($grandBuy, 2) ?></td>
                        <td class="px-2 py-2 border text-right text-red-800 bg-red-100"><?= number_format($grandPayout, 2) ?></td>
                        <td class="px-2 py-2 border text-right bg-yellow-100 <?= ($grandBuy - $grandPayout) >= 0 ? 'text-green-800' : 'text-red-800' ?>">
                            <?= ($grandBuy - $grandPayout) >= 0 ? '+' : '' ?><?= number_format($grandBuy - $grandPayout, 2) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($displayed < count($numberRows)): ?>
    <div class="text-center mt-3 text-sm text-gray-500">
        แสดง <?= $displayed ?> จาก <?= count($numberRows) ?> เลข — 
        <a href="?date=<?= $selectedDate ?>&lottery=<?= $selectedLottery ?>&sort=<?= $sortBy ?>&limit=9999" class="text-blue-600 hover:underline">ดูทั้งหมด</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
