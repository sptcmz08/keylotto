<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$adminPage = 'results';
$adminTitle = 'จัดการผลรางวัล';
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    
    if ($action === 'save_result') {
        $lotteryId = intval($_POST['lottery_type_id']);
        $drawDate = $_POST['draw_date'];
        $threeTop = trim($_POST['three_top'] ?? '');
        $threeTod = trim($_POST['three_tod'] ?? '');
        $twoTop = trim($_POST['two_top'] ?? '');
        $twoBot = trim($_POST['two_bot'] ?? '');
        $runTop = trim($_POST['run_top'] ?? '');
        $runBot = trim($_POST['run_bot'] ?? '');

        $stmt = $pdo->prepare("
            INSERT INTO results (lottery_type_id, draw_date, three_top, three_tod, two_top, two_bot, run_top, run_bot)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE three_top=VALUES(three_top), three_tod=VALUES(three_tod), two_top=VALUES(two_top), two_bot=VALUES(two_bot), run_top=VALUES(run_top), run_bot=VALUES(run_bot), updated_at=NOW()
        ");
        $stmt->execute([$lotteryId, $drawDate, $threeTop, $threeTod, $twoTop, $twoBot, $runTop, $runBot]);
        $msg = 'บันทึกผลรางวัลสำเร็จ';
        $msgType = 'success';
    } elseif ($action === 'delete_result') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM results WHERE id = ?")->execute([$id]);
        $msg = 'ลบผลรางวัลสำเร็จ';
        $msgType = 'success';
    }
}

$lotteries = $pdo->query("SELECT lt.*, lc.name as category_name FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id ORDER BY lc.sort_order, lt.sort_order")->fetchAll();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$results = $pdo->prepare("
    SELECT r.*, lt.name as lottery_name, lt.flag_emoji, lc.name as category_name
    FROM results r
    JOIN lottery_types lt ON r.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE r.draw_date = ?
    ORDER BY lc.sort_order, lt.sort_order
");
$results->execute([$selectedDate]);
$results = $results->fetchAll();

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Add Result Form -->
<div class="bg-white rounded-xl shadow-sm border mb-6">
    <div class="px-4 py-3 border-b bg-purple-50">
        <span class="font-bold text-purple-700 text-sm"><i class="fas fa-plus-circle mr-1"></i>กรอกผลรางวัล</span>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="form_action" value="save_result">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="text-xs text-gray-500 block mb-1">หวย *</label>
                <select name="lottery_type_id" required class="w-full border rounded-lg px-3 py-2 text-sm focus:border-purple-500 outline-none">
                    <?php foreach ($lotteries as $l): ?>
                    <option value="<?= $l['id'] ?>">[<?= htmlspecialchars($l['category_name']) ?>] <?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">วันที่งวด *</label>
                <input type="date" name="draw_date" required class="w-full border rounded-lg px-3 py-2 text-sm focus:border-purple-500 outline-none" value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">3 ตัวบน</label>
                <input type="text" name="three_top" maxlength="3" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-purple-500 outline-none text-center font-mono text-lg" placeholder="xxx">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">3 ตัวโต๊ด</label>
                <input type="text" name="three_tod" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-purple-500 outline-none text-center" placeholder="xxx, xxx, xxx">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">2 ตัวบน</label>
                <input type="text" name="two_top" maxlength="2" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-purple-500 outline-none text-center font-mono text-lg" placeholder="xx">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">2 ตัวล่าง</label>
                <input type="text" name="two_bot" maxlength="2" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-purple-500 outline-none text-center font-mono text-lg" placeholder="xx">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">วิ่งบน</label>
                <input type="text" name="run_top" maxlength="1" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-purple-500 outline-none text-center font-mono text-lg" placeholder="x">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">วิ่งล่าง</label>
                <input type="text" name="run_bot" maxlength="1" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-purple-500 outline-none text-center font-mono text-lg" placeholder="x">
            </div>
        </div>
        <div class="mt-4 text-right">
            <button type="submit" class="bg-purple-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-purple-600 transition">
                <i class="fas fa-save mr-1"></i>บันทึกผลรางวัล
            </button>
        </div>
    </form>
</div>

<!-- Results List -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
        <span class="font-bold text-gray-700 text-sm"><i class="fas fa-list mr-1"></i>ผลรางวัลวันที่ <?= formatDateDisplay($selectedDate) ?></span>
        <form method="GET" class="flex gap-2">
            <input type="date" name="date" value="<?= $selectedDate ?>" class="border rounded-lg px-3 py-1.5 text-sm outline-none">
            <button type="submit" class="bg-gray-500 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-gray-600"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">หวย</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">งวด</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">3 ตัวบน</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">3 ตัวโต๊ด</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">2 ตัวบน</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">2 ตัวล่าง</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">วิ่งบน</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">วิ่งล่าง</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">ลบ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400"><i class="fas fa-inbox text-2xl mb-2 block"></i>ไม่พบผลรางวัล</td></tr>
                <?php else: foreach ($results as $r):
                    $flagUrl = getFlagForCountry($r['flag_emoji']);
                ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-3 py-2 flex items-center space-x-2">
                        <img src="<?= $flagUrl ?>" class="w-6 h-4 object-cover rounded border">
                        <span class="text-xs"><?= htmlspecialchars($r['lottery_name']) ?></span>
                    </td>
                    <td class="px-3 py-2 text-center text-xs"><?= formatDateDisplay($r['draw_date']) ?></td>
                    <td class="px-3 py-2 text-center font-bold text-green-700 font-mono"><?= $r['three_top'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $r['three_tod'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center font-bold text-blue-600 font-mono"><?= $r['two_top'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center font-bold text-red-600 font-mono"><?= $r['two_bot'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center font-mono"><?= $r['run_top'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center font-mono"><?= $r['run_bot'] ?: '-' ?></td>
                    <td class="px-3 py-2 text-center">
                        <form method="POST" class="inline" onsubmit="return confirm('ลบผลรางวัลนี้?')">
                            <input type="hidden" name="form_action" value="delete_result">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="text-red-400 hover:text-red-600"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
