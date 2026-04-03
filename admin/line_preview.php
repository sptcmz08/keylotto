<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../line/common.php';
requireLogin();

$lotteryTypeId = (int) ($_GET['lottery_type_id'] ?? 0);
$drawDate = trim((string) ($_GET['draw_date'] ?? ''));

if ($lotteryTypeId <= 0 || $drawDate === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Preview parameters are invalid';
    exit;
}

$resultRow = lineFetchResultRow($pdo, $lotteryTypeId, $drawDate);
if (!$resultRow) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Result not found';
    exit;
}

$prepared = linePrepareResultImageMessage($pdo, $resultRow);
if (empty($prepared['ok']) || empty($prepared['image_url'])) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Preview image generation failed';
    $detail = trim((string) ($prepared['detail'] ?? ''));
    if ($detail !== '') {
        echo "\n" . $detail;
    }
    exit;
}

header('Location: ' . $prepared['image_url'], true, 302);
exit;
