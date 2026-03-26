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
    // Removed add/edit/delete logic since it's synced from lottery_types
}

// ==========================================
// Auto-Heal: ซิงค์ข้อมูลอัตโนมัติทุกครั้งที่เข้าหน้านี้
// ==========================================
try {
    // 1. ลบข้อมูลใน result_links ที่ซ้ำซ้อนกันในตัวเอง (ชื่อเดียวกัน เก็บแค่ตัวแรกที่สร้างไว้)
    // ต้องทำก่อนอัพเดทหมวดหมู่ เพื่อป้องกัน Error 1062 Duplicate Entry
    $pdo->exec("
        DELETE FROM result_links 
        WHERE id NOT IN (
            SELECT min_id FROM (
                SELECT MIN(id) as min_id FROM result_links GROUP BY TRIM(name)
            ) as temp
        )
    ");

    // 2. อัพเดทรหัสหมวดหมู่ให้ตรงกันตารางหลัก
    $pdo->exec("
        UPDATE result_links rl
        JOIN lottery_types lt ON TRIM(rl.name) = TRIM(lt.name)
        SET rl.category_id = lt.category_id
        WHERE rl.category_id != lt.category_id
    ");

    // 3. ลบลิงค์ดูผลที่ไม่มีอยู่ในชื่อหวยตารางจัดการหวยแล้วออก (กันค้าง 100%)
    $pdo->exec("
        DELETE FROM result_links 
        WHERE TRIM(name) NOT IN (
            SELECT TRIM(name) FROM lottery_types WHERE name IS NOT NULL
        )
    ");

    // 4. ดึงหวยที่มีในจัดการหวย แต่ยังไม่มีในลิงค์ดูผล เข้ามาใส่ให้อัตโนมัติ (กันตกหล่น)
    $pdo->exec("
        INSERT IGNORE INTO result_links (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, sort_order, is_active)
        SELECT lt.category_id, TRIM(lt.name), lt.flag_emoji, lt.close_time, lt.result_time, lt.result_url, lt.result_label, lt.sort_order, lt.is_active
        FROM lottery_types lt
        WHERE TRIM(lt.name) NOT IN (SELECT TRIM(name) FROM result_links)
            AND lt.name IS NOT NULL AND TRIM(lt.name) != ''
    ");
} catch (Exception $e) {
    // มีข้อผิดพลาดในการ Auto-Heal ให้แจ้งเตือนแอดมินเพื่อให้เราตรวจสอบได้ (แทนที่การเงียบไป)
    $msg = 'Auto-Heal Error: ' . $e->getMessage();
    $msgType = 'error';
}


$categories = $pdo->query("SELECT * FROM lottery_categories ORDER BY sort_order")->fetchAll();
$links = $pdo->query("SELECT rl.*, lc.name as category_name FROM result_links rl JOIN lottery_categories lc ON rl.category_id = lc.id ORDER BY lc.sort_order, rl.sort_order")->fetchAll();

$flagOptions = ['🇹🇭'=>'ไทย','🇻🇳'=>'เวียดนาม','🇱🇦'=>'ลาว','🇺🇸'=>'อเมริกา','🇩🇪'=>'เยอรมัน','🇷🇺'=>'รัสเซีย','🇬🇧'=>'อังกฤษ','🇭🇰'=>'ฮ่องกง','🇯🇵'=>'ญี่ปุ่น','🇰🇷'=>'เกาหลี','🇨🇳'=>'จีน','🇲🇾'=>'มาเลเซีย','🇸🇬'=>'สิงคโปร์','🏳️'=>'อื่นๆ'];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm bg-green-50 text-green-700 border border-green-200"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Form Removed: Managed via lottery_types.php -->

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
                    <th class="px-3 py-2 text-center text-xs text-gray-500">สถานะ</th>
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
                    <td class="px-3 py-2 text-center">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= $l['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= $l['is_active'] ? 'เปิด' : 'ปิด' ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Scripts Removed -->

<?php require_once 'includes/footer.php'; ?>
