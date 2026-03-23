<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

// Handle cancel approval (safe - columns may not exist yet)
try {
    if (isset($_POST['approve_cancel'])) {
        $betId = intval($_POST['bet_id']);
        $pdo->prepare("UPDATE bets SET status = 'cancelled', cancel_approved_by = 'admin', cancel_approved_at = NOW() WHERE id = ?")->execute([$betId]);
        header('Location: index.php?msg=cancel_approved');
        exit;
    }
    if (isset($_POST['reject_cancel'])) {
        $betId = intval($_POST['bet_id']);
        $pdo->prepare("UPDATE bets SET cancel_requested = 0, cancel_reason = NULL, cancel_requested_at = NULL WHERE id = ?")->execute([$betId]);
        header('Location: index.php?msg=cancel_rejected');
        exit;
    }
} catch (Exception $e) { /* columns not yet created */ }

$adminPage = 'dashboard';
$adminTitle = 'Dashboard';

// Stats
$totalLotteries = $pdo->query("SELECT COUNT(*) FROM lottery_types WHERE is_active = 1")->fetchColumn();
$todayBets = $pdo->query("SELECT COUNT(*) FROM bets WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$todayAmount = $pdo->query("SELECT COALESCE(SUM(net_amount), 0) FROM bets WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalResults = $pdo->query("SELECT COUNT(*) FROM results")->fetchColumn();
$totalLinks = $pdo->query("SELECT COUNT(*) FROM result_links")->fetchColumn();
$totalRates = $pdo->query("SELECT COUNT(DISTINCT lottery_type_id) FROM pay_rates")->fetchColumn();

// Today win/loss summary (win_amount may not exist)
try {
    $todayWin = $pdo->query("SELECT COALESCE(SUM(win_amount), 0) FROM bets WHERE DATE(created_at) = CURDATE() AND status = 'won'")->fetchColumn();
} catch (Exception $e) { $todayWin = 0; }
$todayLost = $pdo->query("SELECT COALESCE(SUM(net_amount), 0) FROM bets WHERE DATE(created_at) = CURDATE() AND status = 'lost'")->fetchColumn();

// Pending cancel requests (safe - column may not exist)
try {
    $cancelRequests = $pdo->query("
        SELECT b.*, lt.name as lottery_name 
        FROM bets b 
        JOIN lottery_types lt ON b.lottery_type_id = lt.id 
        WHERE b.cancel_requested = 1 AND b.status != 'cancelled'
        ORDER BY b.cancel_requested_at DESC
    ")->fetchAll();
} catch (Exception $e) { $cancelRequests = []; }

// Recent bets (last 10)
$recentBets = $pdo->query("
    SELECT b.*, lt.name as lottery_name 
    FROM bets b 
    JOIN lottery_types lt ON b.lottery_type_id = lt.id 
    ORDER BY b.created_at DESC LIMIT 10
")->fetchAll();

require_once 'includes/header.php';
?>

<?php if (isset($_GET['msg'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const msgs = { cancel_approved: 'อนุมัติยกเลิกโพยแล้ว', cancel_rejected: 'ปฏิเสธคำขอยกเลิกแล้ว' };
    const msg = msgs['<?= $_GET['msg'] ?>'] || '';
    if (msg && typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: msg, timer: 2000, showConfirmButton: false });
    else if (msg) alert(msg);
});
</script>
<?php endif; ?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">หวยทั้งหมด</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?= $totalLotteries ?></p>
                <p class="text-xs text-green-500 mt-1">เปิดใช้งาน <?= $totalLotteries ?></p>
            </div>
            <div class="bg-green-100 p-3 rounded-xl"><i class="fas fa-dice text-green-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">เดิมพันวันนี้</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?= $todayBets ?></p>
                <p class="text-xs text-blue-500 mt-1">ทั้งหมด <?= $todayBets ?> บิล</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-xl"><i class="fas fa-receipt text-blue-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">ยอดวันนี้</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($todayAmount, 0) ?></p>
                <p class="text-xs text-emerald-500 mt-1">บาท</p>
            </div>
            <div class="bg-emerald-100 p-3 rounded-xl"><i class="fas fa-baht-sign text-emerald-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">ผลรางวัล</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?= $totalResults ?></p>
                <p class="text-xs text-purple-500 mt-1">ลิงค์ <?= $totalLinks ?> | อัตรา <?= $totalRates ?></p>
            </div>
            <div class="bg-purple-100 p-3 rounded-xl"><i class="fas fa-trophy text-purple-600 text-xl"></i></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-bolt text-yellow-500 mr-2"></i>การจัดการ</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <a href="lottery_types.php" class="flex flex-col items-center p-4 bg-green-50 rounded-xl hover:bg-green-100 transition group">
            <i class="fas fa-dice text-green-600 text-2xl mb-2 group-hover:scale-110 transition"></i>
            <span class="text-xs font-medium text-gray-700">จัดการหวย</span>
        </a>
        <a href="pay_rates.php" class="flex flex-col items-center p-4 bg-blue-50 rounded-xl hover:bg-blue-100 transition group">
            <i class="fas fa-coins text-blue-600 text-2xl mb-2 group-hover:scale-110 transition"></i>
            <span class="text-xs font-medium text-gray-700">อัตราจ่าย</span>
        </a>
        <a href="results_manage.php" class="flex flex-col items-center p-4 bg-purple-50 rounded-xl hover:bg-purple-100 transition group">
            <i class="fas fa-trophy text-purple-600 text-2xl mb-2 group-hover:scale-110 transition"></i>
            <span class="text-xs font-medium text-gray-700">ผลรางวัล</span>
        </a>
        <a href="result_links.php" class="flex flex-col items-center p-4 bg-cyan-50 rounded-xl hover:bg-cyan-100 transition group">
            <i class="fas fa-link text-cyan-600 text-2xl mb-2 group-hover:scale-110 transition"></i>
            <span class="text-xs font-medium text-gray-700">ลิงค์ดูผล</span>
        </a>
        <a href="bets.php" class="flex flex-col items-center p-4 bg-orange-50 rounded-xl hover:bg-orange-100 transition group">
            <i class="fas fa-receipt text-orange-600 text-2xl mb-2 group-hover:scale-110 transition"></i>
            <span class="text-xs font-medium text-gray-700">รายการเดิมพัน</span>
        </a>
        <a href="../index.php" class="flex flex-col items-center p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition group">
            <i class="fas fa-globe text-gray-600 text-2xl mb-2 group-hover:scale-110 transition"></i>
            <span class="text-xs font-medium text-gray-700">ดูหน้าเว็บ</span>
        </a>
    </div>
</div>

<!-- Pending Cancel Requests -->
<?php if (!empty($cancelRequests)): ?>
<div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
    <h2 class="text-lg font-bold text-red-600 mb-4">
        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>คำขอยกเลิกโพย
        <span class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full ml-2"><?= count($cancelRequests) ?></span>
    </h2>
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-red-50">
                <tr>
                    <th class="px-3 py-2 text-left border font-bold text-gray-700">เลขที่โพย</th>
                    <th class="px-3 py-2 text-left border font-bold text-gray-700">หวย</th>
                    <th class="px-3 py-2 text-center border font-bold text-gray-700">ยอด</th>
                    <th class="px-3 py-2 text-left border font-bold text-gray-700">เหตุผล</th>
                    <th class="px-3 py-2 text-center border font-bold text-gray-700">เวลาขอ</th>
                    <th class="px-3 py-2 text-center border font-bold text-gray-700 w-40">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cancelRequests as $cr): ?>
                <tr class="border-b hover:bg-red-50/50">
                    <td class="px-3 py-2 border font-mono"><?= htmlspecialchars($cr['bet_number']) ?></td>
                    <td class="px-3 py-2 border"><?= htmlspecialchars($cr['lottery_name']) ?></td>
                    <td class="px-3 py-2 text-center border font-bold text-blue-600"><?= number_format($cr['net_amount'], 2) ?></td>
                    <td class="px-3 py-2 border text-red-600"><?= htmlspecialchars($cr['cancel_reason'] ?? '-') ?></td>
                    <td class="px-3 py-2 text-center border text-xs"><?= date('d/m/Y H:i', strtotime($cr['cancel_requested_at'])) ?></td>
                    <td class="px-3 py-2 text-center border">
                        <div class="flex gap-2 justify-center">
                            <form method="POST" class="inline">
                                <input type="hidden" name="bet_id" value="<?= $cr['id'] ?>">
                                <button type="submit" name="approve_cancel" class="px-3 py-1 bg-red-500 text-white rounded text-xs font-medium hover:bg-red-600 transition">
                                    <i class="fas fa-check mr-1"></i>อนุมัติ
                                </button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="bet_id" value="<?= $cr['id'] ?>">
                                <button type="submit" name="reject_cancel" class="px-3 py-1 bg-gray-400 text-white rounded text-xs font-medium hover:bg-gray-500 transition">
                                    <i class="fas fa-times mr-1"></i>ปฏิเสธ
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Today's Summary -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <!-- Win/Loss -->
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-chart-bar text-green-500 mr-2"></i>ได้-เสีย วันนี้</h2>
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-green-50 rounded-lg p-4 text-center border border-green-200">
                <div class="text-xs text-green-600 font-medium mb-1">ยอดรับ (ลูกค้าแทง)</div>
                <div class="text-xl font-bold text-green-700"><?= number_format($todayAmount, 2) ?></div>
            </div>
            <div class="bg-red-50 rounded-lg p-4 text-center border border-red-200">
                <div class="text-xs text-red-600 font-medium mb-1">ยอดจ่าย (ลูกค้าถูก)</div>
                <div class="text-xl font-bold text-red-600"><?= number_format($todayWin, 2) ?></div>
            </div>
        </div>
        <div class="mt-3 text-center">
            <?php $profit = $todayAmount - $todayWin; ?>
            <div class="inline-block bg-<?= $profit >= 0 ? 'green' : 'red' ?>-100 rounded-lg px-6 py-2 border border-<?= $profit >= 0 ? 'green' : 'red' ?>-200">
                <span class="text-xs text-gray-600">กำไร/ขาดทุน:</span>
                <span class="text-lg font-bold text-<?= $profit >= 0 ? 'green-700' : 'red-600' ?> ml-2"><?= number_format($profit, 2) ?></span>
            </div>
        </div>
    </div>

    <!-- Recent Bets -->
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-history text-blue-500 mr-2"></i>โพยล่าสุด</h2>
        <div class="overflow-y-auto max-h-[200px]">
            <table class="w-full text-[12px]">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-2 py-1 text-left border font-bold">เลขที่</th>
                        <th class="px-2 py-1 text-left border font-bold">หวย</th>
                        <th class="px-2 py-1 text-center border font-bold">ยอด</th>
                        <th class="px-2 py-1 text-center border font-bold">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentBets as $rb):
                        switch ($rb['status']) {
                            case 'won': $stBadge = '<span class="text-green-600 font-bold">ถูก</span>'; break;
                            case 'lost': $stBadge = '<span class="text-red-400">ไม่ถูก</span>'; break;
                            case 'cancelled': $stBadge = '<span class="text-red-500">ยกเลิก</span>'; break;
                            default: $stBadge = '<span class="text-blue-400">รอผล</span>'; break;
                        }
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-2 py-1.5 border font-mono"><?= htmlspecialchars($rb['bet_number']) ?></td>
                        <td class="px-2 py-1.5 border"><?= htmlspecialchars($rb['lottery_name']) ?></td>
                        <td class="px-2 py-1.5 text-center border text-blue-600 font-bold"><?= number_format($rb['net_amount'], 2) ?></td>
                        <td class="px-2 py-1.5 text-center border"><?= $stBadge ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
