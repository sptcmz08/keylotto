<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
// CORS: same-origin only (no wildcard)
$allowedOrigin = ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($allowedOrigin && parse_url($allowedOrigin, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}
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

// Auth check: write operations require login
$writeActions = ['save_bet', 'cancel_bet'];
if (in_array($action, $writeActions)) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'กรุณาเข้าสู่ระบบก่อน']);
        exit;
    }
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

            // Server-side validation: digit count + negative amounts
            $validLengths = [
                '3top' => 3, '3tod' => 3,
                '2top' => 2, '2bot' => 2,
                'run_top' => 1, 'run_bot' => 1,
            ];
            $errors = [];
            foreach ($items as $idx => $item) {
                $num = $item['number'] ?? '';
                $type = $item['type'] ?? '';
                $amt = floatval($item['amount'] ?? 0);
                
                // Block non-digit characters
                if (!preg_match('/^\d+$/', $num)) {
                    $errors[] = "เลข \"{$num}\" ต้องเป็นตัวเลขเท่านั้น";
                }
                // Block wrong digit count
                if (isset($validLengths[$type]) && strlen($num) !== $validLengths[$type]) {
                    $errors[] = "เลข \"{$num}\" ({$type}) ต้องมี {$validLengths[$type]} หลัก";
                }
                // Block negative amounts
                if ($amt < 0) {
                    $errors[] = "เลข \"{$num}\" จำนวนเงินติดลบไม่ได้";
                }
                if ($amt <= 0) {
                    $errors[] = "เลข \"{$num}\" จำนวนเงินต้องมากกว่า 0";
                }
            }
            if (!empty($errors)) {
                echo json_encode(['error' => implode("\n", $errors)]);
                exit;
            }

            // Calculate totals
            $totalAmount = 0;
            $totalItems = count($items);
            foreach ($items as $item) {
                $totalAmount += floatval($item['amount'] ?? 0);
            }

            $netAmount = $totalAmount;
            $betNumber = date('YmdHis') . rand(10, 99);
            
            // คำนวณ drawDate จาก open_time/close_time (ไม่ใช้ lottery_types.draw_date ที่อาจ stale)
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $now = new DateTime();
            $openTime = $lottery['open_time'] ?? null;
            $closeTime = $lottery['close_time'] ?? null;
            
            if ($openTime && $closeTime) {
                $closeHour = intval(substr($closeTime, 0, 2));
                $nowHour = intval(date('H'));
                
                // กรณีหวยข้ามเที่ยงคืน: close_time < 06:00
                // เช่น ดาวโจนส์ VIP close=00:10
                if ($closeHour < 6) {
                    // หวยข้ามเที่ยงคืน → draw_date เป็นของเมื่อวานเสมอ
                    // (เปิดรับก่อนเที่ยงคืน ปิดหลังเที่ยงคืน → งวดเดียวกัน = วันเมื่อวาน)
                    if ($nowHour < 6) {
                        // ตอนนี้ 00:00-05:59 → งวดเป็นของเมื่อวาน
                        $drawDate = $yesterday;
                    } else {
                        // ตอนนี้ 06:00+ → งวดเป็นของวันนี้ (จะเปิดรับตอน 20:00+)
                        $drawDate = $today;
                    }
                } else {
                    $openDT = new DateTime($today . ' ' . $openTime);
                    $closeDT = new DateTime($today . ' ' . $closeTime);
                    
                    if ($closeDT <= $openDT) {
                        if ($now < $closeDT) {
                            $drawDate = $today;
                        } else {
                            $drawDate = $tomorrow;
                        }
                    } else {
                        if ($now > $closeDT) {
                            $drawDate = $tomorrow;
                        } else {
                            $drawDate = $today;
                        }
                    }
                }
            } else {
                $drawDate = $today;
            }

            // =============================================
            // ตรวจสอบยอดรับสูงสุดต่อเลข (max_per_number)
            // =============================================
            $stmtRates = $pdo->prepare("SELECT bet_type, max_per_number FROM pay_rates WHERE lottery_type_id = ? AND max_per_number > 0");
            $stmtRates->execute([$lotteryId]);
            $limits = [];
            foreach ($stmtRates->fetchAll() as $r) {
                $limits[$r['bet_type']] = intval($r['max_per_number']);
            }

            if (!empty($limits)) {
                $blocked = [];
                $stmtCheck = $pdo->prepare("
                    SELECT COALESCE(SUM(bi.amount), 0) as total_bet
                    FROM bet_items bi
                    JOIN bets b ON bi.bet_id = b.id
                    WHERE b.lottery_type_id = ?
                      AND b.draw_date = ?
                      AND b.status != 'cancelled'
                      AND bi.number = ?
                      AND bi.bet_type = ?
                ");

                foreach ($items as $item) {
                    $type = $item['type'] ?? '';
                    $number = $item['number'] ?? '';
                    $amount = floatval($item['amount'] ?? 0);

                    if (isset($limits[$type])) {
                        $stmtCheck->execute([$lotteryId, $drawDate, $number, $type]);
                        $existing = floatval($stmtCheck->fetchColumn());
                        $remaining = $limits[$type] - $existing;

                        if ($amount > $remaining) {
                            $blocked[] = [
                                'number' => $number,
                                'type' => $type,
                                'amount' => $amount,
                                'existing' => $existing,
                                'limit' => $limits[$type],
                                'remaining' => max(0, $remaining),
                            ];
                        }
                    }
                }

                if (!empty($blocked)) {
                    $msgs = [];
                    foreach ($blocked as $b) {
                        $msgs[] = "เลข {$b['number']} ({$b['type']}) แทงไม่ได้ — รับสูงสุด {$b['limit']} แทงไปแล้ว {$b['existing']} เหลือ {$b['remaining']}";
                    }
                    echo json_encode(['error' => "เกินยอดรับสูงสุด:\n" . implode("\n", $msgs), 'blocked' => $blocked]);
                    exit;
                }
            }            // =============================================
            // ตรวจสอบตั้งสู้ (fight_limits) — ปิดรับอัตโนมัติเมื่อเกินจำนวน
            // =============================================
            try {
                $stmtFight = $pdo->prepare("SELECT bet_type, max_amount FROM fight_limits WHERE lottery_type_id = ? AND max_amount > 0");
                $stmtFight->execute([$lotteryId]);
                $fightLimits = [];
                foreach ($stmtFight->fetchAll() as $fl) {
                    $fightLimits[$fl['bet_type']] = floatval($fl['max_amount']);
                }
                
                if (!empty($fightLimits)) {
                    $fightBlocked = [];
                    $stmtFightCheck = $pdo->prepare("
                        SELECT COALESCE(SUM(bi.amount), 0) as total_bet
                        FROM bet_items bi
                        JOIN bets b ON bi.bet_id = b.id
                        WHERE b.lottery_type_id = ?
                          AND b.draw_date = ?
                          AND b.status != 'cancelled'
                          AND bi.number = ?
                          AND bi.bet_type = ?
                    ");
                    
                    foreach ($items as $item) {
                        $type = $item['type'] ?? '';
                        $number = $item['number'] ?? '';
                        $amount = floatval($item['amount'] ?? 0);
                        
                        if (isset($fightLimits[$type])) {
                            $stmtFightCheck->execute([$lotteryId, $drawDate, $number, $type]);
                            $existing = floatval($stmtFightCheck->fetchColumn());
                            $maxAllowed = $fightLimits[$type];
                            
                            if (($existing + $amount) > $maxAllowed) {
                                $remaining = max(0, $maxAllowed - $existing);
                                $fightBlocked[] = "เลข {$number} ({$type}) เกินตั้งสู้ — สู้ได้ " . number_format($maxAllowed) . " แทงไปแล้ว " . number_format($existing) . " เหลือ " . number_format($remaining);
                            }
                        }
                    }
                    
                    if (!empty($fightBlocked)) {
                        echo json_encode(['error' => "❌ เกินตั้งสู้:\n" . implode("\n", $fightBlocked), 'fight_blocked' => true]);
                        exit;
                    }
                }
            } catch (Exception $e) {
                // fight_limits table may not exist — skip silently
            }
            // =============================================
            // ตรวจสอบเลขอั้น / ปิดรับ
            // =============================================
            $stmtBlocked = $pdo->prepare("SELECT number, bet_type, is_blocked FROM blocked_numbers WHERE lottery_type_id = ?");
            $stmtBlocked->execute([$lotteryId]);
            $blockedMap = [];
            foreach ($stmtBlocked->fetchAll() as $row) {
                $blockedMap[$row['number'] . '_' . $row['bet_type']] = $row;
            }

            if (!empty($blockedMap)) {
                $rejectedItems = [];
                $halfPayItems = [];

                foreach ($items as &$item) {
                    $key = ($item['number'] ?? '') . '_' . ($item['type'] ?? '');
                    if (isset($blockedMap[$key])) {
                        if ($blockedMap[$key]['is_blocked']) {
                            $rejectedItems[] = "เลข {$item['number']} ({$item['type']}) ปิดรับแทง";
                        } else {
                            $item['half_pay'] = true;
                            $halfPayItems[] = "เลข {$item['number']} ({$item['type']}) จ่ายครึ่ง";
                        }
                    }
                }
                unset($item);

                if (!empty($rejectedItems)) {
                    echo json_encode(['error' => "ปิดรับแทง:\n" . implode("\n", $rejectedItems)]);
                    exit;
                }
            }

            // =============================================
            // อัตราจ่ายลดอัตโนมัติเมื่อเกิน threshold (ต่อหวย ต่อประเภท)
            // =============================================
            $stmtOverRates = $pdo->prepare("SELECT bet_type, over_threshold, over_pay_rate FROM pay_rates WHERE lottery_type_id = ? AND over_threshold > 0 AND over_pay_rate > 0");
            $stmtOverRates->execute([$lotteryId]);
            $overRatesByType = [];
            foreach ($stmtOverRates->fetchAll() as $or) {
                $overRatesByType[$or['bet_type']] = [
                    'threshold' => intval($or['over_threshold']),
                    'rate' => floatval($or['over_pay_rate']),
                ];
            }
            $adjustedRates = [];
            foreach ($overRatesByType as $bt => $cfg) {
                $adjustedRates[$bt] = $cfg['rate'];
            }
            
            $typeCounts = [];
            foreach ($items as $item) {
                $t = $item['type'] ?? '';
                $typeCounts[$t] = ($typeCounts[$t] ?? 0) + 1;
            }
            
            $overLimitTypes = [];
            foreach ($typeCounts as $type => $count) {
                if (isset($overRatesByType[$type]) && $count >= $overRatesByType[$type]['threshold']) {
                    $overLimitTypes[] = $type;
                }
            }
            
            $autoRateAdjusted = !empty($overLimitTypes);

            // =============================================
            // ตั้งสู้ (Fight Limits) — ปิดรับถ้าเกินงบ
            // =============================================
            $betTypeLabelsMap = ['3top'=>'3ตัวบน','3tod'=>'3ตัวโต๊ด','2top'=>'2ตัวบน','2bot'=>'2ตัวล่าง','run_top'=>'วิ่งบน','run_bot'=>'วิ่งล่าง'];
            // =============================================
            try {
                $flStmt = $pdo->prepare("SELECT bet_type, max_amount FROM fight_limits WHERE lottery_type_id = ? AND max_amount > 0");
                $flStmt->execute([$lotteryId]);
                $fightLimits = [];
                foreach ($flStmt->fetchAll() as $fl) $fightLimits[$fl['bet_type']] = floatval($fl['max_amount']);
                
                if (!empty($fightLimits)) {
                    // ดึงยอดรวมปัจจุบันของแต่ละเลข+ประเภท
                    $existingStmt = $pdo->prepare("
                        SELECT bi.number, bi.bet_type, SUM(bi.amount) as total
                        FROM bet_items bi JOIN bets b ON bi.bet_id = b.id
                        WHERE b.lottery_type_id = ? AND b.draw_date = ? AND b.status != 'cancelled'
                        GROUP BY bi.number, bi.bet_type
                    ");
                    $existingStmt->execute([$lotteryId, $drawDate]);
                    $existingTotals = [];
                    foreach ($existingStmt->fetchAll() as $e) {
                        $existingTotals[$e['bet_type'] . '_' . $e['number']] = floatval($e['total']);
                    }
                    
                    $fightRejected = [];
                    foreach ($items as $item) {
                        $bt = $item['type'];
                        $num = $item['number'];
                        if (!isset($fightLimits[$bt])) continue;
                        
                        $key = $bt . '_' . $num;
                        $existing = $existingTotals[$key] ?? 0;
                        $newTotal = $existing + floatval($item['amount']);
                        
                        if ($newTotal > $fightLimits[$bt]) {
                            $remaining = max(0, $fightLimits[$bt] - $existing);
                            $fightRejected[] = $num . " (" . $betTypeLabelsMap[$bt] . ") เกินตั้งสู้ " . number_format($fightLimits[$bt]) . " แทงได้อีก " . number_format($remaining);
                        }
                    }
                    
                    if (!empty($fightRejected)) {
                        echo json_encode(['error' => "⚠️ เกินตั้งสู้:\n" . implode("\n", $fightRejected)]);
                        exit;
                    }
                }
            } catch (Exception $e) {} // table may not exist yet

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO bets (bet_number, lottery_type_id, draw_date, total_items, total_amount, discount_amount, net_amount, note, rate_adjusted)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
            ");
            $stmt->execute([$betNumber, $lotteryId, $drawDate, $totalItems, $totalAmount, $netAmount, $note, $autoRateAdjusted ? 1 : 0]);
            $betId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO bet_items (bet_id, number, bet_type, amount, adjusted_pay_rate) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $adjustedRate = null;
                // เฉพาะประเภทที่เกิน threshold เท่านั้นที่ลดอัตรา
                if (in_array($item['type'], $overLimitTypes) && isset($adjustedRates[$item['type']])) {
                    $adjustedRate = $adjustedRates[$item['type']];
                }
                $stmtItem->execute([$betId, $item['number'], $item['type'], floatval($item['amount']), $adjustedRate]);
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

        case 'cancel_bet':
            $betId = intval($data['bet_id'] ?? 0);
            if (!$betId) { echo json_encode(['error' => 'ไม่ได้ระบุโพย']); break; }
            $stmt = $pdo->prepare("UPDATE bets SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$betId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'ยกเลิกโพยสำเร็จ']);
            } else {
                echo json_encode(['error' => 'ไม่สามารถยกเลิกโพยนี้ได้ (อาจถูกยกเลิกไปแล้ว)']);
            }
            break;

        // ==========================================
        // API: ตรวจสอบเลขอั้น/ปิดรับ (สำหรับหน้าแทง)
        // ==========================================
        case 'check_blocked':
            $lotteryId = intval($_GET['lottery_type_id'] ?? 0);
            $number = $_GET['number'] ?? '';
            $betType = $_GET['bet_type'] ?? '';

            if (!$lotteryId || $number === '') {
                echo json_encode(['status' => 'ok']);
                break;
            }

            $stmtCheck = $pdo->prepare("SELECT is_blocked FROM blocked_numbers WHERE lottery_type_id = ? AND number = ? AND bet_type = ?");
            $stmtCheck->execute([$lotteryId, $number, $betType]);
            $blocked = $stmtCheck->fetch();

            if ($blocked) {
                if ($blocked['is_blocked']) {
                    // ดึงอัตราจ่ายปกติ
                    echo json_encode(['status' => 'blocked', 'message' => "เลข {$number} ปิดรับแทง"]);
                } else {
                    // ดึงอัตราจ่ายปกติแล้วหารครึ่ง
                    $stmtRate = $pdo->prepare("SELECT pay_rate FROM pay_rates WHERE lottery_type_id = ? AND bet_type = ?");
                    $stmtRate->execute([$lotteryId, $betType]);
                    $rateRow = $stmtRate->fetch();
                    $fullRate = $rateRow ? floatval($rateRow['pay_rate']) : 0;
                    $halfRate = $fullRate / 2;
                    echo json_encode(['status' => 'half', 'message' => "เลข {$number} จ่ายครึ่ง (จ่าย {$halfRate} แทน {$fullRate})", 'full_rate' => $fullRate, 'half_rate' => $halfRate]);
                }
            } else {
                echo json_encode(['status' => 'ok']);
            }
            break;

        default:
            echo json_encode(['error' => 'Unknown action: ' . $action]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
