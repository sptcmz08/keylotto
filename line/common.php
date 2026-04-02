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
}

function lineConfigReady(): bool
{
    return LINE_CHANNEL_SECRET !== '' && LINE_CHANNEL_ACCESS_TOKEN !== '';
}

function linePushTextMessage(string $groupId, string $message): array
{
    if (LINE_CHANNEL_ACCESS_TOKEN === '') {
        return [
            'ok' => false,
            'status' => 0,
            'body' => 'LINE_CHANNEL_ACCESS_TOKEN is not configured',
        ];
    }

    $payload = [
        'to' => $groupId,
        'messages' => [
            [
                'type' => 'text',
                'text' => $message,
            ],
        ],
    ];

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN,
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

function lineFetchGroupSummary(string $groupId): ?array
{
    if (LINE_CHANNEL_ACCESS_TOKEN === '' || $groupId === '') {
        return null;
    }

    $ch = curl_init('https://api.line.me/v2/bot/group/' . rawurlencode($groupId) . '/summary');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN,
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

    $summary = lineFetchGroupSummary($groupId);
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
