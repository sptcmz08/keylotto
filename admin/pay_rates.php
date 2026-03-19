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
        $lotteryId = intval($_POST['lottery_type_id']);
        $betTypes = $_POST['bet_type'] ?? [];
        $rateLabels = $_POST['rate_label'] ?? [];
        $payRates = $_POST['pay_rate'] ?? [];
        $discounts = $_POST['discount'] ?? [];
        $minBets = $_POST['min_bet'] ?? [];
        $maxBets = $_POST['max_bet'] ?? [];

        // Delete old rates for this lottery
        $pdo->prepare("DELETE FROM pay_rates WHERE lottery_type_id = ?")->execute([$lotteryId]);

        // Insert new rates
        $stmt = $pdo->prepare("INSERT INTO pay_rates (lottery_type_id, bet_type, rate_label, pay_rate, discount, min_bet, max_bet) VALUES (?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($betTypes); $i++) {
            if (!empty($betTypes[$i]) && floatval($payRates[$i]) > 0) {
                $stmt->execute([
                    $lotteryId,
                    $betTypes[$i],
                    $rateLabels[$i] ?? '',
                    floatval($payRates[$i]),
                    floatval($discounts[$i] ?? 0),
                    intval($minBets[$i] ?? 1),
                    intval($maxBets[$i] ?? 500)
                ]);
            }
        }
        $msg = 'บันทึกอัตราจ่ายสำเร็จ';
        $msgType = 'success';
    } elseif ($action === 'copy_rates') {
        $fromId = intval($_POST['from_lottery_id']);
        $toId = intval($_POST['to_lottery_id']);
        if ($fromId && $toId && $fromId !== $toId) {
            $pdo->prepare("DELETE FROM pay_rates WHERE lottery_type_id = ?")->execute([$toId]);
            $pdo->prepare("INSERT INTO pay_rates (lottery_type_id, bet_type, rate_label, pay_rate, discount, min_bet, max_bet) SELECT ?, bet_type, rate_label, pay_rate, discount, min_bet, max_bet FROM pay_rates WHERE lottery_type_id = ?")->execute([$toId, $fromId]);
            $msg = 'คัดลอกอัตราจ่ายสำเร็จ';
            $msgType = 'success';
        }
    }
}

$selectedLottery = intval($_GET['lottery_id'] ?? 0);
$lotteries = $pdo->query("SELECT lt.*, lc.name as category_name FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id ORDER BY lc.sort_order, lt.sort_order")->fetchAll();

$rates = [];
if ($selectedLottery > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pay_rates WHERE lottery_type_id = ? ORDER BY FIELD(bet_type, '3top','3tod','2top','2bot','run_top','run_bot')");
    $stmt->execute([$selectedLottery]);
    $rates = $stmt->fetchAll();
}

$defaultBetTypes = [
    ['type' => '3top', 'label' => '3 ตัวบน', 'rate' => 800, 'discount' => 5, 'min' => 1, 'max' => 500],
    ['type' => '3tod', 'label' => '3 ตัวโต๊ด', 'rate' => 125, 'discount' => 5, 'min' => 1, 'max' => 500],
    ['type' => '2top', 'label' => '2 ตัวบน', 'rate' => 100, 'discount' => 0, 'min' => 1, 'max' => 500],
    ['type' => '2bot', 'label' => '2 ตัวล่าง', 'rate' => 100, 'discount' => 0, 'min' => 1, 'max' => 500],
    ['type' => 'run_top', 'label' => 'วิ่งบน', 'rate' => 3, 'discount' => 12, 'min' => 1, 'max' => 5000],
    ['type' => 'run_bot', 'label' => 'วิ่งล่าง', 'rate' => 4, 'discount' => 12, 'min' => 1, 'max' => 5000],
];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Select Lottery -->
<div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
    <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="text-xs text-gray-500 block mb-1">เลือกหวย</label>
            <select id="lotterySelect" onchange="window.location='?lottery_id='+this.value" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none">
                <option value="">-- เลือกหวย --</option>
                <?php foreach ($lotteries as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $selectedLottery == $l['id'] ? 'selected' : '' ?>>
                    [<?= htmlspecialchars($l['category_name']) ?>] <?= htmlspecialchars($l['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<?php if ($selectedLottery > 0): ?>
<!-- Copy Rates -->
<div class="bg-yellow-50 rounded-xl border border-yellow-200 p-4 mb-6">
    <form method="POST" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="form_action" value="copy_rates">
        <input type="hidden" name="to_lottery_id" value="<?= $selectedLottery ?>">
        <div class="flex-1 min-w-[200px]">
            <label class="text-xs text-yellow-700 block mb-1"><i class="fas fa-copy mr-1"></i>คัดลอกอัตราจ่ายจากหวยอื่น</label>
            <select name="from_lottery_id" required class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                <option value="">-- เลือกหวยต้นทาง --</option>
                <?php foreach ($lotteries as $l): if ($l['id'] != $selectedLottery): ?>
                <option value="<?= $l['id'] ?>">[<?= htmlspecialchars($l['category_name']) ?>] <?= htmlspecialchars($l['name']) ?></option>
                <?php endif; endforeach; ?>
            </select>
        </div>
        <button type="submit" onclick="return confirm('คัดลอกอัตราจ่าย? ข้อมูลเดิมจะถูกเขียนทับ')" class="bg-yellow-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-yellow-600 transition">
            <i class="fas fa-copy mr-1"></i>คัดลอก
        </button>
    </form>
</div>

<!-- Rates Form -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-green-50">
        <span class="font-bold text-green-700 text-sm"><i class="fas fa-coins mr-1"></i>ตั้งค่าอัตราจ่าย</span>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="form_action" value="save_rates">
        <input type="hidden" name="lottery_type_id" value="<?= $selectedLottery ?>">
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ประเภท</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ชื่อแสดง</th>
                        <th class="px-3 py-2 text-center text-xs text-gray-500">จ่าย (บาท)</th>
                        <th class="px-3 py-2 text-center text-xs text-gray-500">ลด (%)</th>
                        <th class="px-3 py-2 text-center text-xs text-gray-500">ขั้นต่ำ</th>
                        <th class="px-3 py-2 text-center text-xs text-gray-500">สูงสุด</th>
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
                            <input type="number" name="discount[]" value="<?= $existing['discount'] ?? $d['discount'] ?>" step="0.01" class="w-full border rounded px-2 py-1 text-sm text-center focus:border-green-500 outline-none">
                        </td>
                        <td class="px-3 py-2">
                            <input type="number" name="min_bet[]" value="<?= $existing['min_bet'] ?? $d['min'] ?>" class="w-full border rounded px-2 py-1 text-sm text-center focus:border-green-500 outline-none">
                        </td>
                        <td class="px-3 py-2">
                            <input type="number" name="max_bet[]" value="<?= $existing['max_bet'] ?? $d['max'] ?>" class="w-full border rounded px-2 py-1 text-sm text-center focus:border-green-500 outline-none">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 text-right">
            <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
                <i class="fas fa-save mr-1"></i>บันทึกอัตราจ่าย
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border p-12 text-center">
    <i class="fas fa-coins text-gray-300 text-4xl mb-3 block"></i>
    <p class="text-gray-400">กรุณาเลือกหวยเพื่อตั้งค่าอัตราจ่าย</p>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
