<?php
require_once __DIR__ . '/../config.php';

function lineJsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function lineRawBody(): string
{
    return file_get_contents('php://input') ?: '';
}

function lineRequestSignature(): string
{
    return $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
}

function lineVerifySignature(string $body, string $signature, string $secret): bool
{
    if ($secret === '') {
        return true;
    }

    if ($signature === '') {
        return false;
    }

    $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));
    return hash_equals($expected, $signature);
}

function lineLog(string $message): void
{
    error_log('[LINE] ' . $message);
}

function ensureLineTables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS line_groups (
            id INT(11) NOT NULL AUTO_INCREMENT,
            group_id VARCHAR(100) NOT NULL,
            source_type VARCHAR(20) NOT NULL DEFAULT 'group',
            group_name VARCHAR(255) DEFAULT NULL,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            raw_source TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_group_id (group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS line_message_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            group_id VARCHAR(100) NOT NULL,
            message_text TEXT DEFAULT NULL,
            response_code INT(11) DEFAULT NULL,
            response_body TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_group_id (group_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS line_settings (
            id INT(11) NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS line_schedule_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            message_id VARCHAR(64) NOT NULL,
            scheduled_date DATE NOT NULL,
            scheduled_time VARCHAR(5) NOT NULL,
            message_text TEXT DEFAULT NULL,
            sent_groups INT(11) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_message_schedule (message_id, scheduled_date, scheduled_time),
            KEY idx_scheduled_date (scheduled_date),
            KEY idx_message_id (message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS line_image_schedule_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            message_id VARCHAR(64) NOT NULL,
            scheduled_date DATE NOT NULL,
            scheduled_time VARCHAR(5) NOT NULL,
            image_name VARCHAR(255) DEFAULT NULL,
            sent_groups INT(11) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_image_message_schedule (message_id, scheduled_date, scheduled_time),
            KEY idx_image_scheduled_date (scheduled_date),
            KEY idx_image_message_id (message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function lineGetSetting(PDO $pdo, string $key, string $default = ''): string
{
    ensureLineTables($pdo);

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM line_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function lineSetSetting(PDO $pdo, string $key, string $value): void
{
    ensureLineTables($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO line_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}

function lineResolvedChannelSecret(PDO $pdo): string
{
    $dbValue = lineGetSetting($pdo, 'channel_secret', '');
    if ($dbValue !== '') {
        return $dbValue;
    }
    return LINE_CHANNEL_SECRET;
}

function lineResolvedChannelAccessToken(PDO $pdo): string
{
    $dbValue = lineGetSetting($pdo, 'channel_access_token', '');
    if ($dbValue !== '') {
        return $dbValue;
    }
    return LINE_CHANNEL_ACCESS_TOKEN;
}

function lineResolvedPublicBaseUrl(PDO $pdo): string
{
    $default = 'https://member.imzshop97.com';
    return rtrim(trim(lineGetSetting($pdo, 'public_base_url', $default)), '/');
}

function lineAutoSendEnabled(PDO $pdo): bool
{
    return lineGetSetting($pdo, 'auto_send_results', '1') !== '0';
}

function lineAutoSendTextsEnabled(PDO $pdo): bool
{
    return lineGetSetting($pdo, 'auto_send_texts', '0') === '1';
}

function lineNormalizeScheduledMessageId(string $value): string
{
    $normalized = preg_replace('/[^A-Za-z0-9_-]/', '', trim($value));
    return is_string($normalized) ? $normalized : '';
}

function lineGenerateScheduledMessageId(): string
{
    try {
        return 'msg_' . bin2hex(random_bytes(8));
    } catch (Exception $e) {
        return 'msg_' . str_replace('.', '', uniqid('', true));
    }
}

function lineNormalizeScheduledMessageTime(string $value): string
{
    $value = trim($value);
    if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $value, $matches)) {
        return '';
    }

    return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
}

function lineNormalizeScheduledMessageDate(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return '';
    }

    return $value;
}

function lineNormalizeScheduledWeekday(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('/^[0-6]$/', $value)) {
        return '';
    }

    return $value;
}

function lineWeekdayInRange(int $weekday, string $startDay, string $endDay): bool
{
    if ($startDay === '' && $endDay === '') {
        return true;
    }

    if ($startDay === '' && $endDay !== '') {
        $startDay = $endDay;
    } elseif ($endDay === '' && $startDay !== '') {
        $endDay = $startDay;
    }

    if ($startDay === '' || $endDay === '') {
        return true;
    }

    $start = (int) $startDay;
    $end = (int) $endDay;

    if ($start <= $end) {
        return $weekday >= $start && $weekday <= $end;
    }

    return $weekday >= $start || $weekday <= $end;
}

function lineTimeToMinutes(string $time): ?int
{
    $normalized = lineNormalizeScheduledMessageTime($time);
    if ($normalized === '') {
        return null;
    }

    [$hours, $minutes] = array_map('intval', explode(':', $normalized, 2));
    return ($hours * 60) + $minutes;
}

function lineWeekdayLabel(string $value): string
{
    $labels = [
        '0' => 'อาทิตย์',
        '1' => 'จันทร์',
        '2' => 'อังคาร',
        '3' => 'พุธ',
        '4' => 'พฤหัสบดี',
        '5' => 'ศุกร์',
        '6' => 'เสาร์',
    ];

    return $labels[$value] ?? 'ทุกวัน';
}

function lineDescribeWeekdayRange(string $startDay, string $endDay): string
{
    if ($startDay === '' && $endDay === '') {
        return 'ทุกวัน';
    }

    if ($startDay === '' && $endDay !== '') {
        $startDay = $endDay;
    } elseif ($endDay === '' && $startDay !== '') {
        $endDay = $startDay;
    }

    if ($startDay === $endDay) {
        return lineWeekdayLabel($startDay);
    }

    return lineWeekdayLabel($startDay) . ' - ' . lineWeekdayLabel($endDay);
}

function lineDiagnoseScheduledTextMessages(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $scheduledDate = $now->format('Y-m-d');
    $scheduledTime = $now->format('H:i');
    $scheduledWeekday = (int) $now->format('w');
    $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
    $graceMinutes = 15;
    $messages = lineGetScheduledTextMessages($pdo);
    $groups = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

    $diagnostics = [
        'server_time' => $scheduledTime,
        'server_date' => $scheduledDate,
        'server_weekday' => lineWeekdayLabel((string) $scheduledWeekday),
        'grace_minutes' => $graceMinutes,
        'config_ready' => lineConfigReady($pdo),
        'auto_send_texts_enabled' => lineAutoSendTextsEnabled($pdo),
        'active_groups' => count($groups),
        'total_messages' => count($messages),
        'ready_messages' => 0,
        'due_messages' => 0,
        'reason_counts' => [],
        'items' => [],
    ];

    foreach ($messages as $row) {
        $messageId = (string) ($row['id'] ?? '');
        $messageDate = (string) ($row['date'] ?? '');
        $messageDayStart = (string) ($row['day_start'] ?? '');
        $messageDayEnd = (string) ($row['day_end'] ?? '');
        $messageTime = (string) ($row['time'] ?? '');
        $messageText = trim((string) ($row['message'] ?? ''));
        $enabled = !empty($row['enabled']);
        $messageMinutes = lineTimeToMinutes($messageTime);
        $reason = 'ready';

        if (!$enabled) {
            $reason = 'disabled';
        } elseif ($messageText === '') {
            $reason = 'message_empty';
        } elseif ($messageMinutes === null) {
            $reason = 'time_empty';
        } else {
            $diagnostics['ready_messages']++;

            if ($messageDate !== '' && $messageDate !== $scheduledDate) {
                $reason = 'date_mismatch';
            } elseif ($messageDate === '' && !lineWeekdayInRange($scheduledWeekday, $messageDayStart, $messageDayEnd)) {
                $reason = 'weekday_mismatch';
            } elseif ($messageMinutes > $currentMinutes) {
                $reason = 'not_due_yet';
            } elseif (($currentMinutes - $messageMinutes) > $graceMinutes) {
                $reason = 'outside_grace_window';
            } elseif (lineScheduledTextAlreadySent($pdo, $messageId, $scheduledDate, $messageTime)) {
                $reason = 'already_sent';
            } else {
                $reason = 'due_now';
                $diagnostics['due_messages']++;
            }
        }

        if (!isset($diagnostics['reason_counts'][$reason])) {
            $diagnostics['reason_counts'][$reason] = 0;
        }
        $diagnostics['reason_counts'][$reason]++;

        $diagnostics['items'][] = [
            'id' => $messageId,
            'time' => $messageTime,
            'range' => $messageDate !== '' ? $messageDate : lineDescribeWeekdayRange($messageDayStart, $messageDayEnd),
            'reason' => $reason,
            'preview' => substr((string) preg_replace('/\s+/', ' ', $messageText), 0, 120),
        ];
    }

    return $diagnostics;
}

function lineScheduledImagesDir(): string
{
    return __DIR__ . '/scheduled_uploads';
}

function lineEnsureScheduledImagesDir(): bool
{
    $dir = lineScheduledImagesDir();
    if (is_dir($dir)) {
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
        }
        return is_writable($dir);
    }

    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }

    return is_writable($dir);
}

function lineNormalizeScheduledImageName(string $value): string
{
    $value = trim(basename($value));
    if ($value === '') {
        return '';
    }

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
        return '';
    }

    return $value;
}

function lineNormalizeScheduledImageNames(array $values): array
{
    $normalized = [];
    foreach ($values as $value) {
        if (!is_string($value)) {
            continue;
        }

        $name = lineNormalizeScheduledImageName($value);
        if ($name === '') {
            continue;
        }

        $normalized[$name] = $name;
    }

    return array_values($normalized);
}

function lineScheduledImagePath(string $imageName): ?string
{
    $imageName = lineNormalizeScheduledImageName($imageName);
    if ($imageName === '') {
        return null;
    }

    $path = lineScheduledImagesDir() . '/' . $imageName;
    return is_file($path) ? $path : null;
}

function lineScheduledImageUrl(PDO $pdo, string $imageName): ?string
{
    $path = lineScheduledImagePath($imageName);
    if ($path === null) {
        return null;
    }

    $version = @filemtime($path) ?: time();
    return lineResolvedPublicBaseUrl($pdo) . '/line/scheduled_uploads/' . rawurlencode(basename($path)) . '?v=' . $version;
}

function lineScheduledImageUrls(PDO $pdo, array $imageNames): array
{
    $urls = [];
    foreach (lineNormalizeScheduledImageNames($imageNames) as $imageName) {
        $url = lineScheduledImageUrl($pdo, $imageName);
        if ($url !== null) {
            $urls[] = $url;
        }
    }

    return $urls;
}

function lineIndexedUploadFiles(array $files, int $index): array
{
    if (!isset($files['error'][$index])) {
        return [];
    }

    $names = $files['name'][$index] ?? [];
    $types = $files['type'][$index] ?? [];
    $tmpNames = $files['tmp_name'][$index] ?? [];
    $errors = $files['error'][$index] ?? [];
    $sizes = $files['size'][$index] ?? [];

    if (!is_array($errors)) {
        return [[
            'name' => is_string($names) ? $names : '',
            'type' => is_string($types) ? $types : '',
            'tmp_name' => is_string($tmpNames) ? $tmpNames : '',
            'error' => is_int($errors) ? $errors : UPLOAD_ERR_NO_FILE,
            'size' => is_numeric($sizes) ? (int) $sizes : 0,
        ]];
    }

    $uploads = [];
    foreach ($errors as $fileIndex => $error) {
        $uploads[] = [
            'name' => is_string($names[$fileIndex] ?? '') ? (string) $names[$fileIndex] : '',
            'type' => is_string($types[$fileIndex] ?? '') ? (string) $types[$fileIndex] : '',
            'tmp_name' => is_string($tmpNames[$fileIndex] ?? '') ? (string) $tmpNames[$fileIndex] : '',
            'error' => is_int($error) ? $error : UPLOAD_ERR_NO_FILE,
            'size' => is_numeric($sizes[$fileIndex] ?? 0) ? (int) $sizes[$fileIndex] : 0,
        ];
    }

    return $uploads;
}

function lineSaveScheduledImageUpload(array $upload, string $messageId): array
{
    $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => lineUploadErrorMessage($uploadError)];
    }

    if (!lineEnsureScheduledImagesDir()) {
        return ['ok' => false, 'message' => 'Unable to access scheduled image directory'];
    }

    $tmpPath = (string) ($upload['tmp_name'] ?? '');
    if ($tmpPath === '' || !file_exists($tmpPath)) {
        return ['ok' => false, 'message' => 'Uploaded image temporary file is missing'];
    }

    $originalName = (string) ($upload['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        return ['ok' => false, 'message' => 'Supported image types are PNG, JPG, JPEG, and WEBP'];
    }

    $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', $messageId);
    $fileName = 'scheduled-image-' . ($safeId !== '' ? $safeId : date('YmdHis')) . '-' . time() . '.' . $extension;
    $targetPath = lineScheduledImagesDir() . '/' . $fileName;
    $saveResult = lineStoreUploadedFile($tmpPath, $targetPath);
    if (empty($saveResult['ok'])) {
        return ['ok' => false, 'message' => 'Unable to save uploaded image (' . ($saveResult['error'] ?? 'unknown error') . ')'];
    }

    @chmod($targetPath, 0664);
    return ['ok' => true, 'name' => $fileName, 'path' => $targetPath];
}

function lineSaveScheduledImageUploads(array $uploads, string $messageId): array
{
    $savedNames = [];
    foreach ($uploads as $upload) {
        if (!is_array($upload)) {
            continue;
        }

        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $savedUpload = lineSaveScheduledImageUpload($upload, $messageId);
        if (empty($savedUpload['ok'])) {
            return ['ok' => false, 'message' => (string) ($savedUpload['message'] ?? 'Unable to upload scheduled image')];
        }

        $savedNames[] = (string) ($savedUpload['name'] ?? '');
    }

    return ['ok' => true, 'names' => lineNormalizeScheduledImageNames($savedNames)];
}

function lineGetScheduledImageMessages(PDO $pdo): array
{
    $raw = lineGetSetting($pdo, 'scheduled_image_messages', '[]');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $messages = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = lineNormalizeScheduledMessageId((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = lineGenerateScheduledMessageId();
        }

        $dayStart = lineNormalizeScheduledWeekday((string) ($row['day_start'] ?? ''));
        $dayEnd = lineNormalizeScheduledWeekday((string) ($row['day_end'] ?? ''));
        $time = lineNormalizeScheduledMessageTime((string) ($row['time'] ?? ''));
        $enabled = (string) ($row['enabled'] ?? '1') !== '0';

        $imageNames = [];
        if (isset($row['images']) && is_array($row['images'])) {
            $imageNames = lineNormalizeScheduledImageNames($row['images']);
        } else {
            $legacyImageName = lineNormalizeScheduledImageName((string) ($row['image'] ?? ''));
            if ($legacyImageName !== '') {
                $imageNames = [$legacyImageName];
            }
        }

        $messages[] = [
            'id' => $id,
            'day_start' => $dayStart,
            'day_end' => $dayEnd,
            'time' => $time,
            'image' => $imageNames[0] ?? '',
            'images' => $imageNames,
            'enabled' => $enabled,
        ];
    }

    return $messages;
}

function lineSetScheduledImageMessages(PDO $pdo, array $messages, array $uploadedFiles = []): void
{
    $normalized = [];
    foreach ($messages as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = lineNormalizeScheduledMessageId((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = lineGenerateScheduledMessageId();
        }

        $dayStart = lineNormalizeScheduledWeekday((string) ($row['day_start'] ?? ''));
        $dayEnd = lineNormalizeScheduledWeekday((string) ($row['day_end'] ?? ''));
        $time = lineNormalizeScheduledMessageTime((string) ($row['time'] ?? ''));
        $enabled = (string) ($row['enabled'] ?? '0') === '1';

        $imageNames = [];
        $imagesJson = trim((string) ($row['images_json'] ?? ''));
        if ($imagesJson !== '') {
            $decodedImages = json_decode($imagesJson, true);
            if (is_array($decodedImages)) {
                $imageNames = lineNormalizeScheduledImageNames($decodedImages);
            }
        }
        if (empty($imageNames)) {
            $legacyImageName = lineNormalizeScheduledImageName((string) ($row['image'] ?? ''));
            if ($legacyImageName !== '') {
                $imageNames = [$legacyImageName];
            }
        }

        if ($dayStart === '' && $dayEnd !== '') {
            $dayStart = $dayEnd;
        } elseif ($dayEnd === '' && $dayStart !== '') {
            $dayEnd = $dayStart;
        }

        $uploads = lineIndexedUploadFiles($uploadedFiles, (int) $index);
        if (!empty($uploads)) {
            $savedUploads = lineSaveScheduledImageUploads($uploads, $id);
            if (empty($savedUploads['ok'])) {
                throw new RuntimeException((string) ($savedUploads['message'] ?? 'Unable to upload scheduled image'));
            }
            $imageNames = array_values(array_unique(array_merge($imageNames, $savedUploads['names'] ?? [])));
        }

        $normalized[] = [
            'id' => $id,
            'day_start' => $dayStart,
            'day_end' => $dayEnd,
            'time' => $time,
            'image' => $imageNames[0] ?? '',
            'images' => lineNormalizeScheduledImageNames($imageNames),
            'enabled' => $enabled ? 1 : 0,
        ];
    }

    lineSetSetting(
        $pdo,
        'scheduled_image_messages',
        json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function lineScheduledImageAlreadySent(PDO $pdo, string $messageId, string $scheduledDate, string $scheduledTime): bool
{
    ensureLineTables($pdo);

    $stmt = $pdo->prepare("
        SELECT 1
        FROM line_image_schedule_logs
        WHERE message_id = ?
          AND scheduled_date = ?
          AND scheduled_time = ?
        LIMIT 1
    ");
    $stmt->execute([$messageId, $scheduledDate, $scheduledTime]);

    return (bool) $stmt->fetchColumn();
}

function lineMarkScheduledImageSent(
    PDO $pdo,
    string $messageId,
    string $scheduledDate,
    string $scheduledTime,
    string $imageName,
    int $sentGroups
): void {
    ensureLineTables($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO line_image_schedule_logs (message_id, scheduled_date, scheduled_time, image_name, sent_groups)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            image_name = VALUES(image_name),
            sent_groups = VALUES(sent_groups)
    ");
    $stmt->execute([$messageId, $scheduledDate, $scheduledTime, $imageName, $sentGroups]);
}

function linePushImageMessage(PDO $pdo, string $groupId, string $imageUrl): array
{
    return linePushMessages($pdo, $groupId, [
        [
            'type' => 'image',
            'originalContentUrl' => $imageUrl,
            'previewImageUrl' => $imageUrl,
        ],
    ]);
}

function linePushImageMessages(PDO $pdo, string $groupId, array $imageUrls): array
{
    $imageUrls = array_values(array_filter($imageUrls, static fn($url) => is_string($url) && $url !== ''));
    if (empty($imageUrls)) {
        return ['ok' => false, 'status' => 0, 'body' => 'No image URLs to send'];
    }

    $batches = array_chunk($imageUrls, 5);
    $lastStatus = 0;
    $responses = [];
    foreach ($batches as $batch) {
        $messages = [];
        foreach ($batch as $imageUrl) {
            $messages[] = [
                'type' => 'image',
                'originalContentUrl' => $imageUrl,
                'previewImageUrl' => $imageUrl,
            ];
        }

        $result = linePushMessages($pdo, $groupId, $messages);
        $lastStatus = (int) ($result['status'] ?? 0);
        $responses[] = (string) ($result['body'] ?? '');
        if (empty($result['ok'])) {
            return [
                'ok' => false,
                'status' => $lastStatus,
                'body' => implode(' | ', $responses),
            ];
        }
    }

    return [
        'ok' => true,
        'status' => $lastStatus ?: 200,
        'body' => implode(' | ', $responses),
    ];
}

function linePushImageToActiveGroups(PDO $pdo, array $imageUrls, string $label = '[scheduled image]'): array
{
    $imageUrls = array_values(array_filter($imageUrls, static fn($url) => is_string($url) && $url !== ''));
    if (empty($imageUrls)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'image_missing'];
    }

    if (!lineConfigReady($pdo)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'config_not_ready'];
    }

    $groups = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    if (empty($groups)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'no_groups'];
    }

    $sent = 0;
    $failed = 0;
    foreach ($groups as $group) {
        $groupId = (string) ($group['group_id'] ?? '');
        if ($groupId === '') {
            continue;
        }

        $result = linePushImageMessages($pdo, $groupId, $imageUrls);
        lineLogPushResult($pdo, $groupId, $label . ' ' . implode(', ', $imageUrls), $result);
        if (!empty($result['ok'])) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'skipped' => false];
}

function lineSendDueScheduledImages(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    if (!lineConfigReady($pdo)) {
        return ['sent_messages' => 0, 'sent_groups' => 0, 'due_messages' => 0, 'skipped' => true, 'reason' => 'config_not_ready'];
    }

    $messages = lineGetScheduledImageMessages($pdo);
    if (empty($messages)) {
        return ['sent_messages' => 0, 'sent_groups' => 0, 'due_messages' => 0, 'skipped' => true, 'reason' => 'no_messages'];
    }

    $groups = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    if (empty($groups)) {
        return ['sent_messages' => 0, 'sent_groups' => 0, 'due_messages' => 0, 'skipped' => true, 'reason' => 'no_groups'];
    }

    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $scheduledDate = $now->format('Y-m-d');
    $scheduledTime = $now->format('H:i');
    $scheduledWeekday = (int) $now->format('w');
    $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
    $graceMinutes = 15;

    $sentMessages = 0;
    $sentGroups = 0;
    $dueMessages = 0;
    foreach ($messages as $row) {
        $messageId = (string) ($row['id'] ?? '');
        $messageDayStart = (string) ($row['day_start'] ?? '');
        $messageDayEnd = (string) ($row['day_end'] ?? '');
        $messageTime = (string) ($row['time'] ?? '');
        $imageNames = lineNormalizeScheduledImageNames((array) ($row['images'] ?? []));
        $enabled = !empty($row['enabled']);
        $messageMinutes = lineTimeToMinutes($messageTime);
        $imageUrls = lineScheduledImageUrls($pdo, $imageNames);

        if (!$enabled || $messageId === '' || $messageMinutes === null || empty($imageUrls)) {
            continue;
        }

        if (!lineWeekdayInRange($scheduledWeekday, $messageDayStart, $messageDayEnd)) {
            continue;
        }

        if ($messageMinutes > $currentMinutes || ($currentMinutes - $messageMinutes) > $graceMinutes) {
            continue;
        }

        $dueMessages++;

        if (lineScheduledImageAlreadySent($pdo, $messageId, $scheduledDate, $messageTime)) {
            continue;
        }

        $deliveredGroups = 0;
        foreach ($groups as $group) {
            $groupId = (string) ($group['group_id'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $result = linePushImageMessages($pdo, $groupId, $imageUrls);
            lineLogPushResult($pdo, $groupId, '[scheduled image] ' . implode(', ', $imageUrls), $result);
            if (!empty($result['ok'])) {
                $deliveredGroups++;
            }
        }

        if ($deliveredGroups <= 0) {
            lineLog('Scheduled LINE image not delivered for message ' . $messageId . ' at ' . $scheduledDate . ' ' . $messageTime);
            continue;
        }

        lineMarkScheduledImageSent($pdo, $messageId, $scheduledDate, $messageTime, implode(', ', $imageNames), $deliveredGroups);
        $sentMessages++;
        $sentGroups += $deliveredGroups;
    }

    return [
        'sent_messages' => $sentMessages,
        'sent_groups' => $sentGroups,
        'due_messages' => $dueMessages,
        'skipped' => false,
        'time' => $scheduledTime,
        'grace_minutes' => $graceMinutes,
    ];
}

function lineDiagnoseScheduledImages(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $scheduledDate = $now->format('Y-m-d');
    $scheduledTime = $now->format('H:i');
    $scheduledWeekday = (int) $now->format('w');
    $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
    $graceMinutes = 15;
    $messages = lineGetScheduledImageMessages($pdo);
    $groups = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

    $diagnostics = [
        'server_time' => $scheduledTime,
        'server_date' => $scheduledDate,
        'server_weekday' => lineWeekdayLabel((string) $scheduledWeekday),
        'grace_minutes' => $graceMinutes,
        'config_ready' => lineConfigReady($pdo),
        'active_groups' => count($groups),
        'total_messages' => count($messages),
        'ready_messages' => 0,
        'due_messages' => 0,
        'reason_counts' => [],
        'items' => [],
    ];

    foreach ($messages as $row) {
        $messageId = (string) ($row['id'] ?? '');
        $messageDayStart = (string) ($row['day_start'] ?? '');
        $messageDayEnd = (string) ($row['day_end'] ?? '');
        $messageTime = (string) ($row['time'] ?? '');
        $imageNames = lineNormalizeScheduledImageNames((array) ($row['images'] ?? []));
        $enabled = !empty($row['enabled']);
        $messageMinutes = lineTimeToMinutes($messageTime);
        $imageUrls = lineScheduledImageUrls($pdo, $imageNames);
        $reason = 'ready';

        if (!$enabled) {
            $reason = 'disabled';
        } elseif ($messageMinutes === null) {
            $reason = 'time_empty';
        } elseif (empty($imageUrls)) {
            $reason = 'image_missing';
        } else {
            $diagnostics['ready_messages']++;

            if (!lineWeekdayInRange($scheduledWeekday, $messageDayStart, $messageDayEnd)) {
                $reason = 'weekday_mismatch';
            } elseif ($messageMinutes > $currentMinutes) {
                $reason = 'not_due_yet';
            } elseif (($currentMinutes - $messageMinutes) > $graceMinutes) {
                $reason = 'outside_grace_window';
            } elseif (lineScheduledImageAlreadySent($pdo, $messageId, $scheduledDate, $messageTime)) {
                $reason = 'already_sent';
            } else {
                $reason = 'due_now';
                $diagnostics['due_messages']++;
            }
        }

        if (!isset($diagnostics['reason_counts'][$reason])) {
            $diagnostics['reason_counts'][$reason] = 0;
        }
        $diagnostics['reason_counts'][$reason]++;

        $diagnostics['items'][] = [
            'id' => $messageId,
            'time' => $messageTime,
            'range' => lineDescribeWeekdayRange($messageDayStart, $messageDayEnd),
            'reason' => $reason,
            'preview' => !empty($imageNames) ? implode(', ', $imageNames) : '-',
            'image_urls' => $imageUrls,
            'image_count' => count($imageUrls),
        ];
    }

    return $diagnostics;
}

function lineGetScheduledTextMessages(PDO $pdo): array
{
    $raw = lineGetSetting($pdo, 'scheduled_text_messages', '[]');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $messages = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = lineNormalizeScheduledMessageId((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = lineGenerateScheduledMessageId();
        }

        $date = lineNormalizeScheduledMessageDate((string) ($row['date'] ?? ''));
        $dayStart = lineNormalizeScheduledWeekday((string) ($row['day_start'] ?? ''));
        $dayEnd = lineNormalizeScheduledWeekday((string) ($row['day_end'] ?? ''));
        $time = lineNormalizeScheduledMessageTime((string) ($row['time'] ?? ''));
        $message = trim((string) ($row['message'] ?? ''));
        $enabled = (string) ($row['enabled'] ?? '1') !== '0';

        $messages[] = [
            'id' => $id,
            'date' => $date,
            'day_start' => $dayStart,
            'day_end' => $dayEnd,
            'time' => $time,
            'message' => $message,
            'enabled' => $enabled,
        ];
    }

    return $messages;
}

function lineSetScheduledTextMessages(PDO $pdo, array $messages): void
{
    $normalized = [];
    foreach ($messages as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = lineNormalizeScheduledMessageId((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = lineGenerateScheduledMessageId();
        }

        $date = lineNormalizeScheduledMessageDate((string) ($row['date'] ?? ''));
        $dayStart = lineNormalizeScheduledWeekday((string) ($row['day_start'] ?? ''));
        $dayEnd = lineNormalizeScheduledWeekday((string) ($row['day_end'] ?? ''));
        $time = lineNormalizeScheduledMessageTime((string) ($row['time'] ?? ''));
        $message = trim((string) ($row['message'] ?? ''));
        $enabled = (string) ($row['enabled'] ?? '0') === '1';

        if ($dayStart === '' && $dayEnd !== '') {
            $dayStart = $dayEnd;
        } elseif ($dayEnd === '' && $dayStart !== '') {
            $dayEnd = $dayStart;
        }

        $normalized[] = [
            'id' => $id,
            'date' => $date,
            'day_start' => $dayStart,
            'day_end' => $dayEnd,
            'time' => $time,
            'message' => $message,
            'enabled' => $enabled ? 1 : 0,
        ];
    }

    lineSetSetting(
        $pdo,
        'scheduled_text_messages',
        json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function lineScheduledTextAlreadySent(PDO $pdo, string $messageId, string $scheduledDate, string $scheduledTime): bool
{
    ensureLineTables($pdo);

    $stmt = $pdo->prepare("
        SELECT 1
        FROM line_schedule_logs
        WHERE message_id = ?
          AND scheduled_date = ?
          AND scheduled_time = ?
        LIMIT 1
    ");
    $stmt->execute([$messageId, $scheduledDate, $scheduledTime]);

    return (bool) $stmt->fetchColumn();
}

function lineMarkScheduledTextSent(
    PDO $pdo,
    string $messageId,
    string $scheduledDate,
    string $scheduledTime,
    string $messageText,
    int $sentGroups
): void {
    ensureLineTables($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO line_schedule_logs (message_id, scheduled_date, scheduled_time, message_text, sent_groups)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            message_text = VALUES(message_text),
            sent_groups = VALUES(sent_groups)
    ");
    $stmt->execute([$messageId, $scheduledDate, $scheduledTime, $messageText, $sentGroups]);
}

function lineSendDueScheduledMessages(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    if (!lineConfigReady($pdo)) {
        return ['sent_messages' => 0, 'sent_groups' => 0, 'due_messages' => 0, 'skipped' => true, 'reason' => 'config_not_ready'];
    }

    if (!lineAutoSendTextsEnabled($pdo)) {
        return ['sent_messages' => 0, 'sent_groups' => 0, 'due_messages' => 0, 'skipped' => true, 'reason' => 'auto_send_texts_disabled'];
    }

    $messages = lineGetScheduledTextMessages($pdo);
    if (empty($messages)) {
        return ['sent_messages' => 0, 'sent_groups' => 0, 'due_messages' => 0, 'skipped' => true, 'reason' => 'no_messages'];
    }

    $groups = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    if (empty($groups)) {
        return ['sent_messages' => 0, 'sent_groups' => 0, 'due_messages' => 0, 'skipped' => true, 'reason' => 'no_groups'];
    }

    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $scheduledDate = $now->format('Y-m-d');
    $scheduledTime = $now->format('H:i');
    $scheduledWeekday = (int) $now->format('w');
    $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
    $graceMinutes = 15;

    $sentMessages = 0;
    $sentGroups = 0;
    $dueMessages = 0;
    foreach ($messages as $row) {
        $messageId = (string) ($row['id'] ?? '');
        $messageDate = (string) ($row['date'] ?? '');
        $messageDayStart = (string) ($row['day_start'] ?? '');
        $messageDayEnd = (string) ($row['day_end'] ?? '');
        $messageTime = (string) ($row['time'] ?? '');
        $messageText = trim((string) ($row['message'] ?? ''));
        $enabled = !empty($row['enabled']);
        $messageMinutes = lineTimeToMinutes($messageTime);

        if (!$enabled || $messageId === '' || $messageText === '' || $messageMinutes === null) {
            continue;
        }

        if ($messageDate !== '' && $messageDate !== $scheduledDate) {
            continue;
        }

        if ($messageDate === '' && !lineWeekdayInRange($scheduledWeekday, $messageDayStart, $messageDayEnd)) {
            continue;
        }

        if ($messageMinutes > $currentMinutes || ($currentMinutes - $messageMinutes) > $graceMinutes) {
            continue;
        }

        $dueMessages++;

        if (lineScheduledTextAlreadySent($pdo, $messageId, $scheduledDate, $messageTime)) {
            continue;
        }

        $deliveredGroups = 0;
        foreach ($groups as $group) {
            $groupId = (string) ($group['group_id'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $result = linePushTextMessage($pdo, $groupId, $messageText);
            lineLogPushResult($pdo, $groupId, $messageText, $result);
            if (!empty($result['ok'])) {
                $deliveredGroups++;
            }
        }

        if ($deliveredGroups <= 0) {
            lineLog('Scheduled LINE text not delivered for message ' . $messageId . ' at ' . $scheduledDate . ' ' . $messageTime);
            continue;
        }

        lineMarkScheduledTextSent($pdo, $messageId, $scheduledDate, $messageTime, $messageText, $deliveredGroups);
        $sentMessages++;
        $sentGroups += $deliveredGroups;
    }

    return [
        'sent_messages' => $sentMessages,
        'sent_groups' => $sentGroups,
        'due_messages' => $dueMessages,
        'skipped' => false,
        'time' => $scheduledTime,
        'grace_minutes' => $graceMinutes,
    ];
}

function lineTemplatesDir(): string
{
    return __DIR__ . '/templates';
}

function lineEnsureTemplatesDir(): bool
{
    $dir = lineTemplatesDir();
    if (is_dir($dir)) {
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
        }
        return is_writable($dir);
    }

    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }

    return is_writable($dir);
}

function lineTemplateImagePath(int $lotteryTypeId): ?string
{
    if ($lotteryTypeId <= 0) {
        return null;
    }

    $extensions = ['png', 'jpg', 'webp'];
    foreach ($extensions as $extension) {
        $filePath = lineTemplatesDir() . '/lottery-type-' . $lotteryTypeId . '.' . $extension;
        if (is_file($filePath)) {
            return $filePath;
        }
    }

    return null;
}

function lineTemplateImageUrl(PDO $pdo, int $lotteryTypeId): ?string
{
    $filePath = lineTemplateImagePath($lotteryTypeId);
    if ($filePath === null) {
        return null;
    }

    $baseUrl = lineResolvedPublicBaseUrl($pdo);
    if ($baseUrl === '') {
        return null;
    }

    $version = @filemtime($filePath) ?: time();
    return $baseUrl . '/line/templates/' . rawurlencode(basename($filePath)) . '?v=' . $version;
}

function lineDeleteTemplateImage(int $lotteryTypeId): bool
{
    $deleted = false;
    $extensions = ['png', 'jpg', 'webp'];
    foreach ($extensions as $extension) {
        $filePath = lineTemplatesDir() . '/lottery-type-' . $lotteryTypeId . '.' . $extension;
        if (is_file($filePath) && @unlink($filePath)) {
            $deleted = true;
        }
    }

    return $deleted;
}

function lineUploadErrorMessage(int $errorCode): string
{
    if ($errorCode === UPLOAD_ERR_OK) {
        return '';
    }

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return 'Please choose an image file to upload';
    }

    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        return 'The uploaded image is too large for the server limit';
    }

    if ($errorCode === UPLOAD_ERR_PARTIAL) {
        return 'The image upload was interrupted before completion';
    }

    if ($errorCode === UPLOAD_ERR_NO_TMP_DIR) {
        return 'The server upload temp directory is missing';
    }

    if ($errorCode === UPLOAD_ERR_CANT_WRITE) {
        return 'The server could not write the uploaded image to disk';
    }

    if ($errorCode === UPLOAD_ERR_EXTENSION) {
        return 'A server extension blocked the image upload';
    }

    return 'Image upload failed with error code ' . $errorCode;
}

function lineStoreUploadedFile(string $tmpPath, string $targetPath): array
{
    $attempts = [
        'move_uploaded_file' => function () use ($tmpPath, $targetPath): bool {
            return @move_uploaded_file($tmpPath, $targetPath);
        },
        'rename' => function () use ($tmpPath, $targetPath): bool {
            return @rename($tmpPath, $targetPath);
        },
        'copy' => function () use ($tmpPath, $targetPath): bool {
            return @copy($tmpPath, $targetPath);
        },
        'stream_copy' => function () use ($tmpPath, $targetPath): bool {
            $contents = @file_get_contents($tmpPath);
            if ($contents === false) {
                return false;
            }

            return @file_put_contents($targetPath, $contents) !== false;
        },
    ];

    foreach ($attempts as $method => $callback) {
        if ($callback()) {
            if (file_exists($tmpPath) && $tmpPath !== $targetPath) {
                @unlink($tmpPath);
            }

            return ['ok' => true, 'method' => $method];
        }
    }

    $lastError = error_get_last();
    return [
        'ok' => false,
        'method' => 'none',
        'error' => is_array($lastError) ? (string) ($lastError['message'] ?? '') : '',
    ];
}

function lineSaveTemplateUpload(int $lotteryTypeId, array $upload): array
{
    if ($lotteryTypeId <= 0) {
        return ['ok' => false, 'message' => 'Lottery type is invalid'];
    }

    $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => lineUploadErrorMessage($uploadError)];
    }

    if (!lineEnsureTemplatesDir()) {
        $dir = lineTemplatesDir();
        $debugInfo = sprintf(
            'dir=%s exists=%s writable=%s perms=%s',
            $dir,
            is_dir($dir) ? 'yes' : 'no',
            is_writable($dir) ? 'yes' : 'no',
            is_dir($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : 'n/a'
        );
        lineLog('Template upload failed: unable to prepare template directory (' . $debugInfo . ')');
        return ['ok' => false, 'message' => 'Unable to create template directory (' . $debugInfo . ')'];
    }

    $tmpPath = $upload['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'message' => 'Uploaded file is invalid'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = [
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    $extension = $allowed[$mimeType] ?? null;
    if ($extension === null) {
        return ['ok' => false, 'message' => 'Only PNG, JPG, or WEBP images are supported'];
    }

    $dir = lineTemplatesDir();
    $targetPath = $dir . '/lottery-type-' . $lotteryTypeId . '.' . $extension;
    $stagingPath = $dir . '/.upload-lottery-type-' . $lotteryTypeId . '-' . bin2hex(random_bytes(4)) . '.' . $extension;

    if (is_dir($dir) && !is_writable($dir)) {
        @chmod($dir, 0775);
    }

    $saveResult = lineStoreUploadedFile($tmpPath, $stagingPath);
    $saved = !empty($saveResult['ok']) && is_file($stagingPath);

    if (!$saved) {
        $debugInfo = sprintf(
            'dir_exists=%s writable=%s tmp_exists=%s tmp_size=%s perms=%s target=%s method=%s error=%s',
            is_dir($dir) ? 'yes' : 'no',
            is_writable($dir) ? 'yes' : 'no',
            file_exists($tmpPath) ? 'yes' : 'no',
            file_exists($tmpPath) ? filesize($tmpPath) : '0',
            is_dir($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : 'n/a',
            $targetPath,
            $saveResult['method'] ?? 'none',
            $saveResult['error'] ?? ''
        );
        lineLog('Template upload failed: ' . $debugInfo);
        return ['ok' => false, 'message' => 'Unable to save uploaded image (' . $debugInfo . ')'];
    }

    @chmod($stagingPath, 0664);
    lineDeleteTemplateImage($lotteryTypeId);

    if ($stagingPath !== $targetPath && !@rename($stagingPath, $targetPath)) {
        if (!@copy($stagingPath, $targetPath)) {
            $lastError = error_get_last();
            $debugInfo = sprintf(
                'staging=%s target=%s writable=%s error=%s',
                $stagingPath,
                $targetPath,
                is_writable($dir) ? 'yes' : 'no',
                is_array($lastError) ? (string) ($lastError['message'] ?? '') : ''
            );
            lineLog('Template finalize failed: ' . $debugInfo);
            @unlink($stagingPath);
            return ['ok' => false, 'message' => 'Unable to finalize uploaded image (' . $debugInfo . ')'];
        }

        @unlink($stagingPath);
    }

    @chmod($targetPath, 0664);

    return [
        'ok' => true,
        'message' => 'Template image uploaded successfully',
        'path' => $targetPath,
        'method' => $saveResult['method'] ?? 'unknown',
    ];
}

function lineSharedTemplateGroups(): array
{
    return [
        'thai' => 'ไทย',
        'laos' => 'ลาว',
        'vietnam' => 'ฮานอย / เวียดนาม',
        'usa' => 'อเมริกา / ดาวโจนส์',
        'korea' => 'เกาหลี',
        'japan' => 'ญี่ปุ่น',
        'germany' => 'เยอรมัน',
        'uk' => 'อังกฤษ',
        'egypt' => 'อียิปต์',
        'china' => 'จีน',
        'hongkong' => 'ฮ่องกง',
        'taiwan' => 'ไต้หวัน',
        'india' => 'อินเดีย',
        'singapore' => 'สิงคโปร์',
        'malaysia' => 'มาเลย์',
        'russia' => 'รัสเซีย',
    ];
}

function lineSharedTemplateKey(string $groupKey): string
{
    $groupKey = strtolower(trim($groupKey));
    $groups = lineSharedTemplateGroups();
    return isset($groups[$groupKey]) ? $groupKey : '';
}

function lineSharedTemplateImagePath(string $groupKey): ?string
{
    $groupKey = lineSharedTemplateKey($groupKey);
    if ($groupKey === '') {
        return null;
    }

    $extensions = ['png', 'jpg', 'webp'];
    foreach ($extensions as $extension) {
        $filePath = lineTemplatesDir() . '/shared-group-' . $groupKey . '.' . $extension;
        if (is_file($filePath)) {
            return $filePath;
        }
    }

    return null;
}

function lineSharedTemplateImageUrl(PDO $pdo, string $groupKey): ?string
{
    $filePath = lineSharedTemplateImagePath($groupKey);
    if ($filePath === null) {
        return null;
    }

    $baseUrl = lineResolvedPublicBaseUrl($pdo);
    if ($baseUrl === '') {
        return null;
    }

    $version = @filemtime($filePath) ?: time();
    return $baseUrl . '/line/templates/' . rawurlencode(basename($filePath)) . '?v=' . $version;
}

function lineDeleteSharedTemplateImage(string $groupKey): bool
{
    $groupKey = lineSharedTemplateKey($groupKey);
    if ($groupKey === '') {
        return false;
    }

    $deleted = false;
    $extensions = ['png', 'jpg', 'webp'];
    foreach ($extensions as $extension) {
        $filePath = lineTemplatesDir() . '/shared-group-' . $groupKey . '.' . $extension;
        if (is_file($filePath) && @unlink($filePath)) {
            $deleted = true;
        }
    }

    return $deleted;
}

function lineSaveSharedTemplateUpload(string $groupKey, array $upload): array
{
    $groupKey = lineSharedTemplateKey($groupKey);
    if ($groupKey === '') {
        return ['ok' => false, 'message' => 'Template group is invalid'];
    }

    $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => lineUploadErrorMessage($uploadError)];
    }

    if (!lineEnsureTemplatesDir()) {
        $dir = lineTemplatesDir();
        $debugInfo = sprintf(
            'dir=%s exists=%s writable=%s perms=%s',
            $dir,
            is_dir($dir) ? 'yes' : 'no',
            is_writable($dir) ? 'yes' : 'no',
            is_dir($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : 'n/a'
        );
        lineLog('Shared template upload failed: unable to prepare template directory (' . $debugInfo . ')');
        return ['ok' => false, 'message' => 'Unable to create template directory (' . $debugInfo . ')'];
    }

    $tmpPath = $upload['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'message' => 'Uploaded file is invalid'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = [
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    $extension = $allowed[$mimeType] ?? null;
    if ($extension === null) {
        return ['ok' => false, 'message' => 'Only PNG, JPG, or WEBP images are supported'];
    }

    $dir = lineTemplatesDir();
    $targetPath = $dir . '/shared-group-' . $groupKey . '.' . $extension;
    $stagingPath = $dir . '/.upload-shared-group-' . $groupKey . '-' . bin2hex(random_bytes(4)) . '.' . $extension;

    if (is_dir($dir) && !is_writable($dir)) {
        @chmod($dir, 0775);
    }

    $saveResult = lineStoreUploadedFile($tmpPath, $stagingPath);
    $saved = !empty($saveResult['ok']) && is_file($stagingPath);

    if (!$saved) {
        $debugInfo = sprintf(
            'dir_exists=%s writable=%s tmp_exists=%s tmp_size=%s perms=%s target=%s method=%s error=%s',
            is_dir($dir) ? 'yes' : 'no',
            is_writable($dir) ? 'yes' : 'no',
            file_exists($tmpPath) ? 'yes' : 'no',
            file_exists($tmpPath) ? filesize($tmpPath) : '0',
            is_dir($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : 'n/a',
            $targetPath,
            $saveResult['method'] ?? 'none',
            $saveResult['error'] ?? ''
        );
        lineLog('Shared template upload failed: ' . $debugInfo);
        return ['ok' => false, 'message' => 'Unable to save uploaded image (' . $debugInfo . ')'];
    }

    @chmod($stagingPath, 0664);
    lineDeleteSharedTemplateImage($groupKey);

    if ($stagingPath !== $targetPath && !@rename($stagingPath, $targetPath)) {
        if (!@copy($stagingPath, $targetPath)) {
            $lastError = error_get_last();
            $debugInfo = sprintf(
                'staging=%s target=%s writable=%s error=%s',
                $stagingPath,
                $targetPath,
                is_writable($dir) ? 'yes' : 'no',
                is_array($lastError) ? (string) ($lastError['message'] ?? '') : ''
            );
            lineLog('Shared template finalize failed: ' . $debugInfo);
            @unlink($stagingPath);
            return ['ok' => false, 'message' => 'Unable to finalize uploaded image (' . $debugInfo . ')'];
        }

        @unlink($stagingPath);
    }

    @chmod($targetPath, 0664);

    return [
        'ok' => true,
        'message' => 'Shared template image uploaded successfully',
        'path' => $targetPath,
        'method' => $saveResult['method'] ?? 'unknown',
    ];
}

function lineDetectTemplateGroupKey(array $lotteryRow): string
{
    $lotteryName = trim((string) ($lotteryRow['lottery_name'] ?? $lotteryRow['name'] ?? ''));
    $flagEmoji = trim((string) ($lotteryRow['flag_emoji'] ?? ''));
    $countryCode = '';

    if (function_exists('getFlagForCountry')) {
        $flagUrl = getFlagForCountry($flagEmoji, $lotteryName);
        if (preg_match('#/([a-z]{2})\.png$#i', (string) $flagUrl, $matches)) {
            $countryCode = strtolower($matches[1]);
        }
    }

    $countryMap = [
        'th' => 'thai',
        'la' => 'laos',
        'vn' => 'vietnam',
        'us' => 'usa',
        'kr' => 'korea',
        'jp' => 'japan',
        'de' => 'germany',
        'gb' => 'uk',
        'eg' => 'egypt',
        'cn' => 'china',
        'hk' => 'hongkong',
        'tw' => 'taiwan',
        'in' => 'india',
        'sg' => 'singapore',
        'my' => 'malaysia',
        'ru' => 'russia',
    ];

    if ($countryCode !== '' && isset($countryMap[$countryCode])) {
        return $countryMap[$countryCode];
    }

    $keywordMap = [
        'ฮานอย' => 'vietnam',
        'เวียดนาม' => 'vietnam',
        'ลาว' => 'laos',
        'ไทย' => 'thai',
        'รัฐบาล' => 'thai',
        'ออมสิน' => 'thai',
        'ธกส' => 'thai',
        'ดาวโจนส์' => 'usa',
        'อเมริกา' => 'usa',
        'เกาหลี' => 'korea',
        'ญี่ปุ่น' => 'japan',
        'เยอรมัน' => 'germany',
        'อังกฤษ' => 'uk',
        'อียิปต์' => 'egypt',
        'จีน' => 'china',
        'ฮ่องกง' => 'hongkong',
        'ไต้หวัน' => 'taiwan',
        'อินเดีย' => 'india',
        'สิงคโปร์' => 'singapore',
        'มาเลย์' => 'malaysia',
        'รัสเซีย' => 'russia',
    ];

    foreach ($keywordMap as $keyword => $groupKey) {
        if ($lotteryName !== '' && mb_strpos($lotteryName, $keyword) !== false) {
            return $groupKey;
        }
    }

    return '';
}

function lineResolveTemplateImageInfo(PDO $pdo, array $lotteryRow): ?array
{
    $lotteryTypeId = (int) ($lotteryRow['lottery_type_id'] ?? $lotteryRow['id'] ?? 0);
    $exactPath = $lotteryTypeId > 0 ? lineTemplateImagePath($lotteryTypeId) : null;
    if ($exactPath !== null) {
        $version = @filemtime($exactPath) ?: time();
        return [
            'path' => $exactPath,
            'url' => lineResolvedPublicBaseUrl($pdo) . '/line/templates/' . rawurlencode(basename($exactPath)) . '?v=' . $version,
            'source_type' => 'lottery',
            'source_key' => (string) $lotteryTypeId,
            'source_label' => 'เฉพาะหวย',
        ];
    }

    $groupKey = lineDetectTemplateGroupKey($lotteryRow);
    $sharedPath = $groupKey !== '' ? lineSharedTemplateImagePath($groupKey) : null;
    if ($sharedPath !== null) {
        $version = @filemtime($sharedPath) ?: time();
        $groups = lineSharedTemplateGroups();
        return [
            'path' => $sharedPath,
            'url' => lineResolvedPublicBaseUrl($pdo) . '/line/templates/' . rawurlencode(basename($sharedPath)) . '?v=' . $version,
            'source_type' => 'shared',
            'source_key' => $groupKey,
            'source_label' => 'กลุ่มร่วม: ' . ($groups[$groupKey] ?? $groupKey),
        ];
    }

    return null;
}

function lineConfigReady(PDO $pdo): bool
{
    return lineResolvedChannelSecret($pdo) !== '' && lineResolvedChannelAccessToken($pdo) !== '';
}

function linePushMessages(PDO $pdo, string $to, array $messages): array
{
    $accessToken = lineResolvedChannelAccessToken($pdo);

    if ($accessToken === '') {
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'LINE_CHANNEL_ACCESS_TOKEN is not configured',
        ];
    }

    $payload = [
        'to' => $to,
        'messages' => array_values($messages),
    ];

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $response = curl_error($ch);
    }
    curl_close($ch);

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => (string) $response,
    ];
}

function linePushTextMessage(PDO $pdo, string $groupId, string $message): array
{
    return linePushMessages($pdo, $groupId, [
        [
            'type' => 'text',
            'text' => $message,
        ],
    ]);
}

function lineLogPushResult(PDO $pdo, string $groupId, string $message, array $result): void
{
    ensureLineTables($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO line_message_logs (group_id, message_text, response_code, response_body, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $groupId,
        $message,
        $result['status'] ?? 0,
        $result['body'] ?? '',
        !empty($result['ok']) ? 'success' : 'failed',
    ]);
}

function lineFetchGroupSummary(PDO $pdo, string $groupId): ?array
{
    $accessToken = lineResolvedChannelAccessToken($pdo);

    if ($accessToken === '' || $groupId === '') {
        return null;
    }

    $ch = curl_init('https://api.line.me/v2/bot/group/' . rawurlencode($groupId) . '/summary');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function lineUpsertGroup(PDO $pdo, array $source, ?string $eventType = null): void
{
    $sourceType = $source['type'] ?? '';
    $groupId = $source['groupId'] ?? '';

    if ($sourceType !== 'group' || $groupId === '') {
        return;
    }

    ensureLineTables($pdo);

    $summary = lineFetchGroupSummary($pdo, $groupId);
    $groupName = $summary['groupName'] ?? null;
    $rawSource = json_encode($source, JSON_UNESCAPED_UNICODE);
    $isActive = ($eventType === 'leave') ? 0 : 1;

    $stmt = $pdo->prepare("
        INSERT INTO line_groups (group_id, source_type, group_name, joined_at, last_seen_at, is_active, raw_source)
        VALUES (?, ?, ?, NOW(), NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE
            source_type = VALUES(source_type),
            group_name = COALESCE(VALUES(group_name), group_name),
            last_seen_at = NOW(),
            is_active = VALUES(is_active),
            raw_source = VALUES(raw_source)
    ");
    $stmt->execute([$groupId, $sourceType, $groupName, $isActive, $rawSource]);
}

function lineResultSummaryText(array $resultRow): string
{
    $parts = [];
    $parts[] = ($resultRow['lottery_name'] ?? 'ผลหวย') . ' งวด ' . formatDateDisplay($resultRow['draw_date'] ?? '');

    if (!empty($resultRow['three_top'])) {
        $parts[] = '3 บน ' . $resultRow['three_top'];
    }
    if (!empty($resultRow['two_top'])) {
        $parts[] = '2 บน ' . $resultRow['two_top'];
    }
    if (!empty($resultRow['two_bot'])) {
        $parts[] = '2 ล่าง ' . $resultRow['two_bot'];
    }

    return implode(' | ', $parts);
}

function linePushTextToActiveGroups(PDO $pdo, string $message): array
{
    $message = trim($message);
    if ($message === '') {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'message_empty'];
    }

    if (!lineConfigReady($pdo)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'config_not_ready'];
    }

    $groups = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    if (empty($groups)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'no_groups'];
    }

    $sent = 0;
    $failed = 0;
    foreach ($groups as $group) {
        $groupId = (string) ($group['group_id'] ?? '');
        if ($groupId === '') {
            continue;
        }

        $result = linePushTextMessage($pdo, $groupId, $message);
        lineLogPushResult($pdo, $groupId, $message, $result);
        if (!empty($result['ok'])) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'skipped' => false];
}

function lineRenderAutoTextTemplate(string $template, array $resultRow): string
{
    $drawDate = (string) ($resultRow['draw_date'] ?? '');
    $replacements = [
        '{lottery_name}' => (string) ($resultRow['lottery_name'] ?? ''),
        '{category_name}' => (string) ($resultRow['category_name'] ?? ''),
        '{draw_date}' => $drawDate,
        '{draw_date_display}' => function_exists('formatDateDisplay') ? (string) formatDateDisplay($drawDate) : $drawDate,
        '{three_top}' => (string) ($resultRow['three_top'] ?? ''),
        '{two_top}' => (string) ($resultRow['two_top'] ?? ''),
        '{two_bot}' => (string) ($resultRow['two_bot'] ?? ''),
    ];

    $message = strtr($template, $replacements);
    $message = preg_replace("/\r\n?/", "\n", $message);
    $message = preg_replace("/\n{3,}/", "\n\n", (string) $message);

    return trim((string) $message);
}

function lineCompactErrorDetail(string $detail, int $maxLength = 280): string
{
    $detail = trim(preg_replace('/\s+/u', ' ', $detail));
    if ($detail === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($detail) <= $maxLength) {
            return $detail;
        }

        return rtrim(mb_substr($detail, 0, $maxLength - 3)) . '...';
    }

    if (strlen($detail) <= $maxLength) {
        return $detail;
    }

    return rtrim(substr($detail, 0, $maxLength - 3)) . '...';
}

function lineResolvedNodeBinary(): string
{
    $envNode = getenv('NODE_PATH') ?: '';
    if ($envNode !== '') {
        return $envNode;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        return 'node';
    }

    return '/usr/bin/node';
}

function lineResolvedPuppeteerCacheDir(): string
{
    return dirname(__DIR__) . '/.cache/puppeteer';
}

function lineFindTtfFont(): ?string
{
    $candidates = [
        __DIR__ . '/fonts/NotoSansThai-Regular.ttf',
        __DIR__ . '/fonts/Prompt-Regular.ttf',
        'C:/Windows/Fonts/tahoma.ttf',
        'C:/Windows/Fonts/arial.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansThai-Regular.ttf',
        '/usr/share/fonts/opentype/noto/NotoSansThai-Regular.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
    ];

    foreach ($candidates as $fontPath) {
        if (is_file($fontPath)) {
            return $fontPath;
        }
    }

    $scanDirectories = [
        __DIR__ . '/fonts',
        '/usr/share/fonts',
        '/usr/local/share/fonts',
        'C:/Windows/Fonts',
    ];

    foreach ($scanDirectories as $directory) {
        if (!is_dir($directory)) {
            continue;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $extension = strtolower((string) $fileInfo->getExtension());
                if (!in_array($extension, ['ttf', 'otf'], true)) {
                    continue;
                }

                return $fileInfo->getPathname();
            }
        } catch (Throwable $e) {
            continue;
        }
    }

    return null;
}

function lineGdColor($image, string $hex, int $alpha = 0): int
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $red = hexdec(substr($hex, 0, 2));
    $green = hexdec(substr($hex, 2, 2));
    $blue = hexdec(substr($hex, 4, 2));

    return imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
}

function lineWrapTextForImage(string $text, string $fontFile, int $fontSize, int $maxWidth): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return ['-'];
    }

    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        $box = imagettfbbox($fontSize, 0, $fontFile, $candidate);
        $width = $box ? abs($box[2] - $box[0]) : 0;

        if ($current !== '' && $width > $maxWidth) {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $candidate;
        }
    }

    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines ?: ['-'];
}

function lineRenderResultImageWithGd(array $payload, string $outputPath): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $fontFile = lineFindTtfFont();
    if ($fontFile === null) {
        lineLog('GD renderer fallback: no TTF font found, using built-in font');

        $width = 1040;
        $height = 900;
        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            return false;
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $green = imagecolorallocate($image, 18, 144, 75);
        $light = imagecolorallocate($image, 242, 249, 244);
        $dark = imagecolorallocate($image, 24, 44, 31);
        $blue = imagecolorallocate($image, 37, 99, 235);

        imagefill($image, 0, 0, $light);
        imagefilledrectangle($image, 40, 40, 1000, 170, $green);
        imagefilledrectangle($image, 40, 200, 1000, 860, $white);

        imagestring($image, 5, 70, 70, 'LOTTERY RESULT', $white);
        imagestring($image, 4, 70, 110, substr((string) ($payload['site_name'] ?? ''), 0, 50), $white);
        imagestring($image, 4, 70, 230, 'DATE: ' . (string) ($payload['draw_date_display'] ?? $payload['draw_date'] ?? ''), $dark);
        imagestring($image, 5, 70, 320, '3 TOP : ' . (string) ($payload['three_top'] ?? '-'), $dark);
        imagestring($image, 5, 70, 420, '2 TOP : ' . (string) ($payload['two_top'] ?? '-'), $dark);
        imagestring($image, 5, 70, 520, '2 BOT : ' . (string) ($payload['two_bot'] ?? '-'), $dark);
        imagestring($image, 4, 70, 650, 'SUMMARY', $blue);
        imagestring($image, 3, 70, 700, substr((string) ($payload['summary_text'] ?? ''), 0, 120), $dark);
        imagestring($image, 3, 70, 780, 'Generated: ' . (string) ($payload['generated_at'] ?? ''), $dark);

        $saved = imagepng($image, $outputPath, 6);
        imagedestroy($image);

        return $saved && file_exists($outputPath);
    }

    $width = 1040;
    $height = 1280;
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
        return false;
    }

    imageantialias($image, true);
    imagesavealpha($image, true);

    $white = lineGdColor($image, '#FFFFFF');
    $bg = lineGdColor($image, '#F4FBF6');
    $hero = lineGdColor($image, '#12904B');
    $heroAlt = lineGdColor($image, '#28B764');
    $border = lineGdColor($image, '#DCEEE2');
    $textDark = lineGdColor($image, '#173525');
    $textMuted = lineGdColor($image, '#547062');
    $primaryBox = lineGdColor($image, '#E9FFF0');
    $secondaryBox = lineGdColor($image, '#EEF6FF');
    $accentBox = lineGdColor($image, '#FFF6E7');
    $stampBg = lineGdColor($image, '#143B28');

    imagefill($image, 0, 0, $bg);

    imagefilledrectangle($image, 40, 40, 1000, 1240, $white);
    imagerectangle($image, 40, 40, 1000, 1240, $border);

    imagefilledrectangle($image, 40, 40, 1000, 330, $hero);
    imagefilledrectangle($image, 520, 40, 1000, 330, $heroAlt);

    imagettftext($image, 30, 0, 90, 110, $white, $fontFile, 'ประกาศผลหวย');
    imagettftext($image, 20, 0, 90, 160, $white, $fontFile, (string) ($payload['site_name'] ?? ''));
    imagettftext($image, 40, 0, 90, 230, $white, $fontFile, (string) ($payload['lottery_name'] ?? 'ผลหวย'));
    imagettftext($image, 22, 0, 90, 285, $white, $fontFile, (string) ($payload['category_name'] ?? ''));
    imagettftext($image, 22, 0, 640, 285, $white, $fontFile, 'งวดวันที่ ' . (string) ($payload['draw_date_display'] ?? ''));

    $cards = [
        ['x1' => 90, 'x2' => 380, 'label' => '3 ตัวบน', 'value' => (string) ($payload['three_top'] ?? '-'), 'bg' => $primaryBox],
        ['x1' => 400, 'x2' => 690, 'label' => '2 ตัวบน', 'value' => (string) ($payload['two_top'] ?? '-'), 'bg' => $secondaryBox],
        ['x1' => 710, 'x2' => 950, 'label' => '2 ตัวล่าง', 'value' => (string) ($payload['two_bot'] ?? '-'), 'bg' => $accentBox],
    ];

    foreach ($cards as $card) {
        imagefilledrectangle($image, $card['x1'], 390, $card['x2'], 690, $card['bg']);
        imagerectangle($image, $card['x1'], 390, $card['x2'], 690, $border);
        imagettftext($image, 24, 0, $card['x1'] + 22, 450, $textMuted, $fontFile, $card['label']);
        imagettftext($image, 60, 0, $card['x1'] + 30, 590, $textDark, $fontFile, $card['value'] !== '' ? $card['value'] : '-');
    }

    imagefilledrectangle($image, 90, 740, 700, 1030, $bg);
    imagerectangle($image, 90, 740, 700, 1030, $border);
    imagettftext($image, 20, 0, 120, 790, $textMuted, $fontFile, 'สรุปผลล่าสุด');

    $summaryLines = lineWrapTextForImage((string) ($payload['summary_text'] ?? ''), $fontFile, 28, 540);
    $summaryY = 850;
    foreach (array_slice($summaryLines, 0, 5) as $line) {
        imagettftext($image, 28, 0, 120, $summaryY, $textDark, $fontFile, $line);
        $summaryY += 52;
    }

    imagefilledrectangle($image, 730, 740, 950, 1030, $stampBg);
    imagettftext($image, 18, 0, 760, 790, $white, $fontFile, 'สร้างภาพเมื่อ');
    $stampLines = lineWrapTextForImage((string) ($payload['generated_at'] ?? ''), $fontFile, 24, 150);
    $stampY = 860;
    foreach (array_slice($stampLines, 0, 3) as $line) {
        imagettftext($image, 24, 0, 760, $stampY, $white, $fontFile, $line);
        $stampY += 42;
    }

    $saved = imagepng($image, $outputPath, 6);
    imagedestroy($image);

    return $saved && file_exists($outputPath);
}

function lineGenerateResultImage(PDO $pdo, array $resultRow): ?array
{
    $baseUrl = lineResolvedPublicBaseUrl($pdo);
    if ($baseUrl === '') {
        return ['error' => 'Public base URL is not configured'];
    }

    $rootDir = dirname(__DIR__);
    $generatedDir = $rootDir . '/line/generated';
    if (!is_dir($generatedDir) && !mkdir($generatedDir, 0775, true) && !is_dir($generatedDir)) {
        lineLog('Unable to create line/generated directory');
        return ['error' => 'line/generated directory is not writable'];
    }

    $safeDate = preg_replace('/[^0-9\-]/', '', (string)($resultRow['draw_date'] ?? date('Y-m-d')));
    $safeLotteryId = (int)($resultRow['lottery_type_id'] ?? 0);
    $uniqueSuffix = date('YmdHis') . '-' . substr(md5(uniqid((string) $safeLotteryId, true)), 0, 8);
    $outputFilename = 'result-' . $safeLotteryId . '-' . $safeDate . '-' . $uniqueSuffix . '.png';
    $outputPath = $generatedDir . '/' . $outputFilename;
    $templateInfo = lineResolveTemplateImageInfo($pdo, $resultRow);

    $payload = [
        'site_name' => SITE_NAME,
        'lottery_name' => $resultRow['lottery_name'] ?? '',
        'category_name' => $resultRow['category_name'] ?? '',
        'draw_date' => $resultRow['draw_date'] ?? '',
        'draw_date_display' => formatDateDisplay($resultRow['draw_date'] ?? ''),
        'three_top' => $resultRow['three_top'] ?? '',
        'two_top' => $resultRow['two_top'] ?? '',
        'two_bot' => $resultRow['two_bot'] ?? '',
        'summary_text' => lineResultSummaryText($resultRow),
        'generated_at' => date('d-m-Y H:i:s'),
        'background_image_path' => $templateInfo['path'] ?? '',
    ];

    $tempJson = tempnam(sys_get_temp_dir(), 'line_result_');
    if ($tempJson === false) {
        return ['error' => 'Unable to create temporary JSON file for renderer'];
    }

    $cleanupPattern = $generatedDir . '/result-' . $safeLotteryId . '-' . $safeDate . '*.png';
    foreach (glob($cleanupPattern) ?: [] as $oldFile) {
        if (is_file($oldFile) && $oldFile !== $outputPath) {
            @unlink($oldFile);
        }
    }

    file_put_contents($tempJson, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $scriptPath = $rootDir . '/scripts/render_line_result_image.js';
    $nodeBinary = lineResolvedNodeBinary();
    $output = ['Node renderer skipped'];
    $exitCode = 1;

    if (is_file($scriptPath)) {
        $puppeteerCacheDir = lineResolvedPuppeteerCacheDir();
        if (!is_dir($puppeteerCacheDir) && !mkdir($puppeteerCacheDir, 0775, true) && !is_dir($puppeteerCacheDir)) {
            lineLog('Unable to create puppeteer cache directory');
        }

        $commandPrefix = '';
        if (PHP_OS_FAMILY !== 'Windows') {
            $commandPrefix =
                'PUPPETEER_CACHE_DIR=' . escapeshellarg($puppeteerCacheDir) . ' ' .
                'HOME=' . escapeshellarg($rootDir) . ' ';
        }

        $command = $commandPrefix . escapeshellarg($nodeBinary) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tempJson) . ' ' . escapeshellarg($outputPath) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
    }

    @unlink($tempJson);

    if (($exitCode !== 0 || !file_exists($outputPath)) && lineRenderResultImageWithGd($payload, $outputPath)) {
        return [
            'path' => $outputPath,
            'url' => $baseUrl . '/line/generated/' . rawurlencode($outputFilename),
            'renderer' => 'gd',
        ];
    }

    if ($exitCode !== 0 || !file_exists($outputPath)) {
        $detailParts = [];
        $nodeOutput = lineCompactErrorDetail(implode(' | ', array_filter($output, static function ($line) {
            return trim((string) $line) !== '';
        })));
        if ($nodeOutput !== '') {
            $detailParts[] = $nodeOutput;
        }
        if (!extension_loaded('gd')) {
            $detailParts[] = 'GD extension is not enabled';
        }
        if (!is_writable($generatedDir)) {
            $detailParts[] = 'line/generated is not writable';
        }
        $detailParts[] = 'node=' . $nodeBinary;
        $detailParts[] = 'exit=' . $exitCode;
        $detail = implode(' | ', array_unique(array_filter($detailParts)));

        lineLog('Result image render failed: ' . $detail);
        return ['error' => $detail];
    }

    return [
        'path' => $outputPath,
        'url' => $baseUrl . '/line/generated/' . rawurlencode($outputFilename),
        'renderer' => 'node',
    ];
}

function lineFetchResultRow(PDO $pdo, int $lotteryTypeId, string $drawDate): ?array
{
    $stmt = $pdo->prepare("
        SELECT r.lottery_type_id, r.draw_date, r.three_top, r.two_top, r.two_bot,
               lt.flag_emoji,
               lt.name AS lottery_name, lc.name AS category_name
        FROM results r
        JOIN lottery_types lt ON r.lottery_type_id = lt.id
        JOIN lottery_categories lc ON lt.category_id = lc.id
        WHERE r.lottery_type_id = ? AND r.draw_date = ?
        LIMIT 1
    ");
    $stmt->execute([$lotteryTypeId, $drawDate]);

    $resultRow = $stmt->fetch();
    return $resultRow ?: null;
}

function linePrepareResultImageMessage(PDO $pdo, array $resultRow): array
{
    $image = lineGenerateResultImage($pdo, $resultRow);
    if (!$image || empty($image['url'])) {
        return [
            'ok' => false,
            'reason' => 'image_generation_failed',
            'detail' => lineCompactErrorDetail((string) ($image['error'] ?? 'Image generation failed')),
        ];
    }

    return [
        'ok' => true,
        'summary_text' => lineResultSummaryText($resultRow),
        'messages' => [[
            'type' => 'image',
            'originalContentUrl' => $image['url'],
            'previewImageUrl' => $image['url'],
        ]],
        'image_url' => $image['url'],
        'renderer' => $image['renderer'] ?? '',
    ];
}

function lineSendPreparedResultToGroup(PDO $pdo, string $groupId, array $prepared): array
{
    if ($groupId === '') {
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'Group ID is required',
            'reason' => 'missing_group_id',
        ];
    }

    $result = linePushMessages($pdo, $groupId, $prepared['messages']);
    lineLogPushResult($pdo, $groupId, $prepared['summary_text'], $result);

    return array_merge($result, [
        'summary_text' => $prepared['summary_text'],
        'image_url' => $prepared['image_url'] ?? '',
        'renderer' => $prepared['renderer'] ?? '',
    ]);
}

function linePushResultImageToGroup(PDO $pdo, string $groupId, int $lotteryTypeId, string $drawDate): array
{
    if (!lineConfigReady($pdo)) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'LINE is not configured',
            'reason' => 'config_not_ready',
        ];
    }

    $resultRow = lineFetchResultRow($pdo, $lotteryTypeId, $drawDate);
    if (!$resultRow) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'Result not found',
            'reason' => 'result_not_found',
        ];
    }

    $prepared = linePrepareResultImageMessage($pdo, $resultRow);
    if (empty($prepared['ok'])) {
        $detail = lineCompactErrorDetail((string) ($prepared['detail'] ?? ''));
        lineLog('Manual result image send skipped: image generation failed for lottery_type_id=' . $lotteryTypeId . ' draw_date=' . $drawDate . ($detail !== '' ? ' detail=' . $detail : ''));
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'Image generation failed',
            'reason' => $prepared['reason'] ?? 'image_generation_failed',
            'detail' => $detail,
        ];
    }

    return lineSendPreparedResultToGroup($pdo, $groupId, $prepared);
}

function lineSendResultNotification(PDO $pdo, int $lotteryTypeId, string $drawDate): array
{
    if (!lineConfigReady($pdo) || !lineAutoSendEnabled($pdo)) {
        return ['sent' => 0, 'skipped' => true];
    }

    $resultRow = lineFetchResultRow($pdo, $lotteryTypeId, $drawDate);
    if (!$resultRow) {
        return ['sent' => 0, 'skipped' => true];
    }

    $groups = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    if (empty($groups)) {
        return ['sent' => 0, 'skipped' => true];
    }

    $prepared = linePrepareResultImageMessage($pdo, $resultRow);
    if (empty($prepared['ok'])) {
        $detail = lineCompactErrorDetail((string) ($prepared['detail'] ?? ''));
        lineLog('Result notification skipped: image generation failed for lottery_type_id=' . $lotteryTypeId . ' draw_date=' . $drawDate . ($detail !== '' ? ' detail=' . $detail : ''));
        return [
            'sent' => 0,
            'skipped' => true,
            'used_image' => false,
            'reason' => $prepared['reason'] ?? 'image_generation_failed',
            'detail' => $detail,
        ];
    }

    $sent = 0;
    foreach ($groups as $group) {
        $groupId = $group['group_id'] ?? '';
        if ($groupId === '') {
            continue;
        }

        $result = lineSendPreparedResultToGroup($pdo, $groupId, $prepared);
        if (!empty($result['ok'])) {
            $sent++;
        }
    }

    return [
        'sent' => $sent,
        'skipped' => false,
        'used_image' => true,
        'image_url' => $prepared['image_url'] ?? '',
        'renderer' => $prepared['renderer'] ?? '',
    ];
}

function lineSendConfiguredTextNotification(PDO $pdo, int $lotteryTypeId, string $drawDate): array
{
    return ['sent' => 0, 'skipped' => true, 'reason' => 'deprecated'];
}
