<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$adminPage = 'rate_settings';
$adminTitle = 'ตั้งค่าอัตราเกิน';
$msg = '';
$msgType = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save_settings') {
    $lotteryIds = $_POST['lottery_ids'] ?? [];
    $threshold = intval($_POST['over_limit_threshold'] ?? 50);
    $betTypes = ['2top', '2bot', '3top', '3tod', 'run_top', 'run_bot'];
    $overRates = [];
    foreach ($betTypes as $bt) {
        $overRates[$bt] = floatval($_POST['over_rate_' . $bt] ?? 0);
    }

    if (empty($lotteryIds)) {
        $msg = 'กรุณาเลือกหวยอย่างน้อย 1 รายการ';
        $msgType = 'error';
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE pay_rates SET over_threshold = ?, over_pay_rate = ? WHERE lottery_type_id = ? AND bet_type = ?");
        foreach ($lotteryIds as $lid) {
            $lid = intval($lid);
            foreach ($betTypes as $bt) {
                $stmtUpdate->execute([$threshold, $overRates[$bt], $lid, $bt]);
            }
        }
        $msg = 'บันทึกอัตราจ่ายเกินสำเร็จ (' . count($lotteryIds) . ' หวย)';
        $msgType = 'success';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'clear_over') {
    $lid = intval($_POST['lottery_type_id']);
    $pdo->prepare("UPDATE pay_rates SET over_threshold = 0, over_pay_rate = 0 WHERE lottery_type_id = ?")->execute([$lid]);
    $msg = 'ล้างค่าอัตราเกินสำเร็จ';
    $msgType = 'success';
}

// Get all lotteries grouped by category
$lotteries = $pdo->query("SELECT lt.*, lc.name as category_name FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id ORDER BY lc.sort_order, lt.sort_order")->fetchAll();
$categories = [];
foreach ($lotteries as $l) {
    $categories[$l['category_name']][] = $l;
}

// Load rates for editing if ?edit=id
$editLottery = intval($_GET['edit'] ?? 0);
$editRates = [];
$editLotteryName = '';
if ($editLottery > 0) {
    $stmt = $pdo->prepare("SELECT bet_type, pay_rate, over_threshold, over_pay_rate FROM pay_rates WHERE lottery_type_id = ?");
    $stmt->execute([$editLottery]);
    foreach ($stmt->fetchAll() as $r) {
        $editRates[$r['bet_type']] = $r;
    }
    $stmtName = $pdo->prepare("SELECT name FROM lottery_types WHERE id = ?");
    $stmtName->execute([$editLottery]);
    $editLotteryName = $stmtName->fetchColumn() ?: '';
}

// Get summary of all lotteries with over-limit configured
$overSummary = $pdo->query("
    SELECT pr.lottery_type_id, lt.name as lottery_name, lc.name as category_name,
           pr.bet_type, pr.pay_rate, pr.over_threshold, pr.over_pay_rate
    FROM pay_rates pr
    JOIN lottery_types lt ON pr.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE pr.over_threshold > 0 AND pr.over_pay_rate > 0
    ORDER BY lc.sort_order, lt.sort_order, FIELD(pr.bet_type, '3top','3tod','2top','2bot','run_top','run_bot')
")->fetchAll();

$overByLottery = [];
foreach ($overSummary as $r) {
    $overByLottery[$r['lottery_type_id']]['name'] = $r['lottery_name'];
    $overByLottery[$r['lottery_type_id']]['category'] = $r['category_name'];
    $overByLottery[$r['lottery_type_id']]['threshold'] = $r['over_threshold'];
    $overByLottery[$r['lottery_type_id']]['rates'][$r['bet_type']] = ['pay' => $r['pay_rate'], 'over' => $r['over_pay_rate']];
}

// Which lotteries have pay_rates at all
$hasRates = $pdo->query("SELECT DISTINCT lottery_type_id FROM pay_rates")->fetchAll(PDO::FETCH_COLUMN);

$betTypeLabels = [
    '2top' => '2 ตัวบน', '2bot' => '2 ตัวล่าง',
    '3top' => '3 ตัวบน', '3tod' => '3 ตัวโต๊ด',
    'run_top' => 'วิ่งบน', 'run_bot' => 'วิ่งล่าง',
];

$defaultOverRates = [
    '2top' => 95, '2bot' => 95,
    '3top' => 800, '3tod' => 125,
    'run_top' => 3, 'run_bot' => 4,
];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check' : 'exclamation' ?>-circle mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="bg-white border border-[#2e7d32] rounded-xl overflow-hidden mb-6">
    <div class="bg-[#2e7d32] text-white px-4 py-3 font-bold text-sm flex items-center">
        <i class="fas fa-sliders-h mr-2"></i> ตั้งค่าอัตราจ่ายเมื่อเกินจำนวน
    </div>
    
    <form method="POST" class="p-4 space-y-4">
        <input type="hidden" name="form_action" value="save_settings">
        
        <!-- คำอธิบาย -->
        <div class="bg-yellow-50 border border-yellow-300 rounded p-3 text-sm text-yellow-800">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>หลักการ:</strong> เมื่อรายการเดิมพัน <u>แต่ละประเภท</u> เกินจำนวนที่กำหนด อัตราจ่ายจะลดลงตามที่ตั้งค่า<br>
            <span class="text-xs mt-1 block text-yellow-700">
                กำหนดค่าแยกหวยได้ เช่น หวยไทย จ่าย 2 ตัวบน = 95 แต่หวยอื่น = 100
            </span>
        </div>
        
        <!-- Threshold -->
        <div class="border border-gray-200 rounded p-4">
            <label class="block text-sm font-bold text-gray-700 mb-2">
                <i class="fas fa-hashtag mr-1 text-[#2e7d32]"></i> จำนวนรายการที่เกิน (ต่อประเภท)
            </label>
            <div class="flex items-center gap-2">
                <input type="number" name="over_limit_threshold" value="<?= $editRates ? (reset($editRates)['over_threshold'] ?: 50) : 50 ?>" 
                    class="border border-gray-300 rounded px-3 py-2 text-center w-32 text-lg font-bold focus:border-[#2e7d32] outline-none" min="1">
                <span class="text-gray-500 text-sm">รายการ</span>
            </div>
        </div>
        
        <!-- Rate Settings Table -->
        <div class="border border-gray-200 rounded overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[#e8f5e9]">
                    <tr>
                        <th class="px-4 py-2 text-left font-bold text-[#2e7d32] border-b border-gray-200">ประเภท</th>
                        <th class="px-4 py-2 text-center font-bold text-[#e53935] border-b border-gray-200">อัตราจ่ายเมื่อเกิน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($betTypeLabels as $bt => $label): 
                        $val = $editRates[$bt]['over_pay_rate'] ?? $defaultOverRates[$bt] ?? 0;
                    ?>
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium"><?= $label ?></td>
                        <td class="px-4 py-3 text-center">
                            <input type="number" name="over_rate_<?= $bt ?>" value="<?= $val ?>" 
                                class="border border-gray-300 rounded px-2 py-1 text-center w-24 font-bold text-[#e53935] focus:border-[#e53935] outline-none" step="0.01">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Lottery Checklist -->
        <div class="border rounded-lg p-3">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-bold text-gray-700"><i class="fas fa-check-square mr-1 text-green-600"></i>เลือกหวยที่ต้องการใช้อัตราเกินนี้</span>
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
                    <?php foreach ($catLotteries as $l): 
                        $hasRate = in_array($l['id'], $hasRates);
                    ?>
                    <label class="flex items-center space-x-1.5 text-xs p-1.5 rounded hover:bg-green-50 cursor-pointer <?= $hasRate ? '' : 'opacity-40' ?>">
                        <input type="checkbox" name="lottery_ids[]" value="<?= $l['id'] ?>" class="w-3.5 h-3.5 text-green-500 rounded lottery-cb" <?= !$hasRate ? 'disabled title="ยังไม่มีอัตราจ่าย"' : '' ?>>
                        <img src="<?= getFlagForCountry($l['flag_emoji'] ?? '', $l['name']) ?>" class="inline-block w-4 h-3 object-cover rounded border">
                        <span class="truncate"><?= htmlspecialchars($l['name']) ?></span>
                        <?php if (isset($overByLottery[$l['id']])): ?>
                        <span class="text-red-500 text-[9px]">⚡</span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <p class="text-xs text-gray-400 mt-2"><i class="fas fa-info-circle mr-1"></i>⚡ = มีอัตราเกินแล้ว | หวยจาง = ยังไม่มีอัตราจ่าย (ต้องตั้งอัตราจ่ายก่อน)</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2 bg-[#2e7d32] text-white rounded-lg text-sm font-medium hover:bg-green-700 transition">
                <i class="fas fa-save mr-1"></i> บันทึกอัตราเกิน
            </button>
        </div>
    </form>
</div>

<?php if ($editLottery > 0 && !empty($editRates)): ?>
<div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700">
    <i class="fas fa-info-circle mr-1"></i>โหลดค่าจาก: <strong><?= htmlspecialchars($editLotteryName) ?></strong> — แก้ไขแล้วเลือกหวยที่ต้องการใช้
</div>
<?php endif; ?>

<!-- Summary Table -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <span class="font-bold text-gray-700 text-sm"><i class="fas fa-list mr-1"></i>อัตราเกินที่กำหนดไว้ (<?= count($overByLottery) ?> หวย)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-2 py-2 text-left text-xs text-gray-600">หวย</th>
                    <th class="px-2 py-2 text-center text-xs text-gray-600">เกิน (รายการ)</th>
                    <?php foreach (['2top'=>'2บน', '2bot'=>'2ล่าง', '3top'=>'3บน', '3tod'=>'3โต๊ด', 'run_top'=>'วิ่งบน', 'run_bot'=>'วิ่งล่าง'] as $bt => $label): ?>
                    <th class="px-2 py-2 text-center text-xs text-gray-600"><?= $label ?></th>
                    <?php endforeach; ?>
                    <th class="px-2 py-2 text-center text-xs text-gray-500">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($overByLottery)): ?>
                <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400">ยังไม่มีอัตราเกิน</td></tr>
                <?php else: 
                    $lastCat = '';
                    foreach ($overByLottery as $lid => $data):
                        if ($data['category'] !== $lastCat):
                            $lastCat = $data['category'];
                ?>
                <tr class="bg-red-50">
                    <td colspan="9" class="px-2 py-1.5 text-xs font-bold text-red-700"><?= htmlspecialchars($lastCat) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-2 py-1.5 font-medium text-gray-800 whitespace-nowrap"><?= htmlspecialchars($data['name']) ?></td>
                    <td class="px-2 py-1.5 text-center font-bold text-red-600"><?= $data['threshold'] ?></td>
                    <?php foreach (['2top', '2bot', '3top', '3tod', 'run_top', 'run_bot'] as $bt): 
                        $r = $data['rates'][$bt] ?? null;
                    ?>
                    <td class="px-2 py-1.5 text-center">
                        <?php if ($r): ?>
                        <span class="text-gray-400 text-[10px]"><?= number_format($r['pay']) ?>→</span>
                        <span class="text-red-600 font-bold"><?= number_format($r['over']) ?></span>
                        <?php else: ?>
                        <span class="text-gray-300">-</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="px-2 py-1.5 text-center whitespace-nowrap">
                        <a href="?edit=<?= $lid ?>" class="text-blue-500 hover:text-blue-700 mr-1" title="โหลดค่ามาแก้ไข"><i class="fas fa-edit"></i></a>
                        <button onclick="clearOver(<?= $lid ?>, '<?= htmlspecialchars($data['name']) ?>')" class="text-red-400 hover:text-red-600" title="ล้างค่า"><i class="fas fa-trash-alt"></i></button>
                        <form id="clearForm_<?= $lid ?>" method="POST" class="hidden">
                            <input type="hidden" name="form_action" value="clear_over">
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
    document.querySelectorAll('.lottery-cb:not(:disabled)').forEach(cb => cb.checked = master.checked);
}

function toggleCategory(btn) {
    const parent = btn.closest('.mb-3');
    const cbs = parent.querySelectorAll('.lottery-cb:not(:disabled)');
    const allChecked = [...cbs].every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
}

function clearOver(lid, name) {
    Swal.fire({
        title: `ล้างอัตราเกิน ${name}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i>ล้าง',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('clearForm_' + lid).submit();
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
