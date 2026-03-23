<?php
$pageTitle = 'คีย์หวย - รายการโพย';
$currentPage = 'bills';
require_once 'auth.php';
requireLogin();

// ==========================================
// Handle AJAX: bill detail
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detail' && isset($_GET['bet_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $betId = intval($_GET['bet_id']);
    
    $stmt = $pdo->prepare("SELECT b.*, lt.name as lottery_name, lc.name as category_name FROM bets b JOIN lottery_types lt ON b.lottery_type_id = lt.id JOIN lottery_categories lc ON lt.category_id = lc.id WHERE b.id = ?");
    $stmt->execute([$betId]);
    $bet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bet) { echo json_encode(['error' => 'not found']); exit; }
    
    $stmt = $pdo->prepare("SELECT * FROM bet_items WHERE bet_id = ? ORDER BY FIELD(bet_type,'3top','3tod','2top','2bot','run_top','run_bot'), number");
    $stmt->execute([$betId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM results WHERE lottery_type_id = ? AND draw_date = ?");
    $stmt->execute([$bet['lottery_type_id'], $bet['draw_date']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM pay_rates WHERE lottery_type_id = ?");
    $stmt->execute([$bet['lottery_type_id']]);
    $ratesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rateMap = [];
    foreach ($ratesRaw as $r) $rateMap[$r['bet_type']] = $r;
    
    foreach ($items as &$item) {
        $item['is_winner'] = false;
        $item['pay_multiplier'] = $rateMap[$item['bet_type']]['pay_rate'] ?? 0;
        $item['net_amount'] = floatval($item['amount']);
        $item['win_amount'] = 0;
        
        if ($result) {
            $num = $item['number'];
            switch ($item['bet_type']) {
                case '3top': if ($result['three_top'] === $num) { $item['is_winner'] = true; $item['win_amount'] = $item['net_amount'] * $item['pay_multiplier']; } break;
                case '3tod': 
                    $tods = array_filter(array_map('trim', explode(',', $result['three_tod'] ?? '')));
                    if (in_array($num, $tods) || ($result['three_top'] && $num !== $result['three_top'] && sorted_str($num) === sorted_str($result['three_top']))) {
                        $item['is_winner'] = true; $item['win_amount'] = $item['net_amount'] * $item['pay_multiplier'];
                    }
                    break;
                case '2top': if ($result['two_top'] === $num) { $item['is_winner'] = true; $item['win_amount'] = $item['net_amount'] * $item['pay_multiplier']; } break;
                case '2bot': if ($result['two_bot'] === $num) { $item['is_winner'] = true; $item['win_amount'] = $item['net_amount'] * $item['pay_multiplier']; } break;
                case 'run_top': if ($result['run_top'] !== null && strpos($result['three_top'] ?? '', $num) !== false) { $item['is_winner'] = true; $item['win_amount'] = $item['net_amount'] * $item['pay_multiplier']; } break;
                case 'run_bot': if ($result['run_bot'] !== null && strpos($result['two_bot'] ?? '', $num) !== false) { $item['is_winner'] = true; $item['win_amount'] = $item['net_amount'] * $item['pay_multiplier']; } break;
            }
        }
    }
    unset($item);
    
    echo json_encode(['bet' => $bet, 'items' => $items, 'result' => $result]);
    exit;
}

// ==========================================
// Handle AJAX: cancel request
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'cancel_request' && isset($_GET['bet_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $betId = intval($_GET['bet_id']);
    $reason = $_GET['reason'] ?? 'ลูกค้าไม่จ่ายเงิน';
    
    // Mark bet as pending_cancel (needs admin approval)
    $stmt = $pdo->prepare("UPDATE bets SET cancel_requested = 1, cancel_reason = ?, cancel_requested_at = NOW() WHERE id = ? AND status != 'cancelled'");
    $stmt->execute([$reason, $betId]);
    
    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}

function sorted_str($s) { $a = str_split($s); sort($a); return implode('', $a); }

// ==========================================
// Filters
// ==========================================
$filterType = $_GET['filter'] ?? 'today';
$filterMonth = $_GET['month'] ?? date('m/Y');
$filterFrom = $_GET['from'] ?? date('d/m/Y');
$filterTo = $_GET['to'] ?? date('d/m/Y');
$statusFilter = $_GET['status'] ?? 'all';
$lotteryFilter = intval($_GET['lottery'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;

switch ($filterType) {
    case 'today': $dateFrom = date('Y-m-d'); $dateTo = date('Y-m-d'); break;
    case 'yesterday': $dateFrom = date('Y-m-d', strtotime('-1 day')); $dateTo = $dateFrom; break;
    case 'this_week': $dateFrom = date('Y-m-d', strtotime('monday this week')); $dateTo = date('Y-m-d'); break;
    case 'last_week': $dateFrom = date('Y-m-d', strtotime('monday last week')); $dateTo = date('Y-m-d', strtotime('sunday last week')); break;
    case 'month':
        $parts = explode('/', $filterMonth);
        $m = intval($parts[0] ?? date('m'));
        $y = intval($parts[1] ?? date('Y'));
        $dateFrom = sprintf('%04d-%02d-01', $y, $m);
        $dateTo = date('Y-m-t', strtotime($dateFrom));
        break;
    case 'range':
        $dateFrom = date('Y-m-d', strtotime(str_replace('/', '-', $filterFrom)));
        $dateTo = date('Y-m-d', strtotime(str_replace('/', '-', $filterTo)));
        break;
    default: $dateFrom = date('Y-m-d'); $dateTo = date('Y-m-d');
}

// Build WHERE
$where = "DATE(b.created_at) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];

if ($statusFilter === 'pending') $where .= " AND b.status = 'pending'";
elseif ($statusFilter === 'won') $where .= " AND b.status = 'won'";
elseif ($statusFilter === 'cancelled') $where .= " AND b.status = 'cancelled'";
elseif ($statusFilter === 'lost') $where .= " AND b.status = 'lost'";

if ($lotteryFilter > 0) {
    $where .= " AND b.lottery_type_id = ?";
    $params[] = $lotteryFilter;
}

// Count total for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bets b WHERE {$where}");
$countStmt->execute($params);
$totalBets = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalBets / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Fetch page
$stmt = $pdo->prepare("
    SELECT b.*, lt.name as lottery_name, lc.name as category_name
    FROM bets b
    JOIN lottery_types lt ON b.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE {$where}
    ORDER BY b.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$bets = $stmt->fetchAll();

// Summary stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_count,
        SUM(total_amount) as total_amount,

        SUM(net_amount) as total_net,
        SUM(win_amount) as total_win,
        SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) as won_count,
        SUM(CASE WHEN status='lost' THEN 1 ELSE 0 END) as lost_count,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled_count
    FROM bets b WHERE {$where}
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Group by lottery for tab 2
$allBetsStmt = $pdo->prepare("
    SELECT b.*, lt.name as lottery_name, lc.name as category_name
    FROM bets b JOIN lottery_types lt ON b.lottery_type_id = lt.id JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE {$where} ORDER BY b.created_at DESC
");
$allBetsStmt->execute($params);
$allBetsForGroup = $allBetsStmt->fetchAll();
$betsByLottery = [];
foreach ($allBetsForGroup as $b) {
    $key = '[' . $b['category_name'] . '] - ' . $b['lottery_name'];
    if (!isset($betsByLottery[$key])) $betsByLottery[$key] = [];
    $betsByLottery[$key][] = $b;
}

// Lottery types for filter dropdown
$lotteryTypes = $pdo->query("SELECT lt.id, lt.name, lc.name as cat FROM lottery_types lt JOIN lottery_categories lc ON lt.category_id = lc.id WHERE lt.is_active = 1 ORDER BY lc.sort_order, lt.sort_order")->fetchAll();

$thaiMonths = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$displayDate = intval(date('d', strtotime($dateFrom))) . ' ' . $thaiMonths[intval(date('m', strtotime($dateFrom)))] . ' ' . (intval(date('Y', strtotime($dateFrom))) + 543);

require_once 'includes/header.php';
?>

<div class="bg-white card-outline p-4 mb-4">
    <h2 class="text-lg font-bold text-gray-700 mb-3">รายการโพย</h2>
    
    <!-- Filter Section -->
    <div class="bg-gray-50 rounded-lg border p-4 mb-4">
        <div class="text-sm text-gray-600 font-medium mb-2"><i class="fas fa-filter mr-1"></i> ตัวเลือกการค้นหา</div>
        <form method="GET" id="filterForm">
            <div class="space-y-2 mb-3">
                <div class="flex flex-wrap gap-4">
                    <?php foreach (['today'=>'วันนี้','yesterday'=>'เมื่อวาน','this_week'=>'สัปดาห์นี้','last_week'=>'สัปดาห์ที่แล้ว'] as $fk=>$fv): ?>
                    <label class="flex items-center text-sm cursor-pointer">
                        <input type="radio" name="filter" value="<?= $fk ?>" <?= $filterType === $fk ? 'checked' : '' ?> class="mr-1.5"> <?= $fv ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="flex flex-wrap gap-4 items-center">
                    <label class="flex items-center text-sm cursor-pointer">
                        <input type="radio" name="filter" value="month" <?= $filterType === 'month' ? 'checked' : '' ?> class="mr-1.5"> เดือน
                    </label>
                    <input type="text" name="month" value="<?= htmlspecialchars($filterMonth) ?>" class="border rounded px-2 py-1 text-sm w-28" placeholder="MM/YYYY">
                </div>
                <div class="flex flex-wrap gap-4 items-center">
                    <label class="flex items-center text-sm cursor-pointer">
                        <input type="radio" name="filter" value="range" <?= $filterType === 'range' ? 'checked' : '' ?> class="mr-1.5"> วันที่
                    </label>
                    <input type="date" name="from" value="<?= $dateFrom ?>" class="border rounded px-2 py-1 text-sm">
                    <span class="text-sm">ถึง</span>
                    <input type="date" name="to" value="<?= $dateTo ?>" class="border rounded px-2 py-1 text-sm">
                </div>
                <!-- Lottery Type Filter -->
                <div class="flex flex-wrap gap-4 items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-ticket-alt mr-1"></i> ประเภทหวย:</span>
                    <select name="lottery" class="border rounded px-2 py-1 text-sm">
                        <option value="0">ทั้งหมด</option>
                        <?php foreach ($lotteryTypes as $lt): ?>
                        <option value="<?= $lt['id'] ?>" <?= $lotteryFilter == $lt['id'] ? 'selected' : '' ?>>[<?= htmlspecialchars($lt['cat']) ?>] <?= htmlspecialchars($lt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>" id="statusInput">
            <button type="submit" class="bg-[#2e7d32] text-white px-4 py-1.5 rounded text-sm font-medium hover:bg-green-700 transition">
                <i class="fas fa-search mr-1"></i> ค้นหา
            </button>
        </form>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-4">
        <div class="bg-gray-50 rounded border p-2 text-center">
            <div class="text-xs text-gray-500">ทั้งหมด</div>
            <div class="text-lg font-bold text-gray-700"><?= $stats['total_count'] ?? 0 ?></div>
        </div>
        <div class="bg-blue-50 rounded border border-blue-200 p-2 text-center">
            <div class="text-xs text-blue-500">รอผล</div>
            <div class="text-lg font-bold text-blue-600"><?= $stats['pending_count'] ?? 0 ?></div>
        </div>
        <div class="bg-green-50 rounded border border-green-200 p-2 text-center">
            <div class="text-xs text-green-500">ถูกรางวัล</div>
            <div class="text-lg font-bold text-green-600"><?= $stats['won_count'] ?? 0 ?></div>
        </div>
        <div class="bg-red-50 rounded border border-red-200 p-2 text-center">
            <div class="text-xs text-red-400">ไม่ถูกรางวัล</div>
            <div class="text-lg font-bold text-red-500"><?= $stats['lost_count'] ?? 0 ?></div>
        </div>
        <div class="bg-orange-50 rounded border border-orange-200 p-2 text-center">
            <div class="text-xs text-orange-500">ยอดรวม</div>
            <div class="text-lg font-bold text-orange-600"><?= formatMoney($stats['total_net'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Date Header + Status Buttons -->
    <div class="flex flex-wrap justify-between items-center mb-3">
        <div class="text-sm font-bold text-[#2e7d32]">ข้อมูลวันที่ <?= $displayDate ?> (หน้า <?= $page ?>/<?= $totalPages ?>)</div>
        <div class="flex gap-1">
            <?php 
            $statuses = [
                'all' => ['ทั้งหมด', 'bg-[#2e7d32] text-white'],
                'pending' => ['รอผล', 'bg-[#2196f3] text-white'],
                'won' => ['ถูกรางวัล', 'bg-[#4caf50] text-white'],
                'lost' => ['ไม่ถูกรางวัล', 'bg-[#ef5350] text-white'],
                'cancelled' => ['ยกเลิก', 'bg-red-100 text-red-700'],
            ];
            foreach ($statuses as $sKey => $sVal):
                $isActive = $statusFilter === $sKey;
                $cls = $isActive ? $sVal[1] : 'bg-gray-100 text-gray-500 hover:bg-gray-200';
            ?>
            <button onclick="setStatus('<?= $sKey ?>')" class="px-3 py-1 rounded text-xs font-medium transition <?= $cls ?>">
                <?= $sVal[0] ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex border-b mb-0">
        <button onclick="switchBillTab('all')" id="billTab-all" class="px-4 py-2 text-sm font-medium border-b-2 border-[#2e7d32] text-[#2e7d32] bg-white">รายการทั้งหมด</button>
        <button onclick="switchBillTab('byType')" id="billTab-byType" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 bg-gray-50 rounded-t border border-b-0 border-gray-200 ml-1">ตามชนิดหวย</button>
    </div>

    <!-- Tab: All -->
    <div id="billPanel-all" class="overflow-x-auto">
        <table class="w-full text-[13px] text-gray-600 border">
            <thead class="bg-[#e8f5e9] border-b">
                <tr>
                    <th class="px-2 py-2 text-center font-bold text-gray-700 border">เลขที่</th>
                    <th class="px-2 py-2 text-center font-bold text-gray-700 border">วันที่</th>
                    <th class="px-2 py-2 text-left font-bold text-gray-700 border">ชนิดหวย</th>
                    <th class="px-2 py-2 text-center font-bold text-gray-700 border">งวด</th>
                    <th class="px-2 py-2 text-center font-bold text-gray-700 border">รายการ</th>
                    <th class="px-2 py-2 text-center font-bold text-gray-700 border">ยอด</th>

                    <th class="px-2 py-2 text-center font-bold text-gray-700 border">รวม</th>
                    <th class="px-2 py-2 text-center font-bold text-gray-700 border">ถูกรางวัล</th>
                    <th class="px-2 py-2 text-center font-bold text-gray-700 border">หมายเหตุ</th>
                    <th class="px-2 py-2 text-center font-bold text-gray-700 border w-20">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bets)): ?>
                <tr><td colspan="11" class="px-3 py-12 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-3 block"></i>ยังไม่มีรายการโพย</td></tr>
                <?php else: foreach ($bets as $i => $b):
                    $isCancelled = $b['status'] === 'cancelled';
                    $cancelRequested = !empty($b['cancel_requested']);
                    $rowClass = $isCancelled ? 'bg-red-50' : ($cancelRequested ? 'bg-yellow-50' : ($i % 2 === 0 ? 'bg-white' : 'bg-gray-50'));
                    
                    $winBadge = '';
                    switch ($b['status']) {
                        case 'won': $winBadge = '<span class="text-green-600 font-bold">' . formatMoney($b['win_amount']) . '</span>'; break;
                        case 'lost': $winBadge = '<span class="text-red-400">ไม่ถูกรางวัล</span>'; break;
                        case 'cancelled': $winBadge = '<span class="text-red-500 font-bold">ยกเลิก</span>'; break;
                        default: $winBadge = '<span class="text-blue-400">รอผล</span>'; break;
                    }
                ?>
                <tr class="<?= $rowClass ?> border-b hover:bg-blue-50/50 transition">
                    <td class="px-2 py-2 text-center border">
                        <?php if ($isCancelled): ?><span class="text-red-500 mr-1">×</span><?php endif; ?>
                        <?php if ($cancelRequested && !$isCancelled): ?><span class="text-yellow-500 mr-1">⏳</span><?php endif; ?>
                        <span class="<?= $isCancelled ? 'text-red-500 line-through' : '' ?>"><?= htmlspecialchars($b['bet_number']) ?></span>
                    </td>
                    <td class="px-2 py-2 text-center text-xs border"><?= date('d/m/Y H:i:s', strtotime($b['created_at'])) ?></td>
                    <td class="px-2 py-2 text-left border text-xs">[<?= htmlspecialchars($b['category_name']) ?>] - <?= htmlspecialchars($b['lottery_name']) ?></td>
                    <td class="px-2 py-2 text-center border"><?= date('Y-m-d', strtotime($b['draw_date'])) ?></td>
                    <td class="px-2 py-2 text-center border"><?= $b['total_items'] ?></td>
                    <td class="px-2 py-2 text-center border"><?= formatMoney($b['total_amount']) ?></td>

                    <td class="px-2 py-2 text-center border text-[#2196f3]"><?= formatMoney($b['net_amount']) ?></td>
                    <td class="px-2 py-2 text-center border"><?= $winBadge ?></td>
                    <td class="px-2 py-2 text-center border text-xs"><?= htmlspecialchars($b['note'] ?? '') ?></td>
                    <td class="px-2 py-2 text-center border">
                        <div class="flex gap-1 justify-center">
                            <button onclick="viewBillDetail(<?= $b['id'] ?>)" class="w-7 h-7 bg-gray-100 hover:bg-gray-200 rounded border text-gray-500 transition" title="ดูรายละเอียด">
                                <i class="fas fa-list-ul text-xs"></i>
                            </button>
                            <?php if (!$isCancelled && !$cancelRequested): ?>
                            <button onclick="requestCancel(<?= $b['id'] ?>, '<?= htmlspecialchars($b['bet_number']) ?>')" class="w-7 h-7 bg-red-50 hover:bg-red-100 rounded border border-red-200 text-red-400 transition" title="ขอยกเลิก">
                                <i class="fas fa-ban text-xs"></i>
                            </button>
                            <?php elseif ($cancelRequested && !$isCancelled): ?>
                            <span class="text-[10px] text-yellow-600 font-medium">รออนุมัติ</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center items-center gap-2 py-3">
            <?php
            $baseUrl = '?' . http_build_query(array_filter([
                'filter' => $filterType, 'month' => $filterMonth, 'from' => $dateFrom, 'to' => $dateTo,
                'status' => $statusFilter, 'lottery' => $lotteryFilter ?: null
            ]));
            ?>
            <?php if ($page > 1): ?>
            <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-100 rounded text-sm hover:bg-gray-200 transition">← ก่อนหน้า</a>
            <?php endif; ?>
            
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
            <a href="<?= $baseUrl ?>&page=<?= $p ?>" class="px-3 py-1 rounded text-sm transition <?= $p === $page ? 'bg-[#2e7d32] text-white' : 'bg-gray-100 hover:bg-gray-200' ?>"><?= $p ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-100 rounded text-sm hover:bg-gray-200 transition">ถัดไป →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tab: By Lottery Type -->
    <div id="billPanel-byType" class="overflow-x-auto" style="display:none">
        <?php if (empty($betsByLottery)): ?>
        <div class="py-12 text-center text-gray-400"><i class="fas fa-inbox text-3xl mb-3 block"></i>ยังไม่มีรายการโพย</div>
        <?php else: foreach ($betsByLottery as $lotteryName => $lBets):
            $grpTotal = array_sum(array_column($lBets, 'total_items'));
            $grpAmount = array_sum(array_column($lBets, 'total_amount'));

            $grpNet = array_sum(array_column($lBets, 'net_amount'));
            $grpWin = array_sum(array_column($lBets, 'win_amount'));
            $grpWonCount = count(array_filter($lBets, fn($b) => $b['status'] === 'won'));
            $grpLostCount = count(array_filter($lBets, fn($b) => $b['status'] === 'lost'));
        ?>
        <div class="mb-3">
            <div class="text-sm font-bold text-[#2e7d32] py-1 border-b border-[#2e7d32] flex justify-between items-center">
                <span><?= htmlspecialchars($lotteryName) ?> (<?= count($lBets) ?> โพย)</span>
                <span class="text-xs font-normal">
                    <span class="text-green-600">ถูก <?= $grpWonCount ?></span> | 
                    <span class="text-red-500">ไม่ถูก <?= $grpLostCount ?></span> | 
                    <span class="text-blue-500">ยอด <?= formatMoney($grpNet) ?></span> | 
                    <span class="text-orange-500">จ่าย <?= formatMoney($grpWin) ?></span>
                </span>
            </div>
            <table class="w-full text-[13px] text-gray-600 border">
                <thead class="bg-[#e8f5e9]">
                    <tr>
                        <th class="px-2 py-1.5 text-center font-bold text-gray-700 border">เลขที่</th>
                        <th class="px-2 py-1.5 text-center font-bold text-gray-700 border">วันที่</th>
                        <th class="px-2 py-1.5 text-center font-bold text-gray-700 border">รายการ</th>
                        <th class="px-2 py-1.5 text-center font-bold text-gray-700 border">ยอด</th>

                        <th class="px-2 py-1.5 text-center font-bold text-gray-700 border">รวม</th>
                        <th class="px-2 py-1.5 text-center font-bold text-gray-700 border">ถูกรางวัล</th>
                        <th class="px-2 py-1.5 text-center font-bold text-gray-700 border">หมายเหตุ</th>
                        <th class="px-2 py-1.5 text-center font-bold text-gray-700 border w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lBets as $i => $b):
                        $isCancelled = $b['status'] === 'cancelled';
                        $rowClass = $isCancelled ? 'bg-red-50' : ($i % 2 === 0 ? 'bg-white' : 'bg-gray-50');
                        $winBadge = '';
                        switch ($b['status']) {
                            case 'won': $winBadge = '<span class="text-green-600 font-bold">' . formatMoney($b['win_amount']) . '</span>'; break;
                            case 'lost': $winBadge = '<span class="text-red-400">ไม่ถูก</span>'; break;
                            case 'cancelled': $winBadge = '<span class="text-red-500">ยกเลิก</span>'; break;
                            default: $winBadge = '<span class="text-blue-400">รอผล</span>'; break;
                        }
                    ?>
                    <tr class="<?= $rowClass ?> border-b hover:bg-blue-50/50 transition">
                        <td class="px-2 py-1.5 text-center border"><?= htmlspecialchars($b['bet_number']) ?></td>
                        <td class="px-2 py-1.5 text-center text-xs border"><?= date('d/m/Y H:i', strtotime($b['created_at'])) ?></td>
                        <td class="px-2 py-1.5 text-center border"><?= $b['total_items'] ?></td>
                        <td class="px-2 py-1.5 text-center border"><?= formatMoney($b['total_amount']) ?></td>

                        <td class="px-2 py-1.5 text-center border text-[#2196f3]"><?= formatMoney($b['net_amount']) ?></td>
                        <td class="px-2 py-1.5 text-center border"><?= $winBadge ?></td>
                        <td class="px-2 py-1.5 text-center border text-xs"><?= htmlspecialchars($b['note'] ?? '') ?></td>
                        <td class="px-2 py-1.5 text-center border">
                            <button onclick="viewBillDetail(<?= $b['id'] ?>)" class="w-7 h-7 bg-gray-100 hover:bg-gray-200 rounded border text-gray-500 transition" title="ดูรายละเอียด">
                                <i class="fas fa-list-ul text-xs"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="bg-[#e8f5e9] font-bold">
                        <td colspan="2" class="px-2 py-2 text-center border">รวม <?= count($lBets) ?> โพย</td>
                        <td class="px-2 py-2 text-center border"><?= $grpTotal ?></td>
                        <td class="px-2 py-2 text-center border"><?= formatMoney($grpAmount) ?></td>

                        <td class="px-2 py-2 text-center border text-[#2196f3]"><?= formatMoney($grpNet) ?></td>
                        <td class="px-2 py-2 text-center border text-green-600"><?= formatMoney($grpWin) ?></td>
                        <td colspan="2" class="border"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Detail Modal -->
<div id="billModal" class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-8 overflow-y-auto" style="display:none" onclick="if(event.target===this)closeBillModal()">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl mb-8 relative">
        <div id="billModalContent" class="p-0">
            <div class="p-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>
        </div>
    </div>
</div>

<script>
function setStatus(status) {
    document.getElementById('statusInput').value = status;
    document.getElementById('filterForm').submit();
}

function switchBillTab(tab) {
    const panels = { all: document.getElementById('billPanel-all'), byType: document.getElementById('billPanel-byType') };
    const tabs = { all: document.getElementById('billTab-all'), byType: document.getElementById('billTab-byType') };
    Object.keys(panels).forEach(t => {
        if (t === tab) {
            panels[t].style.display = '';
            tabs[t].className = 'px-4 py-2 text-sm font-medium border-b-2 border-[#2e7d32] text-[#2e7d32] bg-white';
        } else {
            panels[t].style.display = 'none';
            tabs[t].className = 'px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 bg-gray-50 rounded-t border border-b-0 border-gray-200 ml-1';
        }
    });
}

function getBetTypeLabel(type) {
    const labels = { '3top': '3 ตัวบน', '3tod': '3 ตัวโต๊ด', '2top': '2 ตัวบน', '2bot': '2 ตัวล่าง', 'run_top': 'วิ่งบน', 'run_bot': 'วิ่งล่าง' };
    return labels[type] || type;
}

function requestCancel(betId, betNumber) {
    Swal.fire({
        title: 'ขอยกเลิกโพย #' + betNumber,
        input: 'text',
        inputLabel: 'เหตุผล',
        inputValue: 'ลูกค้าไม่จ่ายเงิน',
        inputPlaceholder: 'ระบุเหตุผล...',
        showCancelButton: true,
        cancelButtonText: 'ไม่',
        confirmButtonText: 'ส่งคำขอยกเลิก',
        confirmButtonColor: '#e53935',
        icon: 'warning',
        html: '<div class="text-sm text-gray-500 mt-2"><i class="fas fa-info-circle"></i> คำขอจะถูกส่งถึงเจ้าของเพื่ออนุมัติ</div>'
    }).then(result => {
        if (result.isConfirmed) {
            fetch('bills.php?ajax=cancel_request&bet_id=' + betId + '&reason=' + encodeURIComponent(result.value || 'ลูกค้าไม่จ่ายเงิน'))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'ส่งคำขอยกเลิกแล้ว', text: 'รอเจ้าของอนุมัติ', timer: 2000, showConfirmButton: false });
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', confirmButtonColor: '#e53935' });
                    }
                });
        }
    });
}

function viewBillDetail(betId) {
    const modal = document.getElementById('billModal');
    const content = document.getElementById('billModalContent');
    modal.style.display = '';
    document.body.style.overflow = 'hidden';
    content.innerHTML = '<div class="p-8 text-center text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i> กำลังโหลด...</div>';
    
    fetch('bills.php?ajax=detail&bet_id=' + betId)
        .then(r => r.json())
        .then(data => {
            if (data.error) { content.innerHTML = '<div class="p-8 text-center text-red-500">ไม่พบข้อมูล</div>'; return; }
            
            const bet = data.bet;
            const items = data.items;
            const isCancelled = bet.status === 'cancelled';
            
            let statusBadge = '';
            if (bet.status === 'won') statusBadge = '<span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-bold">ถูกรางวัล</span>';
            else if (bet.status === 'lost') statusBadge = '<span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs font-bold">ไม่ถูกรางวัล</span>';
            else if (bet.status === 'cancelled') statusBadge = '<span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs font-bold">ยกเลิก</span>';
            else statusBadge = '<span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs font-bold">รอผล</span>';
            
            let totalAmount = 0, totalNet = 0, totalWin = 0;
            let rows = '';
            items.forEach(item => {
                const isWin = item.is_winner;
                const rowCls = isWin ? 'bg-green-50' : '';
                const winText = isWin ? '<span class="text-green-600 font-bold">' + fmt(item.win_amount) + '</span>' : '<span class="text-gray-400">-</span>';
                totalAmount += parseFloat(item.amount);

                totalNet += parseFloat(item.net_amount);
                totalWin += parseFloat(item.win_amount || 0);
                
                rows += '<tr class="border-b ' + rowCls + ' hover:bg-gray-50/50">';
                rows += '<td class="px-2 py-1.5 text-center border">' + getBetTypeLabel(item.bet_type) + '</td>';
                rows += '<td class="px-2 py-1.5 text-center border font-bold font-mono ' + (isWin ? 'text-green-700' : '') + '">' + item.number + '</td>';
                rows += '<td class="px-2 py-1.5 text-center border">' + fmt(item.amount) + '</td>';

                rows += '<td class="px-2 py-1.5 text-center border">' + fmt(item.net_amount) + '</td>';
                rows += '<td class="px-2 py-1.5 text-center border">' + fmt(item.pay_multiplier) + '</td>';
                rows += '<td class="px-2 py-1.5 text-center border">' + winText + '</td>';
                rows += '</tr>';
            });
            
            rows += '<tr class="bg-[#e8f5e9] font-bold">';
            rows += '<td class="px-2 py-1.5 text-center border">รวม</td>';
            rows += '<td class="px-2 py-1.5 text-center border">' + items.length + ' รายการ</td>';
            rows += '<td class="px-2 py-1.5 text-center border">' + fmt(totalAmount) + '</td>';

            rows += '<td class="px-2 py-1.5 text-center border">' + fmt(totalNet) + '</td>';
            rows += '<td class="px-2 py-1.5 text-center border"></td>';
            rows += '<td class="px-2 py-1.5 text-center border text-green-600">' + fmt(totalWin) + '</td>';
            rows += '</tr>';
            
            const html = `
                <div class="bg-[#e8f5e9] px-4 py-3 flex justify-between items-center rounded-t-lg">
                    <div>
                        <span class="font-bold text-[#2e7d32] text-sm">#${bet.bet_number}</span>
                        <span class="ml-2 text-sm text-gray-700">[${bet.category_name}] ${bet.lottery_name} - ${bet.draw_date}</span>
                        <span class="ml-2">${statusBadge}</span>
                    </div>
                    <button onclick="closeBillModal()" class="w-7 h-7 bg-white rounded-full border text-gray-500 hover:text-red-500 hover:border-red-300 transition text-xs">✕</button>
                </div>
                ${bet.note ? '<div class="px-4 py-1 bg-yellow-50 text-xs text-gray-600"><i class="fas fa-sticky-note mr-1 text-yellow-500"></i> หมายเหตุ: ' + bet.note + '</div>' : ''}
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px] text-gray-600 border-collapse">
                        <thead class="bg-[#e8f5e9]">
                            <tr>
                                <th class="px-2 py-2 text-center font-bold text-gray-700 border">ประเภท</th>
                                <th class="px-2 py-2 text-center font-bold text-gray-700 border">หมายเลข</th>
                                <th class="px-2 py-2 text-center font-bold text-gray-700 border">ยอดเดิมพัน</th>

                                <th class="px-2 py-2 text-center font-bold text-gray-700 border">รวม</th>
                                <th class="px-2 py-2 text-center font-bold text-gray-700 border">จ่าย</th>
                                <th class="px-2 py-2 text-center font-bold text-gray-700 border">ถูกรางวัล</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
            content.innerHTML = html;
        })
        .catch(() => { content.innerHTML = '<div class="p-8 text-center text-red-500">เกิดข้อผิดพลาด</div>'; });
}

function closeBillModal() {
    document.getElementById('billModal').style.display = 'none';
    document.body.style.overflow = '';
}

function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeBillModal(); });
</script>

<?php require_once 'includes/footer.php'; ?>
