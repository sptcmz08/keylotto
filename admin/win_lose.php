<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'win_lose';
$adminTitle = 'คาดคะเน ได้-เสีย';

// Filters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedLottery = $_GET['lottery'] ?? 'all';

// Get lottery types for filter
$lotteryTypes = $pdo->query("SELECT lt.id, lt.name, lc.name as cat_name FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id WHERE lt.is_active = 1 ORDER BY lt.category_id, lt.name")->fetchAll();

// Build WHERE clause
$where = "WHERE b.draw_date = ? AND b.status != 'cancelled'";
$params = [$selectedDate];
if ($selectedLottery !== 'all') {
    $where .= " AND b.lottery_type_id = ?";
    $params[] = $selectedLottery;
}

// Summary by bet type across ALL lotteries (or selected)
$summaryStmt = $pdo->prepare("
    SELECT 
        bi.bet_type,
        SUM(bi.amount) as total_buy,
        COUNT(*) as total_count
    FROM bet_items bi
    JOIN bets b ON bi.bet_id = b.id
    $where
    GROUP BY bi.bet_type
");
$summaryStmt->execute($params);
$summaryByType = [];
foreach ($summaryStmt->fetchAll() as $r) {
    $summaryByType[$r['bet_type']] = $r;
}

// Get pay rates for payout calculation
$rateQuery = "SELECT bet_type, AVG(pay_rate) as avg_rate FROM pay_rates GROUP BY bet_type";
$rates = [];
foreach ($pdo->query($rateQuery)->fetchAll() as $r) {
    $rates[$r['bet_type']] = $r['avg_rate'];
}

// Per-lottery summary
$perLotteryStmt = $pdo->prepare("
    SELECT 
        lt.id as lottery_id,
        lt.name as lottery_name,
        lc.name as cat_name,
        bi.bet_type,
        SUM(bi.amount) as total_buy,
        COUNT(*) as total_count
    FROM bet_items bi
    JOIN bets b ON bi.bet_id = b.id
    JOIN lottery_types lt ON b.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE b.draw_date = ? AND b.status != 'cancelled'
    GROUP BY lt.id, lt.name, lc.name, bi.bet_type
    ORDER BY lc.name, lt.name, bi.bet_type
");
$perLotteryStmt->execute([$selectedDate]);
$perLotteryData = [];
foreach ($perLotteryStmt->fetchAll() as $r) {
    $key = $r['lottery_id'];
    if (!isset($perLotteryData[$key])) {
        $perLotteryData[$key] = ['name' => $r['lottery_name'], 'cat' => $r['cat_name'], 'types' => []];
    }
    $perLotteryData[$key]['types'][$r['bet_type']] = $r;
}

// Total bets count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bets b $where");
$countStmt->execute($params);
$totalBetCount = $countStmt->fetchColumn();

// Grand totals
$grandBuy = 0;
$betTypes = ['3top', '3tod', '2top', '2bot', 'run_top', 'run_bot'];
$betTypeLabels = ['3top' => '3 ตัวบน', '3tod' => '3 ตัวโต๊ด', '2top' => '2 ตัวบน', '2bot' => '2 ตัวล่าง', 'run_top' => 'วิ่งบน', 'run_bot' => 'วิ่งล่าง'];

foreach ($betTypes as $bt) {
    if (isset($summaryByType[$bt])) {
        $grandBuy += $summaryByType[$bt]['total_buy'];
    }
}

$grandNet = $grandBuy;

// Potential max payout (worst case: every number wins)
$grandPayout = 0;
foreach ($betTypes as $bt) {
    if (isset($summaryByType[$bt]) && isset($rates[$bt])) {
        $grandPayout += $summaryByType[$bt]['total_buy'] * $rates[$bt];
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
            <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
                <i class="fas fa-search mr-1"></i> ดูรายงาน
            </button>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-lg border p-4 text-center">
            <p class="text-xs text-gray-400">จำนวนโพย</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format($totalBetCount) ?></p>
        </div>
        <div class="bg-green-50 rounded-lg border border-green-200 p-4 text-center">
            <p class="text-xs text-green-600">ยอดรับสุทธิ</p>
            <p class="text-xl font-bold text-green-700"><?= number_format($grandNet, 2) ?></p>
        </div>

        <div class="bg-blue-50 rounded-lg border border-blue-200 p-4 text-center">
            <p class="text-xs text-blue-500">ยอดซื้อทั้งหมด</p>
            <p class="text-xl font-bold text-blue-700"><?= number_format($grandBuy, 2) ?></p>
        </div>
    </div>

    <!-- Summary by Bet Type -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-4">
        <div class="bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-2 font-bold text-sm">
            <i class="fas fa-table mr-1"></i> สรุปยอดตามประเภทแทง <?= $selectedLottery === 'all' ? '(รวมทุกหวย)' : '' ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left border text-xs font-bold text-gray-700">ประเภท</th>
                        <th class="px-3 py-2 text-center border text-xs font-bold text-gray-700">จำนวน</th>
                        <th class="px-3 py-2 text-right border text-xs font-bold text-gray-700">ยอดซื้อ</th>
                        <th class="px-3 py-2 text-right border text-xs font-bold text-gray-700">อัตราจ่าย (เฉลี่ย)</th>
                        <th class="px-3 py-2 text-right border text-xs font-bold text-gray-700">จ่ายสูงสุด (ถ้าถูกทุกตัว)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($betTypes as $bt):
                        $data = $summaryByType[$bt] ?? null;
                        $rate = $rates[$bt] ?? 0;
                        $buy = $data ? $data['total_buy'] : 0;
                        $count = $data ? $data['total_count'] : 0;
                        $maxPay = $buy * $rate;
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2 border font-medium"><?= $betTypeLabels[$bt] ?></td>
                        <td class="px-3 py-2 text-center border"><?= number_format($count) ?></td>
                        <td class="px-3 py-2 text-right border text-blue-600 font-bold"><?= number_format($buy, 2) ?></td>
                        <td class="px-3 py-2 text-right border text-gray-600"><?= number_format($rate, 2) ?></td>
                        <td class="px-3 py-2 text-right border text-red-600 font-bold"><?= number_format($maxPay, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="bg-green-50 font-bold">
                        <td class="px-3 py-2 border text-green-700">รวมทั้งหมด</td>
                        <td class="px-3 py-2 text-center border"><?= number_format(array_sum(array_column($summaryByType, 'total_count'))) ?></td>
                        <td class="px-3 py-2 text-right border text-blue-700"><?= number_format($grandBuy, 2) ?></td>
                        <td class="px-3 py-2 text-right border text-gray-500">-</td>
                        <td class="px-3 py-2 text-right border text-red-700"><?= number_format($grandPayout, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Per Lottery Breakdown -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 font-bold text-sm">
            <i class="fas fa-list mr-1"></i> แยกตามหวย (<?= count($perLotteryData) ?> หวย)
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12px]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-2 text-left border font-bold text-gray-700">#</th>
                        <th class="px-2 py-2 text-left border font-bold text-gray-700">หวย</th>
                        <th class="px-2 py-2 text-right border font-bold text-gray-700">3ตัวบน</th>
                        <th class="px-2 py-2 text-right border font-bold text-gray-700">3ตัวโต๊ด</th>
                        <th class="px-2 py-2 text-right border font-bold text-gray-700">2ตัวบน</th>
                        <th class="px-2 py-2 text-right border font-bold text-gray-700">2ตัวล่าง</th>
                        <th class="px-2 py-2 text-right border font-bold text-gray-700">วิ่งบน</th>
                        <th class="px-2 py-2 text-right border font-bold text-gray-700">วิ่งล่าง</th>
                        <th class="px-2 py-2 text-right border font-bold text-green-700 bg-green-50">รวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($perLotteryData)): ?>
                    <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400"><i class="fas fa-inbox text-xl mb-1 block"></i>ไม่มีข้อมูลในวันที่เลือก</td></tr>
                    <?php else:
                        $i = 0;
                        $grandTotals = array_fill_keys($betTypes, 0);
                        $grandSum = 0;
                        foreach ($perLotteryData as $lid => $ld):
                            $i++;
                            $rowTotal = 0;
                            foreach ($betTypes as $bt) {
                                $amt = isset($ld['types'][$bt]) ? $ld['types'][$bt]['total_buy'] : 0;
                                $grandTotals[$bt] += $amt;
                                $rowTotal += $amt;
                            }
                            $grandSum += $rowTotal;
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-2 py-1.5 border text-center text-gray-400"><?= $i ?></td>
                        <td class="px-2 py-1.5 border font-medium whitespace-nowrap">[<?= htmlspecialchars($ld['cat']) ?>] <?= htmlspecialchars($ld['name']) ?></td>
                        <?php foreach ($betTypes as $bt):
                            $amt = isset($ld['types'][$bt]) ? $ld['types'][$bt]['total_buy'] : 0;
                        ?>
                        <td class="px-2 py-1.5 border text-right <?= $amt > 0 ? 'text-blue-600' : 'text-gray-300' ?>"><?= $amt > 0 ? number_format($amt, 2) : '-' ?></td>
                        <?php endforeach; ?>
                        <td class="px-2 py-1.5 border text-right font-bold text-green-700 bg-green-50"><?= number_format($rowTotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Grand Total -->
                    <tr class="bg-green-100 font-bold border-t-2 border-green-500">
                        <td class="px-2 py-2 border" colspan="2">รวมทั้งหมด</td>
                        <?php foreach ($betTypes as $bt): ?>
                        <td class="px-2 py-2 border text-right text-blue-700"><?= number_format($grandTotals[$bt], 2) ?></td>
                        <?php endforeach; ?>
                        <td class="px-2 py-2 border text-right text-green-800 bg-green-200"><?= number_format($grandSum, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
