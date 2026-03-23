<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$adminPage = 'rates';
$adminTitle = 'อัตราจ่าย';
$msg = '';
$msgType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    
    if ($action === 'save_rates') {
        $lotteryIds = $_POST['lottery_ids'] ?? [];
        $betTypes = $_POST['bet_type'] ?? [];
        $rateLabels = $_POST['rate_label'] ?? [];
        $payRates = $_POST['pay_rate'] ?? [];
        $minBets = $_POST['min_bet'] ?? [];
        $maxBets = $_POST['max_bet'] ?? [];
        $maxPerNumbers = $_POST['max_per_number'] ?? [];

        if (empty($lotteryIds)) {
            $msg = 'กรุณาเลือกหวยอย่างน้อย 1 รายการ';
            $msgType = 'error';
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM pay_rates WHERE lottery_type_id = ?");
            $stmtIns = $pdo->prepare("INSERT INTO pay_rates (lottery_type_id, bet_type, rate_label, pay_rate, discount, min_bet, max_bet, max_per_number) VALUES (?, ?, ?, ?, 0, ?, ?, ?)");
            
            foreach ($lotteryIds as $lid) {
                $lid = intval($lid);
                if ($lid <= 0) continue;
                $stmtDel->execute([$lid]);
                for ($i = 0; $i < count($betTypes); $i++) {
                    if (!empty($betTypes[$i]) && floatval($payRates[$i]) > 0) {
                        $stmtIns->execute([
                            $lid,
                            $betTypes[$i],
                            $rateLabels[$i] ?? '',
                            floatval($payRates[$i]),
                            intval($minBets[$i] ?? 1),
                            intval($maxBets[$i] ?? 500),
                            intval($maxPerNumbers[$i] ?? 0)
                        ]);
                    }
                }
            }
            $msg = 'บันทึกอัตราจ่ายสำเร็จ (' . count($lotteryIds) . ' หวย)';
            $msgType = 'success';
        }
    } elseif ($action === 'delete_lottery_rates') {
        $lid = intval($_POST['lottery_type_id']);
        $pdo->prepare("DELETE FROM pay_rates WHERE lottery_type_id = ?")->execute([$lid]);
        $msg = 'ลบอัตราจ่ายสำเร็จ';
        $msgType = 'success';
    }
}

// Get all lotteries grouped by category
$lotteries = $pdo->query("SELECT lt.*, lc.name as category_name FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id WHERE lt.is_active = 1 ORDER BY lc.sort_order, lt.sort_order")->fetchAll();
$categories = [];
foreach ($lotteries as $l) {
    $categories[$l['category_name']][] = $l;
}

// Load rates for editing if ?edit=id
$editLottery = intval($_GET['edit'] ?? 0);
$rates = [];
if ($editLottery > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pay_rates WHERE lottery_type_id = ? ORDER BY FIELD(bet_type, '3top','3tod','2top','2bot','run_top','run_bot')");
    $stmt->execute([$editLottery]);
    $rates = $stmt->fetchAll();
}

// Get all rates for the summary table
$allRates = $pdo->query("
    SELECT pr.*, lt.name as lottery_name, lc.name as category_name
    FROM pay_rates pr
    JOIN lottery_types lt ON pr.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    ORDER BY lc.sort_order, lt.sort_order, FIELD(pr.bet_type, '3top','3tod','2top','2bot','run_top','run_bot')
")->fetchAll();

// Group rates by lottery
$ratesByLottery = [];
foreach ($allRates as $r) {
    $ratesByLottery[$r['lottery_type_id']]['name'] = $r['lottery_name'];
    $ratesByLottery[$r['lottery_type_id']]['category'] = $r['category_name'];
    $ratesByLottery[$r['lottery_type_id']]['rates'][$r['bet_type']] = $r;
}

$betTypeLabels = [
    '3top' => '3บน', '3tod' => '3โต๊ด',
    '2top' => '2บน', '2bot' => '2ล่าง',
    'run_top' => 'วิ่งบน', 'run_bot' => 'วิ่งล่าง',
];

$defaultBetTypes = [
    ['type' => '3top', 'label' => '3 ตัวบน', 'rate' => 800, 'min' => 1, 'max' => 500, 'max_num' => 0],
    ['type' => '3tod', 'label' => '3 ตัวโต๊ด', 'rate' => 125, 'min' => 1, 'max' => 500, 'max_num' => 0],
    ['type' => '2top', 'label' => '2 ตัวบน', 'rate' => 100, 'min' => 1, 'max' => 500, 'max_num' => 0],
    ['type' => '2bot', 'label' => '2 ตัวล่าง', 'rate' => 100, 'min' => 1, 'max' => 500, 'max_num' => 0],
    ['type' => 'run_top', 'label' => 'วิ่งบน', 'rate' => 3, 'min' => 1, 'max' => 5000, 'max_num' => 0],
    ['type' => 'run_bot', 'label' => 'วิ่งล่าง', 'rate' => 4, 'min' => 1, 'max' => 5000, 'max_num' => 0],
];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check' : 'exclamation' ?>-circle mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Rates Form -->
<div class="bg-white rounded-xl shadow-sm border mb-6">
    <div class="px-4 py-3 border-b bg-green-50">
        <span class="font-bold text-green-700 text-sm"><i class="fas fa-coins mr-1"></i>กำหนดอัตราจ่าย</span>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="form_action" value="save_rates">
        
        <!-- Rate Table -->
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ประเภท</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ชื่อแสดง</th>
                        <th class="px-3 py-2 text-center text-xs text-gray-500">จ่าย (บาท)</th>
                        <th class="px-3 py-2 text-center text-xs text-gray-500">ขั้นต่ำ/บิล</th>
                        <th class="px-3 py-2 text-center text-xs text-gray-500">สูงสุด/บิล</th>
                        <th class="px-3 py-2 text-center text-xs text-gray-500 bg-orange-50 text-orange-700">รับสูงสุด/เลข</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rateMap = [];
                    foreach ($rates as $r) $rateMap[$r['bet_type']] = $r;
                    foreach ($defaultBetTypes as $d):
                        $existing = $rateMap[$d['type']] ?? null;
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2">
                            <input type="hidden" name="bet_type[]" value="<?= $d['type'] ?>">
                            <span class="text-xs font-medium text-gray-700"><?= $d['type'] ?></span>
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" name="rate_label[]" value="<?= htmlspecialchars($existing['rate_label'] ?? $d['label']) ?>" class="w-full border rounded px-2 py-1 text-sm focus:border-green-500 outline-none">
                        </td>
                        <td class="px-3 py-2">
                            <input type="number" name="pay_rate[]" value="<?= $existing['pay_rate'] ?? $d['rate'] ?>" step="0.01" class="w-full border rounded px-2 py-1 text-sm text-center focus:border-green-500 outline-none">
                        </td>
                        <td class="px-3 py-2">
                            <input type="number" name="min_bet[]" value="<?= $existing['min_bet'] ?? $d['min'] ?>" class="w-full border rounded px-2 py-1 text-sm text-center focus:border-green-500 outline-none">
                        </td>
                        <td class="px-3 py-2">
                            <input type="number" name="max_bet[]" value="<?= $existing['max_bet'] ?? $d['max'] ?>" class="w-full border rounded px-2 py-1 text-sm text-center focus:border-green-500 outline-none">
                        </td>
                        <td class="px-3 py-2 bg-orange-50">
                            <input type="number" name="max_per_number[]" value="<?= $existing['max_per_number'] ?? $d['max_num'] ?>" placeholder="0=ไม่จำกัด" class="w-full border border-orange-300 rounded px-2 py-1 text-sm text-center focus:border-orange-500 outline-none">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Lottery Checklist -->
        <div class="border rounded-lg p-3 mb-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-bold text-gray-700"><i class="fas fa-check-square mr-1 text-green-600"></i>เลือกหวยที่ต้องการใช้อัตราจ่ายนี้</span>
                <label class="flex items-center space-x-2 text-sm cursor-pointer bg-green-100 px-3 py-1 rounded-full hover:bg-green-200 transition">
                    <input type="checkbox" id="selectAll" class="w-4 h-4 text-green-500 rounded" onchange="toggleAll(this)">
                    <span class="text-green-700 font-medium">เลือกทั้งหมด</span>
                </label>
            </div>
            
            <?php foreach ($categories as $catName => $catLotteries): ?>
            <div class="mb-3">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xs font-bold text-gray-500 uppercase"><?= htmlspecialchars($catName) ?></span>
                    <button type="button" onclick="toggleCategory(this)" class="text-[10px] text-blue-500 hover:text-blue-700 cursor-pointer">เลือกกลุ่มนี้</button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-1">
                    <?php foreach ($catLotteries as $l): ?>
                    <label class="flex items-center space-x-1.5 text-xs p-1.5 rounded hover:bg-green-50 cursor-pointer">
                        <input type="checkbox" name="lottery_ids[]" value="<?= $l['id'] ?>" class="w-3.5 h-3.5 text-green-500 rounded lottery-cb">
                        <span class="truncate"><?= htmlspecialchars($l['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
                <i class="fas fa-save mr-1"></i>บันทึกอัตราจ่าย
            </button>
        </div>
    </form>
</div>

<!-- Existing Rates Summary Table -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <span class="font-bold text-gray-700 text-sm"><i class="fas fa-list mr-1"></i>อัตราจ่ายที่กำหนดไว้ (<?= count($ratesByLottery) ?> หวย)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-2 py-2 text-left text-xs text-gray-600 sticky left-0 bg-gray-100 z-10">หวย</th>
                    <?php foreach ($betTypeLabels as $bt => $label): ?>
                    <th class="px-2 py-2 text-center text-xs text-gray-600"><?= $label ?></th>
                    <?php endforeach; ?>
                    <th class="px-2 py-2 text-center text-xs text-gray-600">ขั้นต่ำ</th>
                    <th class="px-2 py-2 text-center text-xs text-gray-600">สูงสุด</th>
                    <th class="px-2 py-2 text-center text-xs text-orange-600 bg-orange-50">สูงสุด/เลข</th>
                    <th class="px-2 py-2 text-center text-xs text-gray-500">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ratesByLottery)): ?>
                <tr><td colspan="10" class="px-3 py-8 text-center text-gray-400">ยังไม่มีอัตราจ่าย</td></tr>
                <?php else: 
                    $lastCat = '';
                    foreach ($ratesByLottery as $lid => $data):
                        if ($data['category'] !== $lastCat):
                            $lastCat = $data['category'];
                ?>
                <tr class="bg-green-50">
                    <td colspan="10" class="px-2 py-1.5 text-xs font-bold text-green-700"><?= htmlspecialchars($lastCat) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-2 py-1.5 font-medium text-gray-800 sticky left-0 bg-white whitespace-nowrap"><?= htmlspecialchars($data['name']) ?></td>
                    <?php foreach ($betTypeLabels as $bt => $label): 
                        $r = $data['rates'][$bt] ?? null;
                    ?>
                    <td class="px-2 py-1.5 text-center <?= $r ? 'text-gray-800' : 'text-gray-300' ?>"><?= $r ? number_format($r['pay_rate']) : '-' ?></td>
                    <?php endforeach; ?>
                    <td class="px-2 py-1.5 text-center text-gray-600">
                        <?php 
                        $firstRate = reset($data['rates']);
                        echo $firstRate ? $firstRate['min_bet'] : '-';
                        ?>
                    </td>
                    <td class="px-2 py-1.5 text-center text-gray-600">
                        <?= $firstRate ? number_format($firstRate['max_bet']) : '-' ?>
                    </td>
                    <td class="px-2 py-1.5 text-center bg-orange-50 text-orange-700 font-medium">
                        <?= ($firstRate && $firstRate['max_per_number'] > 0) ? number_format($firstRate['max_per_number']) : '-' ?>
                    </td>
                    <td class="px-2 py-1.5 text-center whitespace-nowrap">
                        <a href="?edit=<?= $lid ?>" class="text-blue-500 hover:text-blue-700 mr-1" title="แก้ไข"><i class="fas fa-edit"></i></a>
                        <button onclick="deleteRates(<?= $lid ?>, '<?= htmlspecialchars($data['name']) ?>')" class="text-red-400 hover:text-red-600" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                        <form id="deleteRatesForm_<?= $lid ?>" method="POST" class="hidden">
                            <input type="hidden" name="form_action" value="delete_lottery_rates">
                            <input type="hidden" name="lottery_type_id" value="<?= $lid ?>">
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleAll(master) {
    document.querySelectorAll('.lottery-cb').forEach(cb => cb.checked = master.checked);
}

function toggleCategory(btn) {
    const parent = btn.closest('.mb-3');
    const cbs = parent.querySelectorAll('.lottery-cb');
    const allChecked = [...cbs].every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
    updateSelectAll();
}

function updateSelectAll() {
    const all = document.querySelectorAll('.lottery-cb');
    const checked = document.querySelectorAll('.lottery-cb:checked');
    document.getElementById('selectAll').checked = all.length === checked.length;
}

document.querySelectorAll('.lottery-cb').forEach(cb => cb.addEventListener('change', updateSelectAll));

function deleteRates(lid, name) {
    Swal.fire({
        title: `ลบอัตราจ่าย ${name}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i>ลบ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteRatesForm_' + lid).submit();
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
