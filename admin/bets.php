<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$adminPage = 'bets';
$adminTitle = 'รายการเดิมพัน';
$msg = '';

// Handle status updates (CSRF protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $msg = '❌ CSRF token ไม่ถูกต้อง กรุณาลองใหม่';
    } else {
        $action = $_POST['form_action'] ?? '';
        if ($action === 'update_status') {
            $id = intval($_POST['id']);
            $status = $_POST['status'];
            if ($status === 'cancelled') {
                try {
                    $pdo->prepare("UPDATE bets SET status = 'cancelled', win_amount = 0 WHERE id = ?")->execute([$id]);
                } catch (Exception $e) {
                    $pdo->prepare("UPDATE bets SET status = 'cancelled' WHERE id = ?")->execute([$id]);
                }
            } else {
                $pdo->prepare("UPDATE bets SET status = ? WHERE id = ?")->execute([$status, $id]);
            }
            $msg = 'อัพเดทสถานะสำเร็จ';
        } elseif ($action === 'cancel_bet') {
            $id = intval($_POST['id']);
            try {
                $pdo->prepare("UPDATE bets SET status = 'cancelled', win_amount = 0, cancel_approved_by = 'admin', cancel_approved_at = NOW() WHERE id = ?")->execute([$id]);
            } catch (Exception $e) {
                $pdo->prepare("UPDATE bets SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            }
            $msg = 'ยกเลิกโพยสำเร็จ - ยอดจะถูกหักออกจากรายการรวม';
        } elseif ($action === 'delete_bet') {
            $id = intval($_POST['id']);
            try {
                $pdo->prepare("UPDATE bets SET status = 'cancelled', win_amount = 0, cancel_approved_by = 'admin', cancel_approved_at = NOW() WHERE id = ?")->execute([$id]);
            } catch (Exception $e) {
                $pdo->prepare("UPDATE bets SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            }
            $msg = 'ยกเลิกบิลสำเร็จ (ข้อมูลยังเก็บไว้ในระบบ)';
        } elseif ($action === 'bulk_cancel') {
            $lotteryTypeId = intval($_POST['lottery_type_id'] ?? 0);
            $drawDate = $_POST['draw_date'] ?? '';
            if ($lotteryTypeId && $drawDate) {
                $stmt = $pdo->prepare("UPDATE bets SET status = 'cancelled', win_amount = 0, cancel_approved_by = 'admin', cancel_approved_at = NOW() WHERE lottery_type_id = ? AND draw_date = ? AND status = 'pending'");
                $stmt->execute([$lotteryTypeId, $drawDate]);
                $cancelCount = $stmt->rowCount();
                $lnStmt = $pdo->prepare("SELECT name FROM lottery_types WHERE id = ?");
                $lnStmt->execute([$lotteryTypeId]);
                $ln = $lnStmt->fetchColumn();
                $msg = "ยกเลิกโพยทั้งหมดของ {$ln} งวด {$drawDate} สำเร็จ ({$cancelCount} โพย)";
            }
        }
    }
}

// Fetch bets
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedLottery = $_GET['lottery'] ?? '';

$sql = "SELECT b.*, lt.name as lottery_name, lt.flag_emoji, lc.name as category_name
    FROM bets b
    JOIN lottery_types lt ON b.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE b.draw_date = ?";
$params = [$selectedDate];
if ($selectedLottery) {
    $sql .= " AND b.lottery_type_id = ?";
    $params[] = intval($selectedLottery);
}
$sql .= " ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bets = $stmt->fetchAll();

// Lottery list for filter
$lotteryList = $pdo->query("SELECT id, name FROM lottery_types WHERE is_active = 1 ORDER BY name")->fetchAll();

// Summary
$totalBets = count($bets);
$pendingBets = count(array_filter($bets, fn($b) => $b['status'] === 'pending'));
$totalAmount = array_sum(array_map(fn($b) => $b['status'] !== 'cancelled' ? floatval($b['net_amount']) : 0, $bets));

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Filter + Summary -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-4 md:col-span-2">
        <form method="GET" class="flex gap-2 items-end flex-wrap">
            <div>
                <label class="text-xs text-gray-500 block mb-1">วันที่งวด</label>
                <input type="date" name="date" value="<?= $selectedDate ?>" class="border rounded-lg px-3 py-2 text-sm outline-none">
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="text-xs text-gray-500 block mb-1">หวย</label>
                <select name="lottery" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($lotteryList as $lt): ?>
                    <option value="<?= $lt['id'] ?>" <?= $selectedLottery == $lt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-600"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <p class="text-xs text-gray-400">บิล (รอผล)</p>
        <p class="text-2xl font-bold text-gray-800"><?= $totalBets ?> <span class="text-sm text-orange-500">(<?= $pendingBets ?>)</span></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <p class="text-xs text-gray-400">ยอดรวม</p>
        <p class="text-2xl font-bold text-green-600">฿<?= number_format($totalAmount, 2) ?></p>
    </div>
</div>

<?php if ($pendingBets > 0 && $selectedLottery): ?>
<!-- Bulk Cancel Button -->
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl flex items-center justify-between">
    <div class="text-sm text-red-700">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        พบ <strong><?= $pendingBets ?></strong> โพยที่รอผลอยู่
    </div>
    <form method="POST" id="bulkCancelForm">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="bulk_cancel">
        <input type="hidden" name="lottery_type_id" value="<?= intval($selectedLottery) ?>">
        <input type="hidden" name="draw_date" value="<?= $selectedDate ?>">
        <button type="button" onclick="confirmBulkCancel()" class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-red-600">
            <i class="fas fa-ban mr-1"></i>ยกเลิกทั้งหมด (<?= $pendingBets ?> โพย)
        </button>
    </form>
</div>
<?php endif; ?>

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

                    <td class="px-3 py-2 text-right text-xs font-bold text-green-700"><?= formatMoney($b['net_amount']) ?></td>
                    <td class="px-3 py-2 text-center">
                        <?php 
                        switch ($b['status']) {
                            case 'won': echo '<span class="text-[12px] text-green-600 font-bold">ถูกรางวัล</span>'; break;
                            case 'lost': echo '<span class="text-[12px] text-red-400">ไม่ถูก</span>'; break;
                            case 'cancelled': echo '<span class="text-[12px] text-red-500 font-bold">ยกเลิก</span>'; break;
                            default: echo '<span class="text-[12px] text-blue-500">รอผล</span>'; break;
                        }
                        ?>
                    </td>
                    <td class="px-3 py-2 text-center whitespace-nowrap">
                        <button onclick="viewBetDetail(<?= $b['id'] ?>)" class="text-blue-500 hover:text-blue-700 mr-1" title="ดูรายละเอียด"><i class="fas fa-eye"></i></button>
                        <?php if ($b['status'] !== 'cancelled'): ?>
                        <button onclick="confirmCancel(<?= $b['id'] ?>, '<?= htmlspecialchars($b['bet_number']) ?>')" class="text-orange-500 hover:text-orange-700 mr-1" title="ยกเลิกโพย"><i class="fas fa-ban"></i></button>
                        <?php endif; ?>
                        <button onclick="confirmDelete(<?= $b['id'] ?>, '<?= htmlspecialchars($b['bet_number']) ?>')" class="text-red-400 hover:text-red-600" title="ลบบิล"><i class="fas fa-trash-alt"></i></button>
                        <!-- Hidden forms for submission -->
                        <form id="cancelForm_<?= $b['id'] ?>" method="POST" class="hidden">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="cancel_bet">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        </form>
                        <form id="deleteForm_<?= $b['id'] ?>" method="POST" class="hidden">
                            <?= csrfField() ?>
                            <input type="hidden" name="form_action" value="delete_bet">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
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

function confirmCancel(id, betNumber) {
    Swal.fire({
        title: 'ยืนยันยกเลิกโพย?',
        html: `<p class="text-gray-600">โพยเลขที่ <strong>#${betNumber}</strong></p><p class="text-sm text-red-500 mt-2">⚠️ ยอดเดิมพันทั้งหมดจะถูกหักออก</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f97316',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-ban mr-1"></i>ยืนยันยกเลิก',
        cancelButtonText: 'ไม่ใช่',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('cancelForm_' + id).submit();
        }
    });
}

function confirmDelete(id, betNumber) {
    Swal.fire({
        title: 'ลบบิลถาวร?',
        html: `<p class="text-gray-600">โพยเลขที่ <strong>#${betNumber}</strong></p><p class="text-sm text-red-500 mt-2">⚠️ ลบแล้วจะไม่สามารถกู้คืนได้</p>`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i>ลบถาวร',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteForm_' + id).submit();
        }
    });
}
function confirmBulkCancel() {
    Swal.fire({
        title: 'ยกเลิกโพยทั้งหมด?',
        html: '<p class="text-red-500 font-bold">⚠️ โพยที่รอผลทั้งหมดจะถูกยกเลิก</p><p class="text-sm text-gray-500 mt-2">การกระทำนี้ไม่สามารถย้อนกลับได้</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-ban mr-1"></i>ยืนยันยกเลิกทั้งหมด',
        cancelButtonText: 'ไม่ใช่',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('bulkCancelForm').submit();
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
