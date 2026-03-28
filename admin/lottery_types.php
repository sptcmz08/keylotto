<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$adminPage = 'lottery';
$adminTitle = 'จัดการหวย';

// Handle form submissions
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $categoryId = intval($_POST['category_id']);
        $name = trim($_POST['name']);
        $flagEmoji = trim($_POST['flag_emoji'] ?? '🏳️');
        $closeTime = $_POST['close_time'] ?? null;
        $openTime = $_POST['open_time'] ?? null;
        $resultTime = $_POST['result_time'] ?? null;
        $resultUrl = trim($_POST['result_url'] ?? '');
        $resultLabel = trim($_POST['result_label'] ?? '');
        $drawSchedule = trim($_POST['draw_schedule'] ?? 'daily');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = intval($_POST['sort_order'] ?? 0);

        // ตรวจสอบชื่อหวยซ้ำ
        $checkStmt = $pdo->prepare("SELECT id FROM lottery_types WHERE name = ? AND id != ?");
        $checkStmt->execute([$name, $id]);
        
        if ($checkStmt->fetchColumn()) {
            $msg = 'ชื่อหวย "'.htmlspecialchars($name).'" มีอยู่ในระบบแล้ว ไม่สามารถเพิ่มหรือแก้ไขซ้ำได้';
            $msgType = 'error';
        } else {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO lottery_types (category_id, name, flag_emoji, close_time, open_time, result_time, result_url, result_label, draw_schedule, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoryId, $name, $flagEmoji, $closeTime, $openTime, $resultTime, $resultUrl, $resultLabel, $drawSchedule, $isActive, $sortOrder]);
                $msg = 'เพิ่มหวยและลิงค์ดูผลสำเร็จ';
                $msgType = 'success';
            } else {
                // ดึงชื่อเดิมก่อนเปลี่ยน เพื่ออัพเดท result_links ให้ตรงกันก่อนทำ UPSERT
                $stmtOld = $pdo->prepare("SELECT name FROM lottery_types WHERE id=?");
                $stmtOld->execute([$id]);
                $oldName = $stmtOld->fetchColumn();

                $stmt = $pdo->prepare("UPDATE lottery_types SET category_id=?, name=?, flag_emoji=?, close_time=?, open_time=?, result_time=?, result_url=?, result_label=?, draw_schedule=?, is_active=?, sort_order=? WHERE id=?");
                $stmt->execute([$categoryId, $name, $flagEmoji, $closeTime, $openTime, $resultTime, $resultUrl, $resultLabel, $drawSchedule, $isActive, $sortOrder, $id]);
                
                if ($oldName && $oldName !== $name) {
                    // ถ้าเปลี่ยนชื่อหวย ให้ไปเปลี่ยนชื่อใน result_links ด้วยเพื่อรักษาข้อมูลเดิม (เช่น scraper_url)
                    $pdo->prepare("UPDATE result_links SET name=? WHERE name=?")->execute([$name, $oldName]);
                }

                $msg = 'แก้ไขข้อมูลหวยและลิงค์ดูผลสำเร็จ';
                $msgType = 'success';
            }

            // ==========================================
            // Sync ข้อมูลไปที่ตาราง result_links อัตโนมัติ (UPSERT)
            // ==========================================
            $syncStmt = $pdo->prepare("
                INSERT INTO result_links 
                (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, sort_order, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                category_id=VALUES(category_id), 
                flag_emoji=VALUES(flag_emoji),
                close_time=VALUES(close_time), 
                result_time=VALUES(result_time), 
                result_url=VALUES(result_url), 
                result_label=VALUES(result_label), 
                sort_order=VALUES(sort_order), 
                is_active=VALUES(is_active)
            ");
            $syncStmt->execute([$categoryId, $name, $flagEmoji, $closeTime, $resultTime, $resultUrl, $resultLabel, $sortOrder, $isActive]);
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        // ดึงชื่อหวยก่อนลบ เพื่อลบ result_links ที่ชื่อตรงกันด้วย
        $nameStmt = $pdo->prepare("SELECT name FROM lottery_types WHERE id = ?");
        $nameStmt->execute([$id]);
        $ltName = $nameStmt->fetchColumn();
        $pdo->prepare("DELETE FROM lottery_types WHERE id = ?")->execute([$id]);
        if ($ltName) {
            $pdo->prepare("DELETE FROM result_links WHERE name = ?")->execute([$ltName]);
        }
        $msg = 'ลบสำเร็จ';
        $msgType = 'success';
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE lottery_types SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $msg = 'เปลี่ยนสถานะสำเร็จ';
        $msgType = 'success';
    } elseif ($action === 'toggle_bet') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE lottery_types SET bet_closed = NOT bet_closed WHERE id = ?")->execute([$id]);
        $msg = 'เปลี่ยนสถานะรับแทงสำเร็จ';
        $msgType = 'success';
    }
}

// Fetch data
$categories = $pdo->query("SELECT * FROM lottery_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$lotteries = $pdo->query("
    SELECT lt.*, lc.name as category_name
    FROM lottery_types lt
    JOIN lottery_categories lc ON lt.category_id = lc.id
    ORDER BY lc.sort_order, lt.sort_order
")->fetchAll();

// Emoji options
// Icon mapping per category
$categoryIcons = [
    'หวยชุด' => '🎰',
    'หวยไทย' => '🇹🇭',
    'หวยต่างประเทศ' => '🌏',
    'หวยรายวัน' => '📅',
    'หวยหุ้น' => '📈',
    'หวย One' => '🎯',
    'หวยสากล' => '🌐',
    'หุ้น VIP' => '⭐',
];

$flagOptions = [
    '🇹🇭' => 'ไทย', '🇻🇳' => 'เวียดนาม', '🇱🇦' => 'ลาว', '🇺🇸' => 'อเมริกา',
    '🇩🇪' => 'เยอรมัน', '🇷🇺' => 'รัสเซีย', '🇬🇧' => 'อังกฤษ', '🇭🇰' => 'ฮ่องกง',
    '🇯🇵' => 'ญี่ปุ่น', '🇰🇷' => 'เกาหลี', '🇨🇳' => 'จีน', '🇲🇾' => 'มาเลเซีย',
    '🇸🇬' => 'สิงคโปร์', '🏳️' => 'อื่นๆ'
];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Header + Add Button -->
<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-800"><i class="fas fa-dice text-green-600 mr-2"></i>จัดการหวย</h1>
        <p class="text-xs text-gray-400">ทั้งหมด <?= count($lotteries) ?> รายการ</p>
    </div>
    <button onclick="openModal('add')" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
        <i class="fas fa-plus mr-1"></i> เพิ่มหวย
    </button>
</div>

<!-- List -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ID</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ธง</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ชื่อ</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">หมวด</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">เปิดรับ</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">ปิดรับ</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">ผลออก</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">ตารางออก</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">สถานะ</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">รับแทง</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowNum = 1; foreach ($lotteries as $l):
                    $flagUrl = getFlagForCountry($l['flag_emoji'], $l['name']);
                ?>
                <tr class="border-b hover:bg-gray-50 transition">
                    <td class="px-3 py-2 text-xs text-gray-400"><?= $rowNum++ ?></td>
                    <td class="px-3 py-2"><img src="<?= $flagUrl ?>" class="w-8 h-5 object-cover rounded border"></td>
                    <td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($l['name']) ?></td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?= $categoryIcons[$l['category_name']] ?? '🏳️' ?> <?= htmlspecialchars($l['category_name']) ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $l['open_time'] ? date('H:i', strtotime($l['open_time'])) : '-' ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $l['close_time'] ? date('H:i', strtotime($l['close_time'])) : '-' ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $l['result_time'] ? date('H:i', strtotime($l['result_time'])) : '-' ?></td>
                    <td class="px-3 py-2 text-center text-xs">
                        <?php
                        $schedLabels = ['daily'=>'ทุกวัน','weekday'=>'จ-ศ','sun_thu'=>'อา-พฤ','mon_wed_fri'=>'จ/พ/ศ','1st_16th'=>'1,16','16th'=>'16'];
                        $sch = $l['draw_schedule'] ?? 'daily';
                        echo $schedLabels[$sch] ?? $sch;
                        ?>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="form_action" value="toggle">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= $l['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= $l['is_active'] ? 'เปิด' : 'ปิด' ?>
                            </button>
                        </form>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="form_action" value="toggle_bet">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= empty($l['bet_closed']) ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' ?>">
                                <?= empty($l['bet_closed']) ? 'เปิดรับ' : 'ปิดรับ' ?>
                            </button>
                        </form>
                    </td>
                    <td class="px-3 py-2 text-center space-x-1">
                        <button onclick='editLottery(<?= json_encode($l) ?>)' class="text-blue-500 hover:text-blue-700"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="inline" onsubmit="return confirm('ลบ <?= htmlspecialchars($l['name']) ?>?')">
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="text-red-400 hover:text-red-600"><i class="fas fa-trash-alt"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Popup -->
<div id="lotteryModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden" onclick="if(event.target===this)closeModal()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-4 border-b bg-green-50 rounded-t-xl flex justify-between items-center sticky top-0 z-10">
            <span class="font-bold text-green-700" id="modalTitle"><i class="fas fa-plus-circle mr-1"></i> เพิ่มหวย</span>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-lg"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-5">
            <input type="hidden" name="form_action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">หมวดหมู่ *</label>
                    <select name="category_id" id="categoryId" required class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none">
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $categoryIcons[$c['name']] ?? '🏳️' ?> <?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">ชื่อหวย *</label>
                    <input type="text" name="name" id="formName" required class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" placeholder="เช่น ดาวโจนส์อเมริกา">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">ธงชาติ</label>
                    <select name="flag_emoji" id="flagEmoji" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none">
                        <?php foreach ($flagOptions as $emoji => $label): ?>
                        <option value="<?= $emoji ?>"><?= $emoji ?> <?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">เวลาเปิดรับ</label>
                    <input type="time" name="open_time" id="openTime" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" step="1">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">เวลาปิดรับ</label>
                    <input type="time" name="close_time" id="closeTime" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" step="1">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">เวลาผลออก</label>
                    <input type="time" name="result_time" id="resultTime" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" step="1">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">ตารางออกผล</label>
                    <select name="draw_schedule" id="drawSchedule" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none">
                        <option value="daily">ทุกวัน</option>
                        <option value="weekday">จันทร์-ศุกร์</option>
                        <option value="sun_thu">อาทิตย์-พฤหัสบดี (อียิปต์)</option>
                        <option value="mon_wed_fri">จันทร์/พุธ/ศุกร์</option>
                        <option value="1st_16th">วันที่ 1, 16</option>
                        <option value="16th">วันที่ 16</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">URL ดูผล</label>
                    <input type="text" name="result_url" id="resultUrl" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" placeholder="https://...">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">ป้ายลิงค์ผล</label>
                    <input type="text" name="result_label" id="resultLabel" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" placeholder="เช่น www.glo.or.th">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">ลำดับ</label>
                    <input type="number" name="sort_order" id="sortOrder" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" value="0">
                </div>
            </div>
            <div class="flex items-center justify-between mt-5 pt-4 border-t">
                <label class="flex items-center space-x-2 text-sm">
                    <input type="checkbox" name="is_active" id="isActive" checked class="w-4 h-4 text-green-500 rounded">
                    <span class="text-gray-600">เปิดใช้งาน</span>
                </label>
                <div class="flex gap-2">
                    <button type="button" onclick="closeModal()" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300 transition">ยกเลิก</button>
                    <button type="submit" class="px-6 py-2 bg-green-500 text-white rounded-lg text-sm font-medium hover:bg-green-600 transition">
                        <i class="fas fa-save mr-1"></i><span id="submitLabel">เพิ่ม</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(mode) {
    if (mode === 'add') {
        resetForm();
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle mr-1"></i> เพิ่มหวยใหม่';
    }
    document.getElementById('lotteryModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('lotteryModal').classList.add('hidden');
}

function editLottery(data) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('categoryId').value = data.category_id;
    document.getElementById('formName').value = data.name;
    document.getElementById('flagEmoji').value = data.flag_emoji || '🏳️';
    document.getElementById('closeTime').value = data.close_time || '';
    document.getElementById('openTime').value = data.open_time || '';
    document.getElementById('resultTime').value = data.result_time || '';
    document.getElementById('drawSchedule').value = data.draw_schedule || 'daily';
    document.getElementById('resultUrl').value = data.result_url || '';
    document.getElementById('resultLabel').value = data.result_label || '';
    document.getElementById('sortOrder').value = data.sort_order || 0;
    document.getElementById('isActive').checked = data.is_active == 1;
    document.getElementById('submitLabel').textContent = 'บันทึก';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit mr-1"></i> แก้ไข: ' + data.name;
    document.getElementById('lotteryModal').classList.remove('hidden');
}

function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('formName').value = '';
    document.getElementById('closeTime').value = '';
    document.getElementById('openTime').value = '';
    document.getElementById('resultTime').value = '';
    document.getElementById('drawSchedule').value = 'daily';
    document.getElementById('resultUrl').value = '';
    document.getElementById('resultLabel').value = '';
    document.getElementById('sortOrder').value = '0';
    document.getElementById('isActive').checked = true;
    document.getElementById('submitLabel').textContent = 'เพิ่ม';
}
</script>

<?php require_once 'includes/footer.php'; ?>
