<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';
if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

try {
    switch ($action) {
        case 'save_bet':
            $data = $data ?? json_decode(file_get_contents('php://input'), true);
            $lotteryId = intval($data['lottery_type_id'] ?? 0);
            $note = $data['note'] ?? '';
            $items = $data['items'] ?? [];

            if (!$lotteryId || empty($items)) {
                echo json_encode(['error' => 'ข้อมูลไม่ครบถ้วน']);
                exit;
            }

            // Get lottery info
            $stmt = $pdo->prepare("SELECT * FROM lottery_types WHERE id = ?");
            $stmt->execute([$lotteryId]);
            $lottery = $stmt->fetch();
            if (!$lottery) {
                echo json_encode(['error' => 'ไม่พบหวยที่เลือก']);
                exit;
            }

            // Calculate totals
            $totalAmount = 0;
            $totalItems = count($items);
            foreach ($items as $item) {
                $totalAmount += floatval($item['amount'] ?? 0);
            }

            // Calculate discount
            $discountAmount = 0;
            // Check if pay rates have discount
            $stmtRates = $pdo->prepare("SELECT bet_type, discount FROM pay_rates WHERE lottery_type_id = ?");
            $stmtRates->execute([$lotteryId]);
            $rateDiscounts = [];
            while ($rd = $stmtRates->fetch()) {
                $rateDiscounts[$rd['bet_type']] = floatval($rd['discount']);
            }
            foreach ($items as $item) {
                $type = $item['type'] ?? '';
                if (isset($rateDiscounts[$type]) && $rateDiscounts[$type] > 0) {
                    $discountAmount += floatval($item['amount']) * $rateDiscounts[$type] / 100;
                }
            }

            $netAmount = $totalAmount - $discountAmount;
            $betNumber = date('YmdHis') . rand(10, 99);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO bets (bet_number, lottery_type_id, draw_date, total_items, total_amount, discount_amount, net_amount, note)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$betNumber, $lotteryId, $lottery['draw_date'] ?? date('Y-m-d'), $totalItems, $totalAmount, $discountAmount, $netAmount, $note]);
            $betId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO bet_items (bet_id, number, bet_type, amount) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmtItem->execute([$betId, $item['number'], $item['type'], floatval($item['amount'])]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'bet_number' => $betNumber, 'bet_id' => $betId]);
            break;

        case 'get_bets':
            $date = $_GET['date'] ?? date('Y-m-d');
            $stmt = $pdo->prepare("
                SELECT b.*, lt.name as lottery_name
                FROM bets b
                JOIN lottery_types lt ON b.lottery_type_id = lt.id
                WHERE b.draw_date = ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$date]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'get_bet_detail':
            $betId = intval($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM bets WHERE id = ?");
            $stmt->execute([$betId]);
            $bet = $stmt->fetch();
            if ($bet) {
                $stmtItems = $pdo->prepare("SELECT * FROM bet_items WHERE bet_id = ?");
                $stmtItems->execute([$betId]);
                $bet['items'] = $stmtItems->fetchAll();
            }
            echo json_encode($bet ?: ['error' => 'Not found']);
            break;

        case 'get_rates':
            $lotteryId = intval($_GET['lottery_type_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM pay_rates WHERE lottery_type_id = ?");
            $stmt->execute([$lotteryId]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'get_results':
            $lotteryId = intval($_GET['lottery_type_id'] ?? 0);
            $limit = intval($_GET['limit'] ?? 5);
            $stmt = $pdo->prepare("SELECT * FROM results WHERE lottery_type_id = ? ORDER BY draw_date DESC LIMIT ?");
            $stmt->execute([$lotteryId, $limit]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'get_lottery_types':
            $stmt = $pdo->query("
                SELECT lt.*, lc.name as category_name
                FROM lottery_types lt
                JOIN lottery_categories lc ON lt.category_id = lc.id
                WHERE lt.is_active = 1
                ORDER BY lc.sort_order, lt.sort_order
            ");
            echo json_encode($stmt->fetchAll());
            break;

        default:
            echo json_encode(['error' => 'Unknown action: ' . $action]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
