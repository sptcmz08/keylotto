<?php
$pageTitle = 'คีย์หวย - ลิงค์ดูผล';
$currentPage = 'links';
require_once 'auth.php';
requireLogin();

// ================================
// 3 กลุ่มเหมือนหน้าหลัก
// ================================
$LINK_GROUPS = [
    [
        'id' => 'thai',
        'label' => 'หวยไทย',
        'names' => ['รัฐบาลไทย', 'ออมสิน', 'ธกส'],
    ],
    [
        'id' => 'daily',
        'label' => 'หวยรายวัน',
        'names' => [
            // ลาว
            'ลาวประตูชัย', 'ลาวสันติภาพ', 'ประชาชนลาว', 'ลาว EXTRA',
            'ลาว TV', 'ลาว HD', 'ลาวสตาร์', 'ลาวใต้',
            'ลาวสามัคคี', 'ลาวอาเซียน', 'ลาว VIP', 'ลาวสามัคคี VIP',
            'ลาวกาชาด', 'ลาวพัฒนา',
            // ฮานอย
            'ฮานอยอาเซียน', 'ฮานอย HD', 'ฮานอยสตาร์', 'ฮานอย TV',
            'ฮานอยกาชาด', 'ฮานอยพิเศษ', 'ฮานอยสามัคคี', 'ฮานอยปกติ',
            'ฮานอยตรุษจีน', 'ฮานอย VIP', 'ฮานอยพัฒนา', 'ฮานอย EXTRA',
            // VIP
            'นิเคอิเช้า VIP', 'นิเคอิบ่าย VIP',
            'จีนเช้า VIP', 'จีนบ่าย VIP',
            'ฮั่งเส็งเช้า VIP', 'ฮั่งเส็งบ่าย VIP',
            'ไต้หวัน VIP', 'เกาหลี VIP', 'สิงคโปร์ VIP',
            'อังกฤษ VIP', 'เยอรมัน VIP', 'รัสเซีย VIP', 'ดาวโจนส์ VIP',
            'ลาวสตาร์ VIP',
        ],
    ],
    [
        'id' => 'stock',
        'label' => 'หวยหุ้น',
        'names' => [
            'นิเคอิ - เช้า', 'นิเคอิ - บ่าย',
            'หุ้นจีน - เช้า', 'หุ้นจีน - บ่าย',
            'ฮั่งเส็ง - เช้า', 'ฮั่งเส็ง - บ่าย',
            'หุ้นไต้หวัน', 'หุ้นเกาหลี', 'หุ้นสิงคโปร์',
            'หุ้นไทย - เย็น', 'หุ้นอินเดีย', 'หุ้นอียิปต์',
            'หวย 12 ราศี',
            'หุ้นอังกฤษ', 'หุ้นเยอรมัน', 'หุ้นรัสเซีย',
            'ดาวโจนส์ STAR', 'หุ้นดาวโจนส์',
        ],
    ],
];

// Fetch all result links indexed by name
$allLinks = $pdo->query("SELECT * FROM result_links WHERE is_active = 1")->fetchAll();
$linksByName = [];
foreach ($allLinks as $l) {
    $linksByName[$l['name']] = $l;
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
            <?php foreach ($LINK_GROUPS as $i => $group): ?>
            <button onclick="switchLinkTab('<?= $group['id'] ?>')" 
                    id="linkTab-<?= $group['id'] ?>"
                    class="link-tab px-4 py-2 text-sm font-medium whitespace-nowrap 
                           <?= $i === 0 ? 'bg-white text-gray-800 rounded-t-lg' : 'text-gray-700 hover:text-gray-900' ?>">
                <?= $group['label'] ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Link Panels -->
    <div class="bg-white min-h-[500px]">
        <?php foreach ($LINK_GROUPS as $i => $group): ?>
        <div id="linkPanel-<?= $group['id'] ?>" class="link-panel <?= $i > 0 ? 'hidden' : '' ?>">
            <div class="flex flex-col">
                <?php foreach ($group['names'] as $name):
                    $link = $linksByName[$name] ?? null;
                    if (!$link) continue;
                    $flagUrl = getFlagForCountry($link['flag_emoji'] ?? '', $link['name']);
                ?>
                <div class="flex items-start space-x-4 p-4 border-b border-gray-100 hover:bg-gray-50 transition">
                    <img src="<?= $flagUrl ?>" alt="<?= htmlspecialchars($link['name']) ?>" class="w-16 h-11 object-cover shadow-sm border border-gray-200 rounded">
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<script>
function switchLinkTab(id) {
    document.querySelectorAll('.link-tab').forEach(t => {
        t.className = 'link-tab px-4 py-2 text-sm font-medium whitespace-nowrap text-gray-700 hover:text-gray-900';
    });
    document.getElementById('linkTab-' + id).className = 'link-tab px-4 py-2 text-sm font-medium whitespace-nowrap bg-white text-gray-800 rounded-t-lg';
    
    document.querySelectorAll('.link-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('linkPanel-' + id).classList.remove('hidden');
}
</script>

<?php require_once 'includes/footer.php'; ?>
