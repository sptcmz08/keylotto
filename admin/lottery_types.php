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
        $resultTime = $_POST['result_time'] ?? null;
        $resultUrl = trim($_POST['result_url'] ?? '');
        $resultLabel = trim($_POST['result_label'] ?? '');
        $drawDate = $_POST['draw_date'] ?? date('Y-m-d');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = intval($_POST['sort_order'] ?? 0);

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO lottery_types (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, draw_date, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$categoryId, $name, $flagEmoji, $closeTime, $resultTime, $resultUrl, $resultLabel, $drawDate, $isActive, $sortOrder]);
            $msg = 'เพิ่มหวยสำเร็จ';
            $msgType = 'success';
        } else {
            $stmt = $pdo->prepare("UPDATE lottery_types SET category_id=?, name=?, flag_emoji=?, close_time=?, result_time=?, result_url=?, result_label=?, draw_date=?, is_active=?, sort_order=? WHERE id=?");
            $stmt->execute([$categoryId, $name, $flagEmoji, $closeTime, $resultTime, $resultUrl, $resultLabel, $drawDate, $isActive, $sortOrder, $id]);
            $msg = 'แก้ไขสำเร็จ';
            $msgType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM lottery_types WHERE id = ?")->execute([$id]);
        $msg = 'ลบสำเร็จ';
        $msgType = 'success';
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE lottery_types SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $msg = 'เปลี่ยนสถานะสำเร็จ';
        $msgType = 'success';
    }
}

// Fetch data
$categories = $pdo->query("SELECT * FROM lottery_categories ORDER BY sort_order")->fetchAll();
$lotteries = $pdo->query("
    SELECT lt.*, lc.name as category_name
    FROM lottery_types lt
    JOIN lottery_categories lc ON lt.category_id = lc.id
    ORDER BY lc.sort_order, lt.sort_order
")->fetchAll();

// Emoji options
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

<!-- Add Form -->
<div class="bg-white rounded-xl shadow-sm border mb-6">
    <div class="px-4 py-3 border-b bg-green-50 flex items-center justify-between">
        <span class="font-bold text-green-700 text-sm"><i class="fas fa-plus-circle mr-1"></i>เพิ่ม/แก้ไขหวย</span>
        <button onclick="resetForm()" class="text-xs text-gray-500 hover:text-gray-700"><i class="fas fa-redo mr-1"></i>รีเซ็ต</button>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="form_action" id="formAction" value="add">
        <input type="hidden" name="id" id="formId" value="">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label class="text-xs text-gray-500 block mb-1">หมวดหมู่ *</label>
                <select name="category_id" id="categoryId" required class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none">
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
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
                <label class="text-xs text-gray-500 block mb-1">เวลาปิดรับ</label>
                <input type="time" name="close_time" id="closeTime" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" step="1">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">เวลาผลออก</label>
                <input type="time" name="result_time" id="resultTime" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" step="1">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">วันที่งวด</label>
                <input type="date" name="draw_date" id="drawDate" class="w-full border rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none" value="<?= date('Y-m-d') ?>">
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
        <div class="flex items-center justify-between mt-4">
            <label class="flex items-center space-x-2 text-sm">
                <input type="checkbox" name="is_active" id="isActive" checked class="w-4 h-4 text-green-500 rounded">
                <span class="text-gray-600">เปิดใช้งาน</span>
            </label>
            <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
                <i class="fas fa-save mr-1"></i><span id="submitLabel">เพิ่ม</span>
            </button>
        </div>
    </form>
</div>

<!-- List -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <span class="font-bold text-gray-700 text-sm"><i class="fas fa-list mr-1"></i>รายการหวยทั้งหมด (<?= count($lotteries) ?>)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ID</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ธง</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ชื่อ</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">หมวด</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">ปิดรับ</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">ผลออก</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">งวด</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">สถานะ</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lotteries as $l):
                    $flagUrl = getFlagForCountry($l['flag_emoji']);
                ?>
                <tr class="border-b hover:bg-gray-50 transition">
                    <td class="px-3 py-2 text-xs text-gray-400"><?= $l['id'] ?></td>
                    <td class="px-3 py-2"><img src="<?= $flagUrl ?>" class="w-8 h-5 object-cover rounded border"></td>
                    <td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($l['name']) ?></td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($l['category_name']) ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $l['close_time'] ? date('H:i', strtotime($l['close_time'])) : '-' ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $l['result_time'] ? date('H:i', strtotime($l['result_time'])) : '-' ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $l['draw_date'] ? date('d/m/Y', strtotime($l['draw_date'])) : '-' ?></td>
                    <td class="px-3 py-2 text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="form_action" value="toggle">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= $l['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= $l['is_active'] ? 'เปิด' : 'ปิด' ?>
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

<script>
function editLottery(data) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('categoryId').value = data.category_id;
    document.getElementById('formName').value = data.name;
    document.getElementById('flagEmoji').value = data.flag_emoji || '🏳️';
    document.getElementById('closeTime').value = data.close_time || '';
    document.getElementById('resultTime').value = data.result_time || '';
    document.getElementById('drawDate').value = data.draw_date || '';
    document.getElementById('resultUrl').value = data.result_url || '';
    document.getElementById('resultLabel').value = data.result_label || '';
    document.getElementById('sortOrder').value = data.sort_order || 0;
    document.getElementById('isActive').checked = data.is_active == 1;
    document.getElementById('submitLabel').textContent = 'แก้ไข';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('formName').value = '';
    document.getElementById('closeTime').value = '';
    document.getElementById('resultTime').value = '';
    document.getElementById('drawDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('resultUrl').value = '';
    document.getElementById('resultLabel').value = '';
    document.getElementById('sortOrder').value = '0';
    document.getElementById('isActive').checked = true;
    document.getElementById('submitLabel').textContent = 'เพิ่ม';
}
</script>

<?php require_once 'includes/footer.php'; ?>
