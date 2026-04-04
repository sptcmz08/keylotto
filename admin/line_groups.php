<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../line/common.php';
requireLogin();

ensureLineTables($pdo);

$adminPage = 'line_groups';
$adminTitle = 'LINE Groups';

function lineGroupsRedirectWithFlash(string $type, string $message, string $tab = 'settings'): void
{
    $_SESSION['line_groups_flash'] = ['type' => $type, 'message' => $message];
    $allowedTabs = ['settings', 'shared-templates', 'send-image', 'auto-text', 'groups'];
    if (!in_array($tab, $allowedTabs, true)) {
        $tab = 'settings';
    }
    header('Location: line_groups.php?tab=' . rawurlencode($tab));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'save_credentials') {
        $channelSecret = trim($_POST['channel_secret'] ?? '');
        $channelAccessToken = trim($_POST['channel_access_token'] ?? '');
        $publicBaseUrl = trim($_POST['public_base_url'] ?? '');
        $autoSendResults = isset($_POST['auto_send_results']) ? '1' : '0';
        $autoSendTexts = isset($_POST['auto_send_texts']) ? '1' : '0';

        if ($channelSecret === '' || $channelAccessToken === '') {
            lineGroupsRedirectWithFlash('error', 'กรุณากรอก Channel secret และ Channel access token ให้ครบ', 'settings');
        }

        lineSetSetting($pdo, 'channel_secret', $channelSecret);
        lineSetSetting($pdo, 'channel_access_token', $channelAccessToken);
        lineSetSetting($pdo, 'public_base_url', $publicBaseUrl !== '' ? $publicBaseUrl : 'https://member.imzshop97.com');
        lineSetSetting($pdo, 'auto_send_results', $autoSendResults);
        lineSetSetting($pdo, 'auto_send_texts', $autoSendTexts);
        lineGroupsRedirectWithFlash('success', 'บันทึก LINE settings สำเร็จ', 'settings');
    }

    if ($action === 'save_scheduled_messages') {
        $messages = $_POST['scheduled_messages'] ?? [];
        lineSetScheduledTextMessages($pdo, is_array($messages) ? $messages : []);
        $savedScheduledMessages = lineGetScheduledTextMessages($pdo);
        $hasEnabledScheduledMessage = false;
        foreach ($savedScheduledMessages as $scheduledMessage) {
            if (!empty($scheduledMessage['enabled']) && trim((string) ($scheduledMessage['time'] ?? '')) !== '' && trim((string) ($scheduledMessage['message'] ?? '')) !== '') {
                $hasEnabledScheduledMessage = true;
                break;
            }
        }

        if ($hasEnabledScheduledMessage && !lineAutoSendTextsEnabled($pdo)) {
            lineSetSetting($pdo, 'auto_send_texts', '1');
            lineGroupsRedirectWithFlash('success', 'บันทึกข้อความอัตโนมัติสำเร็จ และเปิดส่งข้อความอัตโนมัติตามเวลาให้แล้ว', 'auto-text');
        }

        lineGroupsRedirectWithFlash('success', 'บันทึกข้อความอัตโนมัติตามช่วงวันและเวลาสำเร็จ', 'auto-text');
    }

    if ($action === 'send_test') {
        $groupId = trim($_POST['group_id'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($groupId === '' || $message === '') {
            lineGroupsRedirectWithFlash('error', 'กรุณาเลือกกลุ่มและใส่ข้อความทดสอบ', 'auto-text');
        }
        if (!lineConfigReady($pdo)) {
            lineGroupsRedirectWithFlash('error', 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server', 'auto-text');
        }

        $result = linePushTextMessage($pdo, $groupId, $message);
        lineLogPushResult($pdo, $groupId, $message, $result);
        if (!empty($result['ok'])) {
            lineGroupsRedirectWithFlash('success', 'ส่งข้อความทดสอบไป LINE สำเร็จ', 'auto-text');
        }
        lineGroupsRedirectWithFlash('error', 'ส่งข้อความไม่สำเร็จ (HTTP ' . ($result['status'] ?: 0) . ')', 'auto-text');
    }

    if ($action === 'send_scheduled_message_now') {
        $message = trim($_POST['scheduled_message_now'] ?? '');
        if ($message === '') {
            lineGroupsRedirectWithFlash('error', 'กรุณาใส่ข้อความก่อนกดส่งทันที', 'auto-text');
        }

        $result = linePushTextToActiveGroups($pdo, $message);
        if (!empty($result['sent'])) {
            lineGroupsRedirectWithFlash('success', 'ส่งข้อความทันทีสำเร็จไป ' . (int) $result['sent'] . ' กลุ่ม', 'auto-text');
        }

        if (($result['reason'] ?? '') === 'config_not_ready') {
            lineGroupsRedirectWithFlash('error', 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server', 'auto-text');
        }
        if (($result['reason'] ?? '') === 'no_groups') {
            lineGroupsRedirectWithFlash('error', 'ยังไม่มีกลุ่ม LINE ที่ active สำหรับส่งข้อความ', 'auto-text');
        }

        lineGroupsRedirectWithFlash('error', 'ส่งข้อความทันทีไม่สำเร็จ', 'auto-text');
    }

    if ($action === 'send_result_image') {
        $groupId = trim($_POST['group_id'] ?? '');
        $lotteryTypeId = (int) ($_POST['lottery_type_id'] ?? 0);
        $drawDate = trim($_POST['draw_date'] ?? '');
        if ($groupId === '' || $lotteryTypeId <= 0 || $drawDate === '') {
            lineGroupsRedirectWithFlash('error', 'ข้อมูลสำหรับส่งรูปผลหวยไม่ครบ', 'send-image');
        }

        $result = linePushResultImageToGroup($pdo, $groupId, $lotteryTypeId, $drawDate);
        if (!empty($result['ok'])) {
            $renderer = !empty($result['renderer']) ? ' (' . $result['renderer'] . ')' : '';
            lineGroupsRedirectWithFlash('success', 'ส่งรูปผลหวยไป LINE สำเร็จ' . $renderer, 'send-image');
        }

        $reason = $result['reason'] ?? '';
        $detail = trim((string) ($result['detail'] ?? ''));
        if ($reason === 'image_generation_failed' && $detail !== '') {
            lineGroupsRedirectWithFlash('error', 'Image generation failed - ' . $detail, 'send-image');
        }
        if ($reason === 'image_generation_failed') {
            lineGroupsRedirectWithFlash('error', 'สร้างรูปผลหวยไม่สำเร็จ กรุณาตรวจ Node/Puppeteer หรือ GD บน server', 'send-image');
        }
        if ($reason === 'result_not_found') {
            lineGroupsRedirectWithFlash('error', 'ไม่พบผลหวยรายการนี้ในฐานข้อมูล', 'send-image');
        }
        if ($reason === 'config_not_ready') {
            lineGroupsRedirectWithFlash('error', 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server', 'send-image');
        }
        lineGroupsRedirectWithFlash('error', 'ส่งรูปผลหวยไม่สำเร็จ (HTTP ' . ($result['status'] ?: 0) . ')', 'send-image');
    }

    if ($action === 'upload_shared_template') {
        $groupKey = trim((string) ($_POST['shared_group_key'] ?? ''));
        $upload = lineSaveSharedTemplateUpload($groupKey, $_FILES['template_image'] ?? []);
        lineGroupsRedirectWithFlash($upload['ok'] ? 'success' : 'error', $upload['message'], 'shared-templates');
    }

    if ($action === 'delete_shared_template') {
        $groupKey = trim((string) ($_POST['shared_group_key'] ?? ''));
        if ($groupKey === '') {
            lineGroupsRedirectWithFlash('error', 'Template group is invalid', 'shared-templates');
        }
        if (lineDeleteSharedTemplateImage($groupKey)) {
            lineGroupsRedirectWithFlash('success', 'Shared template image removed', 'shared-templates');
        }
        lineGroupsRedirectWithFlash('error', 'Shared template image was not found', 'shared-templates');
    }
}

$flash = $_SESSION['line_groups_flash'] ?? null;
unset($_SESSION['line_groups_flash']);
$msg = is_array($flash) ? (string) ($flash['message'] ?? '') : '';
$msgType = is_array($flash) ? (string) ($flash['type'] ?? 'success') : 'success';

$groups = $pdo->query("SELECT * FROM line_groups ORDER BY is_active DESC, last_seen_at DESC, id DESC")->fetchAll();
$recentLogs = $pdo->query("SELECT * FROM line_message_logs ORDER BY id DESC LIMIT 20")->fetchAll();
$recentResults = $pdo->query("
    SELECT r.id, r.lottery_type_id, r.draw_date, r.three_top, r.two_top, r.two_bot, r.updated_at,
           lt.name AS lottery_name, lc.name AS category_name
    FROM results r
    JOIN lottery_types lt ON r.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    ORDER BY r.draw_date DESC, r.updated_at DESC, r.id DESC
    LIMIT 30
")->fetchAll();

$sharedTemplateGroups = lineSharedTemplateGroups();
$sharedTemplateUrls = [];
foreach ($sharedTemplateGroups as $groupKey => $groupLabel) {
    $sharedTemplateUrls[$groupKey] = lineSharedTemplateImageUrl($pdo, $groupKey);
}

$savedChannelSecret = lineResolvedChannelSecret($pdo);
$savedChannelAccessToken = lineResolvedChannelAccessToken($pdo);
$savedPublicBaseUrl = lineResolvedPublicBaseUrl($pdo);
$autoSendResults = lineAutoSendEnabled($pdo);
$autoSendTexts = lineAutoSendTextsEnabled($pdo);
$scheduledMessages = lineGetScheduledTextMessages($pdo);
$scheduledDiagnostics = lineDiagnoseScheduledTextMessages($pdo);
$scheduledReasonLabels = [
    'due_now' => 'ถึงเวลาส่งตอนนี้',
    'already_sent' => 'ส่งแล้วในรอบวันนี้',
    'not_due_yet' => 'ยังไม่ถึงเวลา',
    'outside_grace_window' => 'เลยช่วงเวลาส่งแล้ว',
    'weekday_mismatch' => 'ไม่อยู่ในช่วงวัน',
    'date_mismatch' => 'ไม่ตรงวันที่กำหนด',
    'time_empty' => 'ยังไม่ได้ตั้งเวลา',
    'message_empty' => 'ยังไม่ได้ใส่ข้อความ',
    'disabled' => 'ปิดใช้งานอยู่',
    'ready' => 'พร้อมใช้งาน',
];
if (empty($scheduledMessages)) {
    $scheduledMessages = [[
        'id' => lineGenerateScheduledMessageId(),
        'day_start' => '',
        'day_end' => '',
        'time' => '',
        'message' => '',
        'enabled' => true,
    ]];
}
$activeGroupsCount = count(array_filter($groups, static fn($group) => !empty($group['is_active'])));
$weekdayOptions = [
    '0' => 'อาทิตย์',
    '1' => 'จันทร์',
    '2' => 'อังคาร',
    '3' => 'พุธ',
    '4' => 'พฤหัสบดี',
    '5' => 'ศุกร์',
    '6' => 'เสาร์',
];

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="mb-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-lg font-bold text-gray-800"><i class="fab fa-line text-green-600 mr-2"></i>LINE Groups</h1>
            <p class="text-xs text-gray-400">จัดการการส่งรูปผลหวย ข้อความอัตโนมัติตามเวลา และกลุ่ม LINE ที่เชื่อมผ่าน webhook แล้ว <?= count($groups) ?> กลุ่ม</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-line-tab="settings" class="line-tab-btn bg-[#1b5e20] text-white px-4 py-2 rounded-full text-sm font-medium">ตั้งค่า</button>
            <button type="button" data-line-tab="shared-templates" class="line-tab-btn bg-white text-gray-700 border border-gray-200 px-4 py-2 rounded-full text-sm font-medium">Shared Templates</button>
            <button type="button" data-line-tab="send-image" class="line-tab-btn bg-white text-gray-700 border border-gray-200 px-4 py-2 rounded-full text-sm font-medium">ส่งรูปภาพ</button>
            <button type="button" data-line-tab="auto-text" class="line-tab-btn bg-white text-gray-700 border border-gray-200 px-4 py-2 rounded-full text-sm font-medium">ส่งข้อความ Auto</button>
            <button type="button" data-line-tab="groups" class="line-tab-btn bg-white text-gray-700 border border-gray-200 px-4 py-2 rounded-full text-sm font-medium">กลุ่มและประวัติ</button>
        </div>
    </div>
</div>

<?php if (!lineConfigReady($pdo)): ?>
<div class="mb-4 p-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 text-sm">
    <div class="font-bold mb-1">ยังไม่ได้ตั้งค่า secret / token บน server</div>
    <div>กรอกค่า Channel secret และ Channel access token ด้านล่าง แล้วกดบันทึกได้เลย</div>
</div>
<?php endif; ?>

<section data-line-panel="settings" class="line-panel">
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
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
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                <div class="font-medium text-gray-700 text-sm">ตัวเลือกการส่งอัตโนมัติ</div>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="auto_send_results" value="1" class="mt-1 rounded border-gray-300" <?= $autoSendResults ? 'checked' : '' ?>>
                    <span>เปิดส่งผลหวยอัตโนมัติเป็นรูปเมื่อมีผลใหม่</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="auto_send_texts" value="1" class="mt-1 rounded border-gray-300" <?= $autoSendTexts ? 'checked' : '' ?>>
                    <span>เปิดส่งข้อความอัตโนมัติตามเวลาที่ตั้งไว้</span>
                </label>
            </div>
            <div class="lg:col-span-2">
                <button type="submit" class="bg-[#2e7d32] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#1b5e20] transition">
                    <i class="fas fa-save mr-1"></i> บันทึก LINE Settings
                </button>
            </div>
        </form>
    </div>
</section>

<section data-line-panel="shared-templates" class="line-panel hidden">
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">Shared Template Groups</div>
        <div class="px-4 py-3 text-xs text-gray-500 border-b bg-gray-50/60">
            ใช้แค่ template รวมรายกลุ่มประเทศพอ ระบบจะ fallback มาใช้ให้อัตโนมัติเมื่อไม่มี template เฉพาะรายหวย
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">กลุ่ม</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">Current template</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">Upload / Remove</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sharedTemplateGroups as $groupKey => $groupLabel): ?>
                    <?php $sharedTemplateUrl = $sharedTemplateUrls[$groupKey] ?? null; ?>
                    <tr class="border-b hover:bg-gray-50 align-top">
                        <td class="px-3 py-3 font-medium text-gray-800"><?= htmlspecialchars($groupLabel) ?></td>
                        <td class="px-3 py-3">
                            <?php if ($sharedTemplateUrl): ?>
                            <a href="<?= htmlspecialchars($sharedTemplateUrl) ?>" target="_blank" class="inline-block">
                                <img src="<?= htmlspecialchars($sharedTemplateUrl) ?>" alt="Shared template preview" class="w-44 h-28 object-cover rounded-lg border border-gray-200 shadow-sm">
                            </a>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">ยังไม่มี shared template</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 min-w-[340px]">
                            <form method="POST" enctype="multipart/form-data" class="space-y-2">
                                <input type="hidden" name="form_action" value="upload_shared_template">
                                <input type="hidden" name="shared_group_key" value="<?= htmlspecialchars($groupKey) ?>">
                                <input type="file" name="template_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="block w-full text-xs text-gray-500 border rounded-lg px-3 py-2 bg-white">
                                <button type="submit" class="bg-[#7b1fa2] text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-[#6a1b9a] transition">
                                    <i class="fas fa-layer-group mr-1"></i> Upload shared template
                                </button>
                            </form>
                            <?php if ($sharedTemplateUrl): ?>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="form_action" value="delete_shared_template">
                                <input type="hidden" name="shared_group_key" value="<?= htmlspecialchars($groupKey) ?>">
                                <button type="submit" class="bg-red-50 text-red-700 border border-red-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-100 transition">
                                    <i class="fas fa-trash-alt mr-1"></i> Remove shared template
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section data-line-panel="send-image" class="line-panel hidden">
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
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
                            <a href="line_preview.php?lottery_type_id=<?= (int) $resultRow['lottery_type_id'] ?>&draw_date=<?= urlencode((string) $resultRow['draw_date']) ?>" target="_blank" class="mt-2 inline-flex items-center justify-center w-full bg-white text-[#6a1b9a] border border-[#d1b3e5] px-3 py-2 rounded-lg text-sm font-medium hover:bg-[#faf5ff] transition">
                                <i class="fas fa-eye mr-1"></i> Preview image
                            </a>
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
</section>

<section data-line-panel="auto-text" class="line-panel hidden">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
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
                    <textarea name="message" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" placeholder="ทดสอบส่งข้อความจากระบบ LINE">ทดสอบส่งข้อความจากระบบ LINE</textarea>
                </div>
                <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition disabled:opacity-50" <?= empty($groups) ? 'disabled' : '' ?>>
                    <i class="fab fa-line mr-1"></i> ส่งข้อความทดสอบ
                </button>
            </form>
        </div>

        <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">ข้อความอัตโนมัติตามช่วงวันและเวลา</div>
            <div class="px-4 py-3 text-xs text-gray-500 border-b bg-gray-50/60">
                เลือกวันเริ่มและวันสิ้นสุดได้ เช่น จันทร์-ศุกร์ หรือ อาทิตย์-จันทร์ ถ้าเว้นทั้งคู่ไว้ ระบบจะส่งทุกวันตามเวลาที่ตั้งไว้
            </div>
            <div class="px-4 py-4 border-b bg-white">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs text-gray-500 mb-1">เวลา server</div>
                        <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($scheduledDiagnostics['server_date']) ?> <?= htmlspecialchars($scheduledDiagnostics['server_time']) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($scheduledDiagnostics['server_weekday']) ?></div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs text-gray-500 mb-1">สถานะ Auto Text</div>
                        <div class="text-sm font-semibold <?= !empty($scheduledDiagnostics['auto_send_texts_enabled']) ? 'text-green-700' : 'text-red-600' ?>">
                            <?= !empty($scheduledDiagnostics['auto_send_texts_enabled']) ? 'เปิดใช้งานแล้ว' : 'ยังปิดอยู่' ?>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Config <?= !empty($scheduledDiagnostics['config_ready']) ? 'พร้อม' : 'ยังไม่พร้อม' ?> / กลุ่ม active <?= (int) $scheduledDiagnostics['active_groups'] ?></div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs text-gray-500 mb-1">ข้อความพร้อมส่ง</div>
                        <div class="text-sm font-semibold text-gray-800"><?= (int) $scheduledDiagnostics['due_messages'] ?> รายการ</div>
                        <div class="text-xs text-gray-500 mt-1">ข้อความที่ตั้งครบ <?= (int) $scheduledDiagnostics['ready_messages'] ?> / ทั้งหมด <?= (int) $scheduledDiagnostics['total_messages'] ?></div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs text-gray-500 mb-1">ช่วงเผื่อเวลา</div>
                        <div class="text-sm font-semibold text-gray-800"><?= (int) $scheduledDiagnostics['grace_minutes'] ?> นาที</div>
                        <div class="text-xs text-gray-500 mt-1">ถ้า cron มาช้า ระบบยังตามส่งในช่วงนี้</div>
                    </div>
                </div>
                <?php if (!empty($scheduledDiagnostics['reason_counts'])): ?>
                <div class="mt-4">
                    <div class="text-xs text-gray-500 mb-2">สรุปสถานะรายการที่ตั้งไว้ตอนนี้</div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($scheduledDiagnostics['reason_counts'] as $reasonKey => $reasonCount): ?>
                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700">
                            <?= htmlspecialchars($scheduledReasonLabels[$reasonKey] ?? $reasonKey) ?> <?= (int) $reasonCount ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($scheduledDiagnostics['items'])): ?>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 border-y">
                            <tr>
                                <th class="px-3 py-2 text-left text-gray-500">เวลา</th>
                                <th class="px-3 py-2 text-left text-gray-500">ช่วงวัน</th>
                                <th class="px-3 py-2 text-left text-gray-500">สถานะ</th>
                                <th class="px-3 py-2 text-left text-gray-500">ตัวอย่างข้อความ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduledDiagnostics['items'] as $diagnosticItem): ?>
                            <tr class="border-b">
                                <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars($diagnosticItem['time'] !== '' ? $diagnosticItem['time'] : '-') ?></td>
                                <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars($diagnosticItem['range']) ?></td>
                                <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars($scheduledReasonLabels[$diagnosticItem['reason']] ?? $diagnosticItem['reason']) ?></td>
                                <td class="px-3 py-2 text-gray-500"><?= htmlspecialchars($diagnosticItem['preview'] !== '' ? $diagnosticItem['preview'] : '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <form method="POST" class="p-4 space-y-4">
                <input type="hidden" name="form_action" value="save_scheduled_messages">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div id="scheduledMessagesCount" class="text-sm text-gray-600">ตอนนี้มี <?= count($scheduledMessages) ?> รายการ</div>
                    <button type="button" id="addScheduledMessageBtn" class="bg-[#1565c0] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0d47a1] transition">
                        <i class="fas fa-plus mr-1"></i> เพิ่มข้อความ
                    </button>
                </div>
                <div id="scheduledMessagesList" class="space-y-3">
                    <?php foreach ($scheduledMessages as $index => $scheduledMessage): ?>
                    <div class="scheduled-message-item rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                        <input type="hidden" name="scheduled_messages[<?= $index ?>][id]" value="<?= htmlspecialchars((string) $scheduledMessage['id']) ?>">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                            <div class="w-full lg:w-48">
                                <label class="text-xs text-gray-500 block mb-1">วันเริ่ม</label>
                                <select name="scheduled_messages[<?= $index ?>][day_start]" class="w-full border rounded-lg px-3 py-2 text-sm outline-none bg-white">
                                    <option value="">ทุกวัน</option>
                                    <?php foreach ($weekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                    <option value="<?= $weekdayValue ?>" <?= (string) ($scheduledMessage['day_start'] ?? '') === (string) $weekdayValue ? 'selected' : '' ?>><?= $weekdayLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="w-full lg:w-48">
                                <label class="text-xs text-gray-500 block mb-1">วันสิ้นสุด</label>
                                <select name="scheduled_messages[<?= $index ?>][day_end]" class="w-full border rounded-lg px-3 py-2 text-sm outline-none bg-white">
                                    <option value="">ทุกวัน</option>
                                    <?php foreach ($weekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                    <option value="<?= $weekdayValue ?>" <?= (string) ($scheduledMessage['day_end'] ?? '') === (string) $weekdayValue ? 'selected' : '' ?>><?= $weekdayLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="w-full lg:w-44">
                                <label class="text-xs text-gray-500 block mb-1">เวลาส่ง</label>
                                <input type="time" name="scheduled_messages[<?= $index ?>][time]" value="<?= htmlspecialchars((string) $scheduledMessage['time']) ?>" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                            </div>
                            <div class="flex items-center gap-2 pt-0 lg:pt-5">
                                <input type="hidden" name="scheduled_messages[<?= $index ?>][enabled]" value="0">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="scheduled_messages[<?= $index ?>][enabled]" value="1" class="rounded border-gray-300" <?= !empty($scheduledMessage['enabled']) ? 'checked' : '' ?>>
                                    <span>เปิดใช้</span>
                                </label>
                            </div>
                            <div class="lg:ml-auto pt-0 lg:pt-5">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="send-scheduled-now bg-blue-50 text-blue-700 border border-blue-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-100 transition">
                                        <i class="fas fa-paper-plane mr-1"></i> ส่งทันที
                                    </button>
                                    <button type="button" class="remove-scheduled-message bg-red-50 text-red-700 border border-red-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-100 transition">
                                        <i class="fas fa-trash-alt mr-1"></i> ลบรายการ
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">ข้อความ</label>
                            <textarea name="scheduled_messages[<?= $index ?>][message]" rows="5" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" placeholder="พิมพ์ข้อความที่ต้องการส่งอัตโนมัติ"><?= htmlspecialchars((string) $scheduledMessage['message']) ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t pt-4">
                    <button type="submit" class="bg-[#2e7d32] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#1b5e20] transition">
                        <i class="fas fa-save mr-1"></i> บันทึกข้อความตามช่วงวันและเวลา
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<section data-line-panel="groups" class="line-panel hidden">
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
            <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">สรุปการเชื่อมต่อ</div>
            <div class="p-4 space-y-3 text-sm">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="text-xs text-gray-500">จำนวนกลุ่มที่เชื่อมแล้ว</div>
                    <div class="mt-1 text-2xl font-bold text-gray-800"><?= number_format(count($groups)) ?></div>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 p-3">
                    <div class="text-xs text-green-700">กลุ่มที่ active</div>
                    <div class="mt-1 text-2xl font-bold text-green-700"><?= number_format($activeGroupsCount) ?></div>
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <div class="text-xs text-blue-700">ประวัติการส่งล่าสุด</div>
                    <div class="mt-1 text-sm text-blue-700"><?= !empty($recentLogs) ? htmlspecialchars((string) ($recentLogs[0]['created_at'] ?? '-')) : '-' ?></div>
                </div>
            </div>
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
                        <td class="px-3 py-2"><?= nl2br(htmlspecialchars((string) $log['message_text'])) ?></td>
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
</section>

<form method="POST" id="sendScheduledNowForm" class="hidden">
    <input type="hidden" name="form_action" value="send_scheduled_message_now">
    <input type="hidden" name="scheduled_message_now" id="scheduledMessageNowInput" value="">
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = Array.from(document.querySelectorAll('[data-line-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-line-panel]'));
    const allowedTabs = new Set(['settings', 'shared-templates', 'send-image', 'auto-text', 'groups']);
    const scheduledMessagesList = document.getElementById('scheduledMessagesList');
    const addScheduledMessageBtn = document.getElementById('addScheduledMessageBtn');
    const scheduledMessagesCount = document.getElementById('scheduledMessagesCount');
    const sendScheduledNowForm = document.getElementById('sendScheduledNowForm');
    const scheduledMessageNowInput = document.getElementById('scheduledMessageNowInput');
    let scheduledMessageIndex = <?= count($scheduledMessages) ?>;

    function activateTab(tabName) {
        const resolvedTab = allowedTabs.has(tabName) ? tabName : 'settings';
        buttons.forEach((button) => {
            const active = button.dataset.lineTab === resolvedTab;
            button.classList.toggle('bg-[#1b5e20]', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('border', !active);
            button.classList.toggle('border-gray-200', !active);
            button.classList.toggle('bg-white', !active);
            button.classList.toggle('text-gray-700', !active);
        });

        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.linePanel !== resolvedTab);
        });

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', resolvedTab);
            window.history.replaceState({}, '', url.toString());
            window.localStorage.setItem('lineGroupsActiveTab', resolvedTab);
        } catch (error) {
            // Ignore browser history/localStorage failures.
        }
    }

    function buildScheduledMessageItem(index) {
        const wrapper = document.createElement('div');
        const generatedId = 'msg_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
        wrapper.className = 'scheduled-message-item rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3';
        wrapper.innerHTML = `
            <input type="hidden" name="scheduled_messages[${index}][id]" value="${generatedId}">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="w-full lg:w-48">
                    <label class="text-xs text-gray-500 block mb-1">วันเริ่ม</label>
                    <select name="scheduled_messages[${index}][day_start]" class="w-full border rounded-lg px-3 py-2 text-sm outline-none bg-white">
                        <option value="">ทุกวัน</option>
                        <option value="0">อาทิตย์</option>
                        <option value="1">จันทร์</option>
                        <option value="2">อังคาร</option>
                        <option value="3">พุธ</option>
                        <option value="4">พฤหัสบดี</option>
                        <option value="5">ศุกร์</option>
                        <option value="6">เสาร์</option>
                    </select>
                </div>
                <div class="w-full lg:w-48">
                    <label class="text-xs text-gray-500 block mb-1">วันสิ้นสุด</label>
                    <select name="scheduled_messages[${index}][day_end]" class="w-full border rounded-lg px-3 py-2 text-sm outline-none bg-white">
                        <option value="">ทุกวัน</option>
                        <option value="0">อาทิตย์</option>
                        <option value="1">จันทร์</option>
                        <option value="2">อังคาร</option>
                        <option value="3">พุธ</option>
                        <option value="4">พฤหัสบดี</option>
                        <option value="5">ศุกร์</option>
                        <option value="6">เสาร์</option>
                    </select>
                </div>
                <div class="w-full lg:w-44">
                    <label class="text-xs text-gray-500 block mb-1">เวลาส่ง</label>
                    <input type="time" name="scheduled_messages[${index}][time]" value="" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                </div>
                <div class="flex items-center gap-2 pt-0 lg:pt-5">
                    <input type="hidden" name="scheduled_messages[${index}][enabled]" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="scheduled_messages[${index}][enabled]" value="1" class="rounded border-gray-300" checked>
                        <span>เปิดใช้</span>
                    </label>
                </div>
                <div class="lg:ml-auto pt-0 lg:pt-5">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="send-scheduled-now bg-blue-50 text-blue-700 border border-blue-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-100 transition">
                            <i class="fas fa-paper-plane mr-1"></i> ส่งทันที
                        </button>
                        <button type="button" class="remove-scheduled-message bg-red-50 text-red-700 border border-red-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-100 transition">
                            <i class="fas fa-trash-alt mr-1"></i> ลบรายการ
                        </button>
                    </div>
                </div>
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ข้อความ</label>
                <textarea name="scheduled_messages[${index}][message]" rows="5" class="w-full border rounded-lg px-3 py-2 text-sm outline-none" placeholder="พิมพ์ข้อความที่ต้องการส่งอัตโนมัติ"></textarea>
            </div>
        `;
        return wrapper;
    }

    function updateScheduledMessagesCount() {
        if (!scheduledMessagesCount || !scheduledMessagesList) {
            return;
        }

        const items = scheduledMessagesList.querySelectorAll('.scheduled-message-item').length;
        scheduledMessagesCount.textContent = `ตอนนี้มี ${items} รายการ`;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', function () {
            activateTab(button.dataset.lineTab);
        });
    });

    if (addScheduledMessageBtn && scheduledMessagesList) {
        addScheduledMessageBtn.addEventListener('click', function () {
            const newItem = buildScheduledMessageItem(scheduledMessageIndex);
            scheduledMessagesList.appendChild(newItem);
            scheduledMessageIndex += 1;
            updateScheduledMessagesCount();
            newItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            const textarea = newItem.querySelector('textarea');
            if (textarea) {
                textarea.focus();
            }
        });

        scheduledMessagesList.addEventListener('click', function (event) {
            const sendNowButton = event.target.closest('.send-scheduled-now');
            if (sendNowButton) {
                const item = sendNowButton.closest('.scheduled-message-item');
                const textarea = item ? item.querySelector('textarea') : null;
                const message = textarea ? textarea.value.trim() : '';
                if (message === '') {
                    window.alert('กรุณาใส่ข้อความก่อนกดส่งทันที');
                    return;
                }

                if (sendScheduledNowForm && scheduledMessageNowInput) {
                    scheduledMessageNowInput.value = message;
                    sendScheduledNowForm.submit();
                }
                return;
            }

            const removeButton = event.target.closest('.remove-scheduled-message');
            if (!removeButton) {
                return;
            }
            const item = removeButton.closest('.scheduled-message-item');
            if (!item) {
                return;
            }

            const items = scheduledMessagesList.querySelectorAll('.scheduled-message-item');
            if (items.length === 1) {
                const selects = item.querySelectorAll('select');
                const timeInput = item.querySelector('input[type=\"time\"]');
                const textarea = item.querySelector('textarea');
                const checkbox = item.querySelector('input[type=\"checkbox\"]');
                selects.forEach((select) => {
                    select.value = '';
                });
                if (timeInput) timeInput.value = '';
                if (textarea) textarea.value = '';
                if (checkbox) checkbox.checked = true;
                return;
            }

            item.remove();
            updateScheduledMessagesCount();
        });
    }

    updateScheduledMessagesCount();

    let initialTab = 'settings';
    try {
        const url = new URL(window.location.href);
        const tabFromUrl = url.searchParams.get('tab');
        const tabFromStorage = window.localStorage.getItem('lineGroupsActiveTab');
        if (tabFromUrl && allowedTabs.has(tabFromUrl)) {
            initialTab = tabFromUrl;
        } else if (tabFromStorage && allowedTabs.has(tabFromStorage)) {
            initialTab = tabFromStorage;
        }
    } catch (error) {
        // Ignore browser URL/localStorage failures and fall back to default tab.
    }

    activateTab(initialTab);
});
</script>

<?php require_once 'includes/footer.php'; ?>
