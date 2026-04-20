<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../line/common.php';

session_start();

// ตรวจสอบ session
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Unauthorized']));
}

// รับ email + password จาก POST body
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($email) || empty($password)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'กรุณากรอก email และ password']);
    exit;
}

// ส่ง POST ไป worker /login
$baseUrl = lineResolvedPersonalApiUrl($pdo);
if ($baseUrl === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'LINE Personal API URL is not configured']);
    exit;
}

$url = $baseUrl . '/login';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode(['email' => $email, 'password' => $password]),
    CURLOPT_TIMEOUT => 70,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$data = json_decode($response, true);

header('Content-Type: application/json; charset=utf-8');

if ($response === false) {
    echo json_encode(['ok' => false, 'error' => 'CURL Error: ' . $curlError]);
    exit;
}

if ($status >= 200 && $status < 300 && !empty($data['success'])) {
    echo json_encode([
        'ok' => true,
        'status' => $data['status'] ?? '',
        'logged_in' => !empty($data['logged_in']),
        'pin_code' => $data['pin_code'] ?? '',
        'message' => $data['message'] ?? 'Login successful',
        'base64' => $data['screenshot_base64'] ?? null,
    ]);
} else {
    $detail = $data['detail'] ?? null;
    if (is_array($detail)) {
        $detail = $detail['error'] ?? json_encode($detail, JSON_UNESCAPED_UNICODE);
    }
    echo json_encode([
        'ok' => false,
        'status' => $data['status'] ?? '',
        'logged_in' => !empty($data['logged_in']),
        'error' => $data['error'] ?? $detail ?? "HTTP $status",
        'hint' => $data['hint'] ?? '',
        'base64' => $data['screenshot_base64'] ?? null,
    ]);
}
