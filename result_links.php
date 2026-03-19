<?php
$pageTitle = 'คีย์หวย - ลิงค์ดูผล';
$currentPage = 'links';
require_once 'auth.php';
requireLogin();

// Fetch categories
$categories = $pdo->query("SELECT * FROM lottery_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Fetch result links grouped by category
$links = $pdo->query("
    SELECT rl.*, lc.name as category_name, lc.slug as category_slug
    FROM result_links rl
    JOIN lottery_categories lc ON rl.category_id = lc.id
    WHERE rl.is_active = 1
    ORDER BY lc.sort_order, rl.sort_order
")->fetchAll();

$groupedLinks = [];
foreach ($links as $l) {
    $groupedLinks[$l['category_slug']][] = $l;
}

require_once 'includes/header.php';
?>

<div class="card-outline bg-white">
    <!-- Header & Tabs -->
    <div class="bg-[#67cf8a] pt-3">
        <div class="px-4 pb-3 flex items-center space-x-2 text-gray-800">
            <i class="fas fa-link text-lg"></i>
            <span class="font-bold text-lg">ลิงค์ดูผล</span>
        </div>
        
        <div class="flex overflow-x-auto no-scrollbar">
            <?php foreach ($categories as $i => $c): ?>
            <button onclick="switchLinkTab('<?= $c['slug'] ?>')" 
                    id="linkTab-<?= $c['slug'] ?>"
                    class="link-tab px-4 py-2 text-sm font-medium whitespace-nowrap 
                           <?= $i === 0 ? 'bg-white text-gray-800 rounded-t-lg' : 'text-gray-700 hover:text-gray-900' ?>">
                <?= htmlspecialchars($c['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Link Panels -->
    <div class="bg-white min-h-[500px]">
        <?php foreach ($categories as $i => $c): ?>
        <div id="linkPanel-<?= $c['slug'] ?>" class="link-panel <?= $i > 0 ? 'hidden' : '' ?>">
            <?php if (isset($groupedLinks[$c['slug']])): ?>
                <div class="flex flex-col">
                    <?php foreach ($groupedLinks[$c['slug']] as $link):
                        $flagUrl = getFlagForCountry($link['flag_emoji']);
                    ?>
                    <div class="flex items-start space-x-4 p-4 border-b border-gray-100 hover:bg-gray-50 transition">
                        <img src="<?= $flagUrl ?>" alt="flag" class="w-16 h-11 object-cover shadow-sm border border-gray-200">
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 text-[15px]"><?= htmlspecialchars($link['name']) ?></h3>
                            <div class="text-[13px] space-y-0.5 mt-1">
                                <p class="text-[#c62828]">
                                    <i class="far fa-times-circle mr-1"></i>
                                    ปิดรับ: <?= $link['close_time'] ? date('H:i', strtotime($link['close_time'])) : '-' ?> น.
                                </p>
                                <p class="text-[#2e7d32]">
                                    <i class="far fa-check-circle mr-1"></i>
                                    ผลออก: <?= $link['result_time'] ? date('H:i', strtotime($link['result_time'])) : '-' ?> น.
                                </p>
                                <?php if ($link['result_url'] && $link['result_url'] !== '#'): ?>
                                <p class="text-[#1565c0]">
                                    <i class="fas fa-link mr-1 text-[10px]"></i>
                                    ดูผล: <a href="<?= htmlspecialchars($link['result_url']) ?>" target="_blank" class="hover:underline">
                                        <?= htmlspecialchars($link['result_label'] ?? $link['result_url']) ?> 
                                        <i class="fas fa-external-link-alt text-[10px] ml-0.5"></i>
                                    </a>
                                </p>
                                <?php else: ?>
                                <p class="text-[#1565c0]">
                                    <i class="fas fa-link mr-1 text-[10px]"></i>
                                    ดูผล: <span><?= htmlspecialchars($link['result_label'] ?? '-') ?></span>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p>ยังไม่มีข้อมูลลิงค์สำหรับหมวดนี้</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<script>
function switchLinkTab(slug) {
    document.querySelectorAll('.link-tab').forEach(t => {
        t.className = 'link-tab px-4 py-2 text-sm font-medium whitespace-nowrap text-gray-700 hover:text-gray-900';
    });
    document.getElementById('linkTab-' + slug).className = 'link-tab px-4 py-2 text-sm font-medium whitespace-nowrap bg-white text-gray-800 rounded-t-lg';
    
    document.querySelectorAll('.link-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('linkPanel-' + slug).classList.remove('hidden');
}
</script>

<?php require_once 'includes/footer.php'; ?>
