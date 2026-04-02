<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../line/common.php';
requireLogin();

ensureLineTables($pdo);

$adminPage = 'line_groups';
$adminTitle = 'LINE Groups';

function lineGroupsRedirectWithFlash(string $type, string $message): void
{
    $_SESSION['line_groups_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: line_groups.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'save_credentials') {
        $channelSecret = trim($_POST['channel_secret'] ?? '');
        $channelAccessToken = trim($_POST['channel_access_token'] ?? '');
        $publicBaseUrl = trim($_POST['public_base_url'] ?? '');
        $autoSendResults = isset($_POST['auto_send_results']) ? '1' : '0';

        if ($channelSecret === '' || $channelAccessToken === '') {
            lineGroupsRedirectWithFlash('error', 'กรุณากรอก Channel secret และ Channel access token ให้ครบ');
        }

        lineSetSetting($pdo, 'channel_secret', $channelSecret);
        lineSetSetting($pdo, 'channel_access_token', $channelAccessToken);
        lineSetSetting($pdo, 'public_base_url', $publicBaseUrl !== '' ? $publicBaseUrl : 'https://member.imzshop97.com');
        lineSetSetting($pdo, 'auto_send_results', $autoSendResults);

        lineGroupsRedirectWithFlash('success', 'บันทึก LINE settings สำเร็จ');
    }

    if ($action === 'send_test') {
        $groupId = trim($_POST['group_id'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($groupId === '' || $message === '') {
            lineGroupsRedirectWithFlash('error', 'กรุณาเลือกกลุ่มและใส่ข้อความทดสอบ');
        }

        if (!lineConfigReady($pdo)) {
            lineGroupsRedirectWithFlash('error', 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server');
        }

        $result = linePushTextMessage($pdo, $groupId, $message);
        lineLogPushResult($pdo, $groupId, $message, $result);

        if (!empty($result['ok'])) {
            lineGroupsRedirectWithFlash('success', 'ส่งข้อความทดสอบไป LINE สำเร็จ');
        }

        lineGroupsRedirectWithFlash('error', 'ส่งข้อความไม่สำเร็จ (HTTP ' . ($result['status'] ?: 0) . ')');
    }

    if ($action === 'send_result_image') {
        $groupId = trim($_POST['group_id'] ?? '');
        $lotteryTypeId = (int) ($_POST['lottery_type_id'] ?? 0);
        $drawDate = trim($_POST['draw_date'] ?? '');

        if ($groupId === '' || $lotteryTypeId <= 0 || $drawDate === '') {
            lineGroupsRedirectWithFlash('error', 'ข้อมูลสำหรับส่งรูปผลหวยไม่ครบ');
        }

        $result = linePushResultImageToGroup($pdo, $groupId, $lotteryTypeId, $drawDate);

        if (!empty($result['ok'])) {
            $renderer = !empty($result['renderer']) ? ' (' . $result['renderer'] . ')' : '';
            lineGroupsRedirectWithFlash('success', 'ส่งรูปผลหวยไป LINE สำเร็จ' . $renderer);
        }

        $reason = $result['reason'] ?? '';
        if ($reason === 'image_generation_failed') {
            lineGroupsRedirectWithFlash('error', 'สร้างรูปผลหวยไม่สำเร็จ กรุณาตรวจ Node/Puppeteer หรือ GD บน server');
        }
        if ($reason === 'result_not_found') {
            lineGroupsRedirectWithFlash('error', 'ไม่พบผลหวยรายการนี้ในฐานข้อมูล');
        }
        if ($reason === 'config_not_ready') {
            lineGroupsRedirectWithFlash('error', 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server');
        }

        lineGroupsRedirectWithFlash('error', 'ส่งรูปผลหวยไม่สำเร็จ (HTTP ' . ($result['status'] ?: 0) . ')');
    }
}

$flash = $_SESSION['line_groups_flash'] ?? null;
unset($_SESSION['line_groups_flash']);

$msg = is_array($flash) ? (string) ($flash['message'] ?? '') : '';
$msgType = is_array($flash) ? (string) ($flash['type'] ?? 'success') : 'success';

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

$recentResults = $pdo->query("
    SELECT
        r.id,
        r.lottery_type_id,
        r.draw_date,
        r.three_top,
        r.two_top,
        r.two_bot,
        r.updated_at,
        lt.name AS lottery_name,
        lc.name AS category_name
    FROM results r
    JOIN lottery_types lt ON r.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    ORDER BY r.draw_date DESC, r.updated_at DESC, r.id DESC
    LIMIT 30
")->fetchAll();

$savedChannelSecret = lineResolvedChannelSecret($pdo);
$savedChannelAccessToken = lineResolvedChannelAccessToken($pdo);
$savedPublicBaseUrl = lineResolvedPublicBaseUrl($pdo);
$autoSendResults = lineAutoSendEnabled($pdo);

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
    <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">ตั้งค่า LINE Settings</div>
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
        <div>
            <label class="text-xs text-gray-500 block mb-1">Public base URL</label>
            <input type="text" name="public_base_url" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" value="<?= htmlspecialchars($savedPublicBaseUrl) ?>" placeholder="https://member.imzshop97.com">
            <div class="text-[11px] text-gray-400 mt-1">ใช้สำหรับสร้าง URL รูปภาพที่ LINE จะดึงไปแสดง</div>
        </div>
        <div class="flex items-center pt-6">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="auto_send_results" value="1" class="rounded border-gray-300" <?= $autoSendResults ? 'checked' : '' ?>>
                เปิดส่งผลหวยอัตโนมัติเป็นรูปเมื่อมีผลใหม่
            </label>
        </div>
        <div class="lg:col-span-2">
            <button type="submit" class="bg-[#2e7d32] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#1b5e20] transition">
                <i class="fas fa-save mr-1"></i> บันทึก LINE Settings
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
                <select name="group_id" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" <?= empty($groups) ? 'disabled' : '' ?>>
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
            <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition disabled:opacity-50" <?= empty($groups) ? 'disabled' : '' ?>>
                <i class="fab fa-line mr-1"></i> ส่งข้อความทดสอบ
            </button>
        </form>
    </div>
</div>

<div class="mt-4 bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">ผลหวยที่ออกแล้ว</div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">หวย</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">หมวด</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">งวด</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">3 ตัวบน</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">2 ตัวบน</th>
                    <th class="px-3 py-2 text-center text-xs text-gray-500">2 ตัวล่าง</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-500">ส่งเข้ากลุ่ม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentResults as $resultRow): ?>
                <tr class="border-b hover:bg-gray-50 align-top">
                    <td class="px-3 py-3 font-medium text-gray-800"><?= htmlspecialchars($resultRow['lottery_name']) ?></td>
                    <td class="px-3 py-3 text-gray-500"><?= htmlspecialchars($resultRow['category_name']) ?></td>
                    <td class="px-3 py-3 text-gray-500"><?= htmlspecialchars($resultRow['draw_date']) ?></td>
                    <td class="px-3 py-3 text-center font-bold text-green-700"><?= htmlspecialchars($resultRow['three_top'] ?: '-') ?></td>
                    <td class="px-3 py-3 text-center font-bold text-blue-600"><?= htmlspecialchars($resultRow['two_top'] ?: '-') ?></td>
                    <td class="px-3 py-3 text-center font-bold text-cyan-600"><?= htmlspecialchars($resultRow['two_bot'] ?: '-') ?></td>
                    <td class="px-3 py-3 min-w-[280px]">
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="form_action" value="send_result_image">
                            <input type="hidden" name="lottery_type_id" value="<?= (int) $resultRow['lottery_type_id'] ?>">
                            <input type="hidden" name="draw_date" value="<?= htmlspecialchars($resultRow['draw_date']) ?>">
                            <select name="group_id" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" <?= empty($groups) ? 'disabled' : '' ?>>
                                <?php foreach ($groups as $group): ?>
                                <option value="<?= htmlspecialchars($group['group_id']) ?>">
                                    <?= htmlspecialchars(($group['group_name'] ?: 'group') . ' - ' . $group['group_id']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full bg-[#1565c0] text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-[#0d47a1] transition disabled:opacity-50" <?= empty($groups) ? 'disabled' : '' ?>>
                                <i class="fas fa-image mr-1"></i> ส่งรูปผลหวยนี้
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentResults)): ?>
                <tr>
                    <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-400">ยังไม่พบผลหวยในระบบ</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
