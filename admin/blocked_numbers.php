<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'blocked';
$adminTitle = 'เลขอั้น/เลขปิดรับ';
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    
    if ($action === 'add') {
        $lotteryIds = $_POST['lottery_ids'] ?? [];
        $numbers = preg_split('/[\s,]+/', trim($_POST['numbers'] ?? ''));
        $betType = $_POST['bet_type'] ?? '2top';
        $blockType = $_POST['block_type'] ?? 'half'; // 'half' = จ่ายครึ่ง, 'block' = ปิดรับ
        
        $isBlocked = ($blockType === 'block') ? 1 : 0;
        
        if (empty($lotteryIds)) {
            $msg = 'กรุณาเลือกหวยอย่างน้อย 1 รายการ';
            $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO blocked_numbers (lottery_type_id, number, bet_type, custom_pay_rate, is_blocked) VALUES (?, ?, ?, NULL, ?) ON DUPLICATE KEY UPDATE is_blocked = VALUES(is_blocked), custom_pay_rate = NULL");
            $count = 0;
            foreach ($lotteryIds as $lid) {
                $lid = intval($lid);
                foreach ($numbers as $num) {
                    $num = trim($num);
                    if ($num !== '' && $lid > 0) {
                        $stmt->execute([$lid, $num, $betType, $isBlocked]);
                        $count++;
                    }
                }
            }
            $msg = ($isBlocked ? '🚫 ปิดรับ' : '½ จ่ายครึ่ง') . " {$count} รายการ (" . count($lotteryIds) . " หวย)";
            $msgType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM blocked_numbers WHERE id = ?")->execute([$id]);
        $msg = 'ลบสำเร็จ';
        $msgType = 'success';
    } elseif ($action === 'update') {
        $id = intval($_POST['id']);
        $isBlocked = intval($_POST['is_blocked'] ?? 0);
        $pdo->prepare("UPDATE blocked_numbers SET is_blocked = ?, custom_pay_rate = NULL WHERE id = ?")->execute([$isBlocked, $id]);
        $msg = 'แก้ไขสำเร็จ';
        $msgType = 'success';
    } elseif ($action === 'clear_all') {
        $lotteryId = intval($_POST['lottery_type_id']);
        if ($lotteryId === 0) {
            $pdo->prepare("DELETE FROM blocked_numbers")->execute();
            $msg = 'ล้างเลขอั้นทุกหวยสำเร็จ';
        } else {
            $pdo->prepare("DELETE FROM blocked_numbers WHERE lottery_type_id = ?")->execute([$lotteryId]);
            $msg = 'ล้างเลขอั้นสำเร็จ';
        }
        $msgType = 'success';
    }
}

// Get all lotteries grouped by category
$lotteries = $pdo->query("SELECT lt.*, lc.name as category_name FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id WHERE lt.is_active = 1 ORDER BY lc.sort_order, lt.sort_order")->fetchAll();
$categories = [];
foreach ($lotteries as $l) {
    $categories[$l['category_name']][] = $l;
}

$filterLottery = intval($_GET['filter'] ?? 0);

$blockedQuery = "SELECT bn.*, lt.name as lottery_name FROM blocked_numbers bn JOIN lottery_types lt ON bn.lottery_type_id = lt.id";
if ($filterLottery > 0) $blockedQuery .= " WHERE bn.lottery_type_id = " . $filterLottery;
$blockedQuery .= " ORDER BY bn.number, bn.bet_type, lt.name";
$blockedNumbers = $pdo->query($blockedQuery)->fetchAll();

$betTypeLabels = [
    '2top' => '2 ตัวบน', '2bot' => '2 ตัวล่าง',
    '3top' => '3 ตัวบน', '3tod' => '3 ตัวโต๊ด',
    'run_top' => 'วิ่งบน', 'run_bot' => 'วิ่งล่าง',
];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check' : 'exclamation' ?>-circle mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Add Form -->
<div class="bg-white rounded-xl shadow-sm border mb-6">
    <div class="px-4 py-3 border-b bg-orange-50">
        <span class="font-bold text-orange-700 text-sm"><i class="fas fa-plus-circle mr-1"></i>เพิ่มเลขอั้น / ปิดรับ</span>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="form_action" value="add">
        
        <!-- Row 1: Number + Type + Action -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="text-xs text-gray-500 block mb-1">เลข (คั่นด้วยเว้นวรรคหรือคอมมา)</label>
                <input type="text" name="numbers" required class="w-full border rounded-lg px-3 py-2 text-sm focus:border-orange-500 outline-none" placeholder="เช่น 69 96 12 21">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ประเภท</label>
                <select name="bet_type" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-orange-500 outline-none">
                    <?php foreach ($betTypeLabels as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">การกระทำ</label>
                <div class="flex gap-3 mt-1">
                    <label class="flex items-center space-x-2 cursor-pointer bg-orange-50 border border-orange-200 rounded-lg px-4 py-2 hover:bg-orange-100 transition">
                        <input type="radio" name="block_type" value="half" checked class="w-4 h-4 text-orange-500">
                        <span class="text-orange-700 font-medium text-sm">½ จ่ายครึ่ง</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer bg-red-50 border border-red-200 rounded-lg px-4 py-2 hover:bg-red-100 transition">
                        <input type="radio" name="block_type" value="block" class="w-4 h-4 text-red-500">
                        <span class="text-red-700 font-medium text-sm">🚫 ปิดรับแทง</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Row 2: Lottery Checklist -->
        <div class="border rounded-lg p-3 mb-4">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-bold text-gray-700"><i class="fas fa-check-square mr-1 text-orange-500"></i>เลือกหวยที่ต้องการใช้</span>
                <label class="flex items-center space-x-2 text-sm cursor-pointer bg-orange-100 px-3 py-1 rounded-full hover:bg-orange-200 transition">
                    <input type="checkbox" id="selectAll" class="w-4 h-4 text-orange-500 rounded" onchange="toggleAll(this)">
                    <span class="text-orange-700 font-medium">เลือกทั้งหมด</span>
                </label>
            </div>
            
            <?php foreach ($categories as $catName => $catLotteries): ?>
            <div class="mb-3">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xs font-bold text-gray-500 uppercase"><?= htmlspecialchars($catName) ?></span>
                    <button type="button" onclick="toggleCategory(this)" class="text-[10px] text-blue-500 hover:text-blue-700 cursor-pointer">เลือกกลุ่มนี้</button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-1">
                    <?php foreach ($catLotteries as $l): ?>
                    <label class="flex items-center space-x-1.5 text-xs p-1.5 rounded hover:bg-orange-50 cursor-pointer">
                        <input type="checkbox" name="lottery_ids[]" value="<?= $l['id'] ?>" class="w-3.5 h-3.5 text-orange-500 rounded lottery-cb">
                        <span class="truncate"><?= htmlspecialchars($l['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-orange-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-orange-600 transition">
                <i class="fas fa-save mr-1"></i>บันทึก
            </button>
        </div>
    </form>
</div>

<?php
$countBlocked = 0; $countHalf = 0;
foreach ($blockedNumbers as $bn) { if ($bn['is_blocked']) $countBlocked++; else $countHalf++; }
?>
<!-- List -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
            <span class="font-bold text-gray-700 text-sm"><i class="fas fa-list mr-1"></i>รายการ <span id="visibleCount"><?= count($blockedNumbers) ?></span> รายการ</span>
            <div class="flex items-center gap-2">
                <select onchange="window.location='?filter='+this.value" class="border rounded-lg px-2 py-1 text-xs outline-none">
                    <option value="0">ทุกหวย</option>
                    <?php foreach ($lotteries as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $filterLottery == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <form method="POST" class="inline">
                    <input type="hidden" name="form_action" value="clear_all">
                    <input type="hidden" name="lottery_type_id" value="<?= $filterLottery ?>">
                    <button type="submit" onclick="return confirm('ล้าง<?= $filterLottery ? 'หวยนี้' : 'ทั้งหมดทุกหวย' ?>?')" class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs font-medium hover:bg-red-600 transition">
                        <i class="fas fa-trash mr-1"></i>ล้าง<?= $filterLottery ? 'หวยนี้' : 'ทั้งหมด' ?>
                    </button>
                </form>
            </div>
        </div>
        <div class="flex gap-2">
            <button onclick="filterStatus('all')" id="filterAll" class="px-4 py-1.5 rounded-full text-xs font-bold transition bg-gray-700 text-white">ทั้งหมด (<?= count($blockedNumbers) ?>)</button>
            <button onclick="filterStatus('half')" id="filterHalf" class="px-4 py-1.5 rounded-full text-xs font-bold transition bg-white text-orange-600 border border-orange-300 hover:bg-orange-50">½ จ่ายครึ่ง (<?= $countHalf ?>)</button>
            <button onclick="filterStatus('blocked')" id="filterBlocked" class="px-4 py-1.5 rounded-full text-xs font-bold transition bg-white text-red-600 border border-red-300 hover:bg-red-50">🚫 ห้ามแทง (<?= $countBlocked ?>)</button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">เลข</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">หวย</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ประเภท</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">สถานะ</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($blockedNumbers)): ?>
                <tr><td colspan="5" class="px-3 py-8 text-center text-gray-400">ไม่มีรายการ</td></tr>
                <?php else: foreach ($blockedNumbers as $bn): ?>
                <tr class="border-b hover:bg-gray-50 blocked-row" data-blocked="<?= $bn['is_blocked'] ?>">
                    <td class="px-3 py-2 font-bold text-lg font-mono text-gray-800"><?= htmlspecialchars($bn['number']) ?></td>
                    <td class="px-3 py-2 text-xs"><?= htmlspecialchars($bn['lottery_name']) ?></td>
                    <td class="px-3 py-2 text-xs"><?= $betTypeLabels[$bn['bet_type']] ?? $bn['bet_type'] ?></td>
                    <td class="px-3 py-2 text-center">
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?= $bn['is_blocked'] ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' ?>">
                            <?= $bn['is_blocked'] ? '🚫 ห้ามแทง' : '½ จ่ายครึ่ง' ?>
                        </span>
                    </td>
                    <td class="px-3 py-2 text-center whitespace-nowrap">
                        <button onclick="editBlocked(<?= $bn['id'] ?>, '<?= htmlspecialchars($bn['number']) ?>', <?= $bn['is_blocked'] ?>)" class="text-blue-500 hover:text-blue-700 mr-2" title="แก้ไข"><i class="fas fa-edit"></i></button>
                        <button onclick="confirmDeleteBlocked(<?= $bn['id'] ?>, '<?= htmlspecialchars($bn['number']) ?>')" class="text-red-400 hover:text-red-600" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                        <form id="deleteBlockedForm_<?= $bn['id'] ?>" method="POST" class="hidden">
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= $bn['id'] ?>">
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleAll(master) {
    document.querySelectorAll('.lottery-cb').forEach(cb => cb.checked = master.checked);
}

function toggleCategory(btn) {
    const parent = btn.closest('.mb-3');
    const cbs = parent.querySelectorAll('.lottery-cb');
    const allChecked = [...cbs].every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
    updateSelectAll();
}

function updateSelectAll() {
    const all = document.querySelectorAll('.lottery-cb');
    const checked = document.querySelectorAll('.lottery-cb:checked');
    document.getElementById('selectAll').checked = all.length === checked.length;
}

document.querySelectorAll('.lottery-cb').forEach(cb => cb.addEventListener('change', updateSelectAll));

function filterStatus(type) {
    const rows = document.querySelectorAll('.blocked-row');
    let visible = 0;
    rows.forEach(row => {
        const isBlocked = row.dataset.blocked;
        if (type === 'all' || (type === 'blocked' && isBlocked === '1') || (type === 'half' && isBlocked === '0')) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('visibleCount').textContent = visible;
    // Update button styles
    const btnAll = document.getElementById('filterAll');
    const btnHalf = document.getElementById('filterHalf');
    const btnBlocked = document.getElementById('filterBlocked');
    btnAll.className = 'px-4 py-1.5 rounded-full text-xs font-bold transition ' + (type==='all' ? 'bg-gray-700 text-white' : 'bg-white text-gray-500 border border-gray-300 hover:bg-gray-100');
    btnHalf.className = 'px-4 py-1.5 rounded-full text-xs font-bold transition ' + (type==='half' ? 'bg-orange-500 text-white' : 'bg-white text-orange-600 border border-orange-300 hover:bg-orange-50');
    btnBlocked.className = 'px-4 py-1.5 rounded-full text-xs font-bold transition ' + (type==='blocked' ? 'bg-red-500 text-white' : 'bg-white text-red-600 border border-red-300 hover:bg-red-50');
}

function editBlocked(id, number, isBlocked) {
    Swal.fire({
        title: `แก้ไขเลข ${number}`,
        html: `
            <div class="space-y-3 text-left">
                <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg border ${!isBlocked ? 'border-orange-300 bg-orange-50' : 'border-gray-200'} hover:bg-orange-50">
                    <input type="radio" name="swal_type" value="0" ${!isBlocked ? 'checked' : ''} class="w-4 h-4 text-orange-500">
                    <span class="text-orange-700 font-medium">½ จ่ายครึ่ง</span>
                </label>
                <label class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg border ${isBlocked ? 'border-red-300 bg-red-50' : 'border-gray-200'} hover:bg-red-50">
                    <input type="radio" name="swal_type" value="1" ${isBlocked ? 'checked' : ''} class="w-4 h-4 text-red-500">
                    <span class="text-red-700 font-medium">🚫 ปิดรับแทง</span>
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-save mr-1"></i>บันทึก',
        cancelButtonText: 'ยกเลิก',
        preConfirm: () => ({
            blocked: document.querySelector('input[name="swal_type"]:checked')?.value || '0',
        })
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="form_action" value="update">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="is_blocked" value="${result.value.blocked}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function confirmDeleteBlocked(id, number) {
    Swal.fire({
        title: `ลบเลข ${number}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i>ลบ',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteBlockedForm_' + id).submit();
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
