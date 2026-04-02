<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../line/common.php';
requireLogin();

ensureLineTables($pdo);

$adminPage = 'line_groups';
$adminTitle = 'LINE Groups';
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'save_credentials') {
        $channelSecret = trim($_POST['channel_secret'] ?? '');
        $channelAccessToken = trim($_POST['channel_access_token'] ?? '');

        if ($channelSecret === '' || $channelAccessToken === '') {
            $msg = 'กรุณากรอก Channel secret และ Channel access token ให้ครบ';
            $msgType = 'error';
        } else {
            lineSetSetting($pdo, 'channel_secret', $channelSecret);
            lineSetSetting($pdo, 'channel_access_token', $channelAccessToken);
            $msg = 'บันทึก LINE credentials สำเร็จ';
            $msgType = 'success';
        }
    } elseif ($action === 'send_test') {
        $groupId = trim($_POST['group_id'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($groupId === '' || $message === '') {
            $msg = 'กรุณาเลือกกลุ่มและใส่ข้อความทดสอบ';
            $msgType = 'error';
        } elseif (!lineConfigReady($pdo)) {
            $msg = 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server';
            $msgType = 'error';
        } else {
            $result = linePushTextMessage($pdo, $groupId, $message);
            lineLogPushResult($pdo, $groupId, $message, $result);

            if (!empty($result['ok'])) {
                $msg = 'ส่งข้อความทดสอบไป LINE สำเร็จ';
                $msgType = 'success';
            } else {
                $msg = 'ส่งข้อความไม่สำเร็จ (HTTP ' . ($result['status'] ?: 0) . ')';
                $msgType = 'error';
            }
        }
    }
}

$groups = $pdo->query("
    SELECT *
    FROM line_groups
    ORDER BY is_active DESC, last_seen_at DESC, id DESC
")->fetchAll();

$recentLogs = $pdo->query("
    SELECT *
    FROM line_message_logs
    ORDER BY id DESC
    LIMIT 20
")->fetchAll();

$savedChannelSecret = lineResolvedChannelSecret($pdo);
$savedChannelAccessToken = lineResolvedChannelAccessToken($pdo);

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-800"><i class="fab fa-line text-green-600 mr-2"></i>LINE Groups</h1>
        <p class="text-xs text-gray-400">กลุ่มที่เชื่อมผ่าน webhook แล้ว <?= count($groups) ?> กลุ่ม</p>
    </div>
</div>

<?php if (!lineConfigReady($pdo)): ?>
<div class="mb-4 p-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 text-sm">
    <div class="font-bold mb-1">ยังไม่ได้ตั้งค่า secret / token บน server</div>
    <div>กรอกค่า Channel secret และ Channel access token ด้านล่าง แล้วกดบันทึกได้เลย</div>
</div>
<?php endif; ?>

<div class="mb-4 bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">ตั้งค่า LINE Credentials</div>
    <form method="POST" class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
        <input type="hidden" name="form_action" value="save_credentials">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Channel secret</label>
            <input type="text" name="channel_secret" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" value="<?= htmlspecialchars($savedChannelSecret) ?>" placeholder="LINE Channel secret">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Channel access token</label>
            <textarea name="channel_access_token" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" placeholder="LINE Channel access token"><?= htmlspecialchars($savedChannelAccessToken) ?></textarea>
        </div>
        <div class="lg:col-span-2">
            <button type="submit" class="bg-[#2e7d32] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#1b5e20] transition">
                <i class="fas fa-save mr-1"></i> บันทึก LINE Credentials
            </button>
        </div>
    </form>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">รายชื่อกลุ่ม LINE</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">สถานะ</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ชื่อกลุ่ม</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">Group ID</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ล่าสุด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded-full text-[11px] font-medium <?= !empty($group['is_active']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= !empty($group['is_active']) ? 'active' : 'inactive' ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($group['group_name'] ?: '(ยังไม่ทราบชื่อกลุ่ม)') ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($group['group_id']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($group['last_seen_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($groups)): ?>
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-400">ยังไม่พบกลุ่ม LINE</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">ส่งข้อความทดสอบ</div>
        <form method="POST" class="p-4 space-y-4">
            <input type="hidden" name="form_action" value="send_test">
            <div>
                <label class="text-xs text-gray-500 block mb-1">เลือกกลุ่ม</label>
                <select name="group_id" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                    <?php foreach ($groups as $group): ?>
                    <option value="<?= htmlspecialchars($group['group_id']) ?>">
                        <?= htmlspecialchars(($group['group_name'] ?: 'group') . ' - ' . $group['group_id']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ข้อความ</label>
                <textarea name="message" rows="5" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" placeholder="ทดสอบส่งข้อความจากระบบ LINE">ทดสอบส่งข้อความจากระบบ LINE</textarea>
            </div>
            <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition">
                <i class="fab fa-line mr-1"></i> ส่งข้อความทดสอบ
            </button>
        </form>
    </div>
</div>

<div class="mt-4 bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">ประวัติการส่งล่าสุด</div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">เวลา</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">Group ID</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ข้อความ</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ผลลัพธ์</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($log['created_at']) ?></td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($log['group_id']) ?></td>
                    <td class="px-3 py-2"><?= nl2br(htmlspecialchars($log['message_text'])) ?></td>
                    <td class="px-3 py-2">
                        <span class="px-2 py-1 rounded-full text-[11px] font-medium <?= $log['status'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <?= htmlspecialchars($log['status']) ?><?= $log['response_code'] ? ' (' . (int) $log['response_code'] . ')' : '' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentLogs)): ?>
                <tr>
                    <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-400">ยังไม่มีประวัติการส่งข้อความ</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
