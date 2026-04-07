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
    $allowedTabs = ['settings', 'shared-templates', 'bet-close-templates', 'send-image', 'auto-text', 'auto-image', 'bet-close', 'groups'];
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

    if ($action === 'save_bet_close_settings') {
        lineSetSetting($pdo, 'auto_send_bet_close', isset($_POST['auto_send_bet_close']) ? '1' : '0');
        lineGroupsRedirectWithFlash('success', 'บันทึกการตั้งค่าปิดรับแทงสำเร็จ', 'bet-close');
    }

    if ($action === 'save_bet_close_time') {
        $lotteryTypeId = (int) ($_POST['lottery_type_id'] ?? 0);
        $closeTime = lineNormalizeScheduledMessageTime((string) ($_POST['close_time'] ?? ''));

        if ($lotteryTypeId <= 0) {
            lineGroupsRedirectWithFlash('error', 'ไม่พบหวยที่ต้องการตั้งเวลาปิดรับ', 'bet-close');
        }

        $stmt = $pdo->prepare("SELECT id FROM lottery_types WHERE id = ? LIMIT 1");
        $stmt->execute([$lotteryTypeId]);
        if (!$stmt->fetch()) {
            lineGroupsRedirectWithFlash('error', 'ไม่พบหวยที่ต้องการตั้งเวลาปิดรับ', 'bet-close');
        }

        $update = $pdo->prepare("UPDATE lottery_types SET close_time = ? WHERE id = ?");
        $update->execute([$closeTime !== '' ? $closeTime : null, $lotteryTypeId]);

        if ($closeTime === '') {
            lineGroupsRedirectWithFlash('success', 'ล้างเวลาปิดรับสำเร็จ', 'bet-close');
        }

        lineGroupsRedirectWithFlash('success', 'บันทึกเวลาปิดรับสำเร็จ', 'bet-close');
    }

    if ($action === 'save_group_delivery_settings') {
        $groupId = trim((string) ($_POST['group_id'] ?? ''));
        if ($groupId === '') {
            lineGroupsRedirectWithFlash('error', 'ไม่พบกลุ่ม LINE ที่ต้องการแก้ไข', 'groups');
        }

        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $allowTexts = isset($_POST['allow_texts']) ? 1 : 0;
        $allowImages = isset($_POST['allow_images']) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE line_groups
            SET is_active = ?, allow_texts = ?, allow_images = ?
            WHERE group_id = ?
        ");
        $stmt->execute([$isActive, $allowTexts, $allowImages, $groupId]);

        lineGroupsRedirectWithFlash('success', 'บันทึกการตั้งค่าการส่งของกลุ่มสำเร็จ', 'groups');
    }

    if ($action === 'refresh_group_names') {
        if (!lineConfigReady($pdo)) {
            lineGroupsRedirectWithFlash('error', 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server', 'groups');
        }

        $result = lineRefreshAllGroupNames($pdo);
        if (($result['total'] ?? 0) <= 0) {
            lineGroupsRedirectWithFlash('error', 'ยังไม่มีกลุ่ม LINE ให้รีเฟรชชื่อ', 'groups');
        }

        lineGroupsRedirectWithFlash(
            'success',
            'รีเฟรชชื่อกลุ่มสำเร็จ ' . (int) ($result['updated'] ?? 0) . ' กลุ่ม' . ((int) ($result['failed'] ?? 0) > 0 ? ' / ดึงชื่อไม่ได้ ' . (int) ($result['failed'] ?? 0) . ' กลุ่ม' : ''),
            'groups'
        );
    }

    if ($action === 'save_scheduled_messages') {
        $messages = $_POST['scheduled_messages'] ?? [];
        lineSetScheduledTextMessages($pdo, is_array($messages) ? $messages : []);
        lineGroupsRedirectWithFlash('success', 'à¸šà¸±à¸™à¸—à¸¶à¸à¸‚à¹‰à¸­à¸„à¸§à¸²à¸¡à¸­à¸±à¸•à¹‚à¸™à¸¡à¸±à¸•à¸´à¸•à¸²à¸¡à¸Šà¹ˆà¸§à¸‡à¸§à¸±à¸™à¹à¸¥à¸°à¹€à¸§à¸¥à¸²à¸ªà¸³à¹€à¸£à¹‡à¸ˆ', 'auto-text');
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

    if ($action === 'save_scheduled_images') {
        $images = $_POST['scheduled_images'] ?? [];

        try {
            lineSetScheduledImageMessages($pdo, is_array($images) ? $images : [], $_FILES['scheduled_image_files'] ?? []);
            lineGroupsRedirectWithFlash('success', 'บันทึกรายการส่งรูปภาพอัตโนมัติสำเร็จ', 'auto-image');
        } catch (RuntimeException $e) {
            lineGroupsRedirectWithFlash('error', $e->getMessage(), 'auto-image');
        }
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

    if ($action === 'send_scheduled_image_now') {
        $messageId = trim((string) ($_POST['scheduled_image_now_id'] ?? ''));
        $scheduledImages = lineGetScheduledImageMessages($pdo);
        $selectedImage = null;
        foreach ($scheduledImages as $scheduledImage) {
            if ((string) ($scheduledImage['id'] ?? '') === $messageId) {
                $selectedImage = $scheduledImage;
                break;
            }
        }

        if (!$selectedImage) {
            lineGroupsRedirectWithFlash('error', 'ไม่พบรายการรูปภาพที่เลือก', 'auto-image');
        }

        $imageUrls = lineScheduledImageUrls($pdo, (array) ($selectedImage['images'] ?? []));
        if (empty($imageUrls)) {
            lineGroupsRedirectWithFlash('error', 'กรุณาอัปโหลดรูปภาพก่อนกดส่งทันที', 'auto-image');
        }

        $result = linePushImageToActiveGroups($pdo, $imageUrls, '[manual scheduled image]');
        if (!empty($result['sent'])) {
            lineGroupsRedirectWithFlash('success', 'ส่งรูปภาพทันทีสำเร็จไป ' . (int) $result['sent'] . ' กลุ่ม', 'auto-image');
        }

        if (($result['reason'] ?? '') === 'config_not_ready') {
            lineGroupsRedirectWithFlash('error', 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server', 'auto-image');
        }
        if (($result['reason'] ?? '') === 'no_groups') {
            lineGroupsRedirectWithFlash('error', 'ยังไม่มีกลุ่ม LINE ที่ active สำหรับส่งรูปภาพ', 'auto-image');
        }

        lineGroupsRedirectWithFlash('error', 'ส่งรูปภาพทันทีไม่สำเร็จ', 'auto-image');
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

    if ($action === 'send_result_image_all_groups') {
        $lotteryTypeId = (int) ($_POST['lottery_type_id'] ?? 0);
        $drawDate = trim((string) ($_POST['draw_date'] ?? ''));
        if ($lotteryTypeId <= 0 || $drawDate === '') {
            lineGroupsRedirectWithFlash('error', 'à¸‚à¹‰à¸­à¸¡à¸¹à¸¥à¸ªà¸³à¸«à¸£à¸±à¸šà¸ªà¹ˆà¸‡à¸£à¸¹à¸›à¸œà¸¥à¸«à¸§à¸¢à¹„à¸¡à¹ˆà¸„à¸£à¸š', 'send-image');
        }

        $result = linePushResultImageToActiveGroups($pdo, $lotteryTypeId, $drawDate);
        if (!empty($result['sent'])) {
            lineGroupsRedirectWithFlash('success', 'à¸ªà¹ˆà¸‡à¸£à¸¹à¸›à¸œà¸¥à¸«à¸§à¸¢à¹„à¸›à¸—à¸¸à¸à¸à¸¥à¸¸à¹ˆà¸¡à¸ªà¸³à¹€à¸£à¹‡à¸ˆà¹„à¸› ' . (int) $result['sent'] . ' à¸à¸¥à¸¸à¹ˆà¸¡', 'send-image');
        }

        if (($result['reason'] ?? '') === 'config_not_ready') {
            lineGroupsRedirectWithFlash('error', 'à¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¹„à¸”à¹‰à¸•à¸±à¹‰à¸‡à¸„à¹ˆà¸² LINE channel secret à¹à¸¥à¸° access token à¸šà¸™ server', 'send-image');
        }
        if (($result['reason'] ?? '') === 'no_groups') {
            lineGroupsRedirectWithFlash('error', 'à¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¸¡à¸µà¸à¸¥à¸¸à¹ˆà¸¡ LINE à¸—à¸µà¹ˆ active à¹à¸¥à¸°à¹€à¸›à¸´à¸”à¸£à¸±à¸šà¸£à¸¹à¸›à¸ à¸²à¸ž', 'send-image');
        }
        if (($result['reason'] ?? '') === 'result_not_found') {
            lineGroupsRedirectWithFlash('error', 'à¹„à¸¡à¹ˆà¸žà¸šà¸œà¸¥à¸«à¸§à¸¢à¸£à¸²à¸¢à¸à¸²à¸£à¸™à¸µà¹‰à¹ƒà¸™à¸à¸²à¸™à¸‚à¹‰à¸­à¸¡à¸¹à¸¥', 'send-image');
        }
        if (($result['reason'] ?? '') === 'image_generation_failed') {
            lineGroupsRedirectWithFlash('error', 'à¸ªà¸£à¹‰à¸²à¸‡à¸£à¸¹à¸›à¸œà¸¥à¸«à¸§à¸¢à¹„à¸¡à¹ˆà¸ªà¸³à¹€à¸£à¹‡à¸ˆ: ' . (string) ($result['detail'] ?? 'unknown'), 'send-image');
        }

        $status = (int) ($result['last_error_status'] ?? 0);
        lineGroupsRedirectWithFlash('error', 'à¸ªà¹ˆà¸‡à¸£à¸¹à¸›à¸œà¸¥à¸«à¸§à¸¢à¹„à¸›à¸—à¸¸à¸à¸à¸¥à¸¸à¹ˆà¸¡à¹„à¸¡à¹ˆà¸ªà¸³à¹€à¸£à¹‡à¸ˆ' . ($status > 0 ? ' (HTTP ' . $status . ')' : ''), 'send-image');
    }

    if ($action === 'send_bet_close_now') {
        $lotteryTypeId = (int) ($_POST['lottery_type_id'] ?? 0);
        if ($lotteryTypeId <= 0) {
            lineGroupsRedirectWithFlash('error', 'ไม่พบหวยที่ต้องการทดสอบส่งปิดรับแทง', 'bet-close');
        }

        $result = linePushBetCloseImageNow($pdo, $lotteryTypeId);
        if (!empty($result['sent'])) {
            lineGroupsRedirectWithFlash('success', 'ส่งภาพปิดรับแทงสำเร็จไป ' . (int) $result['sent'] . ' กลุ่ม', 'bet-close');
        }
        if (($result['reason'] ?? '') === 'config_not_ready') {
            lineGroupsRedirectWithFlash('error', 'ยังไม่ได้ตั้งค่า LINE channel secret และ access token บน server', 'bet-close');
        }
        if (($result['reason'] ?? '') === 'no_groups') {
            lineGroupsRedirectWithFlash('error', 'ยังไม่มีกลุ่ม LINE ที่เปิดรับรูปภาพ', 'bet-close');
        }
        if (($result['reason'] ?? '') === 'image_generation_failed') {
            lineGroupsRedirectWithFlash('error', 'สร้างภาพปิดรับแทงไม่สำเร็จ: ' . (string) ($result['detail'] ?? 'unknown'), 'bet-close');
        }
        if (($result['reason'] ?? '') === 'lottery_not_found') {
            lineGroupsRedirectWithFlash('error', 'ไม่พบข้อมูลหวยหรือหวยถูกปิดใช้งานอยู่', 'bet-close');
        }
        lineGroupsRedirectWithFlash('error', 'ส่งภาพปิดรับแทงไม่สำเร็จ', 'bet-close');
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

    if ($action === 'upload_bet_close_shared_template') {
        $groupKey = trim((string) ($_POST['shared_group_key'] ?? ''));
        $upload = lineSaveBetCloseSharedTemplateUpload($groupKey, $_FILES['template_image'] ?? []);
        lineGroupsRedirectWithFlash($upload['ok'] ? 'success' : 'error', $upload['message'], 'bet-close-templates');
    }

    if ($action === 'delete_bet_close_shared_template') {
        $groupKey = trim((string) ($_POST['shared_group_key'] ?? ''));
        if ($groupKey === '') {
            lineGroupsRedirectWithFlash('error', 'Template group is invalid', 'bet-close-templates');
        }
        if (lineDeleteBetCloseSharedTemplateImage($groupKey)) {
            lineGroupsRedirectWithFlash('success', 'Bet-close shared template image removed', 'bet-close-templates');
        }
        lineGroupsRedirectWithFlash('error', 'Bet-close shared template image was not found', 'bet-close-templates');
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
$betCloseSharedTemplateUrls = [];
foreach ($sharedTemplateGroups as $groupKey => $groupLabel) {
    $sharedTemplateUrls[$groupKey] = lineSharedTemplateImageUrl($pdo, $groupKey);
    $betCloseSharedTemplateUrls[$groupKey] = lineBetCloseSharedTemplateImageUrl($pdo, $groupKey);
}

$savedChannelSecret = lineResolvedChannelSecret($pdo);
$savedChannelAccessToken = lineResolvedChannelAccessToken($pdo);
$savedPublicBaseUrl = lineResolvedPublicBaseUrl($pdo);
$autoSendResults = lineAutoSendEnabled($pdo);
$autoSendTexts = lineAutoSendTextsEnabled($pdo);
$autoSendBetClose = lineAutoSendBetCloseEnabled($pdo);
$scheduledMessages = lineGetScheduledTextMessages($pdo);
$scheduledImages = lineGetScheduledImageMessages($pdo);
$scheduledDiagnostics = lineDiagnoseScheduledTextMessages($pdo);
$scheduledImageDiagnostics = lineDiagnoseScheduledImages($pdo);
$betCloseDiagnostics = lineDiagnoseBetCloseNotifications($pdo);
$betCloseLotteries = lineFetchBetCloseLotteries($pdo);
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
$scheduledImageReasonLabels = [
    'due_now' => 'ถึงเวลาส่งตอนนี้',
    'already_sent' => 'ส่งแล้วในรอบวันนี้',
    'not_due_yet' => 'ยังไม่ถึงเวลา',
    'outside_grace_window' => 'เลยช่วงเวลาส่งแล้ว',
    'weekday_mismatch' => 'ไม่อยู่ในช่วงวัน',
    'time_empty' => 'ยังไม่ได้ตั้งเวลา',
    'image_missing' => 'ยังไม่ได้อัปโหลดรูป',
    'disabled' => 'ปิดใช้งานอยู่',
    'ready' => 'พร้อมใช้งาน',
];
$betCloseReasonLabels = [
    'due_now' => 'ถึงเวลาส่งตอนนี้',
    'already_sent' => 'ส่งแล้วในรอบวันนี้',
    'not_due_yet' => 'ยังไม่ถึงเวลา',
    'outside_grace_window' => 'เลยช่วงเวลาส่งแล้ว',
    'schedule_mismatch' => 'วันนี้ไม่ใช่รอบออก',
    'time_empty' => 'ยังไม่ได้ตั้งเวลาปิดรับ',
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
if (empty($scheduledImages)) {
    $scheduledImages = [[
        'id' => lineGenerateScheduledMessageId(),
        'day_start' => '',
        'day_end' => '',
        'time' => '',
        'image' => '',
        'images' => [],
        'enabled' => true,
    ]];
}
$activeGroupsCount = count(array_filter($groups, static fn($group) => !empty($group['is_active'])));
$activeTextGroupsCount = count(array_filter($groups, static fn($group) => !empty($group['is_active']) && !empty($group['allow_texts'])));
$activeImageGroupsCount = count(array_filter($groups, static fn($group) => !empty($group['is_active']) && !empty($group['allow_images'])));
$weekdayOptions = [
    '0' => 'อาทิตย์',
    '1' => 'จันทร์',
    '2' => 'อังคาร',
    '3' => 'พุธ',
    '4' => 'พฤหัสบดี',
    '5' => 'ศุกร์',
    '6' => 'เสาร์',
];

$allowedTabs = ['settings', 'shared-templates', 'bet-close-templates', 'send-image', 'auto-text', 'auto-image', 'bet-close', 'groups'];
$activeTab = isset($_GET['tab']) && in_array((string) $_GET['tab'], $allowedTabs, true)
    ? (string) $_GET['tab']
    : 'settings';

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="mt-2 mb-4 relative z-10">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-lg font-bold text-gray-800"><i class="fab fa-line text-green-600 mr-2"></i>LINE Groups</h1>
            <p class="text-xs text-gray-400">จัดการการส่งรูปผลหวย ข้อความอัตโนมัติตามเวลา และกลุ่ม LINE ที่เชื่อมผ่าน webhook แล้ว <?= count($groups) ?> กลุ่ม</p>
        </div>
        <div class="flex flex-wrap gap-2 pt-1 relative z-20">
            <a href="line_groups.php?tab=settings" data-line-tab="settings" class="line-tab-btn relative z-20 px-4 py-2 rounded-full text-sm font-medium <?= $activeTab === 'settings' ? 'bg-[#1b5e20] text-white' : 'bg-white text-gray-700 border border-gray-200' ?>">ตั้งค่า</a>
            <a href="line_groups.php?tab=shared-templates" data-line-tab="shared-templates" class="line-tab-btn relative z-20 px-4 py-2 rounded-full text-sm font-medium <?= $activeTab === 'shared-templates' ? 'bg-[#1b5e20] text-white' : 'bg-white text-gray-700 border border-gray-200' ?>">Shared Templates</a>
            <a href="line_groups.php?tab=bet-close-templates" data-line-tab="bet-close-templates" class="line-tab-btn relative z-20 px-4 py-2 rounded-full text-sm font-medium <?= $activeTab === 'bet-close-templates' ? 'bg-[#1b5e20] text-white' : 'bg-white text-gray-700 border border-gray-200' ?>">Template ปิดรับ</a>
            <a href="line_groups.php?tab=send-image" data-line-tab="send-image" class="line-tab-btn relative z-20 px-4 py-2 rounded-full text-sm font-medium <?= $activeTab === 'send-image' ? 'bg-[#1b5e20] text-white' : 'bg-white text-gray-700 border border-gray-200' ?>">ส่งรูปภาพ</a>
            <a href="line_groups.php?tab=auto-text" data-line-tab="auto-text" class="line-tab-btn relative z-20 px-4 py-2 rounded-full text-sm font-medium <?= $activeTab === 'auto-text' ? 'bg-[#1b5e20] text-white' : 'bg-white text-gray-700 border border-gray-200' ?>">ส่งข้อความ Auto</a>
            <a href="line_groups.php?tab=auto-image" data-line-tab="auto-image" class="line-tab-btn relative z-20 px-4 py-2 rounded-full text-sm font-medium <?= $activeTab === 'auto-image' ? 'bg-[#1b5e20] text-white' : 'bg-white text-gray-700 border border-gray-200' ?>">ส่งรูป Auto</a>
            <a href="line_groups.php?tab=bet-close" data-line-tab="bet-close" class="line-tab-btn relative z-20 px-4 py-2 rounded-full text-sm font-medium <?= $activeTab === 'bet-close' ? 'bg-[#1b5e20] text-white' : 'bg-white text-gray-700 border border-gray-200' ?>">ปิดรับแทง</a>
            <a href="line_groups.php?tab=groups" data-line-tab="groups" class="line-tab-btn relative z-20 px-4 py-2 rounded-full text-sm font-medium <?= $activeTab === 'groups' ? 'bg-[#1b5e20] text-white' : 'bg-white text-gray-700 border border-gray-200' ?>">กลุ่มและประวัติ</a>
        </div>
    </div>
</div>

<?php if (!lineConfigReady($pdo)): ?>
<div class="mb-4 p-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 text-sm">
    <div class="font-bold mb-1">ยังไม่ได้ตั้งค่า secret / token บน server</div>
    <div>กรอกค่า Channel secret และ Channel access token ด้านล่าง แล้วกดบันทึกได้เลย</div>
</div>
<?php endif; ?>

<section data-line-panel="settings" class="line-panel <?= $activeTab === 'settings' ? '' : 'hidden' ?>">
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

<section data-line-panel="shared-templates" class="line-panel <?= $activeTab === 'shared-templates' ? '' : 'hidden' ?>">
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

<section data-line-panel="bet-close-templates" class="line-panel <?= $activeTab === 'bet-close-templates' ? '' : 'hidden' ?>">
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">Shared Template Groups For Bet Close</div>
        <div class="px-4 py-3 text-xs text-gray-500 border-b bg-gray-50/60">
            ใช้ template ปิดรับแยกจากรูปผลหวยได้เลย ระบบปิดรับจะใช้รูปชุดนี้ก่อน และถ้าไม่มีค่อย fallback ไปใช้ shared template ของผลหวย
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
                    <?php $sharedTemplateUrl = $betCloseSharedTemplateUrls[$groupKey] ?? null; ?>
                    <tr class="border-b hover:bg-gray-50 align-top">
                        <td class="px-3 py-3 font-medium text-gray-800"><?= htmlspecialchars($groupLabel) ?></td>
                        <td class="px-3 py-3">
                            <?php if ($sharedTemplateUrl): ?>
                            <a href="<?= htmlspecialchars($sharedTemplateUrl) ?>" target="_blank" class="inline-block">
                                <img src="<?= htmlspecialchars($sharedTemplateUrl) ?>" alt="Bet-close shared template preview" class="w-44 h-28 object-cover rounded-lg border border-gray-200 shadow-sm">
                            </a>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">ยังไม่มี template ปิดรับ</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 min-w-[340px]">
                            <form method="POST" enctype="multipart/form-data" class="space-y-2">
                                <input type="hidden" name="form_action" value="upload_bet_close_shared_template">
                                <input type="hidden" name="shared_group_key" value="<?= htmlspecialchars($groupKey) ?>">
                                <input type="file" name="template_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="block w-full text-xs text-gray-500 border rounded-lg px-3 py-2 bg-white">
                                <button type="submit" class="bg-[#7b1fa2] text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-[#6a1b9a] transition">
                                    <i class="fas fa-layer-group mr-1"></i> Upload bet-close template
                                </button>
                            </form>
                            <?php if ($sharedTemplateUrl): ?>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="form_action" value="delete_bet_close_shared_template">
                                <input type="hidden" name="shared_group_key" value="<?= htmlspecialchars($groupKey) ?>">
                                <button type="submit" class="bg-red-50 text-red-700 border border-red-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-100 transition">
                                    <i class="fas fa-trash-alt mr-1"></i> Remove bet-close template
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

<section data-line-panel="send-image" class="line-panel <?= $activeTab === 'send-image' ? '' : 'hidden' ?>">
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
                                        <?= htmlspecialchars($group['group_name'] ?: 'กลุ่ม LINE') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="w-full bg-[#1565c0] text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-[#0d47a1] transition disabled:opacity-50" <?= empty($groups) ? 'disabled' : '' ?>>
                                    <i class="fas fa-image mr-1"></i> ส่งรูปผลหวยนี้
                                </button>
                            </form>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="form_action" value="send_result_image_all_groups">
                                <input type="hidden" name="lottery_type_id" value="<?= (int) $resultRow['lottery_type_id'] ?>">
                                <input type="hidden" name="draw_date" value="<?= htmlspecialchars($resultRow['draw_date']) ?>">
                                <button type="submit" class="w-full bg-[#2e7d32] text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-[#1b5e20] transition disabled:opacity-50" <?= $activeImageGroupsCount <= 0 ? 'disabled' : '' ?>>
                                    <i class="fas fa-paper-plane mr-1"></i> ส่งผลหวยนี้ทุกกลุ่ม
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

<section data-line-panel="auto-text" class="line-panel <?= $activeTab === 'auto-text' ? '' : 'hidden' ?>">
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
                                    <button type="button" class="copy-scheduled-message bg-amber-50 text-amber-700 border border-amber-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-amber-100 transition">
                                        <i class="fas fa-copy mr-1"></i> คัดลอกรายการ
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

<section data-line-panel="auto-image" class="line-panel <?= $activeTab === 'auto-image' ? '' : 'hidden' ?>">
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">ส่งรูปภาพอัตโนมัติตามช่วงวันและเวลา</div>
        <div class="px-4 py-3 text-xs text-gray-500 border-b bg-gray-50/60">
            อัปโหลดรูปต่อรายการ ตั้งวันเริ่ม-วันสิ้นสุดและเวลาได้เหมือนข้อความ ระบบจะส่งรูปนี้ไปทุกกลุ่ม active ตามเวลาที่ตั้งไว้
        </div>
        <div class="px-4 py-4 border-b bg-white">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">เวลา server</div>
                    <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($scheduledImageDiagnostics['server_date']) ?> <?= htmlspecialchars($scheduledImageDiagnostics['server_time']) ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($scheduledImageDiagnostics['server_weekday']) ?></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">สถานะระบบส่งรูป</div>
                    <div class="text-sm font-semibold <?= !empty($scheduledImageDiagnostics['config_ready']) ? 'text-green-700' : 'text-red-600' ?>">
                        <?= !empty($scheduledImageDiagnostics['config_ready']) ? 'พร้อมส่งรูป' : 'LINE config ยังไม่พร้อม' ?>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">กลุ่ม active <?= (int) $scheduledImageDiagnostics['active_groups'] ?></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">รูปพร้อมส่ง</div>
                    <div class="text-sm font-semibold text-gray-800"><?= (int) $scheduledImageDiagnostics['due_messages'] ?> รายการ</div>
                    <div class="text-xs text-gray-500 mt-1">รายการที่ตั้งครบ <?= (int) $scheduledImageDiagnostics['ready_messages'] ?> / ทั้งหมด <?= (int) $scheduledImageDiagnostics['total_messages'] ?></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">ช่วงเผื่อเวลา</div>
                    <div class="text-sm font-semibold text-gray-800"><?= (int) $scheduledImageDiagnostics['grace_minutes'] ?> นาที</div>
                    <div class="text-xs text-gray-500 mt-1">ถ้า cron มาช้า ระบบยังตามส่งในช่วงนี้</div>
                </div>
            </div>
            <?php if (!empty($scheduledImageDiagnostics['reason_counts'])): ?>
            <div class="mt-4">
                <div class="text-xs text-gray-500 mb-2">สรุปสถานะรายการรูปตอนนี้</div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($scheduledImageDiagnostics['reason_counts'] as $reasonKey => $reasonCount): ?>
                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs text-gray-700">
                        <?= htmlspecialchars($scheduledImageReasonLabels[$reasonKey] ?? $reasonKey) ?> <?= (int) $reasonCount ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-4 space-y-4">
            <input type="hidden" name="form_action" value="save_scheduled_images">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div id="scheduledImagesCount" class="text-sm text-gray-600">ตอนนี้มี <?= count($scheduledImages) ?> รายการ</div>
                <button type="button" id="addScheduledImageBtn" class="bg-[#1565c0] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0d47a1] transition">
                    <i class="fas fa-plus mr-1"></i> เพิ่มรูปภาพ
                </button>
            </div>
            <div id="scheduledImagesList" class="space-y-3">
                <?php foreach ($scheduledImages as $index => $scheduledImage): ?>
                <?php $scheduledImageUrls = lineScheduledImageUrls($pdo, (array) ($scheduledImage['images'] ?? [])); ?>
                <div class="scheduled-image-item rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                    <input type="hidden" name="scheduled_images[<?= $index ?>][id]" value="<?= htmlspecialchars((string) $scheduledImage['id']) ?>">
                    <input type="hidden" name="scheduled_images[<?= $index ?>][images_json]" value="<?= htmlspecialchars(json_encode(array_values((array) ($scheduledImage['images'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                    <input type="hidden" class="scheduled-image-urls-json" value="<?= htmlspecialchars(json_encode(array_values($scheduledImageUrls), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                    <div class="grid grid-cols-1 xl:grid-cols-[180px_180px_180px_auto] gap-3">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">วันเริ่ม</label>
                            <select name="scheduled_images[<?= $index ?>][day_start]" class="w-full border rounded-lg px-3 py-2 text-sm outline-none bg-white">
                                <option value="">ทุกวัน</option>
                                <?php foreach ($weekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                <option value="<?= $weekdayValue ?>" <?= (string) ($scheduledImage['day_start'] ?? '') === (string) $weekdayValue ? 'selected' : '' ?>><?= $weekdayLabel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">วันสิ้นสุด</label>
                            <select name="scheduled_images[<?= $index ?>][day_end]" class="w-full border rounded-lg px-3 py-2 text-sm outline-none bg-white">
                                <option value="">ทุกวัน</option>
                                <?php foreach ($weekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                <option value="<?= $weekdayValue ?>" <?= (string) ($scheduledImage['day_end'] ?? '') === (string) $weekdayValue ? 'selected' : '' ?>><?= $weekdayLabel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">เวลาส่ง</label>
                            <input type="time" name="scheduled_images[<?= $index ?>][time]" value="<?= htmlspecialchars((string) ($scheduledImage['time'] ?? '')) ?>" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                        </div>
                        <div class="flex items-center gap-2 pt-0 xl:pt-6">
                            <input type="hidden" name="scheduled_images[<?= $index ?>][enabled]" value="0">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="scheduled_images[<?= $index ?>][enabled]" value="1" class="rounded border-gray-300" <?= !empty($scheduledImage['enabled']) ? 'checked' : '' ?>>
                                <span>เปิดใช้</span>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-4 items-start">
                        <div class="space-y-2">
                            <label class="text-xs text-gray-500 block">รูปภาพ</label>
                            <div class="scheduled-image-preview-box rounded-xl border border-dashed border-gray-300 bg-white p-3">
                                <?php if (!empty($scheduledImageUrls)): ?>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php foreach ($scheduledImageUrls as $imageUrlIndex => $scheduledImageUrl): ?>
                                    <div class="relative group">
                                        <button type="button" class="remove-saved-image absolute top-1 right-1 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-white shadow hover:bg-red-700 transition" data-index="<?= $imageUrlIndex ?>" title="ลบรูปนี้ออกจากรายการ">
                                            <i class="fas fa-times text-xs"></i>
                                        </button>
                                        <a href="<?= htmlspecialchars($scheduledImageUrl) ?>" target="_blank" class="block">
                                            <img src="<?= htmlspecialchars($scheduledImageUrl) ?>" alt="Scheduled image preview" class="w-full h-28 object-cover rounded-lg border border-gray-200">
                                        </a>
                                        <div class="mt-1 text-[11px] text-gray-500 break-all"><?= htmlspecialchars((string) (($scheduledImage['images'] ?? [])[$imageUrlIndex] ?? '')) ?></div>
                                        <div class="text-[11px] text-green-600">บันทึกแล้ว</div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="h-40 rounded-lg border border-dashed border-gray-200 flex items-center justify-center text-xs text-gray-400">ยังไม่มีรูปอัปโหลด</div>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="scheduled_image_files[<?= $index ?>][]" multiple accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="scheduled-image-file-input block w-full text-xs text-gray-500 border rounded-lg px-3 py-2 bg-white">
                        </div>
                        <div class="space-y-3">
                            <div class="text-xs text-gray-500">สถานะตอนนี้: <?= htmlspecialchars($scheduledImageReasonLabels[$scheduledImageDiagnostics['items'][$index]['reason'] ?? 'ready'] ?? 'พร้อมใช้งาน') ?> / รูปที่บันทึกไว้ <?= (int) ($scheduledImageDiagnostics['items'][$index]['image_count'] ?? count((array) ($scheduledImage['images'] ?? []))) ?> รูป</div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="send-scheduled-image-now bg-blue-50 text-blue-700 border border-blue-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-100 transition">
                                    <i class="fas fa-paper-plane mr-1"></i> ส่งทันที
                                </button>
                                <button type="button" class="copy-scheduled-image bg-amber-50 text-amber-700 border border-amber-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-amber-100 transition">
                                    <i class="fas fa-copy mr-1"></i> คัดลอกรายการ
                                </button>
                                <button type="button" class="remove-scheduled-image bg-red-50 text-red-700 border border-red-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-100 transition">
                                    <i class="fas fa-trash-alt mr-1"></i> ลบรายการ
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="border-t pt-4">
                <button type="submit" class="bg-[#2e7d32] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#1b5e20] transition">
                    <i class="fas fa-save mr-1"></i> บันทึกรูปภาพตามช่วงวันและเวลา
                </button>
            </div>
        </form>
    </div>
</section>

<section data-line-panel="bet-close" class="line-panel <?= $activeTab === 'bet-close' ? '' : 'hidden' ?>">
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-700">แจ้งภาพปิดรับแทงอัตโนมัติ</div>
        <div class="px-4 py-3 text-xs text-gray-500 border-b bg-gray-50/60">
            ระบบจะอิงเวลาจาก <span class="font-semibold">เวลาปิดรับ</span> ในหน้าจัดการหวยโดยตรง และสร้างภาพปิดรับด้วย template เดียวกับรูปผลหวย
        </div>
        <div class="px-4 py-3 text-xs text-blue-700 border-b bg-blue-50/60">
            เวลาทั้งหมดในส่วนนี้ใช้เวลาไทย (Asia/Bangkok)
        </div>
        <form method="POST" class="px-4 py-4 border-b bg-white flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <input type="hidden" name="form_action" value="save_bet_close_settings">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="auto_send_bet_close" value="1" class="rounded border-gray-300" <?= $autoSendBetClose ? 'checked' : '' ?>>
                <span>เปิดส่งภาพปิดรับแทงอัตโนมัติจากเวลาปิดรับของหวย</span>
            </label>
            <button type="submit" class="bg-[#2e7d32] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#1b5e20] transition">
                <i class="fas fa-save mr-1"></i> บันทึกการตั้งค่า
            </button>
        </form>
        <div class="px-4 py-4 border-b bg-white">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">เวลา server</div>
                    <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($betCloseDiagnostics['server_date']) ?> <?= htmlspecialchars($betCloseDiagnostics['server_time']) ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($betCloseDiagnostics['server_weekday']) ?></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">สถานะระบบ</div>
                    <div class="text-sm font-semibold <?= !empty($betCloseDiagnostics['auto_send_enabled']) ? 'text-green-700' : 'text-red-600' ?>">
                        <?= !empty($betCloseDiagnostics['auto_send_enabled']) ? 'เปิดใช้งานแล้ว' : 'ยังปิดอยู่' ?>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Config <?= !empty($betCloseDiagnostics['config_ready']) ? 'พร้อม' : 'ยังไม่พร้อม' ?> / กลุ่มรับรูป <?= (int) $betCloseDiagnostics['active_groups'] ?></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">หวยพร้อมแจ้งตอนนี้</div>
                    <div class="text-sm font-semibold text-gray-800"><?= (int) $betCloseDiagnostics['due_lotteries'] ?> รายการ</div>
                    <div class="text-xs text-gray-500 mt-1">พร้อมใช้งาน <?= (int) $betCloseDiagnostics['ready_lotteries'] ?> / ทั้งหมด <?= (int) $betCloseDiagnostics['total_lotteries'] ?></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs text-gray-500 mb-1">ช่วงเผื่อเวลา</div>
                    <div class="text-sm font-semibold text-gray-800"><?= (int) $betCloseDiagnostics['grace_minutes'] ?> นาที</div>
                    <div class="text-xs text-gray-500 mt-1">cron มาช้า ระบบยังตามส่งในช่วงนี้</div>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">หวย</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">หมวด</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ปิดรับ</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ผลออก</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ตารางออก</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ตั้งเวลาปิดรับ</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">สถานะ</th>
                        <th class="px-3 py-2 text-left text-xs text-gray-500">ทดสอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($betCloseDiagnostics['items'] as $item): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($item['lottery_name']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($item['category_name']) ?></td>
                        <td class="px-3 py-2 text-xs text-gray-700"><?= htmlspecialchars($item['close_time'] !== '' ? lineFormatResultTimeDisplay((string) $item['close_time']) : '-') ?></td>
                        <td class="px-3 py-2 text-xs text-gray-700"><?= htmlspecialchars($item['result_time'] !== '' ? $item['result_time'] : '-') ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars((string) $item['draw_schedule']) ?></td>
                        <td class="px-3 py-2">
                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="form_action" value="save_bet_close_time">
                                <input type="hidden" name="lottery_type_id" value="<?= (int) $item['lottery_type_id'] ?>">
                                <input
                                    type="time"
                                    name="close_time"
                                    value="<?= htmlspecialchars((string) $item['close_time']) ?>"
                                    class="w-28 border rounded-lg px-2 py-2 text-xs outline-none bg-white"
                                    step="60"
                                    title="เวลาประเทศไทย (Asia/Bangkok)"
                                >
                                <button type="submit" class="bg-green-50 text-green-700 border border-green-200 px-3 py-2 rounded-lg text-xs font-medium hover:bg-green-100 transition whitespace-nowrap">
                                    <i class="fas fa-save mr-1"></i>บันทึก
                                </button>
                            </form>
                        </td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded-full text-[11px] font-medium <?= $item['reason'] === 'due_now' ? 'bg-green-100 text-green-700' : ($item['reason'] === 'already_sent' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') ?>">
                                <?= htmlspecialchars($betCloseReasonLabels[$item['reason']] ?? $item['reason']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <form method="POST">
                                <input type="hidden" name="form_action" value="send_bet_close_now">
                                <input type="hidden" name="lottery_type_id" value="<?= (int) $item['lottery_type_id'] ?>">
                                <button type="submit" class="bg-blue-50 text-blue-700 border border-blue-200 px-3 py-2 rounded-lg text-xs font-medium hover:bg-blue-100 transition">
                                    <i class="fas fa-paper-plane mr-1"></i> ส่งทันที
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($betCloseDiagnostics['items'])): ?>
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-400">ยังไม่พบหวยที่ตั้งเวลาปิดรับไว้</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section data-line-panel="groups" class="line-panel <?= $activeTab === 'groups' ? '' : 'hidden' ?>">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between gap-3">
                <div class="font-semibold text-gray-700">รายชื่อกลุ่ม LINE</div>
                <form method="POST">
                    <input type="hidden" name="form_action" value="refresh_group_names">
                    <button type="submit" class="bg-blue-50 text-blue-700 border border-blue-200 px-3 py-2 rounded-lg text-xs font-medium hover:bg-blue-100 transition whitespace-nowrap">
                        <i class="fas fa-sync-alt mr-1"></i>รีเฟรชชื่อกลุ่ม
                    </button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs text-gray-500">สถานะ</th>
                            <th class="px-3 py-2 text-left text-xs text-gray-500">ส่งข้อความ</th>
                            <th class="px-3 py-2 text-left text-xs text-gray-500">ส่งรูป</th>
                            <th class="px-3 py-2 text-left text-xs text-gray-500">ชื่อกลุ่ม</th>
                            <th class="px-3 py-2 text-left text-xs text-gray-500">Group ID</th>
                            <th class="px-3 py-2 text-left text-xs text-gray-500">ล่าสุด</th>
                            <th class="px-3 py-2 text-left text-xs text-gray-500">บันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td colspan="7" class="px-0 py-0">
                                <form method="POST" class="grid grid-cols-1 md:grid-cols-[120px_120px_120px_minmax(180px,1fr)_minmax(220px,1fr)_140px_120px] gap-0 items-center">
                                    <input type="hidden" name="form_action" value="save_group_delivery_settings">
                                    <input type="hidden" name="group_id" value="<?= htmlspecialchars((string) $group['group_id']) ?>">
                                    <div class="px-3 py-3">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" <?= !empty($group['is_active']) ? 'checked' : '' ?>>
                                            <span class="px-2 py-1 rounded-full text-[11px] font-medium <?= !empty($group['is_active']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                                <?= !empty($group['is_active']) ? 'active' : 'inactive' ?>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="px-3 py-3">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="allow_texts" value="1" class="rounded border-gray-300" <?= !empty($group['allow_texts']) ? 'checked' : '' ?>>
                                            <span>เปิด</span>
                                        </label>
                                    </div>
                                    <div class="px-3 py-3">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="allow_images" value="1" class="rounded border-gray-300" <?= !empty($group['allow_images']) ? 'checked' : '' ?>>
                                            <span>เปิด</span>
                                        </label>
                                    </div>
                                    <div class="px-3 py-3 font-medium text-gray-800"><?= htmlspecialchars($group['group_name'] ?: '(ยังไม่ทราบชื่อกลุ่ม)') ?></div>
                                    <div class="px-3 py-3 text-xs text-gray-500 break-all"><?= htmlspecialchars((string) $group['group_id']) ?></div>
                                    <div class="px-3 py-3 text-xs text-gray-500"><?= htmlspecialchars((string) $group['last_seen_at']) ?></div>
                                    <div class="px-3 py-3">
                                        <button type="submit" class="bg-[#2e7d32] text-white px-3 py-2 rounded-lg text-xs font-medium hover:bg-[#1b5e20] transition">
                                            <i class="fas fa-save mr-1"></i>บันทึก
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($groups)): ?>
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-400">ยังไม่พบกลุ่ม LINE</td>
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
                    <div class="text-xs text-blue-700">กลุ่มที่รับข้อความ</div>
                    <div class="mt-1 text-2xl font-bold text-blue-700"><?= number_format($activeTextGroupsCount) ?></div>
                </div>
                <div class="rounded-lg border border-purple-200 bg-purple-50 p-3">
                    <div class="text-xs text-purple-700">กลุ่มที่รับรูป</div>
                    <div class="mt-1 text-2xl font-bold text-purple-700"><?= number_format($activeImageGroupsCount) ?></div>
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

<form method="POST" id="sendScheduledImageNowForm" class="hidden">
    <input type="hidden" name="form_action" value="send_scheduled_image_now">
    <input type="hidden" name="scheduled_image_now_id" id="scheduledImageNowIdInput" value="">
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = Array.from(document.querySelectorAll('[data-line-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-line-panel]'));
    const allowedTabs = new Set(['settings', 'shared-templates', 'bet-close-templates', 'send-image', 'auto-text', 'auto-image', 'bet-close', 'groups']);
    const scheduledMessagesList = document.getElementById('scheduledMessagesList');
    const addScheduledMessageBtn = document.getElementById('addScheduledMessageBtn');
    const scheduledMessagesCount = document.getElementById('scheduledMessagesCount');
    const sendScheduledNowForm = document.getElementById('sendScheduledNowForm');
    const scheduledMessageNowInput = document.getElementById('scheduledMessageNowInput');
    const scheduledImagesList = document.getElementById('scheduledImagesList');
    const addScheduledImageBtn = document.getElementById('addScheduledImageBtn');
    const scheduledImagesCount = document.getElementById('scheduledImagesCount');
    const sendScheduledImageNowForm = document.getElementById('sendScheduledImageNowForm');
    const scheduledImageNowIdInput = document.getElementById('scheduledImageNowIdInput');
    let scheduledMessageIndex = <?= count($scheduledMessages) ?>;
    let scheduledImageIndex = <?= count($scheduledImages) ?>;

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
                        <button type="button" class="copy-scheduled-message bg-amber-50 text-amber-700 border border-amber-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-amber-100 transition">
                            <i class="fas fa-copy mr-1"></i> à¸„à¸±à¸”à¸¥à¸­à¸à¸£à¸²à¸¢à¸à¸²à¸£
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

    function cloneScheduledMessageItem(sourceItem) {
        const newItem = buildScheduledMessageItem(scheduledMessageIndex);
        const sourceDayStart = sourceItem.querySelector('select[name*="[day_start]"]');
        const sourceDayEnd = sourceItem.querySelector('select[name*="[day_end]"]');
        const sourceTime = sourceItem.querySelector('input[type="time"]');
        const sourceCheckbox = sourceItem.querySelector('input[type="checkbox"]');
        const sourceTextarea = sourceItem.querySelector('textarea');

        const targetDayStart = newItem.querySelector('select[name*="[day_start]"]');
        const targetDayEnd = newItem.querySelector('select[name*="[day_end]"]');
        const targetTime = newItem.querySelector('input[type="time"]');
        const targetCheckbox = newItem.querySelector('input[type="checkbox"]');
        const targetTextarea = newItem.querySelector('textarea');

        if (targetDayStart && sourceDayStart) targetDayStart.value = sourceDayStart.value;
        if (targetDayEnd && sourceDayEnd) targetDayEnd.value = sourceDayEnd.value;
        if (targetTime && sourceTime) targetTime.value = sourceTime.value;
        if (targetCheckbox && sourceCheckbox) targetCheckbox.checked = sourceCheckbox.checked;
        if (targetTextarea && sourceTextarea) targetTextarea.value = sourceTextarea.value;

        scheduledMessageIndex += 1;
        return newItem;
    }

    function buildScheduledImageItem(index) {
        const wrapper = document.createElement('div');
        const generatedId = 'msg_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
        wrapper.className = 'scheduled-image-item rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3';
        wrapper.innerHTML = `
            <input type="hidden" name="scheduled_images[${index}][id]" value="${generatedId}">
            <input type="hidden" name="scheduled_images[${index}][images_json]" value="[]">
            <input type="hidden" class="scheduled-image-urls-json" value="[]">
            <div class="grid grid-cols-1 xl:grid-cols-[180px_180px_180px_auto] gap-3">
                <div>
                    <label class="text-xs text-gray-500 block mb-1">วันเริ่ม</label>
                    <select name="scheduled_images[${index}][day_start]" class="w-full border rounded-lg px-3 py-2 text-sm outline-none bg-white">
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
                <div>
                    <label class="text-xs text-gray-500 block mb-1">วันสิ้นสุด</label>
                    <select name="scheduled_images[${index}][day_end]" class="w-full border rounded-lg px-3 py-2 text-sm outline-none bg-white">
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
                <div>
                    <label class="text-xs text-gray-500 block mb-1">เวลาส่ง</label>
                    <input type="time" name="scheduled_images[${index}][time]" value="" class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                </div>
                <div class="flex items-center gap-2 pt-0 xl:pt-6">
                    <input type="hidden" name="scheduled_images[${index}][enabled]" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="scheduled_images[${index}][enabled]" value="1" class="rounded border-gray-300" checked>
                        <span>เปิดใช้</span>
                    </label>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-4 items-start">
                <div class="space-y-2">
                    <label class="text-xs text-gray-500 block">รูปภาพ</label>
                    <div class="scheduled-image-preview-box rounded-xl border border-dashed border-gray-300 bg-white p-3">
                        <div class="h-40 rounded-lg border border-dashed border-gray-200 flex items-center justify-center text-xs text-gray-400">ยังไม่มีรูปอัปโหลด</div>
                    </div>
                    <input type="file" name="scheduled_image_files[${index}][]" multiple accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="scheduled-image-file-input block w-full text-xs text-gray-500 border rounded-lg px-3 py-2 bg-white">
                </div>
                <div class="space-y-3">
                    <div class="text-xs text-gray-500">สถานะตอนนี้: ยังไม่ได้อัปโหลดรูป / รูปที่บันทึกไว้ 0 รูป</div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="send-scheduled-image-now bg-blue-50 text-blue-700 border border-blue-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-100 transition">
                            <i class="fas fa-paper-plane mr-1"></i> ส่งทันที
                        </button>
                        <button type="button" class="copy-scheduled-image bg-amber-50 text-amber-700 border border-amber-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-amber-100 transition">
                            <i class="fas fa-copy mr-1"></i> คัดลอกรายการ
                        </button>
                        <button type="button" class="remove-scheduled-image bg-red-50 text-red-700 border border-red-200 px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-100 transition">
                            <i class="fas fa-trash-alt mr-1"></i> ลบรายการ
                        </button>
                    </div>
                </div>
            </div>
        `;
        return wrapper;
    }

    function cloneScheduledImageItem(sourceItem) {
        const newItem = buildScheduledImageItem(scheduledImageIndex);
        const sourceDayStart = sourceItem.querySelector('select[name*="[day_start]"]');
        const sourceDayEnd = sourceItem.querySelector('select[name*="[day_end]"]');
        const sourceTime = sourceItem.querySelector('input[type="time"]');
        const sourceCheckbox = sourceItem.querySelector('input[type="checkbox"]');
        const sourceImageHidden = sourceItem.querySelector('input[name*="[images_json]"]');
        const sourceImageUrlsHidden = sourceItem.querySelector('.scheduled-image-urls-json');
        const sourceStatusText = sourceItem.querySelector('.space-y-3 > .text-xs.text-gray-500');

        const targetDayStart = newItem.querySelector('select[name*="[day_start]"]');
        const targetDayEnd = newItem.querySelector('select[name*="[day_end]"]');
        const targetTime = newItem.querySelector('input[type="time"]');
        const targetCheckbox = newItem.querySelector('input[type="checkbox"]');
        const targetImageHidden = newItem.querySelector('input[name*="[images_json]"]');
        const targetImageUrlsHidden = newItem.querySelector('.scheduled-image-urls-json');
        const targetStatusText = newItem.querySelector('.space-y-3 > .text-xs.text-gray-500');

        if (targetDayStart && sourceDayStart) targetDayStart.value = sourceDayStart.value;
        if (targetDayEnd && sourceDayEnd) targetDayEnd.value = sourceDayEnd.value;
        if (targetTime && sourceTime) targetTime.value = sourceTime.value;
        if (targetCheckbox && sourceCheckbox) targetCheckbox.checked = sourceCheckbox.checked;
        if (targetImageHidden && sourceImageHidden) targetImageHidden.value = sourceImageHidden.value;
        if (targetImageUrlsHidden && sourceImageUrlsHidden) targetImageUrlsHidden.value = sourceImageUrlsHidden.value;
        if (targetStatusText && sourceStatusText) targetStatusText.textContent = sourceStatusText.textContent;

        scheduledImageIndex += 1;
        renderScheduledImagePreview(newItem);
        return newItem;
    }

    function parseJsonArray(value) {
        try {
            const decoded = JSON.parse(value || '[]');
            return Array.isArray(decoded) ? decoded : [];
        } catch (error) {
            return [];
        }
    }

    function updateScheduledImageStatus(item, savedCount, selectedCount) {
        const statusText = item ? item.querySelector('.space-y-3 > .text-xs.text-gray-500') : null;
        if (!statusText) {
            return;
        }

        let statusLabel = 'ยังไม่ได้อัปโหลดรูป';
        if (savedCount > 0 && selectedCount > 0) {
            statusLabel = 'มีรูปที่บันทึกไว้ และเลือกรูปใหม่แล้ว';
        } else if (savedCount > 0) {
            statusLabel = 'พร้อมใช้งาน';
        } else if (selectedCount > 0) {
            statusLabel = 'เลือกรูปใหม่แล้ว';
        }

        statusText.textContent = `สถานะตอนนี้: ${statusLabel} / รูปที่บันทึกไว้ ${savedCount} รูป / รูปที่เลือกใหม่ ${selectedCount} รูป`;
    }

    function setScheduledImageFiles(fileInput, files) {
        if (typeof DataTransfer === 'undefined') {
            window.alert('เบราว์เซอร์นี้ยังไม่รองรับการลบรูปที่เพิ่งเลือกทีละรูป กรุณาเลือกไฟล์ใหม่อีกครั้ง');
            return false;
        }

        const dataTransfer = new DataTransfer();
        files.forEach((file) => {
            dataTransfer.items.add(file);
        });
        fileInput.files = dataTransfer.files;
        return true;
    }

    function renderScheduledImagePreview(target) {
        const item = target && target.closest ? target.closest('.scheduled-image-item') : target;
        const previewBox = item ? item.querySelector('.scheduled-image-preview-box') : null;
        const fileInput = item ? item.querySelector('.scheduled-image-file-input') : null;
        const imagesInput = item ? item.querySelector('input[name*="[images_json]"]') : null;
        const imageUrlsInput = item ? item.querySelector('.scheduled-image-urls-json') : null;
        if (!item || !previewBox) {
            return;
        }

        const savedNames = imagesInput ? parseJsonArray(imagesInput.value).filter((name) => typeof name === 'string' && name !== '') : [];
        const savedUrls = imageUrlsInput ? parseJsonArray(imageUrlsInput.value).filter((url) => typeof url === 'string' && url !== '') : [];
        const savedEntries = savedNames.map((name, index) => ({
            name,
            url: savedUrls[index] || '',
        }));

        const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];

        for (const file of files) {
            if (!file.type.startsWith('image/')) {
                window.alert('กรุณาเลือกไฟล์รูปภาพ');
                fileInput.value = '';
                return;
            }
        }

        if (savedEntries.length === 0 && files.length === 0) {
            previewBox.innerHTML = `<div class="h-40 rounded-lg border border-dashed border-gray-200 flex items-center justify-center text-xs text-gray-400">ยังไม่มีรูปอัปโหลด</div>`;
            updateScheduledImageStatus(item, 0, 0);
            return;
        }

        const savedHtml = savedEntries.map((entry, index) => `
            <div class="relative group">
                <button type="button" class="remove-saved-image absolute top-1 right-1 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-white shadow hover:bg-red-700 transition" data-index="${index}" title="ลบรูปนี้ออกจากรายการ">
                    <i class="fas fa-times text-xs"></i>
                </button>
                <a href="${entry.url || '#'}" target="_blank" class="block ${entry.url ? '' : 'pointer-events-none'}">
                    <img src="${entry.url || ''}" alt="Saved scheduled image preview" class="w-full h-28 object-cover rounded-lg border border-gray-200">
                </a>
                <div class="mt-1 text-[11px] text-gray-500 break-all">${entry.name}</div>
                <div class="text-[11px] text-green-600">บันทึกแล้ว</div>
            </div>
        `).join('');

        const previewHtml = files.map((file, index) => {
            const previewUrl = URL.createObjectURL(file);
            return `
                <div class="relative group">
                    <button type="button" class="remove-selected-image absolute top-1 right-1 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-white shadow hover:bg-red-700 transition" data-index="${index}" title="เอารูปนี้ออกก่อนบันทึก">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                    <img src="${previewUrl}" alt="Selected image preview" class="w-full h-28 object-cover rounded-lg border border-gray-200">
                    <div class="mt-1 text-[11px] text-gray-500 break-all">${file.name}</div>
                    <div class="text-[11px] text-blue-600">ยังไม่ได้บันทึก</div>
                </div>
            `;
        }).join('');

        previewBox.innerHTML = `
            <div class="grid grid-cols-2 gap-2">${savedHtml}${previewHtml}</div>
            ${files.length > 0 ? `<div class="mt-2 text-[11px] text-green-600">พรีวิวจากไฟล์ที่เลือก ${files.length} รูป ยังไม่ได้บันทึกขึ้นระบบ</div>` : ''}
        `;

        updateScheduledImageStatus(item, savedEntries.length, files.length);
    }

    function updateScheduledMessagesCount() {
        if (!scheduledMessagesCount || !scheduledMessagesList) {
            return;
        }

        const items = scheduledMessagesList.querySelectorAll('.scheduled-message-item').length;
        scheduledMessagesCount.textContent = `ตอนนี้มี ${items} รายการ`;
    }

    function updateScheduledImagesCount() {
        if (!scheduledImagesCount || !scheduledImagesList) {
            return;
        }

        const items = scheduledImagesList.querySelectorAll('.scheduled-image-item').length;
        scheduledImagesCount.textContent = `ตอนนี้มี ${items} รายการ`;
    }

    buttons.forEach((button) => {
        if (button.tagName === 'A') {
            return;
        }
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

            const copyButton = event.target.closest('.copy-scheduled-message');
            if (copyButton) {
                const item = copyButton.closest('.scheduled-message-item');
                if (!item) {
                    return;
                }

                const copiedItem = cloneScheduledMessageItem(item);
                scheduledMessagesList.appendChild(copiedItem);
                updateScheduledMessagesCount();
                copiedItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

    if (addScheduledImageBtn && scheduledImagesList) {
        addScheduledImageBtn.addEventListener('click', function () {
            const newItem = buildScheduledImageItem(scheduledImageIndex);
            scheduledImagesList.appendChild(newItem);
            scheduledImageIndex += 1;
            updateScheduledImagesCount();
            newItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        scheduledImagesList.addEventListener('click', function (event) {
            const sendNowButton = event.target.closest('.send-scheduled-image-now');
            if (sendNowButton) {
                const item = sendNowButton.closest('.scheduled-image-item');
                const idInput = item ? item.querySelector('input[name*="[id]"]') : null;
                const imageInput = item ? item.querySelector('input[name*="[images_json]"]') : null;
                let hasImage = false;
                if (imageInput) {
                    try {
                        const decoded = JSON.parse(imageInput.value || '[]');
                        hasImage = Array.isArray(decoded) && decoded.length > 0;
                    } catch (error) {
                        hasImage = imageInput.value.trim() !== '' && imageInput.value.trim() !== '[]';
                    }
                }
                if (!hasImage) {
                    window.alert('กรุณาอัปโหลดรูปภาพก่อนกดส่งทันที');
                    return;
                }

                if (sendScheduledImageNowForm && scheduledImageNowIdInput && idInput) {
                    scheduledImageNowIdInput.value = idInput.value;
                    sendScheduledImageNowForm.submit();
                }
                return;
            }

            const removeSavedImageButton = event.target.closest('.remove-saved-image');
            if (removeSavedImageButton) {
                const item = removeSavedImageButton.closest('.scheduled-image-item');
                const imageInput = item ? item.querySelector('input[name*="[images_json]"]') : null;
                const imageUrlsInput = item ? item.querySelector('.scheduled-image-urls-json') : null;
                const removeIndex = Number(removeSavedImageButton.dataset.index);
                if (!item || !imageInput || !imageUrlsInput || Number.isNaN(removeIndex)) {
                    return;
                }

                const imageNames = parseJsonArray(imageInput.value);
                const imageUrls = parseJsonArray(imageUrlsInput.value);
                imageNames.splice(removeIndex, 1);
                imageUrls.splice(removeIndex, 1);
                imageInput.value = JSON.stringify(imageNames);
                imageUrlsInput.value = JSON.stringify(imageUrls);
                renderScheduledImagePreview(item);
                return;
            }

            const removeSelectedImageButton = event.target.closest('.remove-selected-image');
            if (removeSelectedImageButton) {
                const item = removeSelectedImageButton.closest('.scheduled-image-item');
                const fileInput = item ? item.querySelector('.scheduled-image-file-input') : null;
                const removeIndex = Number(removeSelectedImageButton.dataset.index);
                if (!item || !fileInput || Number.isNaN(removeIndex)) {
                    return;
                }

                const files = fileInput.files ? Array.from(fileInput.files) : [];
                files.splice(removeIndex, 1);
                if (setScheduledImageFiles(fileInput, files)) {
                    renderScheduledImagePreview(item);
                }
                return;
            }

            const copyButton = event.target.closest('.copy-scheduled-image');
            if (copyButton) {
                const item = copyButton.closest('.scheduled-image-item');
                if (!item) {
                    return;
                }

                const copiedItem = cloneScheduledImageItem(item);
                scheduledImagesList.appendChild(copiedItem);
                updateScheduledImagesCount();
                copiedItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                return;
            }

            const removeButton = event.target.closest('.remove-scheduled-image');
            if (!removeButton) {
                return;
            }
            const item = removeButton.closest('.scheduled-image-item');
            if (!item) {
                return;
            }

            const items = scheduledImagesList.querySelectorAll('.scheduled-image-item');
            if (items.length === 1) {
                const selects = item.querySelectorAll('select');
                const timeInput = item.querySelector('input[type="time"]');
                const checkbox = item.querySelector('input[type="checkbox"]');
                const imageHiddenInput = item.querySelector('input[name*="[images_json]"]');
                const imageUrlsHiddenInput = item.querySelector('.scheduled-image-urls-json');
                const fileInput = item.querySelector('input[type="file"]');
                selects.forEach((select) => {
                    select.value = '';
                });
                if (timeInput) timeInput.value = '';
                if (checkbox) checkbox.checked = true;
                if (imageHiddenInput) imageHiddenInput.value = '[]';
                if (imageUrlsHiddenInput) imageUrlsHiddenInput.value = '[]';
                if (fileInput) fileInput.value = '';
                renderScheduledImagePreview(item);
                return;
            }

            item.remove();
            updateScheduledImagesCount();
        });

        scheduledImagesList.addEventListener('change', function (event) {
            const fileInput = event.target.closest('.scheduled-image-file-input');
            if (!fileInput) {
                return;
            }

            renderScheduledImagePreview(fileInput);
        });
    }

    updateScheduledMessagesCount();
    updateScheduledImagesCount();

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

<script>
(function () {
    const allowedTabs = new Set(['settings', 'shared-templates', 'bet-close-templates', 'send-image', 'auto-text', 'auto-image', 'bet-close', 'groups']);
    const buttons = Array.from(document.querySelectorAll('[data-line-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-line-panel]'));
    if (buttons.length === 0 || panels.length === 0) {
        return;
    }

    function applyTab(tabName) {
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
        } catch (error) {
            // Ignore URL update failures.
        }
    }

    let initialTab = 'settings';
    try {
        const url = new URL(window.location.href);
        const tabFromUrl = url.searchParams.get('tab');
        if (tabFromUrl && allowedTabs.has(tabFromUrl)) {
            initialTab = tabFromUrl;
        }
    } catch (error) {
        // Ignore URL parsing failures and keep default tab.
    }

    applyTab(initialTab);

    buttons.forEach((button) => {
        if (button.tagName === 'A') {
            return;
        }
        button.addEventListener('click', function (event) {
            event.preventDefault();
            applyTab(button.dataset.lineTab);
        });
    });
})();
</script>

<script>
(function () {
    const addScheduledImageBtn = document.getElementById('addScheduledImageBtn');
    const scheduledImagesList = document.getElementById('scheduledImagesList');
    const scheduledImagesCount = document.getElementById('scheduledImagesCount');
    if (!addScheduledImageBtn || !scheduledImagesList) {
        return;
    }

    function updateCount() {
        if (!scheduledImagesCount) {
            return;
        }
        scheduledImagesCount.textContent = `ตอนนี้มี ${scheduledImagesList.querySelectorAll('.scheduled-image-item').length} รายการ`;
    }

    function nextImageIndex() {
        let maxIndex = -1;
        scheduledImagesList.querySelectorAll('.scheduled-image-item').forEach((item) => {
            item.querySelectorAll('[name]').forEach((field) => {
                const match = field.name.match(/scheduled_images\[(\d+)\]/);
                if (match) {
                    maxIndex = Math.max(maxIndex, Number(match[1]));
                }
            });
        });

        return maxIndex + 1;
    }

    function resetClonedScheduledImageItem(item) {
        const generatedId = 'msg_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
        const idInput = item.querySelector('input[name*="[id]"]');
        const imageInput = item.querySelector('input[name*="[images_json]"]');
        const imageUrlsInput = item.querySelector('.scheduled-image-urls-json');
        const fileInput = item.querySelector('.scheduled-image-file-input');
        const timeInput = item.querySelector('input[type="time"]');
        const enabledCheckbox = item.querySelector('input[type="checkbox"][name*="[enabled]"]');
        const previewBox = item.querySelector('.scheduled-image-preview-box');
        const statusText = item.querySelector('.space-y-3 > .text-xs.text-gray-500');

        if (idInput) idInput.value = generatedId;
        if (imageInput) imageInput.value = '[]';
        if (imageUrlsInput) imageUrlsInput.value = '[]';
        if (fileInput) fileInput.value = '';
        if (timeInput) timeInput.value = '';
        if (enabledCheckbox) enabledCheckbox.checked = true;
        item.querySelectorAll('select').forEach((select) => {
            select.value = '';
        });

        if (previewBox) {
            previewBox.innerHTML = '<div class="h-40 rounded-lg border border-dashed border-gray-200 flex items-center justify-center text-xs text-gray-400">ยังไม่มีรูปอัปโหลด</div>';
        }

        if (statusText) {
            statusText.textContent = 'สถานะตอนนี้: ยังไม่ได้อัปโหลดรูป / รูปที่บันทึกไว้ 0 รูป';
        }
    }

    if (!addScheduledImageBtn.dataset.fallbackBound) {
        addScheduledImageBtn.dataset.fallbackBound = '1';
        addScheduledImageBtn.addEventListener('click', function () {
            const template = scheduledImagesList.querySelector('.scheduled-image-item');
            if (!template) {
                return;
            }

            const newIndex = nextImageIndex();
            const clone = template.cloneNode(true);
            clone.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name
                    .replace(/scheduled_images\[\d+\]/g, `scheduled_images[${newIndex}]`)
                    .replace(/scheduled_image_files\[\d+\]/g, `scheduled_image_files[${newIndex}]`);
            });

            resetClonedScheduledImageItem(clone);
            scheduledImagesList.appendChild(clone);
            updateCount();
            clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>
