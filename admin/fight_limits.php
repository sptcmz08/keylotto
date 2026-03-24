<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'fight_limits';
$adminTitle = 'ตั้งค่ารับของ (ตั้งสู้)';
$msg = '';
$msgType = '';

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `fight_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lottery_type_id` INT NOT NULL,
    `bet_type` VARCHAR(20) NOT NULL,
    `max_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_lottery_bet` (`lottery_type_id`, `bet_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$betTypes = ['3top', '3tod', '2top', '2bot', 'run_top', 'run_bot'];
$betTypeLabels = ['3top'=>'3 ตัวบน','3tod'=>'3 ตัวโต๊ด','2top'=>'2 ตัวบน','2bot'=>'2 ตัวล่าง','run_top'=>'วิ่งบน','run_bot'=>'วิ่งล่าง'];

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lotteryId = intval($_POST['lottery_id'] ?? 0);
    $limits = $_POST['limits'] ?? [];
    
    if ($lotteryId > 0) {
        $stmt = $pdo->prepare("INSERT INTO fight_limits (lottery_type_id, bet_type, max_amount) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE max_amount = VALUES(max_amount)");
        foreach ($betTypes as $bt) {
            $amt = floatval($limits[$bt] ?? 0);
            $stmt->execute([$lotteryId, $bt, $amt]);
        }
        $msg = 'บันทึกตั้งสู้สำเร็จ';
        $msgType = 'success';
    }
    
    // Bulk save
    if (!empty($_POST['bulk_save'])) {
        $allLimits = $_POST['all_limits'] ?? [];
        $stmt = $pdo->prepare("INSERT INTO fight_limits (lottery_type_id, bet_type, max_amount) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE max_amount = VALUES(max_amount)");
        $count = 0;
        foreach ($allLimits as $lid => $types) {
            foreach ($betTypes as $bt) {
                $amt = floatval($types[$bt] ?? 0);
                $stmt->execute([intval($lid), $bt, $amt]);
            }
            $count++;
        }
        $msg = "บันทึกตั้งสู้สำเร็จ ($count หวย)";
        $msgType = 'success';
    }
}

// Get all lotteries grouped by category
$lotteries = $pdo->query("
    SELECT lt.*, lc.name as category_name, lc.id as cat_id
    FROM lottery_types lt 
    JOIN lottery_categories lc ON lt.category_id = lc.id 
    WHERE lt.is_active = 1 
    ORDER BY lc.sort_order, lt.sort_order, lt.name
")->fetchAll();

$categories = [];
foreach ($lotteries as $l) {
    $categories[$l['category_name']][] = $l;
}

// Load existing fight limits
$existingLimits = [];
$flRows = $pdo->query("SELECT * FROM fight_limits")->fetchAll();
foreach ($flRows as $f) {
    $existingLimits[$f['lottery_type_id']][$f['bet_type']] = floatval($f['max_amount']);
}

$activeTab = $_GET['tab'] ?? array_key_first($categories);

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check' : 'exclamation' ?>-circle mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border">
    <div class="px-4 py-3 border-b bg-green-50">
        <span class="font-bold text-green-700 text-sm"><i class="fas fa-shield-alt mr-1"></i>ตั้งค่ารับของ</span>
        <span class="text-xs text-gray-500 ml-2">กำหนดจำนวนเงินสูงสุดที่รับแทงได้ต่อเลข/ประเภท (0 = ไม่จำกัด)</span>
    </div>

    <!-- Category Tabs -->
    <div class="flex border-b bg-gray-50 overflow-x-auto">
        <?php foreach (array_keys($categories) as $catName): 
            $isActive = ($catName === $activeTab);
        ?>
        <a href="?tab=<?= urlencode($catName) ?>" 
           class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 <?= $isActive ? 'border-green-500 text-green-700 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100' ?>">
            <?= htmlspecialchars($catName) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <form method="POST">
        <input type="hidden" name="bulk_save" value="1">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="px-3 py-2 text-left text-xs text-gray-600" rowspan="2">หวย</th>
                        <th class="px-2 py-1 text-center text-xs text-gray-400" rowspan="2">เลือก<br><input type="checkbox" id="selectAll" onchange="toggleAll(this)" class="w-3.5 h-3.5"></th>
                        <?php foreach ($betTypes as $bt): ?>
                        <th class="px-2 py-2 text-center text-xs text-gray-600"><?= $betTypeLabels[$bt] ?></th>
                        <?php endforeach; ?>
                        <th class="px-2 py-2 text-center text-xs text-gray-500">แก้ไข</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $tabLotteries = $categories[$activeTab] ?? [];
                    if (empty($tabLotteries)): ?>
                    <tr><td colspan="<?= 3 + count($betTypes) ?>" class="text-center py-8 text-gray-400">ไม่มีข้อมูล</td></tr>
                    <?php else:
                    foreach ($tabLotteries as $i => $lot):
                        $lid = $lot['id'];
                        $limits = $existingLimits[$lid] ?? [];
                    ?>
                    <tr class="border-b <?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' ?> hover:bg-green-50/50 transition">
                        <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">
                            <?= htmlspecialchars($lot['flag_emoji'] ?? '') ?> <?= htmlspecialchars($lot['name']) ?>
                        </td>
                        <td class="px-2 py-2 text-center">
                            <input type="checkbox" class="lot-cb w-3.5 h-3.5" data-lid="<?= $lid ?>">
                        </td>
                        <?php foreach ($betTypes as $bt): 
                            $val = $limits[$bt] ?? 0;
                        ?>
                        <td class="px-1 py-1.5 text-center">
                            <input type="number" name="all_limits[<?= $lid ?>][<?= $bt ?>]" 
                                   value="<?= intval($val) ?>" min="0" step="1" 
                                   class="w-full border rounded px-1 py-1 text-xs text-center focus:border-green-500 outline-none <?= $val > 0 ? 'bg-green-50 border-green-300 font-bold text-green-700' : 'border-gray-200' ?>"
                                   onchange="highlightInput(this)">
                        </td>
                        <?php endforeach; ?>
                        <td class="px-2 py-2 text-center">
                            <button type="button" onclick="editRow(<?= $lid ?>, '<?= htmlspecialchars($lot['name']) ?>')" class="text-blue-500 hover:text-blue-700" title="แก้ไขทีละตัว">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between px-4 py-3 border-t bg-gray-50">
            <span class="text-xs text-gray-500"><i class="fas fa-info-circle mr-1"></i>ใส่ 0 = ไม่จำกัด | ตัวเลขคือจำนวนเงินสูงสุดที่รับได้ต่อเลข</span>
            <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
                <i class="fas fa-save mr-1"></i>บันทึก
            </button>
        </div>
    </form>
</div>

<script>
function toggleAll(el) {
    document.querySelectorAll('.lot-cb').forEach(cb => cb.checked = el.checked);
}

function highlightInput(input) {
    if (parseInt(input.value) > 0) {
        input.className = input.className.replace('border-gray-200', 'border-green-300').replace('bg-white', '');
        if (!input.className.includes('bg-green-50')) input.className += ' bg-green-50 font-bold text-green-700';
    } else {
        input.className = input.className.replace('bg-green-50', '').replace('border-green-300', 'border-gray-200').replace('font-bold', '').replace('text-green-700', '');
    }
}

function editRow(lid, name) {
    const row = document.querySelector(`input[name="all_limits[${lid}][3top]"]`).closest('tr');
    const inputs = row.querySelectorAll('input[type="number"]');
    Swal.fire({
        title: 'ตั้งสู้ ' + name,
        html: '<?php foreach ($betTypes as $bt): ?>' +
              '<div class="flex items-center justify-between mb-2">' +
              '<span class="text-sm font-medium"><?= $betTypeLabels[$bt] ?></span>' +
              '<input type="number" id="swal-<?= $bt ?>" class="border rounded px-2 py-1 w-24 text-center text-sm" value="' + 
              (row.querySelector('input[name="all_limits[' + lid + '][<?= $bt ?>]"]')?.value || 0) + '">' +
              '</div><?php endforeach; ?>',
        confirmButtonText: '<i class="fas fa-save mr-1"></i>บันทึก',
        confirmButtonColor: '#22c55e',
        showCancelButton: true,
        cancelButtonText: 'ยกเลิก',
    }).then(result => {
        if (result.isConfirmed) {
            <?php foreach ($betTypes as $bt): ?>
            row.querySelector('input[name="all_limits[' + lid + '][<?= $bt ?>]"]').value = document.getElementById('swal-<?= $bt ?>').value;
            <?php endforeach; ?>
            // Auto-submit
            row.closest('form').submit();
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
