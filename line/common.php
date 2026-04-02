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

function lineGenerateResultImage(PDO $pdo, array $resultRow): ?array
{
    $baseUrl = lineResolvedPublicBaseUrl($pdo);
    if ($baseUrl === '') {
        return null;
    }

    $rootDir = dirname(__DIR__);
    $generatedDir = $rootDir . '/line/generated';
    if (!is_dir($generatedDir) && !mkdir($generatedDir, 0775, true) && !is_dir($generatedDir)) {
        lineLog('Unable to create line/generated directory');
        return null;
    }

    $safeDate = preg_replace('/[^0-9\-]/', '', (string)($resultRow['draw_date'] ?? date('Y-m-d')));
    $safeLotteryId = (int)($resultRow['lottery_type_id'] ?? 0);
    $outputFilename = 'result-' . $safeLotteryId . '-' . $safeDate . '.png';
    $outputPath = $generatedDir . '/' . $outputFilename;

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
    ];

    $tempJson = tempnam(sys_get_temp_dir(), 'line_result_');
    if ($tempJson === false) {
        return null;
    }

    file_put_contents($tempJson, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $scriptPath = $rootDir . '/scripts/render_line_result_image.js';
    $nodeBinary = lineResolvedNodeBinary();
    $command = escapeshellarg($nodeBinary) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tempJson) . ' ' . escapeshellarg($outputPath) . ' 2>&1';

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    @unlink($tempJson);

    if ($exitCode !== 0 || !file_exists($outputPath)) {
        lineLog('Result image render failed: ' . implode("\n", $output));
        return null;
    }

    return [
        'path' => $outputPath,
        'url' => $baseUrl . '/line/generated/' . rawurlencode($outputFilename),
    ];
}

function lineSendResultNotification(PDO $pdo, int $lotteryTypeId, string $drawDate): array
{
    if (!lineConfigReady($pdo) || !lineAutoSendEnabled($pdo)) {
        return ['sent' => 0, 'skipped' => true];
    }

    $stmt = $pdo->prepare("
        SELECT r.lottery_type_id, r.draw_date, r.three_top, r.two_top, r.two_bot,
               lt.name AS lottery_name, lc.name AS category_name
        FROM results r
        JOIN lottery_types lt ON r.lottery_type_id = lt.id
        JOIN lottery_categories lc ON lt.category_id = lc.id
        WHERE r.lottery_type_id = ? AND r.draw_date = ?
        LIMIT 1
    ");
    $stmt->execute([$lotteryTypeId, $drawDate]);
    $resultRow = $stmt->fetch();

    if (!$resultRow) {
        return ['sent' => 0, 'skipped' => true];
    }

    $groups = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
    if (empty($groups)) {
        return ['sent' => 0, 'skipped' => true];
    }

    $summaryText = lineResultSummaryText($resultRow);
    $image = lineGenerateResultImage($pdo, $resultRow);

    $messages = [];
    if ($image && !empty($image['url'])) {
        $messages[] = [
            'type' => 'image',
            'originalContentUrl' => $image['url'],
            'previewImageUrl' => $image['url'],
        ];
    } else {
        $messages[] = [
            'type' => 'text',
            'text' => $summaryText,
        ];
    }

    $sent = 0;
    foreach ($groups as $group) {
        $groupId = $group['group_id'] ?? '';
        if ($groupId === '') {
            continue;
        }

        $result = linePushMessages($pdo, $groupId, $messages);
        lineLogPushResult($pdo, $groupId, $summaryText, $result);
        if (!empty($result['ok'])) {
            $sent++;
        }
    }

    return [
        'sent' => $sent,
        'skipped' => false,
        'used_image' => !empty($image['url']),
        'image_url' => $image['url'] ?? '',
    ];
}
