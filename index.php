<?php
$pageTitle = 'คีย์หวย - หน้าหลัก';
$currentPage = 'home';
require_once 'auth.php';
requireLogin();

// Fetch lottery types grouped by category
$stmt = $pdo->query("
    SELECT lt.*, lc.name as category_name, lc.slug as category_slug
    FROM lottery_types lt
    JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE lt.is_active = 1
    ORDER BY lc.sort_order, lt.sort_order
");
$lotteries = $stmt->fetchAll();

// Group by category
$grouped = [];
foreach ($lotteries as $l) {
    $grouped[$l['category_name']][] = $l;
}

require_once 'includes/header.php';
?>

<?php foreach ($grouped as $catName => $items): ?>
<div class="card-outline mb-4">
    <div class="bg-white px-3 py-2 border-b border-[#1aa34a] text-[#124d20] font-bold text-sm">
        <?= htmlspecialchars($catName) ?>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-0 bg-[#d0e6d3] p-[1px]">
        <?php foreach ($items as $lt): 
            $flagUrl = getFlagForCountry($lt['flag_emoji']);
            $drawDate = $lt['draw_date'] ? date('d-m-Y', strtotime($lt['draw_date'])) : date('d-m-Y');
            
            // Calculate countdown
            $closeDateTime = $lt['draw_date'] . ' ' . $lt['close_time'];
            $now = new DateTime();
            $close = new DateTime($closeDateTime);
            $diff = $now->diff($close);
            $isOpen = $now < $close;
            
            if ($isOpen) {
                // Card background: pale green-yellow
                $cardBg = 'bg-[#f4f7ec]';
                $statusText = 'ปิดรับใน ';
                // For 'ปิดรับใน', the text 'ปิดรับใน' is left-aligned, the countdown is right-aligned in Image 5
            } else {
                // Card background: cream/white
                $cardBg = 'bg-[#fbf9eb]'; 
                $statusText = 'เปิดในอีก ';
            }
        ?>
        <a href="bet.php?id=<?= $lt['id'] ?>" class="block <?= $cardBg ?> hover:opacity-90 m-[1px] p-2 relative" data-close-time="<?= $closeDateTime ?>" data-is-open="<?= $isOpen ? 'true' : 'false' ?>">
            <div class="flex items-start justify-between border-b border-[#e1ead2] pb-1 mb-1">
                <div class="flex items-center space-x-2">
                    <img src="<?= $flagUrl ?>" alt="flag" class="w-8 h-5 object-cover rounded shadow-sm border border-gray-200">
                    <div>
                        <div class="font-bold text-gray-800 text-[13px] leading-tight"><?= htmlspecialchars($lt['name']) ?></div>
                        <div class="text-[#c62828] font-bold text-[11px]"><?= $drawDate ?></div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-x-2 text-[11px] text-gray-500">
                <div class="text-left">เวลาเปิด</div>
                <div class="text-right"><?= $lt['draw_date'] ? date('d/m/y', strtotime($lt['draw_date'])) : date('d/m/y') ?> <?= $lt['close_time'] ? date('H:i:s', strtotime($lt['close_time'])) : '' ?></div>
                
                <div class="text-left">สถานะ:</div>
                <div class="text-right text-gray-700 status-container">
                    <?= $statusText ?> <span class="countdown-text font-mono">...</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($grouped)): ?>
<div class="text-center py-20 bg-white card-outline">
    <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
    <p class="text-gray-400 text-lg">ยังไม่มีข้อมูลหวย</p>
</div>
<?php endif; ?>

<script>
// Live countdown timer
function updateCountdowns() {
    document.querySelectorAll('[data-close-time]').forEach(card => {
        const closeTime = new Date(card.dataset.closeTime);
        const now = new Date();
        const diff = closeTime - now;
        const countdownEl = card.querySelector('.countdown-text');
        if (!countdownEl) return;
        
        const isOpen = card.dataset.isOpen === 'true';
        if (isOpen && diff > 0) {
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            countdownEl.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        } else {
            const absDiff = Math.abs(diff);
            const h = Math.floor(absDiff / 3600000);
            const m = Math.floor((absDiff % 3600000) / 60000);
            const s = Math.floor((absDiff % 60000) / 1000);
            countdownEl.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        }
    });
}
setInterval(updateCountdowns, 1000);
</script>

<?php require_once 'includes/footer.php'; ?>
