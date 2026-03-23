<?php
$pageTitle = 'คีย์หวย - ตรวจผลรางวัล';
$currentPage = 'results';
require_once 'auth.php';
requireLogin();

// ==========================================
// AJAX: Get bets for a lottery type + draw date
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'lottery_bets') {
    header('Content-Type: application/json; charset=utf-8');
    $lotteryTypeId = intval($_GET['lottery_type_id'] ?? 0);
    $drawDate = $_GET['draw_date'] ?? '';
    
    $stmt = $pdo->prepare("
        SELECT b.*, lt.name as lottery_name, lc.name as category_name
        FROM bets b
        JOIN lottery_types lt ON b.lottery_type_id = lt.id
        JOIN lottery_categories lc ON lt.category_id = lc.id
        WHERE b.lottery_type_id = ? AND b.draw_date = ?
        ORDER BY b.created_at ASC
    ");
    $stmt->execute([$lotteryTypeId, $drawDate]);
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get results for this lottery
    $stmtR = $pdo->prepare("SELECT * FROM results WHERE lottery_type_id = ? AND draw_date = ?");
    $stmtR->execute([$lotteryTypeId, $drawDate]);
    $result = $stmtR->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['bets' => $bets, 'result' => $result]);
    exit;
}

// ==========================================
// AJAX: Get bet detail items
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'bet_detail') {
    header('Content-Type: application/json; charset=utf-8');
    $betId = intval($_GET['bet_id'] ?? 0);
    
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
                case 'run_top': if ($result['three_top'] && strpos($result['three_top'], $num) !== false) { $item['is_winner'] = true; $item['win_amount'] = $item['net_amount'] * $item['pay_multiplier']; } break;
                case 'run_bot': if ($result['two_bot'] && strpos($result['two_bot'], $num) !== false) { $item['is_winner'] = true; $item['win_amount'] = $item['net_amount'] * $item['pay_multiplier']; } break;
            }
        }
    }
    
    echo json_encode(['bet' => $bet, 'items' => $items, 'result' => $result]);
    exit;
}

function sorted_str($s) { $chars = str_split($s); sort($chars); return implode('', $chars); }

// ==========================================
// Main page: Fetch results grouped by category
// ==========================================
$selectedDate = $_GET['date'] ?? date('Y-m-d');

$sql = "SELECT r.*, lt.name as lottery_name, lt.flag_emoji, lc.name as category_name, lc.id as cat_id
        FROM results r
        JOIN lottery_types lt ON r.lottery_type_id = lt.id
        JOIN lottery_categories lc ON lt.category_id = lc.id
        WHERE r.draw_date = ?
        ORDER BY lc.sort_order, lt.sort_order";

$stmt = $pdo->prepare($sql);
$stmt->execute([$selectedDate]);
$results = $stmt->fetchAll();

// Group by category
$grouped = [];
foreach ($results as $r) {
    $catName = $r['category_name'];
    if (!isset($grouped[$catName])) $grouped[$catName] = [];
    $grouped[$catName][] = $r;
}

require_once 'includes/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="gradient-header px-4 py-3 text-white flex items-center space-x-2">
        <i class="fas fa-trophy"></i>
        <span class="font-bold">ตรวจผลรางวัล</span>
    </div>

    <!-- Filters -->
    <div class="p-4 bg-gray-50 border-b">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 block mb-1">วันที่</label>
                <input type="date" name="date" value="<?= $selectedDate ?>" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-green-500 outline-none">
            </div>
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
                <i class="fas fa-search mr-1"></i>ค้นหา
            </button>
        </form>
    </div>

    <!-- Results grouped by category -->
    <div class="p-4">
        <?php if (empty($results)): ?>
        <div class="text-center py-12">
            <i class="fas fa-search text-gray-300 text-4xl mb-3 block"></i>
            <p class="text-gray-400">ไม่พบผลรางวัลสำหรับวันที่ <?= formatDateDisplay($selectedDate) ?></p>
        </div>
        <?php else: ?>
        
        <?php foreach ($grouped as $catName => $catResults): ?>
        <div class="mb-6">
            <h3 class="text-[15px] font-bold text-[#2e7d32] mb-2"><i class="fas fa-layer-group mr-1"></i> <?= htmlspecialchars($catName) ?></h3>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px] border border-gray-200">
                    <thead>
                        <tr class="bg-[#e0f2f1]">
                            <th class="px-3 py-2 text-left font-bold text-[#00796b] border">ประเภทหวย</th>
                            <th class="px-3 py-2 text-center font-bold text-[#00796b] border">งวด</th>
                            <th class="px-3 py-2 text-center font-bold text-[#00796b] border">3 ตัวบน</th>
                            <th class="px-3 py-2 text-center font-bold text-[#00796b] border">2 ตัวบน</th>
                            <th class="px-3 py-2 text-center font-bold text-[#00796b] border">2 ตัวล่าง</th>
                            <th class="px-3 py-2 text-center font-bold text-[#00796b] border">ตรวจผล</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catResults as $i => $r): ?>
                        <tr class="<?= $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> hover:bg-blue-50/50 transition">
                            <td class="px-3 py-2 border"><?= htmlspecialchars($r['lottery_name']) ?></td>
                            <td class="px-3 py-2 text-center border"><?= $r['draw_date'] ?></td>
                            <td class="px-3 py-2 text-center border font-bold text-lg text-green-700"><?= $r['three_top'] ?? '-' ?></td>
                            <td class="px-3 py-2 text-center border font-bold text-lg text-blue-600"><?= $r['two_top'] ?? '-' ?></td>
                            <td class="px-3 py-2 text-center border font-bold text-lg text-cyan-500"><?= $r['two_bot'] ?? '-' ?></td>
                            <td class="px-3 py-2 text-center border">
                                <button onclick="checkPrize(<?= $r['lottery_type_id'] ?>, '<?= $r['draw_date'] ?>', '<?= htmlspecialchars($r['category_name']) ?>', '<?= htmlspecialchars($r['lottery_name']) ?>')"
                                    class="text-[#00796b] hover:text-[#004d40] text-xs font-medium">
                                    <i class="fas fa-search mr-0.5"></i> ตรวจรางวัล
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Bets list for a lottery -->
<div id="modalBets" class="fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-8 px-4 hidden" onclick="if(event.target===this)closeBetsModal()">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[85vh] flex flex-col">
        <div class="flex justify-between items-center px-4 py-3 bg-[#00796b] text-white rounded-t-lg">
            <div id="modalBetsTitle" class="font-bold text-sm"></div>
            <button onclick="closeBetsModal()" class="text-white hover:text-gray-200 text-xl">&times;</button>
        </div>
        <div id="modalBetsBody" class="overflow-auto flex-1 p-0"></div>
    </div>
</div>

<!-- Modal: Bet detail items -->
<div id="modalDetail" class="fixed inset-0 bg-black/50 z-[60] flex items-start justify-center pt-8 px-4 hidden" onclick="if(event.target===this)closeDetailModal()">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[85vh] flex flex-col">
        <div class="flex justify-between items-center px-4 py-3 bg-[#e8f5e9] border-b rounded-t-lg">
            <div id="modalDetailTitle" class="font-bold text-sm text-[#2e7d32]"></div>
            <button onclick="closeDetailModal()" class="text-gray-500 hover:text-gray-800 text-xl">&times;</button>
        </div>
        <div id="modalDetailBody" class="overflow-auto flex-1 p-0"></div>
    </div>
</div>

<script>
function fmt(n) { return parseFloat(n || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function getBetTypeLabel(t) {
    const m = {'3top':'3 ตัวบน','3tod':'3 ตัวโต๊ด','2top':'2 ตัวบน','2bot':'2 ตัวล่าง','run_top':'วิ่งบน','run_bot':'วิ่งล่าง'};
    return m[t] || t;
}

async function checkPrize(lotteryTypeId, drawDate, catName, lotteryName) {
    const modal = document.getElementById('modalBets');
    const title = document.getElementById('modalBetsTitle');
    const body = document.getElementById('modalBetsBody');
    
    title.innerHTML = `ตรวจรางวัล ::: [${catName}] ${lotteryName} - ${drawDate}`;
    body.innerHTML = '<div class="text-center py-12 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>';
    modal.classList.remove('hidden');
    
    try {
        const res = await fetch(`results.php?ajax=lottery_bets&lottery_type_id=${lotteryTypeId}&draw_date=${drawDate}`);
        const data = await res.json();
        const bets = data.bets || [];
        
        if (bets.length === 0) {
            body.innerHTML = '<div class="text-center py-12 text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>ไม่มีรายการแทงสำหรับหวยนี้</div>';
            return;
        }
        
        let totalItems = 0, totalAmount = 0, totalNet = 0, totalWin = 0;
        let rows = '';
        
        bets.forEach(b => {
            const isCancelled = b.status === 'cancelled';
            const rowCls = isCancelled ? 'bg-red-50 text-gray-400' : (b.status === 'won' ? 'bg-green-50' : '');
            const statusText = b.status === 'won' ? '<span class="text-green-600 font-bold">ถูกรางวัล</span>' :
                               b.status === 'lost' ? '<span class="text-gray-500">ไม่ถูกรางวัล</span>' :
                               b.status === 'cancelled' ? '<span class="text-red-500">ยกเลิก</span>' :
                               '<span class="text-blue-500">รอผล</span>';
            
            if (!isCancelled) {
                totalItems += parseInt(b.total_items || 0);
                totalAmount += parseFloat(b.total_amount || 0);
                totalNet += parseFloat(b.net_amount || 0);
                totalWin += parseFloat(b.win_amount || 0);
            }
            
            rows += `<tr class="border-b hover:bg-blue-50/30 ${rowCls} cursor-pointer" onclick="viewBetDetail(${b.id})">
                <td class="px-2 py-2 text-center border font-mono text-xs">${b.bet_number || ''}</td>
                <td class="px-2 py-2 text-center border text-xs whitespace-nowrap">${b.created_at || ''}</td>
                <td class="px-2 py-2 text-center border">${b.total_items}</td>
                <td class="px-2 py-2 text-right border">${fmt(b.total_amount)}</td>
                <td class="px-2 py-2 text-right border font-bold">${fmt(b.net_amount)}</td>
                <td class="px-2 py-2 text-center border">${statusText}</td>
                <td class="px-2 py-2 text-center border text-xs">${b.note || ''}</td>
                <td class="px-2 py-2 text-center border">
                    <button class="w-7 h-7 bg-gray-100 hover:bg-gray-200 rounded border text-gray-500" title="ดูรายละเอียด">
                        <i class="fas fa-list-ul text-xs"></i>
                    </button>
                </td>
            </tr>`;
        });
        
        // Summary row
        rows += `<tr class="bg-[#e8f5e9] font-bold">
            <td colspan="2" class="px-2 py-2 text-center border">รวม</td>
            <td class="px-2 py-2 text-center border">${totalItems}</td>
            <td class="px-2 py-2 text-right border">${fmt(totalAmount)}</td>
            <td class="px-2 py-2 text-right border">${fmt(totalNet)}</td>
            <td class="px-2 py-2 text-center border text-green-700">${fmt(totalWin)}</td>
            <td colspan="2" class="border"></td>
        </tr>`;
        
        body.innerHTML = `<div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="bg-[#00796b] text-white">
                        <th class="px-2 py-2 text-center border">เลขที่</th>
                        <th class="px-2 py-2 text-center border">วันที่</th>
                        <th class="px-2 py-2 text-center border">รายการ</th>
                        <th class="px-2 py-2 text-center border">ยอด</th>
                        <th class="px-2 py-2 text-center border">รวม</th>
                        <th class="px-2 py-2 text-center border">ถูกรางวัล</th>
                        <th class="px-2 py-2 text-center border">หมายเหตุ</th>
                        <th class="px-2 py-2 text-center border w-10"></th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    } catch (e) {
        body.innerHTML = `<div class="text-center py-8 text-red-500">เกิดข้อผิดพลาด: ${e.message}</div>`;
    }
}

function closeBetsModal() {
    document.getElementById('modalBets').classList.add('hidden');
}

async function viewBetDetail(betId) {
    const modal = document.getElementById('modalDetail');
    const title = document.getElementById('modalDetailTitle');
    const body = document.getElementById('modalDetailBody');
    
    title.innerHTML = 'กำลังโหลด...';
    body.innerHTML = '<div class="text-center py-12 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>';
    modal.classList.remove('hidden');
    
    try {
        const res = await fetch(`results.php?ajax=bet_detail&bet_id=${betId}`);
        const data = await res.json();
        const bet = data.bet;
        const items = data.items || [];
        
        const statusBadge = bet.status === 'won' ? '<span class="bg-green-500 text-white px-2 py-0.5 rounded text-xs">ถูกรางวัล</span>' :
                           bet.status === 'lost' ? '<span class="bg-gray-500 text-white px-2 py-0.5 rounded text-xs">ไม่ถูกรางวัล</span>' :
                           bet.status === 'cancelled' ? '<span class="bg-red-500 text-white px-2 py-0.5 rounded text-xs">ยกเลิก</span>' :
                           '<span class="bg-blue-500 text-white px-2 py-0.5 rounded text-xs">รอผล</span>';
        
        title.innerHTML = `#${bet.bet_number} [${bet.category_name}] ${bet.lottery_name} - ${bet.draw_date} ${statusBadge}`;
        
        let totalAmount = 0, totalNet = 0, totalWin = 0;
        let rows = '';
        
        items.forEach(item => {
            const isWin = item.is_winner;
            const rowCls = isWin ? 'bg-green-50' : '';
            const winText = isWin ? `<span class="text-green-600 font-bold">${fmt(item.win_amount)}</span>` : '<span class="text-gray-400">-</span>';
            
            totalAmount += parseFloat(item.amount);
            totalNet += parseFloat(item.net_amount);
            totalWin += parseFloat(item.win_amount || 0);
            
            rows += `<tr class="border-b ${rowCls} hover:bg-gray-50/50">
                <td class="px-2 py-1.5 text-center border">${getBetTypeLabel(item.bet_type)}</td>
                <td class="px-2 py-1.5 text-center border font-bold font-mono ${isWin ? 'text-green-700' : ''}">${item.number}</td>
                <td class="px-2 py-1.5 text-center border">${fmt(item.amount)}</td>
                <td class="px-2 py-1.5 text-center border">${fmt(item.net_amount)}</td>
                <td class="px-2 py-1.5 text-center border">${fmt(item.pay_multiplier)}</td>
                <td class="px-2 py-1.5 text-center border">${winText}</td>
            </tr>`;
        });
        
        rows += `<tr class="bg-[#e8f5e9] font-bold">
            <td class="px-2 py-1.5 text-center border">รวม</td>
            <td class="px-2 py-1.5 text-center border">${items.length} รายการ</td>
            <td class="px-2 py-1.5 text-center border">${fmt(totalAmount)}</td>
            <td class="px-2 py-1.5 text-center border">${fmt(totalNet)}</td>
            <td class="px-2 py-1.5 text-center border"></td>
            <td class="px-2 py-1.5 text-center border text-green-600">${fmt(totalWin)}</td>
        </tr>`;
        
        body.innerHTML = `<div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="bg-[#2e7d32] text-white">
                        <th class="px-2 py-2 text-center border">ประเภท</th>
                        <th class="px-2 py-2 text-center border">หมายเลข</th>
                        <th class="px-2 py-2 text-center border">ยอดเดิมพัน</th>
                        <th class="px-2 py-2 text-center border">รวม</th>
                        <th class="px-2 py-2 text-center border">จ่าย</th>
                        <th class="px-2 py-2 text-center border">ถูกรางวัล</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
    } catch (e) {
        body.innerHTML = `<div class="text-center py-8 text-red-500">เกิดข้อผิดพลาด: ${e.message}</div>`;
    }
}

function closeDetailModal() {
    document.getElementById('modalDetail').classList.add('hidden');
}
</script>

<?php require_once 'includes/footer.php'; ?>
