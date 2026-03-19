<?php
$pageTitle = 'คีย์หวย - แทงหวย';
$currentPage = 'bet';
require_once 'auth.php';
requireLogin();

$lotteryId = intval($_GET['id'] ?? 0);

// If no lottery selected, show selection page
if (!$lotteryId) {
    header('Location: index.php');
    exit;
}

// Fetch lottery info
$stmt = $pdo->prepare("
    SELECT lt.*, lc.name as category_name 
    FROM lottery_types lt
    JOIN lottery_categories lc ON lt.category_id = lc.id
    WHERE lt.id = ?
");
$stmt->execute([$lotteryId]);
$lottery = $stmt->fetch();

if (!$lottery) {
    header('Location: index.php');
    exit;
}

// Fetch pay rates
$stmt = $pdo->prepare("SELECT * FROM pay_rates WHERE lottery_type_id = ? ORDER BY FIELD(bet_type, '3top','3tod','2top','2bot','run_top','run_bot')");
$stmt->execute([$lotteryId]);
$rates = $stmt->fetchAll();

// Fetch past results
$stmt = $pdo->prepare("SELECT r.*, lt.name as lottery_name FROM results r JOIN lottery_types lt ON r.lottery_type_id = lt.id WHERE r.lottery_type_id = ? ORDER BY r.draw_date DESC LIMIT 5");
$stmt->execute([$lotteryId]);
$pastResults = $stmt->fetchAll();

$flagUrl = getFlagForCountry($lottery['flag_emoji']);
$drawDate = $lottery['draw_date'] ? formatDateDisplay($lottery['draw_date']) : date('d-m-Y');
$closeDateTime = $lottery['draw_date'] . ' ' . $lottery['close_time'];

require_once 'includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Left: Betting Form -->
    <div class="lg:col-span-2 space-y-4">
        
        <!-- Header -->
        <div class="bg-white px-2 py-2">
            <a href="index.php" class="text-sm font-bold text-[#37474f] flex items-center mb-1">
                <i class="fas fa-exchange-alt mr-2"></i> เปลี่ยนหวย
            </a>
            <div class="text-[#c62828] text-[13px] font-bold">
                เหลือเวลา <span id="mainCountdown">00:00:00</span>
            </div>
        </div>

        <!-- Lottery Info Box -->
        <div class="bg-[#fafafa] border border-[#c62828] p-3 relative">
            <div class="flex justify-between items-start mb-2">
                <div class="font-bold text-[#c62828] text-[15px]">
                    [<?= htmlspecialchars($lottery['category_name']) ?>] <?= htmlspecialchars($lottery['name']) ?>
                </div>
                <div class="font-bold text-[#c62828] text-[15px]">
                    <?= $drawDate ?>
                </div>
            </div>
            
            <div class="flex items-center text-sm mt-3">
                <span class="text-gray-600 mr-2">อัตราจ่าย:</span>
                <select class="border border-gray-300 rounded px-2 py-0.5 text-xs bg-white text-gray-700 outline-none mr-2">
                    <option>หวย100</option>
                </select>
                <a href="#rates-section" class="text-[#1976d2] text-xs hover:underline">ดูรายละเอียด</a>
            </div>
            
            <img src="<?= $flagUrl ?>" alt="flag" class="absolute bottom-3 right-3 w-8 h-5 object-cover border border-gray-300">
        </div>

        <!-- Bet Type Tabs -->
        <div class="bg-white border border-[#c62828]">
            <div class="flex bg-[#f5f5f5] border-b border-[#e0e0e0]">
                <button onclick="switchBetTab('quick')" id="tab-quick" class="bet-tab px-6 py-3 text-sm font-bold bg-white border-t-[3px] border-[#e53935] text-gray-800">แทงเร็ว</button>
                <button onclick="switchBetTab('classic')" id="tab-classic" class="bet-tab px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-800 border-t-[3px] border-transparent">แทงแบบคลาสสิค</button>
                <button onclick="switchBetTab('paste')" id="tab-paste" class="bet-tab px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-800 border-t-[3px] border-transparent">วางโพย</button>
            </div>

            <!-- Quick Mode -->
            <div id="panel-quick" class="p-3">
                <div class="mb-4">
                    <h3 class="font-bold text-gray-800 text-[15px]">แทงเร็ว</h3>
                    <div class="flex justify-between items-center mt-2">
                        <div class="text-sm text-gray-700">
                            [<?= htmlspecialchars($lottery['category_name']) ?>] <?= htmlspecialchars($lottery['name']) ?>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-sm text-gray-700 mb-1"><?= $drawDate ?></span>
                            <img src="<?= $flagUrl ?>" alt="flag" class="w-8 h-5 object-cover border border-gray-300">
                        </div>
                    </div>
                </div>
                
                <!-- Bet Type Buttons & Right Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-6">
                    <div class="flex flex-wrap gap-2">
                        <button onclick="setBetType('2')" id="btn-type-2" class="bet-type-btn px-4 py-1.5 rounded text-sm font-bold bg-[#ffca28] text-yellow-900 border border-[#ffca28]">2 ตัว</button>
                        <button onclick="setBetType('3')" id="btn-type-3" class="bet-type-btn px-4 py-1.5 rounded text-sm font-medium bg-[#fff8e1] text-[#f57f17] border border-[#ffca28] hover:bg-[#ffca28] hover:text-yellow-900">3 ตัว</button>
                        <button onclick="setBetType('6')" id="btn-type-6" class="bet-type-btn px-4 py-1.5 rounded text-sm font-medium bg-[#fff8e1] text-[#f57f17] border border-[#ffca28] hover:bg-[#ffca28] hover:text-yellow-900">6 กลับ</button>
                        <button onclick="setBetType('19')" id="btn-type-19" class="bet-type-btn px-4 py-1.5 rounded text-sm font-medium bg-[#fff8e1] text-[#f57f17] border border-[#ffca28] hover:bg-[#ffca28] hover:text-yellow-900">19 ประตู</button>
                        <button onclick="setBetType('run')" id="btn-type-run" class="bet-type-btn px-4 py-1.5 rounded text-sm font-medium bg-[#fff8e1] text-[#f57f17] border border-[#ffca28] hover:bg-[#ffca28] hover:text-yellow-900">เลขวิ่ง</button>
                        <button onclick="setBetType('win')" id="btn-type-win" class="bet-type-btn px-4 py-1.5 rounded text-sm font-medium bg-[#fff8e1] text-[#f57f17] border border-[#ffca28] hover:bg-[#ffca28] hover:text-yellow-900">วินเลข</button>
                    </div>
                    
                    <div class="flex flex-col gap-1.5 min-w-[100px]">
                        <button onclick="cancelBet()" class="px-3 py-1.5 bg-[#e53935] text-white rounded text-xs font-medium hover:bg-red-600 transition flex items-center justify-center">
                            <i class="fas fa-trash-alt mr-1"></i> ยกเลิก
                        </button>
                        <button onclick="clearDuplicates()" class="px-3 py-1.5 bg-[#1e88e5] text-white rounded text-xs font-medium hover:bg-blue-600 transition flex items-center justify-center">
                            <i class="fas fa-ban mr-1"></i> ลบเลขซ้ำ
                        </button>
                    </div>
                </div>

                <!-- Number Entry Form -->
                <div class="mb-4">
                    <button onclick="addDoubleBet()" class="mb-2 text-sm bg-[#ffca28] text-yellow-900 px-3 py-1 rounded font-medium hover:bg-yellow-400 transition inline-block">
                        <i class="fas fa-plus mr-1"></i> เลขเบิ้ล
                    </button>
                    
                    <div class="grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-12 sm:col-span-3">
                            <label class="text-[11px] text-gray-500 block mb-0.5">ใส่เลข</label>
                            <input type="text" id="numInput" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none h-[40px]" maxlength="2">
                        </div>
                        <div class="col-span-12 sm:col-span-1 flex items-end">
                            <button onclick="reverseNumber()" class="w-full bg-[#ffca28] text-yellow-900 py-2 rounded text-[13px] font-bold hover:bg-yellow-400 transition h-[40px]">กลับเลข</button>
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label class="text-[11px] text-gray-500 block mb-0.5 text-center">บน</label>
                            <input type="number" id="topAmount" class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-center focus:border-blue-500 outline-none h-[40px]">
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label class="text-[11px] text-gray-500 block mb-0.5 text-center">ล่าง</label>
                            <input type="number" id="botAmount" class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-center focus:border-blue-500 outline-none h-[40px]">
                        </div>
                        <div class="col-span-12 sm:col-span-2">
                            <button onclick="addBetItem()" class="w-full bg-[#26a69a] text-white py-2 rounded text-[13px] hover:bg-teal-600 transition h-[40px] flex items-center justify-center font-medium">
                                <i class="fas fa-plus mr-1"></i> เพิ่มบิล
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bet Items Box (Grouped) -->
                <div class="border border-gray-300 bg-white mb-4 relative min-h-[100px]">
                    <div id="betItemsBody" class="divide-y divide-gray-100">
                        <div id="emptyRow" class="p-8 text-center text-gray-400">
                            ยังไม่มีรายการ
                        </div>
                    </div>
                </div>

                <!-- Footer Summary (Image 1 Style) -->
                <div class="border-t border-[#c62828] pt-4 mt-6">
                    <div class="flex items-center mb-4">
                        <label class="text-[13px] text-gray-600 mr-2 whitespace-nowrap">หมายเหตุ:</label>
                        <input type="text" id="betNote" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:border-blue-500 outline-none">
                        <img src="<?= getFlagForCountry('US') ?>" alt="flag" class="w-8 h-5 object-cover ml-2 border border-gray-300 hidden sm:block">
                    </div>
                    
                    <div class="text-center">
                        <div class="text-[15px] text-[#37474f] font-medium mb-1">
                            [<?= htmlspecialchars($lottery['category_name']) ?>] <?= htmlspecialchars($lottery['name']) ?> - <?= $drawDate ?>
                        </div>
                        <div class="text-[#1565c0] text-xl font-bold mb-4 underline">
                            รวม <span id="totalAmount">0.00</span> บาท
                        </div>
                        
                        <div class="flex justify-center gap-4">
                            <button onclick="clearAllBets()" class="px-6 py-2 bg-[#e53935] text-white rounded text-sm font-medium hover:bg-red-600 transition shadow-sm">
                                ล้างตาราง
                            </button>
                            <button onclick="saveBet()" class="px-6 py-2 bg-[#1e88e5] text-white rounded text-sm font-medium hover:bg-blue-600 transition shadow-sm">
                                บันทึก
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="panel-classic" class="hidden">
                <p class="text-gray-400 text-center py-8">โหมดคลาสสิค - กรุณาใช้แทงเร็ว</p>
            </div>
            <div id="panel-paste" class="hidden">
                <p class="text-gray-400 text-center py-8">โหมดวางโพย - กรุณาใช้แทงเร็ว</p>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div class="space-y-4">
        <!-- Pay Rates -->
        <div id="rates-section" class="bg-white border border-[#2e7d32]">
            <div class="bg-green-100 flex items-center mb-0 border-b border-[#2e7d32]">
                <div class="px-3 py-2 text-[#2e7d32] font-bold text-sm w-full"><i class="fas fa-user mr-1 text-black"></i> อัตราจ่าย</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px] border-collapse">
                    <thead class="bg-[#2e7d32] text-white">
                        <tr>
                            <th class="px-2 py-1.5 text-left font-normal border border-[#2e7d32]"><?= htmlspecialchars($lottery['name']) ?> (หวย100)</th>
                            <th class="px-2 py-1.5 text-center font-normal border border-[#2e7d32]">จ่าย (บาท)</th>
                            <th class="px-2 py-1.5 text-center font-normal border border-[#2e7d32]">ลด (%)</th>
                            <th class="px-2 py-1.5 text-center font-normal border border-[#2e7d32]">ขั้นต่ำ (บาท)</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <?php if (empty($rates)): ?>
                        <tr><td colspan="4" class="px-2 py-3 text-center text-gray-400">ยังไม่มีอัตราจ่าย</td></tr>
                        <?php else: foreach ($rates as $r): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-1.5 border border-gray-300"><?= htmlspecialchars($r['rate_label'] ?? getBetTypeLabel($r['bet_type'])) ?></td>
                            <td class="px-2 py-1.5 text-center border border-gray-300"><?= formatMoney($r['pay_rate']) ?></td>
                            <td class="px-2 py-1.5 text-center border border-gray-300"><?= $r['discount'] > 0 ? formatMoney($r['discount']) : '-' ?></td>
                            <td class="px-2 py-1.5 text-center border border-gray-300"><?= $r['min_bet'] ?> - <?= number_format($r['max_bet']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Numbers Panel -->
        <div class="bg-white border border-[#2e7d32]">
            <div class="bg-[#2e7d32] text-white font-bold text-sm px-3 py-2 flex items-center">
                <i class="fas fa-window-maximize mr-2 opacity-80"></i> เลขอั้น
            </div>
            <div class="bg-[#2e7d32] px-1 pt-1 flex gap-1">
                <button onclick="switchNumTab('3d')" id="numTab-3d" class="num-tab flex-1 py-1 text-center text-[13px] font-medium bg-[#2e7d32] text-white border border-[#2e7d32] hover:bg-green-700">3 ตัว</button>
                <button onclick="switchNumTab('2d')" id="numTab-2d" class="num-tab flex-1 py-1 text-center text-[13px] font-bold bg-white text-gray-800 rounded-t border-t border-l border-r border-[#2e7d32]">2 ตัว</button>
                <button onclick="switchNumTab('run')" id="numTab-run" class="num-tab flex-1 py-1 text-center text-[13px] font-medium bg-[#2e7d32] text-white border border-[#2e7d32] hover:bg-green-700">เลขวิ่ง</button>
            </div>
            <div id="numPanel-content" class="bg-white border-t-0 p-2">
                <table class="w-full text-[12px] border-collapse text-gray-700 text-center">
                    <thead>
                        <tr>
                            <th colspan="3" class="px-2 py-1.5 border border-gray-300 bg-gray-50">เรทจ่าย (บาท)</th>
                        </tr>
                        <tr class="bg-gray-50">
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">เลข</th>
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">2 ตัวบน</th>
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">2 ตัวล่าง</th>
                        </tr>
                    </thead>
                    <tbody id="numListBody">
                        <tr><td colspan="3" class="px-2 py-4 border border-gray-300 text-gray-500">ไม่มีเลขอั้น</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Past Results -->
        <div class="bg-white border border-[#2e7d32]">
            <div class="px-3 py-2 text-[#2e7d32] font-bold text-[15px] border-b border-[#2e7d32]">
                <i class="fas fa-star mr-1 text-green-800"></i> ผลงวดที่ผ่านมา
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px] border-collapse text-center">
                    <thead class="text-[#2e7d32]">
                        <tr>
                            <th class="px-2 py-1.5 font-bold border border-gray-300 text-left">หวย</th>
                            <th class="px-2 py-1.5 font-bold border border-gray-300">งวดวันที่</th>
                            <th class="px-2 py-1.5 font-bold border border-gray-300">3 ตัวบน</th>
                            <th class="px-2 py-1.5 font-bold border border-gray-300">2 ตัวล่าง</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <?php if (empty($pastResults)): ?>
                        <tr><td colspan="4" class="px-2 py-4 border border-gray-300 text-gray-400">ยังไม่มีผลรางวัล</td></tr>
                        <?php else: foreach ($pastResults as $pr): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-2 border border-gray-300 text-left"><?= htmlspecialchars($pr['lottery_name']) ?></td>
                            <td class="px-2 py-2 border border-gray-300"><?= date('d-m-Y', strtotime($pr['draw_date'])) ?></td>
                            <td class="px-2 py-2 border border-gray-300 font-bold"><?= $pr['three_top'] ?? '-' ?></td>
                            <td class="px-2 py-2 border border-gray-300 font-bold"><?= $pr['two_bot'] ?? '-' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const LOTTERY_ID = <?= $lotteryId ?>;
const CLOSE_TIME = '<?= $closeDateTime ?>';
let currentBetType = '2';
let betGroups = [];

// ... countdown and tabs code ...
function updateMainCountdown() {
    const close = new Date(CLOSE_TIME);
    const now = new Date();
    const diff = close - now;
    const el1 = document.getElementById('mainCountdown');
    
    if (diff > 0) {
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        const text = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        if (el1) el1.textContent = text;
    } else {
        if (el1) el1.textContent = 'ปิดรับแล้ว';
    }
}
setInterval(updateMainCountdown, 1000);
updateMainCountdown();

function switchBetTab(tab) {
    document.querySelectorAll('.bet-tab').forEach(b => { b.className = 'bet-tab px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-800 border-t-[3px] border-transparent'; });
    document.getElementById('tab-' + tab).className = 'bet-tab px-6 py-3 text-sm font-bold bg-white border-t-[3px] border-[#e53935] text-gray-800';
    ['quick','classic','paste'].forEach(p => document.getElementById('panel-' + p).classList.add('hidden'));
    document.getElementById('panel-' + tab).classList.remove('hidden');
}

function switchNumTab(tab) {
    document.querySelectorAll('.num-tab').forEach(b => { b.className = 'num-tab flex-1 py-1 text-center text-[13px] font-medium bg-[#2e7d32] text-white border border-[#2e7d32] hover:bg-green-700'; });
    const active = document.getElementById('numTab-' + tab);
    if(active) active.className = 'num-tab flex-1 py-1 text-center text-[13px] font-bold bg-white text-gray-800 rounded-t border-t border-l border-r border-[#2e7d32]';
}

function setBetType(type) {
    currentBetType = type;
    document.querySelectorAll('.bet-type-btn').forEach(b => { b.className = 'bet-type-btn px-4 py-1.5 rounded text-sm font-medium bg-[#fff8e1] text-[#f57f17] border border-[#ffca28] hover:bg-[#ffca28] hover:text-yellow-900'; });
    const btn = document.getElementById('btn-type-' + type);
    if (btn) btn.className = 'bet-type-btn px-4 py-1.5 rounded text-sm font-bold bg-[#ffca28] text-yellow-900 border border-[#ffca28]';
    
    const numInput = document.getElementById('numInput');
    if (type === '3' || type === '6') numInput.maxLength = 3;
    else if (type === 'run' || type === 'win') numInput.maxLength = 1;
    else numInput.maxLength = 2;
}

function reverseNumber() {
    const input = document.getElementById('numInput');
    input.value = input.value.split('').reverse().join('');
}

function addBetItem() {
    let numInputStr = document.getElementById('numInput').value.trim();
    const top = parseFloat(document.getElementById('topAmount').value) || 0;
    const bot = parseFloat(document.getElementById('botAmount').value) || 0;
    
    if (!numInputStr) { alert('กรุณาใส่เลข'); return; }
    if (top === 0 && bot === 0) { alert('กรุณาใส่จำนวนเงิน'); return; }
    
    // Split input by space or comma if user pasted multiple
    let numbers = numInputStr.split(/[\s,]+/).filter(n => n.length > 0);
    if(numbers.length === 0) return;

    let typeLabel1 = currentBetType === 'run' ? 'เลขวิ่ง' : (currentBetType === 'win' ? 'วินเลข' : currentBetType + ' ตัว');
    let typeLabel2 = (top>0 && bot>0) ? 'บน x ล่าง' : (top>0 ? 'บน' : 'ล่าง');
    let typeLabel3 = (top>0 && bot>0) ? `${top} x ${bot}` : (top>0 ? top : bot);

    if (currentBetType === '3') typeLabel2 = (top>0 && bot>0) ? 'บน x โต๊ด' : (top>0 ? 'บน' : 'โต๊ด');
    
    // Find existing group or create new
    let group = betGroups.find(g => g.typeCategory === currentBetType && g.amountTop === top && g.amountBot === bot);
    
    if (group) {
        numbers.forEach(n => {
            if(!group.numbers.includes(n)) group.numbers.push(n);
        });
    } else {
        betGroups.push({
            id: Date.now(),
            typeCategory: currentBetType,
            typeLabel1: typeLabel1,
            typeLabel2: typeLabel2,
            typeLabel3: typeLabel3,
            numbers: numbers,
            amountTop: top,
            amountBot: bot
        });
    }
    
    renderBetItems();
    
    document.getElementById('numInput').value = '';
    document.getElementById('numInput').focus();
}

function addDoubleBet() {
    const num = document.getElementById('numInput').value.trim();
    if (!num || num.length < 2) { alert('กรุณาใส่เลขอย่างน้อย 2 หลัก'); return; }
    const reversed = num.split('').reverse().join('');
    
    const top = parseFloat(document.getElementById('topAmount').value) || 0;
    const bot = parseFloat(document.getElementById('botAmount').value) || 0;
    if (top === 0 && bot === 0) { alert('กรุณาใส่จำนวนเงิน'); return; }
    
    if (reversed !== num) {
        document.getElementById('numInput').value = num + " " + reversed;
        addBetItem();
    } else {
        addBetItem();
    }
}

function removeBetGroup(id) {
    betGroups = betGroups.filter(g => g.id !== id);
    renderBetItems();
}

function clearAllBets() {
    if (betGroups.length === 0) return;
    if (!confirm('ต้องการล้างตารางทั้งหมด?')) return;
    betGroups = [];
    renderBetItems();
}

function cancelBet() {
    clearAllBets();
    document.getElementById('betNote').value = '';
}

function clearDuplicates() {
    // Duplicates within groups are already prevented during entry.
    renderBetItems();
}

function renderBetItems() {
    const tbody = document.getElementById('betItemsBody');
    if (betGroups.length === 0) {
        tbody.innerHTML = '<div id="emptyRow" class="p-8 text-center text-gray-400">ยังไม่มีรายการ</div>';
        document.getElementById('totalAmount').textContent = '0.00';
        return;
    }
    
    let total = 0;
    tbody.innerHTML = betGroups.map(g => {
        let groupCost = 0;
        let numCount = g.numbers.length;
        if(g.typeCategory === 'win') {
            // formula for win is different, simple representation for now
            groupCost = numCount * (g.amountTop + g.amountBot);
        } else {
            // standard multiplier
            let multiplier = (g.amountTop>0 ? 1 : 0) + (g.amountBot>0 ? 1 : 0);
            if(g.typeCategory === '6') multiplier *= 6;
            if(g.typeCategory === '19') multiplier *= 19;
            groupCost = numCount * multiplier * Math.max(g.amountTop, g.amountBot); // Simplified
            // Actual detailed calc is below in API payload preparation, just showing sum here correctly
            let topTotal = g.amountTop * numCount * (g.typeCategory === '6'?6:1) * (g.typeCategory==='19'?19:1);
            let botTotal = g.amountBot * numCount * (g.typeCategory === '6'?6:1) * (g.typeCategory==='19'?19:1);
            groupCost = topTotal + botTotal;
        }
        total += groupCost;
        
        return `<div class="flex p-3 hover:bg-gray-50 items-center border-b border-gray-100 last:border-0 relative">
            <!-- Left Info -->
            <div class="w-24 text-center border-r border-gray-200 pr-3 mr-3 flex-shrink-0">
                <div class="text-[13px] text-gray-800 font-bold">${g.typeLabel1}</div>
                <div class="text-[13px] text-gray-600">${g.typeLabel2}</div>
                <div class="text-[13px] text-gray-800 font-bold">${g.typeLabel3}</div>
            </div>
            <!-- Right Numbers -->
            <div class="flex-1 font-bold text-[15px] font-mono text-gray-900 pr-8 leading-relaxed">
                ${g.numbers.join(' ')}
            </div>
            <!-- Delete -->
            <button onclick="removeBetGroup(${g.id})" class="absolute right-3 top-1/2 -translate-y-1/2 text-red-500 hover:text-red-700">
                <i class="far fa-trash-alt text-lg"></i>
            </button>
        </div>`;
    }).join('');
    
    document.getElementById('totalAmount').textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

async function saveBet() {
    if (betGroups.length === 0) { alert('ยังไม่มีรายการเดิมพัน'); return; }
    
    // Flatten betGroups to items array required by API
    let flatItems = [];
    betGroups.forEach(g => {
        g.numbers.forEach(num => {
            if (g.typeCategory === '2') {
                if (g.amountTop > 0) flatItems.push({ number: num, type: '2top', amount: g.amountTop });
                if (g.amountBot > 0) flatItems.push({ number: num, type: '2bot', amount: g.amountBot });
            } else if (g.typeCategory === '3') {
                if (g.amountTop > 0) flatItems.push({ number: num, type: '3top', amount: g.amountTop });
                if (g.amountBot > 0) flatItems.push({ number: num, type: '3tod', amount: g.amountBot });
            } else if (g.typeCategory === 'run') {
                if (g.amountTop > 0) flatItems.push({ number: num, type: 'run_top', amount: g.amountTop });
                if (g.amountBot > 0) flatItems.push({ number: num, type: 'run_bot', amount: g.amountBot });
            } else {
                if (g.amountTop > 0) flatItems.push({ number: num, type: g.typeCategory + 'top', amount: g.amountTop });
                if (g.amountBot > 0) flatItems.push({ number: num, type: g.typeCategory + 'bot', amount: g.amountBot });
            }
        });
    });
    
    const data = {
        action: 'save_bet',
        lottery_type_id: LOTTERY_ID,
        note: document.getElementById('betNote').value,
        items: flatItems
    };
    
    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            alert('บันทึกสำเร็จ! เลขที่: ' + result.bet_number);
            betGroups = [];
            renderBetItems();
            document.getElementById('betNote').value = '';
        } else {
            alert('เกิดข้อผิดพลาด: ' + (result.error || 'Unknown error'));
        }
    } catch (e) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + e.message);
    }
}

// Enter key to add
document.getElementById('numInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const top = document.getElementById('topAmount');
        if (!top.value) top.focus();
        else addBetItem();
    }
});
document.getElementById('topAmount')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const bot = document.getElementById('botAmount');
        if (!bot.value) bot.focus();
        else addBetItem();
    }
});
document.getElementById('botAmount')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') addBetItem();
});
</script>

<?php require_once 'includes/footer.php'; ?>
