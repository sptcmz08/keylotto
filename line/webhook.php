<?php
require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    lineJsonResponse(200, [
        'status' => 'ok',
        'message' => 'LINE webhook endpoint is ready',
        'time' => date('c'),
    ]);
}

if ($method !== 'POST') {
    lineJsonResponse(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

$body = lineRawBody();
$signature = lineRequestSignature();

if (!lineVerifySignature($body, $signature, LINE_CHANNEL_SECRET)) {
    lineLog('Invalid webhook signature');
    lineJsonResponse(400, ['status' => 'error', 'message' => 'Invalid signature']);
}

$payload = json_decode($body, true);
if (!is_array($payload)) {
    lineJsonResponse(400, ['status' => 'error', 'message' => 'Invalid JSON payload']);
}

$events = $payload['events'] ?? [];
if (!is_array($events)) {
    $events = [];
}

foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }

    $eventType = $event['type'] ?? '';
    $source = $event['source'] ?? [];

    if (is_array($source)) {
        lineUpsertGroup($pdo, $source, $eventType);
    }

    if ($eventType === 'join') {
        lineLog('Bot joined group: ' . ($source['groupId'] ?? 'unknown'));
    } elseif ($eventType === 'leave') {
        lineLog('Bot left group: ' . ($source['groupId'] ?? 'unknown'));
    } elseif ($eventType === 'message') {
        lineLog('Message event from source type ' . ($source['type'] ?? 'unknown'));
    }
}

lineJsonResponse(200, [
    'status' => 'ok',
    'received' => count($events),
]);
