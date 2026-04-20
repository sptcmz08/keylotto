<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../line/common.php';

session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$result = lineFetchPersonalApiJson($pdo, '/screenshot');

if (!empty($result['data']['screenshot_base64'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'base64' => $result['data']['screenshot_base64']]);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'No screenshot data. Status: ' . ($result['status'] ?? '?')]);
}
