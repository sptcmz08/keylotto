<?php
$pageTitle = 'คีย์หวย - ตรวจผล';
$currentPage = 'results';
require_once 'auth.php';
requireLogin();

// Fetch categories for filter
$categories = $pdo->query("SELECT * FROM lottery_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Fetch results
$selectedCat = intval($_GET['cat'] ?? 0);
$selectedDate = $_GET['date'] ?? date('Y-m-d');

$sql = "SELECT r.*, lt.name as lottery_name, lt.flag_emoji, lc.name as category_name
        FROM results r
        JOIN lottery_types lt ON r.lottery_type_id = lt.id
        JOIN lottery_categories lc ON lt.category_id = lc.id
        WHERE r.draw_date = ?";
$params = [$selectedDate];

if ($selectedCat > 0) {
    $sql .= " AND lc.id = ?";
    $params[] = $selectedCat;
}
$sql .= " ORDER BY lc.sort_order, lt.sort_order";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="gradient-header px-4 py-3 text-white flex items-center space-x-2">
        <i class="fas fa-trophy"></i>
        <span class="font-bold">ตรวจผลรางวัล</span>
    </div>

    <!-- Filters -->
    <div class="p-4 bg-gray-50 border-b">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 block mb-1">วันที่</label>
                <input type="date" name="date" value="<?= $selectedDate ?>" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">หมวดหมู่</label>
                <select name="cat" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none">
                    <option value="0">ทั้งหมด</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedCat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
                <i class="fas fa-search mr-1"></i>ค้นหา
            </button>
        </form>
    </div>

    <!-- Results -->
    <div class="p-4">
        <?php if (empty($results)): ?>
        <div class="text-center py-12">
            <i class="fas fa-search text-gray-300 text-4xl mb-3 block"></i>
            <p class="text-gray-400">ไม่พบผลรางวัลสำหรับวันที่ <?= formatDateDisplay($selectedDate) ?></p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($results as $r):
                $flagUrl = getFlagForCountry($r['flag_emoji']);
            ?>
            <div class="border border-green-200 rounded-xl p-4 bg-green-50/50 card-hover">
                <div class="flex items-center space-x-3 mb-3">
                    <img src="<?= $flagUrl ?>" alt="flag" class="w-10 h-7 object-cover rounded shadow border">
                    <div>
                        <h3 class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($r['lottery_name']) ?></h3>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($r['category_name']) ?> | งวด <?= formatDateDisplay($r['draw_date']) ?></p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-white rounded-lg p-2 border">
                        <div class="text-[10px] text-gray-400 mb-0.5">3 ตัวบน</div>
                        <div class="text-xl font-bold text-green-700"><?= $r['three_top'] ?? '-' ?></div>
                    </div>
                    <div class="bg-white rounded-lg p-2 border">
                        <div class="text-[10px] text-gray-400 mb-0.5">2 ตัวบน</div>
                        <div class="text-xl font-bold text-blue-600"><?= $r['two_top'] ?? '-' ?></div>
                    </div>
                    <div class="bg-white rounded-lg p-2 border">
                        <div class="text-[10px] text-gray-400 mb-0.5">2 ตัวล่าง</div>
                        <div class="text-xl font-bold text-red-600"><?= $r['two_bot'] ?? '-' ?></div>
                    </div>
                </div>
                <?php if ($r['three_tod']): ?>
                <div class="mt-2 text-center bg-white rounded-lg p-2 border">
                    <div class="text-[10px] text-gray-400 mb-0.5">3 ตัวโต๊ด</div>
                    <div class="text-sm font-bold text-purple-600"><?= htmlspecialchars($r['three_tod']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
