<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../line/common.php';

session_start();

// ตรวจสอบ session ตามโครงสร้างของโปรเจกต์นี้
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Unauthorized']));
}

$result = lineFetchPersonalApiJson($pdo, '/screenshot');

if (!empty($result['data']['screenshot_base64'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'base64' => $result['data']['screenshot_base64']]);
} else {
    header('Content-Type: application/json; charset=utf-8');
    $statusCode = $result['status'] ?? 0;
    $bodyPreview = substr((string)($result['body'] ?? ''), 0, 200);
    echo json_encode(['ok' => false, 'error' => "No screenshot data. HTTP $statusCode — $bodyPreview"]);
}
