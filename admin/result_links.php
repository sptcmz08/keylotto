<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$adminPage = 'links';
$adminTitle = 'จัดการลิงค์ดูผล';
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
        $scraperUrl = trim($_POST['scraper_url'] ?? '');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO result_links (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, scraper_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$categoryId, $name, $flagEmoji, $closeTime, $resultTime, $resultUrl, $resultLabel, $scraperUrl, $sortOrder, $isActive]);
            $msg = 'เพิ่มลิงค์สำเร็จ';
        } else {
            $stmt = $pdo->prepare("UPDATE result_links SET category_id=?, name=?, flag_emoji=?, close_time=?, result_time=?, result_url=?, result_label=?, scraper_url=?, sort_order=?, is_active=? WHERE id=?");
            $stmt->execute([$categoryId, $name, $flagEmoji, $closeTime, $resultTime, $resultUrl, $resultLabel, $scraperUrl, $sortOrder, $isActive, $id]);
            $msg = 'แก้ไขสำเร็จ';
        }
        $msgType = 'success';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM result_links WHERE id = ?")->execute([intval($_POST['id'])]);
        $msg = 'ลบสำเร็จ';
        $msgType = 'success';
    }
}

$categories = $pdo->query("SELECT * FROM lottery_categories ORDER BY sort_order")->fetchAll();
$links = $pdo->query("SELECT rl.*, lc.name as category_name FROM result_links rl JOIN lottery_categories lc ON rl.category_id = lc.id ORDER BY lc.sort_order, rl.sort_order")->fetchAll();

$flagOptions = ['🇹🇭'=>'ไทย','🇻🇳'=>'เวียดนาม','🇱🇦'=>'ลาว','🇺🇸'=>'อเมริกา','🇩🇪'=>'เยอรมัน','🇷🇺'=>'รัสเซีย','🇬🇧'=>'อังกฤษ','🇭🇰'=>'ฮ่องกง','🇯🇵'=>'ญี่ปุ่น','🇰🇷'=>'เกาหลี','🇨🇳'=>'จีน','🇲🇾'=>'มาเลเซีย','🇸🇬'=>'สิงคโปร์','🏳️'=>'อื่นๆ'];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Add Form -->
<div class="bg-white rounded-xl shadow-sm border mb-6">
    <div class="px-4 py-3 border-b bg-cyan-50">
        <span class="font-bold text-cyan-700 text-sm"><i class="fas fa-plus-circle mr-1"></i>เพิ่ม/แก้ไขลิงค์ดูผล</span>
    </div>
    <form method="POST" class="p-4">
        <input type="hidden" name="form_action" id="formAction" value="add">
        <input type="hidden" name="id" id="formId" value="">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="text-xs text-gray-500 block mb-1">หมวดหมู่ *</label>
                <select name="category_id" id="categoryId" required class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ชื่อ *</label>
                <input type="text" name="name" id="formName" required class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ธง</label>
                <select name="flag_emoji" id="flagEmoji" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                    <?php foreach ($flagOptions as $e => $l): ?><option value="<?= $e ?>"><?= $e ?> <?= $l ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">เวลาปิดรับ</label>
                <input type="time" name="close_time" id="closeTime" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">เวลาผลออก</label>
                <input type="time" name="result_time" id="resultTime" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">URL ดูผล</label>
                <input type="text" name="result_url" id="resultUrl" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" placeholder="https://...">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ป้ายลิงค์</label>
                <input type="text" name="result_label" id="resultLabel" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">URL Scraper <span class="text-green-500">แหล่งที่มา</span></label>
                <input type="text" name="scraper_url" id="scraperUrl" class="w-full border rounded-lg px-3 py-2 text-sm outline-none border-green-300" placeholder="https://...">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ลำดับ</label>
                <input type="number" name="sort_order" id="sortOrder" value="0" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
            </div>
        </div>
        <div class="flex items-center justify-between mt-4">
            <label class="flex items-center space-x-2 text-sm">
                <input type="checkbox" name="is_active" id="isActive" checked class="w-4 h-4 text-green-500 rounded">
                <span class="text-gray-600">เปิดใช้งาน</span>
            </label>
            <div class="space-x-2">
                <button type="button" onclick="resetForm()" class="text-gray-500 hover:text-gray-700 text-sm"><i class="fas fa-redo mr-1"></i>รีเซ็ต</button>
                <button type="submit" class="bg-cyan-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-cyan-600 transition"><i class="fas fa-save mr-1"></i><span id="submitLabel">เพิ่ม</span></button>
            </div>
        </div>
    </form>
</div>

<!-- List -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50">
        <span class="font-bold text-gray-700 text-sm"><i class="fas fa-list mr-1"></i>ลิงค์ดูผลทั้งหมด (<?= count($links) ?>)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ลำดับ</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ธง</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ชื่อ</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">หมวด</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">ปิดรับ</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">ผลออก</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ลิงค์ดูผล</th>
                    <th class="px-3 py-2 text-left text-xs text-green-600">แหล่ง Scraper</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">สถานะ</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowNum = 1; foreach ($links as $l):
                    $flagUrl = getFlagForCountry($l['flag_emoji'], $l['name']);
                ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-3 py-2 text-xs text-gray-400"><?= $rowNum++ ?></td>
                    <td class="px-3 py-2"><img src="<?= $flagUrl ?>" class="w-8 h-5 object-cover rounded border"></td>
                    <td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($l['name']) ?></td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($l['category_name']) ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $l['close_time'] ? date('H:i', strtotime($l['close_time'])) : '-' ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $l['result_time'] ? date('H:i', strtotime($l['result_time'])) : '-' ?></td>
                    <td class="px-3 py-2 text-xs text-blue-500 truncate max-w-[150px]"><?php if ($l['result_url']): ?><a href="<?= htmlspecialchars($l['result_url']) ?>" target="_blank" class="hover:underline"><?= htmlspecialchars($l['result_label'] ?: $l['result_url']) ?></a><?php else: ?>-<?php endif; ?></td>
                    <td class="px-3 py-2 text-xs truncate max-w-[150px]"><?php if ($l['scraper_url']): ?><a href="<?= htmlspecialchars($l['scraper_url']) ?>" target="_blank" class="text-green-600 hover:underline"><?= htmlspecialchars(parse_url($l['scraper_url'], PHP_URL_HOST) ?: $l['scraper_url']) ?></a><?php else: ?><span class="text-gray-300">-</span><?php endif; ?></td>
                    <td class="px-3 py-2 text-center">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= $l['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= $l['is_active'] ? 'เปิด' : 'ปิด' ?></span>
                    </td>
                    <td class="px-3 py-2 text-center space-x-1">
                        <button onclick='editLink(<?= json_encode($l) ?>)' class="text-blue-500 hover:text-blue-700"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="inline" onsubmit="return confirm('ลบลิงค์นี้?')">
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
function editLink(d) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = d.id;
    document.getElementById('categoryId').value = d.category_id;
    document.getElementById('formName').value = d.name;
    document.getElementById('flagEmoji').value = d.flag_emoji || '🏳️';
    document.getElementById('closeTime').value = d.close_time || '';
    document.getElementById('resultTime').value = d.result_time || '';
    document.getElementById('resultUrl').value = d.result_url || '';
    document.getElementById('resultLabel').value = d.result_label || '';
    document.getElementById('scraperUrl').value = d.scraper_url || '';
    document.getElementById('sortOrder').value = d.sort_order || 0;
    document.getElementById('isActive').checked = d.is_active == 1;
    document.getElementById('submitLabel').textContent = 'แก้ไข';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('formName').value = '';
    document.getElementById('closeTime').value = '';
    document.getElementById('resultTime').value = '';
    document.getElementById('resultUrl').value = '';
    document.getElementById('resultLabel').value = '';
    document.getElementById('scraperUrl').value = '';
    document.getElementById('sortOrder').value = '0';
    document.getElementById('isActive').checked = true;
    document.getElementById('submitLabel').textContent = 'เพิ่ม';
}
</script>

<?php require_once 'includes/footer.php'; ?>
