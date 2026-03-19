<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$adminPage = 'dashboard';
$adminTitle = 'Dashboard';

// Stats
$totalLotteries = $pdo->query("SELECT COUNT(*) FROM lottery_types")->fetchColumn();
$activeLotteries = $pdo->query("SELECT COUNT(*) FROM lottery_types WHERE is_active = 1")->fetchColumn();
$totalBets = $pdo->query("SELECT COUNT(*) FROM bets")->fetchColumn();
$todayBets = $pdo->query("SELECT COUNT(*) FROM bets WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalAmount = $pdo->query("SELECT COALESCE(SUM(net_amount), 0) FROM bets WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalResults = $pdo->query("SELECT COUNT(*) FROM results")->fetchColumn();
$totalLinks = $pdo->query("SELECT COUNT(*) FROM result_links")->fetchColumn();
$totalRates = $pdo->query("SELECT COUNT(DISTINCT lottery_type_id) FROM pay_rates")->fetchColumn();

require_once 'includes/header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">หวยทั้งหมด</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?= $totalLotteries ?></p>
                <p class="text-xs text-green-500 mt-1">เปิดใช้งาน <?= $activeLotteries ?></p>
            </div>
            <div class="bg-green-100 p-3 rounded-xl"><i class="fas fa-dice text-green-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">เดิมพันวันนี้</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?= $todayBets ?></p>
                <p class="text-xs text-blue-500 mt-1">ทั้งหมด <?= $totalBets ?> บิล</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-xl"><i class="fas fa-receipt text-blue-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">ยอดวันนี้</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">฿<?= number_format($totalAmount, 0) ?></p>
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
<div class="bg-white rounded-xl shadow-sm border p-6">
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

<?php require_once 'includes/footer.php'; ?>
