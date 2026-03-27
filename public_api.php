<?php
/**
 * Public Lottery Results API
 * ใช้ดึงผลหวยทั้งหมดจากระบบไปแสดงในเว็บอื่น
 * 
 * Usage:
 *   /public_api.php?action=latest_results&key=YOUR_API_KEY
 *   /public_api.php?action=results_by_date&date=2026-03-27&key=YOUR_API_KEY
 *   /public_api.php?action=lottery_list&key=YOUR_API_KEY
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// ============================================
// API Key — เปลี่ยนค่านี้เป็น key ของคุณเอง
// ============================================
define('API_KEY', 'klotto_2026_secret_key');

// Validate API key
$key = $_GET['key'] ?? '';
if ($key !== API_KEY) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // ============================================
    // 1. ผลหวยล่าสุดทุกตัว
    // ============================================
    case 'latest_results':
        $stmt = $pdo->query("
            SELECT r.draw_date, r.three_top, r.two_bot, r.status,
                   lt.name as lottery_name, lc.name as category,
                   lt.flag_emoji
            FROM results r
            JOIN lottery_types lt ON r.lottery_type_id = lt.id
            JOIN lottery_categories lc ON lt.category_id = lc.id
            WHERE r.draw_date = (
                SELECT MAX(r2.draw_date) FROM results r2 WHERE r2.lottery_type_id = r.lottery_type_id
            )
            AND lt.is_active = 1
            ORDER BY lc.sort_order, lt.sort_order, lt.name
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'count' => count($results),
            'data' => $results
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    // ============================================
    // 2. ผลหวยตามวันที่
    // ============================================
    case 'results_by_date':
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid date format. Use YYYY-MM-DD'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $stmt = $pdo->prepare("
            SELECT r.draw_date, r.three_top, r.two_bot, r.status, r.created_at,
                   lt.name as lottery_name, lc.name as category,
                   lt.flag_emoji
            FROM results r
            JOIN lottery_types lt ON r.lottery_type_id = lt.id
            JOIN lottery_categories lc ON lt.category_id = lc.id
            WHERE r.draw_date = ? AND lt.is_active = 1
            ORDER BY lc.sort_order, lt.sort_order, lt.name
        ");
        $stmt->execute([$date]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'date' => $date,
            'count' => count($results),
            'data' => $results
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    // ============================================
    // 3. ผลหวยย้อนหลัง (เฉพาะหวยตัวเดียว)
    // ============================================
    case 'result_history':
        $lotteryId = intval($_GET['lottery_id'] ?? 0);
        $limit = min(intval($_GET['limit'] ?? 30), 100);

        if (!$lotteryId) {
            echo json_encode(['status' => 'error', 'message' => 'lottery_id is required'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $stmt = $pdo->prepare("
            SELECT r.draw_date, r.three_top, r.two_bot, r.status, r.created_at,
                   lt.name as lottery_name, lc.name as category
            FROM results r
            JOIN lottery_types lt ON r.lottery_type_id = lt.id
            JOIN lottery_categories lc ON lt.category_id = lc.id
            WHERE r.lottery_type_id = ? AND lt.is_active = 1
            ORDER BY r.draw_date DESC
            LIMIT ?
        ");
        $stmt->execute([$lotteryId, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'lottery_id' => $lotteryId,
            'count' => count($results),
            'data' => $results
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    // ============================================
    // 4. รายชื่อหวยทั้งหมด
    // ============================================
    case 'lottery_list':
        $stmt = $pdo->query("
            SELECT lt.id, lt.name, lt.flag_emoji, lt.open_time, lt.close_time,
                   lt.draw_schedule, lc.name as category
            FROM lottery_types lt
            JOIN lottery_categories lc ON lt.category_id = lc.id
            WHERE lt.is_active = 1
            ORDER BY lc.sort_order, lt.sort_order, lt.name
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'count' => count($results),
            'data' => $results
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    // ============================================
    // Default
    // ============================================
    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Unknown action',
            'available_actions' => [
                'latest_results' => 'ผลหวยล่าสุดทุกตัว',
                'results_by_date' => 'ผลหวยตามวันที่ (param: date=YYYY-MM-DD)',
                'result_history' => 'ผลหวยย้อนหลัง (param: lottery_id, limit)',
                'lottery_list' => 'รายชื่อหวยทั้งหมด'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
}
