<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$adminPage = 'bets';
$adminTitle = 'รายการเดิมพัน';
$msg = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    if ($action === 'update_status') {
        $id = intval($_POST['id']);
        $status = $_POST['status'];
        $pdo->prepare("UPDATE bets SET status = ? WHERE id = ?")->execute([$status, $id]);
        $msg = 'อัพเดทสถานะสำเร็จ';
    } elseif ($action === 'delete_bet') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM bets WHERE id = ?")->execute([$id]);
        $msg = 'ลบบิลสำเร็จ';
    }
}

// Fetch bets
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT b.*, lt.name as lottery_name, lt.flag_emoji, lc.name as category_name
    FROM bets b
    JOIN lottery_types lt ON b.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE b.draw_date = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$selectedDate]);
$bets = $stmt->fetchAll();

// Summary
$totalBets = count($bets);
$totalAmount = array_sum(array_column($bets, 'net_amount'));

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Filter + Summary -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <form method="GET" class="flex gap-2 items-end">
            <div class="flex-1">
                <label class="text-xs text-gray-500 block mb-1">วันที่งวด</label>
                <input type="date" name="date" value="<?= $selectedDate ?>" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
            </div>
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-600"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <p class="text-xs text-gray-400">จำนวนบิล</p>
        <p class="text-2xl font-bold text-gray-800"><?= $totalBets ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <p class="text-xs text-gray-400">ยอดรวม</p>
        <p class="text-2xl font-bold text-green-600">฿<?= number_format($totalAmount, 2) ?></p>
    </div>
</div>

<!-- Bets Table -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gradient-to-r from-orange-400 to-orange-500 text-white">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-bold">เลขที่</th>
                    <th class="px-3 py-3 text-left text-xs font-bold">เวลา</th>
                    <th class="px-3 py-3 text-left text-xs font-bold">หวย</th>
                    <th class="px-3 py-3 text-center text-xs font-bold">รายการ</th>
                    <th class="px-3 py-3 text-right text-xs font-bold">ยอด</th>
                    <th class="px-3 py-3 text-right text-xs font-bold">ลด</th>
                    <th class="px-3 py-3 text-right text-xs font-bold">สุทธิ</th>
                    <th class="px-3 py-3 text-center text-xs font-bold">สถานะ</th>
                    <th class="px-3 py-3 text-center text-xs font-bold">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bets)): ?>
                <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400"><i class="fas fa-inbox text-2xl mb-2 block"></i>ไม่มีรายการ</td></tr>
                <?php else: foreach ($bets as $b): ?>
                <tr class="border-b hover:bg-gray-50 <?= $b['status'] === 'cancelled' ? 'bg-red-50 opacity-60' : '' ?>">
                    <td class="px-3 py-2 font-mono text-xs"><?= htmlspecialchars($b['bet_number']) ?></td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?= date('H:i:s', strtotime($b['created_at'])) ?></td>
                    <td class="px-3 py-2 text-xs">[<?= htmlspecialchars($b['category_name']) ?>] <?= htmlspecialchars($b['lottery_name']) ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $b['total_items'] ?></td>
                    <td class="px-3 py-2 text-right text-xs text-green-600"><?= formatMoney($b['total_amount']) ?></td>
                    <td class="px-3 py-2 text-right text-xs"><?= $b['discount_amount'] > 0 ? formatMoney($b['discount_amount']) : '-' ?></td>
                    <td class="px-3 py-2 text-right text-xs font-bold text-green-700"><?= formatMoney($b['net_amount']) ?></td>
                    <td class="px-3 py-2 text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="form_action" value="update_status">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <select name="status" onchange="this.form.submit()" class="border rounded px-1 py-0.5 text-[11px] outline-none <?= $b['status'] === 'won' ? 'text-green-600' : ($b['status'] === 'cancelled' ? 'text-red-500' : 'text-gray-600') ?>">
                                <option value="pending" <?= $b['status'] === 'pending' ? 'selected' : '' ?>>รอผล</option>
                                <option value="won" <?= $b['status'] === 'won' ? 'selected' : '' ?>>ถูกรางวัล</option>
                                <option value="lost" <?= $b['status'] === 'lost' ? 'selected' : '' ?>>ไม่ถูก</option>
                                <option value="cancelled" <?= $b['status'] === 'cancelled' ? 'selected' : '' ?>>ยกเลิก</option>
                            </select>
                        </form>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <button onclick="viewBetDetail(<?= $b['id'] ?>)" class="text-blue-500 hover:text-blue-700 mr-1"><i class="fas fa-eye"></i></button>
                        <form method="POST" class="inline" onsubmit="return confirm('ลบบิลนี้?')">
                            <input type="hidden" name="form_action" value="delete_bet">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="text-red-400 hover:text-red-600"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bet Detail Modal -->
<div id="betDetailModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 max-h-[80vh] overflow-y-auto">
        <div class="p-4 border-b flex items-center justify-between bg-green-50">
            <span class="font-bold text-green-700"><i class="fas fa-receipt mr-1"></i>รายละเอียดบิล</span>
            <button onclick="document.getElementById('betDetailModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <div id="betDetailContent" class="p-4">
            <div class="text-center py-8 text-gray-400"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>
        </div>
    </div>
</div>

<script>
async function viewBetDetail(id) {
    document.getElementById('betDetailModal').classList.remove('hidden');
    document.getElementById('betDetailContent').innerHTML = '<div class="text-center py-8 text-gray-400"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>';
    
    try {
        const res = await fetch('../api.php?action=get_bet_detail&id=' + id);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        
        const items = data.items || [];
        let html = `
            <div class="mb-4 space-y-1 text-sm">
                <p><strong>เลขที่:</strong> ${data.bet_number}</p>
                <p><strong>วันที่:</strong> ${data.created_at}</p>
                <p><strong>ยอดรวม:</strong> <span class="text-green-600 font-bold">${Number(data.net_amount).toFixed(2)} บาท</span></p>
                ${data.note ? `<p><strong>หมายเหตุ:</strong> ${data.note}</p>` : ''}
            </div>
            <table class="w-full text-sm border">
                <thead class="bg-gray-50"><tr><th class="px-2 py-1 text-xs">#</th><th class="px-2 py-1 text-xs">เลข</th><th class="px-2 py-1 text-xs">ประเภท</th><th class="px-2 py-1 text-xs text-right">จำนวน</th></tr></thead>
                <tbody>${items.map((item, i) => `
                    <tr class="border-t"><td class="px-2 py-1 text-xs text-center">${i+1}</td><td class="px-2 py-1 text-center font-bold">${item.number}</td><td class="px-2 py-1 text-xs">${item.bet_type}</td><td class="px-2 py-1 text-right text-xs">${Number(item.amount).toFixed(2)}</td></tr>
                `).join('')}</tbody>
            </table>`;
        document.getElementById('betDetailContent').innerHTML = html;
    } catch (e) {
        document.getElementById('betDetailContent').innerHTML = '<div class="text-center py-8 text-red-400"><i class="fas fa-exclamation-circle"></i> ' + e.message + '</div>';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
