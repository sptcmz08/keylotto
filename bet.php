<?php
$pageTitle = 'คีย์หวย - แทงหวย';
$currentPage = 'bet';
require_once 'auth.php';
requireLogin();

$lotteryId = intval($_GET['id'] ?? 0);

// If no lottery selected, show card listing page
if (!$lotteryId) {
    // Same group definitions as index.php
    $LOTTERY_GROUPS = [
        [
            'label' => 'หวยต่างประเทศ',
            'color' => '#2e7d32',
            'bg'    => '#e8f5e9',
            'names' => [
                'ลาวประตูชัย', 'ลาวสันติภาพ', 'ประชาชนลาว', 'ลาว EXTRA',
                'ลาว TV', 'ลาว HD', 'ลาวสตาร์', 'ลาวใต้',
                'ลาวสามัคคี', 'ลาวอาเซียน', 'ลาว VIP', 'ลาวสามัคคี VIP',
                'ลาวสตาร์ VIP', 'ลาวกาชาด', 'ลาวพัฒนา',
                'ฮานอยอาเซียน', 'ฮานอย HD', 'ฮานอยสตาร์', 'ฮานอย TV',
                'ฮานอยกาชาด', 'ฮานอยพิเศษ', 'ฮานอยสามัคคี', 'ฮานอยปกติ',
                'ฮานอยตรุษจีน', 'ฮานอย VIP', 'ฮานอยพัฒนา', 'ฮานอย EXTRA',
            ],
        ],
        [
            'label' => 'หวยรายวัน',
            'color' => '#1565c0',
            'bg'    => '#e3f2fd',
            'names' => [
                'นิเคอิเช้า VIP', 'นิเคอิบ่าย VIP',
                'จีนเช้า VIP', 'จีนบ่าย VIP',
                'ฮั่งเส็งเช้า VIP', 'ฮั่งเส็งบ่าย VIP',
                'ไต้หวัน VIP', 'เกาหลี VIP', 'สิงคโปร์ VIP',
                'อังกฤษ VIP', 'เยอรมัน VIP', 'รัสเซีย VIP', 'ดาวโจนส์ VIP',
            ],
        ],
        [
            'label' => 'หวยหุ้น',
            'color' => '#f57f17',
            'bg'    => '#fffde7',
            'names' => [
                'หุ้นนิเคอิเช้า', 'หุ้นนิเคอิบ่าย',
                'หุ้นจีนเช้า', 'หุ้นจีนบ่าย',
                'หุ้นฮั่งเส็งเช้า', 'หุ้นฮั่งเส็งบ่าย',
                'หุ้นไต้หวัน', 'หุ้นเกาหลี', 'หุ้นสิงคโปร์',
                'หุ้นไทย', 'หุ้นอินเดีย', 'หุ้นอียิปต์', 'หวย 12 ราศี',
                'หุ้นอังกฤษ', 'หุ้นเยอรมัน', 'หุ้นรัสเซีย',
                'ดาวโจนส์อเมริกา', 'ดาวโจนส์ STAR', 'หุ้นดาวโจนส์',
            ],
        ],
        [
            'label' => 'หวยไทย',
            'color' => '#c62828',
            'bg'    => '#ffebee',
            'names' => [
                'รัฐบาลไทย', 'ออมสิน', 'ธกส',
            ],
        ],
    ];

    // Fetch all active lottery types with open/close times
    $stmt = $pdo->query("
        SELECT lt.id, lt.name, lt.flag_emoji, lt.open_time, lt.close_time, lt.draw_date, lt.bet_closed
        FROM lottery_types lt
        WHERE lt.is_active = 1
    ");
    $allLotteries = $stmt->fetchAll();
    $lotteryMap = [];
    foreach ($allLotteries as $l) {
        $lotteryMap[$l['name']] = $l;
    }

    require_once 'includes/header.php';
    ?>

    <?php
    // === เตรียมข้อมูลหวยทั้งหมดเป็น flat array พร้อม draw_time (เวลาออกผล) ===
    $allCards = [];
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $now = new DateTime();
    
    // รวมหวยทุกกลุ่มเข้าด้วยกัน
    $allNames = [];
    foreach ($LOTTERY_GROUPS as $group) {
        foreach ($group['names'] as $n) $allNames[] = $n;
    }
    
    foreach ($allNames as $lotteryName) {
        $lt = $lotteryMap[$lotteryName] ?? null;
        if (!$lt) continue;
        
        $flagUrl = getFlagForCountry($lt['flag_emoji'], $lotteryName);
        $betClosed = intval($lt['bet_closed'] ?? 0);
        $openTime = $lt['open_time'] ?? null;
        $closeTime = $lt['close_time'] ?? null;
        $drawTime = $lt['draw_date'] ?? null; // เวลาออกผล (draw)
        
        if ($openTime && $closeTime) {
            $openDT = new DateTime($today . ' ' . $openTime);
            $closeDT = new DateTime($today . ' ' . $closeTime);
            
            if ($closeDT <= $openDT) {
                if ($now < $closeDT) {
                    $drawDate = $today;
                    $openDT->modify('-1 day');
                } else if ($now >= $openDT) {
                    $drawDate = $tomorrow;
                    $closeDT->modify('+1 day');
                } else {
                    $drawDate = $tomorrow;
                    $closeDT->modify('+1 day');
                }
            } else {
                if ($now > $closeDT) {
                    $drawDate = $tomorrow;
                    $openDT->modify('+1 day');
                    $closeDT->modify('+1 day');
                } else {
                    $drawDate = $today;
                }
            }
            
            $openISO = $openDT->format('Y-m-d\TH:i:s');
            $closeISO = $closeDT->format('Y-m-d\TH:i:s');
            $openDisplay = $openDT->format('d/m/y H:i:s');
        } else {
            $drawDate = $today;
            $openISO = '';
            $closeISO = '';
            $openDisplay = '';
        }
        
        $allCards[] = [
            'lt' => $lt,
            'name' => $lotteryName,
            'flagUrl' => $flagUrl,
            'betClosed' => $betClosed,
            'drawDate' => $drawDate,
            'drawDateDisplay' => date('d-m-Y', strtotime($drawDate)),
            'openISO' => $openISO,
            'closeISO' => $closeISO,
            'openDisplay' => $openDisplay,
        ];
    }
    ?>

    <style>
        .lottery-card {
            border: 2px solid #43a047;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            transition: all 0.4s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            min-height: 90px;
        }
        .lottery-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .lottery-card.card-open {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-color: #43a047;
        }
        .lottery-card.card-waiting {
            background: linear-gradient(135deg, #fffde7 0%, #fff9c4 100%);
            border-color: #f9a825;
        }
        .lottery-card.card-closed {
            background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
            border-color: #bdbdbd;
            opacity: 0.65;
        }
        .lottery-card.card-closed .lottery-title { color: #757575 !important; }
        .lottery-card.card-hidden { display: none !important; }
        .lottery-card .flag {
            width: 50px; height: 34px;
            object-fit: cover; border-radius: 4px;
            border: 1px solid #ddd; flex-shrink: 0; margin-top: 2px;
        }
        .lottery-card .info { flex: 1; min-width: 0; }
        .lottery-card .lottery-title {
            font-weight: 700; color: #1a5c2e; font-size: 14px; line-height: 1.3;
        }
        .lottery-card .draw-date { color: #1aa34a; font-size: 12px; font-weight: 600; }
        .lottery-card .time-row {
            display: flex; justify-content: space-between; align-items: center; margin-top: 4px;
        }
        .lottery-card .time-label { font-size: 11px; color: #d97706; }
        .lottery-card .time-value { font-size: 11px; color: #666; }
        .lottery-card .status-text { font-size: 11px; font-weight: 600; }
        .lottery-card .status-open { color: #16a34a; }
        .lottery-card .status-countdown { color: #d97706; }
        .lottery-card .status-closed { color: #9e9e9e; }
        .status-section-title {
            font-weight: 700; font-size: 14px; padding: 6px 14px;
            border-left: 4px solid; margin-bottom: 8px; margin-top: 16px; border-radius: 2px;
        }
        .status-section-title:first-child { margin-top: 0; }
    </style>

    <!-- Category Sections -->
    <?php foreach ($LOTTERY_GROUPS as $groupIdx => $group):
        $groupCards = [];
        foreach ($group['names'] as $n) {
            foreach ($allCards as $card) {
                if ($card['name'] === $n) {
                    $groupCards[] = $card;
                    break;
                }
            }
        }
        if (empty($groupCards)) continue;
    ?>
    <div class="category-section" style="margin-bottom: 16px;">
        <div class="status-section-title" style="color:<?= $group['color'] ?>; border-color:<?= $group['color'] ?>; background:<?= $group['bg'] ?>;">
            <?= htmlspecialchars($group['label']) ?>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($groupCards as $card):
                $lt = $card['lt'];
            ?>
            <div class="lottery-card card-waiting" onclick="handleCardClick(this)"
               data-id="<?= $lt['id'] ?>"
               data-open="<?= $card['openISO'] ?>"
               data-close="<?= $card['closeISO'] ?>"
               data-forced-closed="<?= $card['betClosed'] ?>"
               data-name="<?= htmlspecialchars($card['name']) ?>">
                <img src="<?= $card['flagUrl'] ?>" alt="flag" class="flag">
                <div class="info">
                    <div class="lottery-title"><?= htmlspecialchars($card['name']) ?></div>
                    <div class="draw-date"><?= $card['drawDateDisplay'] ?></div>
                    <div class="time-row">
                        <span class="time-label">เวลาเปิด</span>
                        <span class="time-value"><?= $card['openDisplay'] ?: '-' ?></span>
                    </div>
                    <div class="time-row">
                        <span class="status-text countdown-status">สถานะ</span>
                        <span class="status-text countdown-text status-countdown">กำลังโหลด...</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
    const CLOSE_GRACE_MINUTES = 10; // แสดง "ปิดรับ" สีเทานาน 10 นาที แล้วซ่อน

    function getCardStatusDetailed(card) {
        const openStr = card.dataset.open;
        const closeStr = card.dataset.close;
        const forcedClosed = card.dataset.forcedClosed === '1';
        const now = new Date();
        
        if (forcedClosed) return { status: 'closed', label: 'ปิดรับ (แอดมิน)', hide: false };
        if (!closeStr) return { status: 'waiting', label: 'รอกำหนดเวลา', hide: false };

        const openTime = openStr ? new Date(openStr) : null;
        const closeTime = new Date(closeStr);
        const msPastClose = now - closeTime;
        const minPastClose = msPastClose / 60000;

        if (openTime && now < openTime) {
            // ยังไม่ถึงเวลาเปิด
            const diff = openTime - now;
            const hoursBeforeOpen = diff / 3600000;
            // แสดงก่อนเปิด 2 ชม. เท่านั้น (ลดความรก)
            if (hoursBeforeOpen > 2) {
                return { status: 'waiting', label: 'รอเปิดรอบใหม่', hide: true };
            }
            return { status: 'waiting', label: 'เปิดในอีก ' + formatDiff(diff), hide: false };
        }
        
        if (now < closeTime) {
            // กำลังเปิดรับแทง
            const diff = closeTime - now;
            return { status: 'open', label: 'ปิดรับใน ' + formatDiff(diff), hide: false };
        }
        
        // เลยเวลาปิดแล้ว
        if (minPastClose <= CLOSE_GRACE_MINUTES) {
            // เพิ่งปิด ≤ 10 นาที → แสดงสีเทา
            const remain = Math.ceil(CLOSE_GRACE_MINUTES - minPastClose);
            return { status: 'closed', label: 'ปิดรับแล้ว', hide: false };
        }
        
        // เลย 10 นาทีแล้ว → ซ่อนไปเลย จนกว่า 2 AM จะ reload หน้าใหม่
        return { status: 'waiting', label: 'รอเปิดรอบใหม่', hide: true };
    }

    function handleCardClick(card) {
        const info = getCardStatusDetailed(card);
        const name = card.dataset.name;
        if (info.status === 'closed') {
            Swal.fire({ icon: 'error', title: 'ปิดรับแทง', text: name + ' ปิดรับแทงแล้ว', confirmButtonColor: '#9e9e9e', confirmButtonText: 'ตกลง' });
            return;
        }
        if (info.status === 'waiting') {
            Swal.fire({ icon: 'warning', title: 'ยังไม่เปิดรับแทง', text: name + ' ยังไม่ถึงเวลาเปิดรับแทง', confirmButtonColor: '#f59e0b', confirmButtonText: 'ตกลง' });
            return;
        }
        window.location.href = 'bet.php?id=' + card.dataset.id;
    }

    function sortAndDistribute() {
        const cards = document.querySelectorAll('.lottery-card');
        
        cards.forEach(card => {
            const info = getCardStatusDetailed(card);
            const statusEl = card.querySelector('.countdown-status');
            const textEl = card.querySelector('.countdown-text');
            
            if (info.hide) {
                card.style.display = 'none';
                return;
            }
            
            card.style.display = '';
            statusEl.textContent = 'สถานะ';
            textEl.textContent = info.label;
            
            if (info.status === 'open') {
                card.className = 'lottery-card card-open';
                card.dataset.sortOrder = '0';
                textEl.className = 'status-text countdown-text status-open';
            } else if (info.status === 'closed') {
                card.className = 'lottery-card card-closed';
                card.dataset.sortOrder = '2';
                textEl.className = 'status-text countdown-text status-closed';
            } else {
                card.className = 'lottery-card card-waiting';
                card.dataset.sortOrder = '1';
                textEl.className = 'status-text countdown-text status-countdown';
            }
        });
        
        // จัดเรียงในแต่ละหมวด: เขียว(0) → เหลือง(1) → เทา(2)
        document.querySelectorAll('.category-section .grid').forEach(grid => {
            const items = Array.from(grid.children);
            items.sort((a, b) => (a.dataset.sortOrder || '1') - (b.dataset.sortOrder || '1'));
            items.forEach(item => grid.appendChild(item));
        });
    }

    function formatDiff(ms) {
        if (ms < 0) ms = 0;
        const h = Math.floor(ms / 3600000);
        const m = Math.floor((ms % 3600000) / 60000);
        const s = Math.floor((ms % 60000) / 1000);
        return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    }

    // เช็คตี 4 → reload หน้าใหม่ เพื่อรีเซ็ตงวด (หลังหุ้นดาวโจนส์ปิด 03:30)
    let hasReloaded = false;
    function checkReset() {
        const now = new Date();
        const hour = now.getHours();
        if (hour === 4 && !hasReloaded) {
            hasReloaded = true;
            location.reload();
        }
        if (hour !== 4) hasReloaded = false;
    }

    // อัพเดททุก 1 วินาที
    setInterval(() => {
        sortAndDistribute();
        checkReset();
    }, 1000);
    sortAndDistribute();
    </script>

    <?php require_once 'includes/footer.php'; ?>
    <?php exit; ?>
<?php } // end !$lotteryId

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

// Fetch blocked numbers for this lottery
$stmtBlocked = $pdo->prepare("SELECT * FROM blocked_numbers WHERE lottery_type_id = ? ORDER BY bet_type, number");
$stmtBlocked->execute([$lotteryId]);
$blockedNumbers = $stmtBlocked->fetchAll();

// Load over-limit settings
$overLimitSettings = ['threshold' => 50, '2top' => 95, '2bot' => 95, '3top' => 800, '3tod' => 125, 'run_top' => 3, 'run_bot' => 4];
try {
    $stmtOL = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'over_limit%'");
    foreach ($stmtOL->fetchAll() as $s) {
        $k = str_replace('over_limit_', '', $s['setting_key']);
        $k = str_replace('_rate', '', $k);
        $overLimitSettings[$k] = floatval($s['setting_value']);
    }
} catch (Exception $e) {}
// Group by type and merge same numbers into one row
$blocked3dMap = [];
$blocked2dMap = [];
$blockedRunMap = [];
foreach ($blockedNumbers as $bn) {
    $num = $bn['number'];
    $bt = $bn['bet_type'];
    $blocked = $bn['is_blocked'];
    if (in_array($bt, ['3top','3tod'])) {
        if (!isset($blocked3dMap[$num])) $blocked3dMap[$num] = ['number' => $num, '3top' => null, '3tod' => null];
        $blocked3dMap[$num][$bt] = $blocked;
    } elseif (in_array($bt, ['2top','2bot'])) {
        if (!isset($blocked2dMap[$num])) $blocked2dMap[$num] = ['number' => $num, '2top' => null, '2bot' => null];
        $blocked2dMap[$num][$bt] = $blocked;
    } elseif (in_array($bt, ['run_top','run_bot'])) {
        if (!isset($blockedRunMap[$num])) $blockedRunMap[$num] = ['number' => $num, 'run_top' => null, 'run_bot' => null];
        $blockedRunMap[$num][$bt] = $blocked;
    }
}
$blocked3d = array_values($blocked3dMap);
$blocked2d = array_values($blocked2dMap);
$blockedRun = array_values($blockedRunMap);

$flagUrl = getFlagForCountry($lottery['flag_emoji'], $lottery['name']);
$drawDate = $lottery['draw_date'] ? formatDateDisplay($lottery['draw_date']) : date('d-m-Y');
$closeDateTime = $lottery['draw_date'] . ' ' . $lottery['close_time'];

// Fetch recent 15 bills
$stmtBills = $pdo->query("
    SELECT b.id, b.bet_number, b.created_at, b.total_items, b.net_amount, b.status, b.note,
           lt.name as lottery_name, lc.name as category_name
    FROM bets b
    JOIN lottery_types lt ON b.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    ORDER BY b.id DESC LIMIT 15
");
$recentBills = $stmtBills->fetchAll();

require_once 'includes/header.php';
?>

<style>
    /* Override container width for bet page */
    main { max-width: 1400px !important; }
    @media (min-width: 1024px) {
        .bet-layout { display: flex !important; flex-direction: row !important; gap: 16px; }
        .bet-left { flex: 1; min-width: 0; }
        .bet-right { width: 380px; flex-shrink: 0; }
    }
</style>

<div class="bet-layout flex flex-col">
    <!-- Left: Betting Form -->
    <div class="bet-left space-y-4">
        
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
        <div class="bg-[#fafafa] border border-[#c62828] p-3">
            <div class="flex justify-between items-center">
                <div class="font-bold text-[#c62828] text-[15px]">
                    [<?= htmlspecialchars($lottery['category_name']) ?>] <?= htmlspecialchars($lottery['name']) ?>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-[#c62828] text-[15px]"><?= $drawDate ?></span>
                    <img src="<?= $flagUrl ?>" alt="flag" class="w-10 h-7 object-cover border border-gray-300 rounded">
                </div>
            </div>
        </div>

        <!-- Bet Type Tabs -->
        <div class="bg-white border border-[#c62828]">
            <div class="flex bg-[#4caf50] border-b border-[#388e3c]">
                <div class="px-6 py-3 text-sm font-bold bg-white text-gray-800 border-t-[3px] border-[#e53935]">แทงเร็ว</div>
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

                <!-- Selected Numbers Display -->
                <div id="selectedNumbers" class="flex flex-wrap gap-2 mb-4 min-h-[36px]"></div>

                <!-- Win Number Panel (วินเลข) -->
                <div id="winPanel" class="mb-4 p-3 bg-[#fffde7] border border-[#ffca28] rounded-lg" style="display:none;">
                    <div class="flex gap-4 mb-3 text-sm">
                        <label class="flex items-center gap-1"><input type="radio" name="winDigit" value="2" checked> <span>จับวิน 2 ตัว</span></label>
                        <label class="flex items-center gap-1"><input type="radio" name="winDigit" value="3"> <span>จับวิน 3 ตัว</span></label>
                    </div>
                    <div class="flex gap-4 mb-3 text-sm">
                        <label class="flex items-center gap-1"><input type="radio" name="winDouble" value="no" checked> <span>จับวินไม่รวมเลขเบิ้ล</span></label>
                        <label class="flex items-center gap-1"><input type="radio" name="winDouble" value="yes"> <span>จับวินรวมเลขเบิ้ล</span></label>
                    </div>
                    <p class="text-[12px] text-gray-500 mb-2">กรุณาเลือกตัวเลขที่ต้องการจับวิน 2 - 7 ตัวเลข</p>
                    <div class="grid grid-cols-5 gap-2 max-w-[280px] mb-3" id="winGrid">
                        <button type="button" onclick="toggleWinDigit(1)" id="wdig-1" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">1</button>
                        <button type="button" onclick="toggleWinDigit(2)" id="wdig-2" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">2</button>
                        <button type="button" onclick="toggleWinDigit(3)" id="wdig-3" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">3</button>
                        <button type="button" onclick="toggleWinDigit(4)" id="wdig-4" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">4</button>
                        <button type="button" onclick="toggleWinDigit(5)" id="wdig-5" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">5</button>
                        <button type="button" onclick="toggleWinDigit(6)" id="wdig-6" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">6</button>
                        <button type="button" onclick="toggleWinDigit(7)" id="wdig-7" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">7</button>
                        <button type="button" onclick="toggleWinDigit(8)" id="wdig-8" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">8</button>
                        <button type="button" onclick="toggleWinDigit(9)" id="wdig-9" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">9</button>
                        <button type="button" onclick="toggleWinDigit(0)" id="wdig-0" class="win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition">0</button>
                    </div>
                    <div class="flex gap-2 mb-3">
                        <button onclick="calculateWin()" class="px-4 py-2 bg-white border-2 border-gray-400 rounded text-sm font-bold hover:bg-gray-100 transition">คำนวน</button>
                        <button onclick="reverseWinNumbers()" class="px-4 py-2 bg-white border-2 border-gray-400 rounded text-sm font-bold hover:bg-gray-100 transition">กลับเลขวิน</button>
                    </div>
                    <div id="winResults" class="flex flex-wrap gap-2"></div>
                </div>

                <!-- Number Entry Form -->
                <div class="mb-4" id="normalInputPanel">
                    <button onclick="addDoubleBet()" class="mb-2 text-sm bg-[#ffca28] text-yellow-900 px-3 py-1 rounded font-medium hover:bg-yellow-400 transition inline-block">
                        <i class="fas fa-plus mr-1"></i> เลขเบิ้ล
                    </button>
                    
                    <div class="grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-12 sm:col-span-3">
                            <label class="text-[11px] text-gray-500 block mb-0.5">ใส่เลข <span class="text-blue-500">(วางหลายตัวได้)</span></label>
                            <input type="text" id="numInput" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-blue-500 outline-none h-[40px]" placeholder="12 21 31 54...">
                        </div>
                        <div class="col-span-12 sm:col-span-1 flex items-end">
                            <button onclick="reverseNumber()" id="btn-reverse" class="w-full bg-[#ffca28] text-yellow-900 py-2 rounded text-[13px] font-bold hover:bg-yellow-400 transition h-[40px]">กลับเลข</button>
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <label class="text-[11px] text-gray-500 block mb-0.5 text-center" id="topLabel">บน</label>
                            <input type="number" id="topAmount" class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-center focus:border-blue-500 outline-none h-[40px]">
                        </div>
                        <div class="col-span-6 sm:col-span-3" id="botAmountWrap">
                            <label class="text-[11px] text-gray-500 block mb-0.5 text-center" id="botLabel">ล่าง</label>
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
                    </div>
                    
                    <div class="text-center relative">
                        <div class="text-[15px] text-[#37474f] font-medium mb-1 flex items-center justify-center gap-2">
                            <img src="<?= $flagUrl ?>" alt="flag" class="w-8 h-5 object-cover border border-gray-300">
                            [<?= htmlspecialchars($lottery['category_name']) ?>] <?= htmlspecialchars($lottery['name']) ?> - <?= $drawDate ?>
                        </div>
                        <div class="text-[#1565c0] text-xl font-bold mb-1 underline">
                            รวม <span id="totalAmount">0.00</span> บาท
                        </div>
                        <div class="text-[#c62828] text-sm font-bold mb-4">
                            เหลือเวลา <span id="footerCountdown">00:00</span>
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
    </div>
    </div><!-- /bet-left -->

    <!-- Right Sidebar -->
    <div class="bet-right space-y-4">
        <div style="position:sticky; top:16px;" class="space-y-4">
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

                            <th class="px-2 py-1.5 text-center font-normal border border-[#2e7d32]">ขั้นต่ำ (บาท)</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <?php if (empty($rates)): ?>
                        <tr><td colspan="3" class="px-2 py-3 text-center text-gray-400">ยังไม่มีอัตราจ่าย</td></tr>
                        <?php else: foreach ($rates as $r): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-1.5 border border-gray-300"><?= htmlspecialchars($r['rate_label'] ?? getBetTypeLabel($r['bet_type'])) ?></td>
                            <td class="px-2 py-1.5 text-center border border-gray-300"><?= formatMoney($r['pay_rate']) ?></td>

                            <td class="px-2 py-1.5 text-center border border-gray-300"><?= $r['min_bet'] ?> - <?= number_format($r['max_bet']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Numbers Panel - เลขอั้น -->
        <div class="bg-white border border-[#2e7d32]">
            <div class="bg-[#2e7d32] text-white font-bold text-sm px-3 py-2 flex items-center">
                <i class="fas fa-window-maximize mr-2 opacity-80"></i> เลขอั้น
            </div>
            <div class="bg-[#2e7d32] px-1 pt-1 flex gap-1">
                <button onclick="switchNumTab('3d')" id="numTab-3d" class="num-tab flex-1 py-1 text-center text-[13px] font-medium bg-[#2e7d32] text-white border border-[#2e7d32] hover:bg-green-700">3 ตัว</button>
                <button onclick="switchNumTab('2d')" id="numTab-2d" class="num-tab flex-1 py-1 text-center text-[13px] font-bold bg-white text-gray-800 rounded-t border-t border-l border-r border-[#2e7d32]">2 ตัว</button>
                <button onclick="switchNumTab('run')" id="numTab-run" class="num-tab flex-1 py-1 text-center text-[13px] font-medium bg-[#2e7d32] text-white border border-[#2e7d32] hover:bg-green-700">เลขวิ่ง</button>
            </div>

            <!-- 3 ตัว -->
            <div id="numPanel-3d" class="bg-white p-2" style="display:none">
                <table class="w-full text-[12px] border-collapse text-gray-700 text-center">
                    <thead>
                        <tr><th colspan="3" class="px-2 py-1.5 border border-gray-300 bg-gray-50">เรทจ่าย (บาท)</th></tr>
                        <tr class="bg-gray-50">
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">เลข</th>
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">3 ตัวบน</th>
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">3 ตัวโต๊ด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blocked3d)): ?>
                        <tr><td colspan="3" class="px-2 py-4 border border-gray-300 text-gray-400">ไม่มีเลขอั้น</td></tr>
                        <?php else: foreach ($blocked3d as $bn): ?>
                        <tr class="hover:bg-red-50">
                            <td class="px-2 py-1.5 border border-gray-300 font-bold font-mono"><?= htmlspecialchars($bn['number']) ?></td>
                            <td class="px-2 py-1.5 border border-gray-300 <?= $bn['3top'] !== null ? ($bn['3top'] ? 'text-red-600 font-bold' : 'text-orange-600') : '' ?>"><?= $bn['3top'] !== null ? ($bn['3top'] ? 'ปิดรับ' : 'จ่ายครึ่ง') : '-' ?></td>
                            <td class="px-2 py-1.5 border border-gray-300 <?= $bn['3tod'] !== null ? ($bn['3tod'] ? 'text-red-600 font-bold' : 'text-orange-600') : '' ?>"><?= $bn['3tod'] !== null ? ($bn['3tod'] ? 'ปิดรับ' : 'จ่ายครึ่ง') : '-' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 2 ตัว (default visible) -->
            <div id="numPanel-2d" class="bg-white p-2">
                <table class="w-full text-[12px] border-collapse text-gray-700 text-center">
                    <thead>
                        <tr><th colspan="3" class="px-2 py-1.5 border border-gray-300 bg-gray-50">เรทจ่าย (บาท)</th></tr>
                        <tr class="bg-gray-50">
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">เลข</th>
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">2 ตัวบน</th>
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">2 ตัวล่าง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blocked2d)): ?>
                        <tr><td colspan="3" class="px-2 py-4 border border-gray-300 text-gray-400">ไม่มีเลขอั้น</td></tr>
                        <?php else: foreach ($blocked2d as $bn): ?>
                        <tr class="hover:bg-red-50">
                            <td class="px-2 py-1.5 border border-gray-300 font-bold font-mono"><?= htmlspecialchars($bn['number']) ?></td>
                            <td class="px-2 py-1.5 border border-gray-300 <?= $bn['2top'] !== null ? ($bn['2top'] ? 'text-red-600 font-bold' : 'text-orange-600') : '' ?>"><?= $bn['2top'] !== null ? ($bn['2top'] ? 'ปิดรับ' : 'จ่ายครึ่ง') : '-' ?></td>
                            <td class="px-2 py-1.5 border border-gray-300 <?= $bn['2bot'] !== null ? ($bn['2bot'] ? 'text-red-600 font-bold' : 'text-orange-600') : '' ?>"><?= $bn['2bot'] !== null ? ($bn['2bot'] ? 'ปิดรับ' : 'จ่ายครึ่ง') : '-' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- เลขวิ่ง -->
            <div id="numPanel-run" class="bg-white p-2" style="display:none">
                <table class="w-full text-[12px] border-collapse text-gray-700 text-center">
                    <thead>
                        <tr><th colspan="3" class="px-2 py-1.5 border border-gray-300 bg-gray-50">เรทจ่าย (บาท)</th></tr>
                        <tr class="bg-gray-50">
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">เลข</th>
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">วิ่งบน</th>
                            <th class="px-2 py-1.5 border border-gray-300 font-normal">วิ่งล่าง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blockedRun)): ?>
                        <tr><td colspan="3" class="px-2 py-4 border border-gray-300 text-gray-400">ไม่มีเลขอั้น</td></tr>
                        <?php else: foreach ($blockedRun as $bn): ?>
                        <tr class="hover:bg-red-50">
                            <td class="px-2 py-1.5 border border-gray-300 font-bold font-mono"><?= htmlspecialchars($bn['number']) ?></td>
                            <td class="px-2 py-1.5 border border-gray-300 <?= $bn['run_top'] !== null ? ($bn['run_top'] ? 'text-red-600 font-bold' : 'text-orange-600') : '' ?>"><?= $bn['run_top'] !== null ? ($bn['run_top'] ? 'ปิดรับ' : 'จ่ายครึ่ง') : '-' ?></td>
                            <td class="px-2 py-1.5 border border-gray-300 <?= $bn['run_bot'] !== null ? ($bn['run_bot'] ? 'text-red-600 font-bold' : 'text-orange-600') : '' ?>"><?= $bn['run_bot'] !== null ? ($bn['run_bot'] ? 'ปิดรับ' : 'จ่ายครึ่ง') : '-' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
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
</div><!-- /bet-layout -->

<!-- Recent Bills Section -->
<div class="mt-4 bg-white border border-[#2e7d32]">
    <div class="flex justify-between items-center px-3 py-2 bg-[#e8f5e9] border-b border-[#2e7d32]">
        <div class="text-[#2e7d32] font-bold text-sm"><i class="fas fa-list mr-1"></i> โพยล่าสุด (แสดง 15 รายการล่าสุด)</div>
        <a href="bills.php" class="text-xs text-[#1976d2] hover:underline">(เพิ่มเติม)</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-[12px]">
            <thead>
                <tr class="bg-[#2e7d32] text-white">
                    <th class="px-2 py-2 text-center">#</th>
                    <th class="px-2 py-2 text-center">เลขที่</th>
                    <th class="px-2 py-2 text-center">เวลาแทง</th>
                    <th class="px-2 py-2 text-left">หวย</th>
                    <th class="px-2 py-2 text-center">รายการ</th>
                    <th class="px-2 py-2 text-center">บาท</th>
                    <th class="px-2 py-2 text-center">หมายเหตุ</th>
                    <th class="px-2 py-2 text-center">ลบโพย</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentBills)): ?>
                <tr><td colspan="8" class="text-center text-gray-400 py-6">ยังไม่มีรายการ</td></tr>
                <?php else: ?>
                <?php foreach ($recentBills as $i => $bill):
                    $statusMap = ['pending'=>'รอผล','won'=>'ถูกรางวัล','lost'=>'ไม่ถูก','cancelled'=>'ยกเลิก'];
                    $isCancelled = $bill['status'] === 'cancelled';
                ?>
                <tr class="border-b border-gray-100 hover:bg-gray-50 <?= $isCancelled ? 'bg-red-50 line-through text-gray-400' : '' ?>">
                    <td class="px-2 py-2 text-center"><?= $isCancelled ? '<span class="text-red-500">✕</span> ' : '' ?><?= $i+1 ?></td>
                    <td class="px-2 py-2 text-center font-mono"><?= htmlspecialchars($bill['bet_number']) ?></td>
                    <td class="px-2 py-2 text-center whitespace-nowrap"><?= date('d/m/Y', strtotime($bill['created_at'])) ?><br><?= date('H:i:s', strtotime($bill['created_at'])) ?></td>
                    <td class="px-2 py-2">[<?= htmlspecialchars($bill['category_name']) ?>]<br><?= htmlspecialchars($bill['lottery_name']) ?></td>
                    <td class="px-2 py-2 text-center font-bold"><?= $bill['total_items'] ?></td>
                    <td class="px-2 py-2 text-center"><?= number_format($bill['net_amount'], 2) ?></td>
                    <td class="px-2 py-2 text-center"><?= htmlspecialchars($bill['note'] ?? '') ?></td>
                    <td class="px-2 py-2 text-center">
                        <?php if (!$isCancelled): ?>
                        <button onclick="deleteBill(<?= $bill['id'] ?>)" class="text-red-500 hover:text-red-700" title="ลบโพย">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const LOTTERY_ID = <?= $lotteryId ?>;
const CLOSE_TIME = '<?= $closeDateTime ?>';
const STORAGE_KEY = 'bet_' + LOTTERY_ID;
const OVER_LIMIT = <?= json_encode($overLimitSettings) ?>;

// เลขอั้น/ปิดรับ map: key = number_bettype, value = {is_blocked, number}
const BLOCKED_MAP = {};
<?php foreach ($blockedNumbers as $bn): ?>
BLOCKED_MAP['<?= $bn['number'] ?>_<?= $bn['bet_type'] ?>'] = { is_blocked: <?= $bn['is_blocked'] ?>, number: '<?= $bn['number'] ?>', bet_type: '<?= $bn['bet_type'] ?>' };
<?php endforeach; ?>

// Pay rates map: key = bet_type, value = pay_rate
const PAY_RATES = {};
<?php foreach ($rates as $r): ?>
PAY_RATES['<?= $r['bet_type'] ?>'] = <?= $r['pay_rate'] ?>;
<?php endforeach; ?>

function showBlockedToast(msg, type) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type === 'blocked' ? 'error' : 'warning',
        title: msg,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
}

let currentBetType = '2';
let reverseMode = false; // kept for backward compat but unused
let betGroups = [];
let selectedNums = [];
let classicBetGroups = [];
let pasteBetGroups = [];

// โหลดข้อมูลจาก sessionStorage
(function loadSavedState() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY));
        if (saved) {
            selectedNums = saved.selectedNums || [];
            betGroups = saved.betGroups || [];
            currentBetType = saved.currentBetType || '2';
        }
    } catch(e) {}
})();

function saveState() {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
            selectedNums, betGroups, currentBetType
        }));
    } catch(e) {}
}

function clearSavedState() {
    sessionStorage.removeItem(STORAGE_KEY);
}

// ==========================================
// Countdown
// ==========================================
function updateMainCountdown() {
    const close = new Date(CLOSE_TIME);
    const now = new Date();
    const diff = close - now;
    const el1 = document.getElementById('mainCountdown');
    const el2 = document.getElementById('footerCountdown');
    if (diff > 0) {
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        const timeStr = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        if (el1) el1.textContent = timeStr;
        if (el2) el2.textContent = timeStr;
    } else {
        if (el1) el1.textContent = 'ปิดรับแล้ว';
        if (el2) el2.textContent = 'ปิดรับแล้ว';
    }
}
setInterval(updateMainCountdown, 1000);
updateMainCountdown();

// ==========================================
// Tab Switching
// ==========================================
function switchBetTab(tab) {
    document.querySelectorAll('.bet-tab').forEach(b => { b.className = 'bet-tab px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-800 border-t-[3px] border-transparent'; });
    document.getElementById('tab-' + tab).className = 'bet-tab px-6 py-3 text-sm font-bold bg-white border-t-[3px] border-[#e53935] text-gray-800';
    ['quick','classic','paste'].forEach(p => document.getElementById('panel-' + p).classList.add('hidden'));
    document.getElementById('panel-' + tab).classList.remove('hidden');
}

function switchNumTab(tab) {
    ['3d','2d','run'].forEach(t => {
        const panel = document.getElementById('numPanel-' + t);
        const btn = document.getElementById('numTab-' + t);
        if (t === tab) {
            if (panel) panel.style.display = '';
            if (btn) btn.className = 'num-tab flex-1 py-1 text-center text-[13px] font-bold bg-white text-gray-800 rounded-t border-t border-l border-r border-[#2e7d32]';
        } else {
            if (panel) panel.style.display = 'none';
            if (btn) btn.className = 'num-tab flex-1 py-1 text-center text-[13px] font-medium bg-[#2e7d32] text-white border border-[#2e7d32] hover:bg-green-700';
        }
    });
}

// ==========================================
// Quick Bet - Bet Type
// ==========================================
function setBetType(type) {
    currentBetType = type;
    selectedNums = [];
    renderSelectedNumbers();
    
    document.querySelectorAll('.bet-type-btn').forEach(b => { b.className = 'bet-type-btn px-4 py-1.5 rounded text-sm font-medium bg-[#fff8e1] text-[#f57f17] border border-[#ffca28] hover:bg-[#ffca28] hover:text-yellow-900'; });
    const btn = document.getElementById('btn-type-' + type);
    if (btn) btn.className = 'bet-type-btn px-4 py-1.5 rounded text-sm font-bold bg-[#ffca28] text-yellow-900 border border-[#ffca28]';
    
    // Show/hide win panel
    const winPanel = document.getElementById('winPanel');
    const normalInput = document.getElementById('normalInputPanel');
    if (type === 'win') {
        if (winPanel) winPanel.style.display = '';
        if (normalInput) normalInput.style.display = 'none';
        // Reset win grid
        winSelectedDigits = [];
        document.querySelectorAll('.win-digit').forEach(b => { b.className = 'win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition'; });
        document.getElementById('winResults').innerHTML = '';
        return;
    } else {
        if (winPanel) winPanel.style.display = 'none';
        if (normalInput) normalInput.style.display = '';
    }
    
    // Update label บน/ล่าง → บน/โต๊ด for 3ตัว & 6กลับ
    const topLabel = document.getElementById('topLabel');
    const botLabel = document.getElementById('botLabel');
    const botWrap = document.getElementById('botAmountWrap');
    
    if (type === '3') {
        if (topLabel) topLabel.textContent = 'บน';
        if (botLabel) botLabel.textContent = 'โต๊ด';
        if (botWrap) botWrap.style.display = '';
    } else if (type === '6') {
        // 6 กลับ: only บน, hide ล่าง/โต๊ด
        if (topLabel) topLabel.textContent = 'บน';
        if (botWrap) botWrap.style.display = 'none';
        document.getElementById('botAmount').value = '';
    } else if (type === 'run') {
        if (topLabel) topLabel.textContent = 'บน';
        if (botLabel) botLabel.textContent = 'ล่าง';
        if (botWrap) botWrap.style.display = '';
    } else {
        if (topLabel) topLabel.textContent = 'บน';
        if (botLabel) botLabel.textContent = 'ล่าง';
        if (botWrap) botWrap.style.display = '';
    }
    
    const numInput = document.getElementById('numInput');
    if (type === '3' || type === '6') numInput.maxLength = 3;
    else if (type === 'run') numInput.maxLength = 1;
    else if (type === '19') numInput.maxLength = 1;
    else numInput.maxLength = 2;
    
    numInput.value = '';
    numInput.focus();
}

// ==========================================
// Auto-add Numbers on Input (auto-add + paste หลายตัว)
// ==========================================
function addSingleNumber(val) {
    const type = currentBetType;
    if (type === '2' && /^\d{2}$/.test(val)) {
        selectedNums.push(val);
        return true;
    } else if (type === '3' && /^\d{3}$/.test(val)) {
        selectedNums.push(val);
        return true;
    } else if (type === '6' && /^\d{3}$/.test(val)) {
        const perms = getPermutations(val);
        perms.forEach(p => { if (!selectedNums.includes(p)) selectedNums.push(p); });
        return true;
    } else if (type === '19' && /^\d$/.test(val)) {
        const nums = get19Door(val);
        // ไม่ตัดซ้ำ: เพิ่มทุกตัวเสมอ (แยกรูด)
        nums.forEach(n => selectedNums.push(n));
        return true;
    } else if (type === 'run' && /^\d$/.test(val)) {
        if (!selectedNums.includes(val)) selectedNums.push(val);
        return true;
    } else if (type === 'win' && /^\d$/.test(val)) {
        if (!selectedNums.includes(val)) selectedNums.push(val);
        return true;
    }
    return false;
}

// ==========================================
// กลับเลข: กดแล้วสลับเลขที่เลือกไว้ทั้งหมด
// ==========================================
function reverseNumber() {
    if (selectedNums.length === 0) {
        Swal.fire({ icon: 'info', title: 'กลับเลข', text: 'ยังไม่มีเลขที่เลือก กรุณาเพิ่มเลขก่อน', confirmButtonColor: '#2e7d32' });
        return;
    }
    const reversed = [];
    const addedRevs = [];
    selectedNums.forEach(num => {
        reversed.push(num);
        const rev = num.split('').reverse().join('');
        if (rev !== num && !reversed.includes(rev)) {
            reversed.push(rev);
            addedRevs.push(rev);
        }
    });
    selectedNums = reversed;
    renderSelectedNumbers();
    saveState();
    if (addedRevs.length > 0) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'กลับเลขเรียบร้อย +' + addedRevs.length + ' ตัว', showConfirmButton: false, timer: 1500 });
    } else {
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'ไม่มีเลขที่ต้องกลับ', showConfirmButton: false, timer: 1500 });
    }
}

function handleNumInput(e) {
    let val = e.target.value;
    // ตัดอักขระพิเศษก่อน: / , . - newline tab => space
    val = val.replace(/[^\d\s]/g, ' ').trim();
    if (!val) return;
    // ตรวจว่ามีช่องว่าง = วางหลายตัว
    if (/\s/.test(val)) {
        handleMultiPaste(val);
        e.target.value = '';
        return;
    }
    if (addSingleNumber(val)) {
        e.target.value = '';
        renderSelectedNumbers();
        saveState();
    }
}

// ==========================================
// Paste หลายตัว: "12 21 31 54 87" → เพิ่มทั้งหมด
// ==========================================
function handleMultiPaste(text) {
    // แยกด้วย space, comma, newline, tab, slash, dash
    const parts = text.split(/[\s,\/\-\n\r\t]+/).filter(n => n.length > 0 && /^\d+$/.test(n));
    let added = 0;
    parts.forEach(num => {
        if (addSingleNumber(num)) added++;
    });
    renderSelectedNumbers();
    if (added > 0) {
        saveState();
        // Flash effect
        const container = document.getElementById('selectedNumbers');
        container.style.transition = 'background 0.3s';
        container.style.background = '#e8f5e9';
        setTimeout(() => { container.style.background = ''; }, 500);
    }
}

// ==========================================
// 6กลับ: Get all unique permutations of 3 digits
// ==========================================
function getPermutations(str) {
    const digits = str.split('');
    const result = new Set();
    for (let i = 0; i < 3; i++) {
        for (let j = 0; j < 3; j++) {
            for (let k = 0; k < 3; k++) {
                if (i !== j && j !== k && i !== k) {
                    result.add(digits[i] + digits[j] + digits[k]);
                }
            }
        }
    }
    return [...result];
}

// ==========================================
// 19ประตู: Get all 19 two-digit numbers containing digit
// ==========================================
function get19Door(digit) {
    const nums = new Set();
    for (let i = 0; i <= 9; i++) {
        nums.add(digit + String(i)); // digit as first
        nums.add(String(i) + digit); // digit as second
    }
    // Remove the double (e.g., 22 appears twice), Set handles that
    return [...nums].sort();
}

// ==========================================
// เลขเบิ้ล: All doubles for 2ตัว
// ==========================================
function addDoubleBet() {
    if (currentBetType === '2' || currentBetType === '3') {
        // For 2ตัว: add all doubles 00,11,22,...99
        if (currentBetType === '2') {
            for (let i = 0; i <= 9; i++) {
                const d = String(i) + String(i);
                if (!selectedNums.includes(d)) selectedNums.push(d);
            }
        } else {
            // For 3ตัว: add all triples 000,111,...999
            for (let i = 0; i <= 9; i++) {
                const d = String(i) + String(i) + String(i);
                if (!selectedNums.includes(d)) selectedNums.push(d);
            }
        }
        renderSelectedNumbers();
        saveState();
    } else {
        Swal.fire({ icon: 'info', title: 'เลขเบิ้ล', text: 'กรุณาเลือกโหมด 2 ตัว หรือ 3 ตัว', confirmButtonColor: '#2e7d32' });
    }
}


// ==========================================
// Render Selected Numbers (red badges)
// ==========================================
function renderSelectedNumbers() {
    const container = document.getElementById('selectedNumbers');
    if (!container) return;
    
    if (selectedNums.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    container.innerHTML = selectedNums.map((n, i) => 
        `<span class="inline-flex items-center px-3 py-1.5 text-sm font-bold bg-[#e53935] text-black cursor-pointer hover:bg-red-400 transition" style="border-radius:4px;" onclick="removeSelectedNum(${i})" title="คลิกเพื่อลบ">${n}</span>`
    ).join('');
}

function removeSelectedNum(index) {
    selectedNums.splice(index, 1);
    renderSelectedNumbers();
    saveState();
}

// (reverseNumber removed - replaced by toggleReverse)

// ==========================================
// Add Bet Item (Quick Mode) - uses selectedNums if available
// ==========================================
function addBetItem() {
    const top = parseFloat(document.getElementById('topAmount').value) || 0;
    const bot = parseFloat(document.getElementById('botAmount').value) || 0;
    
    // Use selectedNums if any, fallback to input field
    let numbers = [];
    if (selectedNums.length > 0) {
        numbers = [...selectedNums];
    } else {
        const numInputStr = document.getElementById('numInput').value.trim();
        if (!numInputStr) { Swal.fire({ icon: 'warning', title: 'กรุณาใส่เลข', confirmButtonColor: '#f59e0b' }); return; }
        numbers = numInputStr.split(/[\s,]+/).filter(n => n.length > 0);
    }
    
    if (numbers.length === 0) { Swal.fire({ icon: 'warning', title: 'กรุณาใส่เลข', confirmButtonColor: '#f59e0b' }); return; }
    if (top === 0 && bot === 0) { Swal.fire({ icon: 'warning', title: 'กรุณาใส่จำนวนเงิน', confirmButtonColor: '#f59e0b' }); return; }

    // =============================================
    // ตรวจสอบเลขอั้น / ปิดรับ + แจ้งเตือนละเอียด
    // =============================================
    const blockedNums = [];
    const blockedDetails = [];
    const halfPayDetails = [];
    const cleanNumbers = [];

    numbers.forEach(num => {
        let betTypes = [];
        if (currentBetType === '2' || currentBetType === '6' || currentBetType === '19') {
            if (top > 0) betTypes.push({key: '2top', label: '2 ตัวบน'});
            if (bot > 0) betTypes.push({key: '2bot', label: '2 ตัวล่าง'});
        } else if (currentBetType === '3') {
            if (top > 0) betTypes.push({key: '3top', label: '3 ตัวบน'});
            if (bot > 0) betTypes.push({key: '3tod', label: '3 ตัวโต๊ด'});
        } else if (currentBetType === 'run' || currentBetType === 'win') {
            if (top > 0) betTypes.push({key: 'run_top', label: 'วิ่งบน'});
            if (bot > 0) betTypes.push({key: 'run_bot', label: 'วิ่งล่าง'});
        }

        let isBlocked = false;
        betTypes.forEach(bt => {
            const key = num + '_' + bt.key;
            if (BLOCKED_MAP[key]) {
                if (BLOCKED_MAP[key].is_blocked) {
                    isBlocked = true;
                    blockedDetails.push(`<b>${num}</b> → ${bt.label} <span style="color:#e53935">ห้ามแทง</span>`);
                } else {
                    const fullRate = PAY_RATES[bt.key] || 0;
                    halfPayDetails.push(`<b>${num}</b> → ${bt.label} <span style="color:#e65100">จ่าย ${fullRate/2} แทน ${fullRate}</span>`);
                }
            }
        });

        if (isBlocked) blockedNums.push(num);
        else cleanNumbers.push(num);
    });

    // แสดง popup แจ้งเตือนละเอียด
    if (blockedDetails.length > 0 || halfPayDetails.length > 0) {
        let html = '';
        if (blockedDetails.length > 0) {
            html += '<div style="text-align:left;margin-bottom:8px;"><b style="color:#e53935">🚫 เลขห้ามแทง (ลบออกแล้ว):</b><br>' + blockedDetails.join('<br>') + '</div>';
        }
        if (halfPayDetails.length > 0) {
            html += '<div style="text-align:left;"><b style="color:#e65100">⚠️ เลขอั้น (จ่ายครึ่ง):</b><br>' + halfPayDetails.join('<br>') + '</div>';
        }
        Swal.fire({
            icon: blockedDetails.length > 0 ? 'error' : 'warning',
            title: blockedDetails.length > 0 ? 'มีเลขห้ามแทง!' : 'มีเลขอั้น',
            html: html,
            confirmButtonColor: '#2e7d32',
            confirmButtonText: 'รับทราบ'
        });
    }

    if (cleanNumbers.length === 0 && blockedNums.length > 0) {
        return; // ทุกเลขถูกปิดรับ
    }

    // ใช้ cleanNumbers แทน numbers (ลบเลขปิดรับออก)
    numbers = cleanNumbers;

    let typeLabel1 = currentBetType === 'run' ? 'เลขวิ่ง' : (currentBetType === 'win' ? 'วินเลข' : currentBetType + ' ตัว');
    if (currentBetType === '6') typeLabel1 = '6 กลับ';
    if (currentBetType === '19') typeLabel1 = '19 ประตู';
    
    let typeLabel2 = (top>0 && bot>0) ? 'บน x ล่าง' : (top>0 ? 'บน' : 'ล่าง');
    let typeLabel3 = (top>0 && bot>0) ? `${top} x ${bot}` : (top>0 ? top : bot);
    if (currentBetType === '3' || currentBetType === '6') typeLabel2 = (top>0 && bot>0) ? 'บน x โต๊ด' : (top>0 ? 'บน' : 'โต๊ด');

    // เพิ่มบิลใหม่ทุกครั้ง → แยกบรรทัดเสมอ (ไม่ merge)
    betGroups.push({
        id: Date.now(),
        typeCategory: currentBetType,
        typeLabel1, typeLabel2, typeLabel3,
        numbers: [...numbers],
        amountTop: top, amountBot: bot
    });
    
    renderBetItems();
    selectedNums = [];
    renderSelectedNumbers();
    saveState();
    document.getElementById('numInput').value = '';
    document.getElementById('numInput').focus();
}

// ==========================================
// Cancel / Clear
// ==========================================
function cancelBet() {
    if (betGroups.length === 0 && selectedNums.length === 0) return;
    Swal.fire({ title: 'ยกเลิกทั้งหมด?', text: 'ล้างเลขที่เลือกและรายการแทงทั้งหมด', icon: 'warning', showCancelButton: true, confirmButtonColor: '#e53935', cancelButtonText: 'ไม่', confirmButtonText: 'ยกเลิกทั้งหมด' }).then(r => {
        if (r.isConfirmed) {
            betGroups = []; selectedNums = [];
            renderBetItems(); renderSelectedNumbers();
            clearSavedState();
            document.getElementById('betNote').value = '';
            document.getElementById('numInput').value = '';
            document.getElementById('topAmount').value = '';
            document.getElementById('botAmount').value = '';
        }
    });
}

// ==========================================
// Win Number (วินเลข) Functions
// ==========================================
let winSelectedDigits = [];

function toggleWinDigit(d) {
    const idx = winSelectedDigits.indexOf(d);
    const btn = document.getElementById('wdig-' + d);
    if (idx >= 0) {
        winSelectedDigits.splice(idx, 1);
        btn.className = 'win-digit py-2 text-center text-lg font-bold border-2 border-gray-300 rounded bg-white hover:bg-yellow-100 transition';
    } else {
        if (winSelectedDigits.length >= 7) { Swal.fire({ icon: 'warning', title: 'เลือกได้สูงสุด 7 ตัว', timer: 1500, showConfirmButton: false }); return; }
        winSelectedDigits.push(d);
        btn.className = 'win-digit py-2 text-center text-lg font-bold border-2 border-[#ffca28] rounded bg-[#ffca28] text-[#e65100] transition';
    }
}

function calculateWin() {
    if (winSelectedDigits.length < 2) { Swal.fire({ icon: 'warning', title: 'กรุณาเลือกอย่างน้อย 2 ตัว', timer: 1500, showConfirmButton: false }); return; }
    
    const digits = [...winSelectedDigits].sort();
    const numDigits = parseInt(document.querySelector('input[name="winDigit"]:checked').value);
    const includeDoubles = document.querySelector('input[name="winDouble"]:checked').value === 'yes';
    
    let combos = [];
    
    if (numDigits === 2) {
        // จับวิน 2 ตัว
        if (includeDoubles) {
            // รวมเบิ้ล: combinations with repetition
            for (let i = 0; i < digits.length; i++) {
                for (let j = i; j < digits.length; j++) {
                    combos.push('' + digits[i] + digits[j]);
                }
            }
        } else {
            // ไม่รวมเบิ้ล: only different digits
            for (let i = 0; i < digits.length; i++) {
                for (let j = i + 1; j < digits.length; j++) {
                    combos.push('' + digits[i] + digits[j]);
                }
            }
        }
    } else {
        // จับวิน 3 ตัว
        if (includeDoubles) {
            // รวมเบิ้ล: combinations with repetition
            for (let i = 0; i < digits.length; i++) {
                for (let j = i; j < digits.length; j++) {
                    for (let k = j; k < digits.length; k++) {
                        combos.push('' + digits[i] + digits[j] + digits[k]);
                    }
                }
            }
        } else {
            // ไม่รวมเบิ้ล: only different digits
            for (let i = 0; i < digits.length; i++) {
                for (let j = i + 1; j < digits.length; j++) {
                    for (let k = j + 1; k < digits.length; k++) {
                        combos.push('' + digits[i] + digits[j] + digits[k]);
                    }
                }
            }
        }
    }
    
    // Add to selectedNums
    combos.forEach(c => { if (!selectedNums.includes(c)) selectedNums.push(c); });
    
    // Render results in winResults area
    const container = document.getElementById('winResults');
    container.innerHTML = combos.map(c => 
        `<span class="inline-flex items-center px-3 py-1.5 text-sm font-bold bg-[#4dd0e1] text-gray-800 rounded cursor-pointer hover:bg-cyan-300 transition" onclick="removeWinResult(this,'${c}')">${c}</span>`
    ).join('');
    
    renderSelectedNumbers();
    saveState();
    
    // Update bet type based on digit count + show input fields
    if (numDigits === 2) currentBetType = '2';
    else currentBetType = '3';
    
    // Show normal input panel so user can enter amounts
    const normalInput = document.getElementById('normalInputPanel');
    if (normalInput) normalInput.style.display = '';
    
    // Update labels
    const topLabel = document.getElementById('topLabel');
    const botLabel = document.getElementById('botLabel');
    const botWrap = document.getElementById('botAmountWrap');
    if (numDigits === 2) {
        if (topLabel) topLabel.textContent = 'บน';
        if (botLabel) botLabel.textContent = 'ล่าง';
        if (botWrap) botWrap.style.display = '';
    } else {
        if (topLabel) topLabel.textContent = 'บน';
        if (botLabel) botLabel.textContent = 'โต๊ด';
        if (botWrap) botWrap.style.display = '';
    }
}

function removeWinResult(el, num) {
    el.remove();
    const idx = selectedNums.indexOf(num);
    if (idx >= 0) selectedNums.splice(idx, 1);
    renderSelectedNumbers();
    saveState();
}

function reverseWinNumbers() {
    // กลับเลขวิน: เพิ่ม reversed ของ selectedNums
    const current = [...selectedNums];
    let added = 0;
    current.forEach(num => {
        if (num.length === 2) {
            // 2 ตัว: กลับ AB → BA
            const rev = num[1] + num[0];
            if (rev !== num && !selectedNums.includes(rev)) { selectedNums.push(rev); added++; }
        } else if (num.length === 3) {
            // 3 ตัว: สร้าง permutations ทั้งหมด
            const perms = getPermutations(num);
            perms.forEach(p => {
                if (!selectedNums.includes(p)) { selectedNums.push(p); added++; }
            });
        }
    });
    renderSelectedNumbers();
    saveState();
    
    // Update winResults to show all
    const container = document.getElementById('winResults');
    container.innerHTML = selectedNums.map(c => 
        `<span class="inline-flex items-center px-3 py-1.5 text-sm font-bold bg-[#4dd0e1] text-gray-800 rounded cursor-pointer hover:bg-cyan-300 transition" onclick="removeWinResult(this,'${c}')">${c}</span>`
    ).join('');
    
    if (added > 0) Swal.fire({ icon: 'success', title: `เพิ่ม ${added} เลขกลับ`, timer: 1500, showConfirmButton: false });
}

function clearAllBets() {
    if (betGroups.length === 0) return;
    Swal.fire({ title: 'ล้างตารางทั้งหมด?', icon: 'question', showCancelButton: true, confirmButtonColor: '#e53935', cancelButtonText: 'ไม่', confirmButtonText: 'ล้าง' }).then(r => {
        if (r.isConfirmed) { betGroups = []; renderBetItems(); }
    });
}

function clearDuplicates() {
    let dupes = [];
    
    // ลบซ้ำจาก selectedNums (เลขที่เลือกไว้ด้านบน)
    if (selectedNums.length > 0) {
        const seen = new Set();
        const unique = [];
        selectedNums.forEach(n => {
            if (seen.has(n)) {
                if (!dupes.includes(n)) dupes.push(n);
            } else {
                seen.add(n);
                unique.push(n);
            }
        });
        selectedNums = unique;
        renderSelectedNumbers();
        saveState();
    }
    
    // ลบซ้ำจาก betGroups (ตารางรายการแทง)
    betGroups.forEach(g => {
        const seen = new Set();
        const unique = [];
        g.numbers.forEach(n => {
            if (seen.has(n)) {
                if (!dupes.includes(n)) dupes.push(n);
            } else {
                seen.add(n);
                unique.push(n);
            }
        });
        g.numbers = unique;
    });
    renderBetItems();
    
    if (dupes.length > 0) {
        Swal.fire({ icon: 'success', title: 'ลบเลขซ้ำแล้ว', html: '<b>เลขที่ซ้ำ:</b> ' + dupes.join(', '), confirmButtonColor: '#2e7d32', confirmButtonText: 'ตกลง' });
    } else {
        Swal.fire({ icon: 'info', title: 'ไม่มีเลขซ้ำ', timer: 1500, showConfirmButton: false });
    }
}

function removeBetGroup(id) { betGroups = betGroups.filter(g => g.id !== id); renderBetItems(); }

// ==========================================
// Render Bet Items
// ==========================================
function renderBetItems() {
    const tbody = document.getElementById('betItemsBody');
    if (betGroups.length === 0) {
        tbody.innerHTML = '<div class="p-8 text-center text-gray-400">ยังไม่มีรายการ</div>';
        document.getElementById('totalAmount').textContent = '0.00';
        return;
    }
    
    let total = 0;
    tbody.innerHTML = betGroups.map(g => {
        let numCount = g.numbers.length;
        let topTotal = g.amountTop * numCount;
        let botTotal = g.amountBot * numCount;
        total += topTotal + botTotal;
        
        return `<div class="flex p-3 hover:bg-gray-50 items-center border-b border-gray-100 last:border-0 relative">
            <div class="w-24 text-center border-r border-gray-200 pr-3 mr-3 flex-shrink-0">
                <div class="text-[13px] text-gray-800 font-bold">${g.typeLabel1}</div>
                <div class="text-[13px] text-gray-600">${g.typeLabel2}</div>
                <div class="text-[13px] text-gray-800 font-bold">${g.typeLabel3}</div>
            </div>
            <div class="flex-1 font-bold text-[15px] font-mono text-gray-900 pr-8 leading-relaxed flex flex-wrap gap-1">
                ${g.numbers.map(n => `<span class="inline-block">${n}</span>`).join(' ')}
                <span class="text-gray-400 text-xs ml-2">(${numCount} เลข)</span>
            </div>
            <button onclick="removeBetGroup(${g.id})" class="absolute right-3 top-1/2 -translate-y-1/2 text-red-500 hover:text-red-700">
                <i class="far fa-trash-alt text-lg"></i>
            </button>
        </div>`;
    }).join('');
    
    document.getElementById('totalAmount').textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// ==========================================
// Save Bet (Quick Mode)
// ==========================================
async function saveBet() {
    if (betGroups.length === 0) { Swal.fire({ icon: 'warning', title: 'ยังไม่มีรายการเดิมพัน', confirmButtonColor: '#f59e0b' }); return; }
    
    let flatItems = [];
    betGroups.forEach(g => {
        g.numbers.forEach(num => {
            const tc = g.typeCategory;
            if (tc === '2') {
                if (g.amountTop > 0) flatItems.push({ number: num, type: '2top', amount: g.amountTop });
                if (g.amountBot > 0) flatItems.push({ number: num, type: '2bot', amount: g.amountBot });
            } else if (tc === '3' || tc === '6') {
                if (g.amountTop > 0) flatItems.push({ number: num, type: '3top', amount: g.amountTop });
                if (g.amountBot > 0) flatItems.push({ number: num, type: '3tod', amount: g.amountBot });
            } else if (tc === '19') {
                if (g.amountTop > 0) flatItems.push({ number: num, type: '2top', amount: g.amountTop });
                if (g.amountBot > 0) flatItems.push({ number: num, type: '2bot', amount: g.amountBot });
            } else if (tc === 'run' || tc === 'win') {
                if (g.amountTop > 0) flatItems.push({ number: num, type: 'run_top', amount: g.amountTop });
                if (g.amountBot > 0) flatItems.push({ number: num, type: 'run_bot', amount: g.amountBot });
            }
        });
    });
    
    // เช็ครายการเกิน threshold ต่อประเภท (แยกนับ 2top, 2bot ฯลฯ)
    const typeCounts = {};
    flatItems.forEach(i => { typeCounts[i.type] = (typeCounts[i.type] || 0) + 1; });
    
    const threshold = OVER_LIMIT.threshold || 50;
    const typeLabels = { '2top': '2 ตัวบน', '2bot': '2 ตัวล่าง', '3top': '3 ตัวบน', '3tod': '3 ตัวโต๊ด', 'run_top': 'วิ่งบน', 'run_bot': 'วิ่งล่าง' };
    const overTypes = Object.entries(typeCounts).filter(([t, c]) => c >= threshold);
    
    if (overTypes.length > 0) {
        let detailHtml = overTypes.map(([type, count]) => {
            const label = typeLabels[type] || type;
            const rate = OVER_LIMIT[type] || '-';
            return `<tr><td style="padding:4px 8px;">${label}</td><td style="padding:4px 8px;text-align:center;font-weight:bold;color:#e53935;">${count}</td><td style="padding:4px 8px;text-align:center;font-weight:bold;color:#e53935;">${rate}</td></tr>`;
        }).join('');
        
        const confirm = await Swal.fire({
            icon: 'warning',
            title: `เกิน ${threshold} รายการ!`,
            html: `<div style="font-size:14px;">
                <p>บิลนี้มีประเภทที่เกิน <b>${threshold}</b> รายการ อัตราจ่ายจะลดลง:</p>
                <table style="width:100%;margin-top:10px;border-collapse:collapse;">
                    <tr style="background:#f5f5f5;"><th style="padding:4px 8px;text-align:left;">ประเภท</th><th style="padding:4px 8px;">จำนวน</th><th style="padding:4px 8px;">อัตราจ่ายใหม่</th></tr>
                    ${detailHtml}
                </table>
                <p style="margin-top:10px;color:#666;">ต้องการดำเนินการต่อหรือไม่?</p>
            </div>`,
            showCancelButton: true,
            confirmButtonColor: '#e53935',
            cancelButtonColor: '#9e9e9e',
            confirmButtonText: 'ยืนยัน บันทึก',
            cancelButtonText: 'ยกเลิก'
        });
        if (!confirm.isConfirmed) return;
    }
    
    await doSaveBet(flatItems, document.getElementById('betNote').value, () => {
        betGroups = []; selectedNums = [];
        renderBetItems(); renderSelectedNumbers();
        clearSavedState();
        document.getElementById('betNote').value = '';
    });
}

// ==========================================
// Shared save function
// ==========================================
async function doSaveBet(items, note, onSuccess) {
    // ==========================================
    // สร้างหน้ายืนยันรายการ (ลบได้ทีละตัว)
    // ==========================================
    const typeLabels = { '3top': '3 ตัวบน', '3tod': '3 ตัวโต๊ด', '2top': '2 ตัวบน', '2bot': '2 ตัวล่าง', 'run_top': 'วิ่งบน', 'run_bot': 'วิ่งล่าง' };
    
    // Store items in window scope for delete access
    window._confirmItems = [...items];
    
    // คำนวณ over-limit rates สำหรับแต่ละประเภท (recalculate ทุกครั้งที่ render)
    function getOverLimitTypes() {
        const tc = {};
        window._confirmItems.forEach(i => { tc[i.type] = (tc[i.type] || 0) + 1; });
        const th = OVER_LIMIT.threshold || 50;
        const olt = {};
        Object.entries(tc).forEach(([type, count]) => {
            if (count >= th && OVER_LIMIT[type]) olt[type] = OVER_LIMIT[type];
        });
        return olt;
    }

    function buildConfirmHtml() {
        const overLimitTypes = getOverLimitTypes();
        let totalAmount = 0;
        let totalDiscount = 0;
        let rows = window._confirmItems.map((item, idx) => {
            const label = typeLabels[item.type] || item.type;
            // แสดงเรทลดเมื่อเกิน threshold
            const rate = overLimitTypes[item.type] || PAY_RATES[item.type] || '-';
            const isReduced = !!overLimitTypes[item.type];
            const rateColor = isReduced ? '#e53935' : '#1565c0';
            const discount = item.discount || 0;
            totalAmount += item.amount;
            totalDiscount += discount;
            return `<tr style="border-bottom:1px solid #eee;" id="confirm-row-${idx}">
                <td style="padding:5px 8px;font-size:13px;">${label}</td>
                <td style="padding:5px 8px;text-align:center;font-weight:bold;font-size:14px;">${item.number}</td>
                <td style="padding:5px 8px;text-align:right;font-size:13px;">${item.amount.toFixed(2)}</td>
                <td style="padding:5px 8px;text-align:right;font-size:13px;color:${rateColor};font-weight:${isReduced ? 'bold' : 'normal'};">${rate}</td>
                <td style="padding:5px 8px;text-align:right;font-size:13px;color:#e53935;">${discount.toFixed(2)}</td>
                <td style="padding:2px 4px;text-align:center;">
                    <button type="button" onclick="window._removeConfirmItem(${idx})" style="background:#fee2e2;border:none;color:#e53935;cursor:pointer;border-radius:4px;padding:4px 6px;font-size:12px;" title="ลบ">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');

        return `
            <div style="max-height:400px;overflow-y:auto;margin:0 -20px;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f5f5f5;position:sticky;top:0;">
                            <th style="padding:6px 8px;text-align:left;">ประเภท</th>
                            <th style="padding:6px 8px;text-align:center;">หมายเลข</th>
                            <th style="padding:6px 8px;text-align:right;">ยอดเดิมพัน</th>
                            <th style="padding:6px 8px;text-align:right;">เรทจ่าย</th>
                            <th style="padding:6px 8px;text-align:right;">ส่วนลด</th>
                            <th style="padding:6px 4px;text-align:center;width:36px;">#</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            <div style="margin-top:12px;padding:10px;background:#f0fdf4;border-radius:8px;font-size:14px;">
                <div style="display:flex;justify-content:space-between;"><span>ยอดเดิมพัน</span><b>${totalAmount.toFixed(2)} บาท</b></div>
                <div style="display:flex;justify-content:space-between;color:#e53935;"><span>ส่วนลด</span><b>${totalDiscount.toFixed(2)} บาท</b></div>
                <div style="display:flex;justify-content:space-between;font-size:16px;margin-top:4px;padding-top:6px;border-top:1px solid #c8e6c9;"><span><b>รวม</b></span><b style="color:#2e7d32;">${(totalAmount - totalDiscount).toFixed(2)} บาท</b></div>
            </div>
            ${note ? `<div style="margin-top:8px;font-size:12px;color:#888;">หมายเหตุ: ${note}</div>` : ''}
        `;
    }

    // Function to remove item and re-render
    window._removeConfirmItem = function(idx) {
        window._confirmItems.splice(idx, 1);
        if (window._confirmItems.length === 0) {
            Swal.close();
            Swal.fire({ icon: 'info', title: 'ลบรายการหมดแล้ว', text: 'ไม่มีรายการเดิมพันเหลือ', confirmButtonColor: '#2e7d32' });
            return;
        }
        // Re-render the popup content
        const container = Swal.getHtmlContainer();
        if (container) container.innerHTML = buildConfirmHtml();
    };

    const confirm = await Swal.fire({
        title: 'กรุณายืนยันรายการ',
        html: buildConfirmHtml(),
        width: 560,
        showCancelButton: true,
        confirmButtonColor: '#2e7d32',
        cancelButtonColor: '#9e9e9e',
        confirmButtonText: '<i class="fas fa-check"></i> ยืนยัน',
        cancelButtonText: '<i class="fas fa-arrow-left"></i> ย้อนกลับ',
        customClass: { popup: 'swal-wide' }
    });

    if (!confirm.isConfirmed) return;

    // ==========================================
    // ส่งข้อมูลไป API
    // ==========================================
    const finalItems = window._confirmItems || items;
    if (finalItems.length === 0) return;
    const data = { action: 'save_bet', lottery_type_id: LOTTERY_ID, note: note, items: finalItems };
    try {
        const res = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            await Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: 'เลขที่: ' + result.bet_number, confirmButtonColor: '#2e7d32' });
            if (onSuccess) onSuccess();
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.error || 'Unknown error', confirmButtonColor: '#e53935' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message, confirmButtonColor: '#e53935' });
    }
}

// ==========================================
// Classic Mode Functions
// ==========================================
function addClassicRow() {
    const tbody = document.getElementById('classicBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="border px-1 py-1"><input type="text" class="classic-num w-full border rounded px-2 py-1.5 text-sm text-center outline-none" maxlength="3" placeholder="000"></td>
        <td class="border px-1 py-1"><input type="number" class="classic-top w-full border rounded px-2 py-1.5 text-sm text-center outline-none" placeholder="0"></td>
        <td class="border px-1 py-1"><input type="number" class="classic-bot w-full border rounded px-2 py-1.5 text-sm text-center outline-none" placeholder="0"></td>
        <td class="border px-1 py-1"><input type="number" class="classic-tod w-full border rounded px-2 py-1.5 text-sm text-center outline-none" placeholder="0"></td>
        <td class="border px-1 py-1 text-center"><button onclick="removeClassicRow(this)" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button></td>`;
    tbody.appendChild(tr);
    tr.querySelector('.classic-num').focus();
}

function removeClassicRow(btn) {
    const tbody = document.getElementById('classicBody');
    if (tbody.rows.length > 1) btn.closest('tr').remove();
}

function addClassicToBill() {
    const rows = document.querySelectorAll('#classicBody tr');
    let items = [];
    rows.forEach(row => {
        const num = row.querySelector('.classic-num')?.value.trim();
        const top = parseFloat(row.querySelector('.classic-top')?.value) || 0;
        const bot = parseFloat(row.querySelector('.classic-bot')?.value) || 0;
        const tod = parseFloat(row.querySelector('.classic-tod')?.value) || 0;
        if (!num) return;
        if (num.length === 2) {
            if (top > 0) items.push({ number: num, type: '2top', amount: top });
            if (bot > 0) items.push({ number: num, type: '2bot', amount: bot });
        } else if (num.length === 3) {
            if (top > 0) items.push({ number: num, type: '3top', amount: top });
            if (bot > 0) items.push({ number: num, type: '2bot', amount: bot });
            if (tod > 0) items.push({ number: num, type: '3tod', amount: tod });
        } else if (num.length === 1) {
            if (top > 0) items.push({ number: num, type: 'run_top', amount: top });
            if (bot > 0) items.push({ number: num, type: 'run_bot', amount: bot });
        }
    });
    
    if (items.length === 0) { Swal.fire({ icon: 'warning', title: 'กรุณาใส่ข้อมูล', confirmButtonColor: '#f59e0b' }); return; }
    
    classicBetGroups.push({ id: Date.now(), items: items });
    renderClassicBetItems();
    // Clear the form
    rows.forEach(row => {
        row.querySelectorAll('input').forEach(inp => inp.value = '');
    });
}

function renderClassicBetItems() {
    const tbody = document.getElementById('classicBetItemsBody');
    if (classicBetGroups.length === 0) {
        tbody.innerHTML = '<div class="p-6 text-center text-gray-400">ยังไม่มีรายการ</div>';
        document.getElementById('classicTotalAmount').textContent = '0.00';
        return;
    }
    let total = 0;
    tbody.innerHTML = classicBetGroups.map(g => {
        let cost = g.items.reduce((s, i) => s + i.amount, 0);
        total += cost;
        let summary = g.items.map(i => `${i.number}(${getBetTypeLabel(i.type)}:${i.amount})`).join(', ');
        return `<div class="flex p-2 hover:bg-gray-50 items-center border-b relative">
            <div class="flex-1 text-[12px] text-gray-700">${summary}</div>
            <div class="text-sm font-bold text-[#1565c0] mr-8">${cost.toFixed(2)}</div>
            <button onclick="classicBetGroups=classicBetGroups.filter(x=>x.id!==${g.id});renderClassicBetItems();" class="absolute right-2 text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
        </div>`;
    }).join('');
    document.getElementById('classicTotalAmount').textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function clearClassicBets() { classicBetGroups = []; renderClassicBetItems(); }

async function saveClassicBet() {
    let allItems = [];
    classicBetGroups.forEach(g => allItems.push(...g.items));
    if (allItems.length === 0) { Swal.fire({ icon: 'warning', title: 'ยังไม่มีรายการ', confirmButtonColor: '#f59e0b' }); return; }
    await doSaveBet(allItems, document.getElementById('classicBetNote')?.value || '', () => {
        classicBetGroups = []; renderClassicBetItems();
        if (document.getElementById('classicBetNote')) document.getElementById('classicBetNote').value = '';
    });
}

function getBetTypeLabel(t) {
    return { '3top': '3ตัวบน', '3tod': '3ตัวโต๊ด', '2top': '2ตัวบน', '2bot': '2ตัวล่าง', 'run_top': 'วิ่งบน', 'run_bot': 'วิ่งล่าง' }[t] || t;
}

// ==========================================
// Paste Mode Functions
// ==========================================
function parsePaste() {
    const text = document.getElementById('pasteInput').value.trim();
    const defaultTop = parseFloat(document.getElementById('pasteTop')?.value) || 0;
    const defaultBot = parseFloat(document.getElementById('pasteBot')?.value) || 0;
    const defaultTod = parseFloat(document.getElementById('pasteTod')?.value) || 0;
    
    if (!text && (defaultTop === 0 && defaultBot === 0 && defaultTod === 0)) { 
        Swal.fire({ icon: 'warning', title: 'กรุณาวางข้อมูลโพยหรือใส่ราคา', confirmButtonColor: '#f59e0b' }); 
        return; 
    }
    
    const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0);
    let items = [];
    
    lines.forEach(line => {
        // Format: เลข*บน*ล่าง or just เลข
        const parts = line.split(/[*x×]/);
        const num = parts[0]?.trim();
        if (!num || !/^\d+$/.test(num)) return;
        
        let top = parts.length > 1 ? (parseFloat(parts[1]) || 0) : defaultTop;
        let bot = parts.length > 2 ? (parseFloat(parts[2]) || 0) : defaultBot;
        let tod = parts.length > 3 ? (parseFloat(parts[3]) || 0) : defaultTod;
        
        // If no per-line prices, use defaults
        if (parts.length === 1) { top = defaultTop; bot = defaultBot; tod = defaultTod; }
        
        if (num.length === 2) {
            if (top > 0) items.push({ number: num, type: '2top', amount: top });
            if (bot > 0) items.push({ number: num, type: '2bot', amount: bot });
        } else if (num.length === 3) {
            if (top > 0) items.push({ number: num, type: '3top', amount: top });
            if (bot > 0) items.push({ number: num, type: '2bot', amount: bot });
            if (tod > 0) items.push({ number: num, type: '3tod', amount: tod });
        } else if (num.length === 1) {
            if (top > 0) items.push({ number: num, type: 'run_top', amount: top });
            if (bot > 0) items.push({ number: num, type: 'run_bot', amount: bot });
        }
    });
    
    if (items.length === 0) { Swal.fire({ icon: 'warning', title: 'ไม่พบรายการที่ถูกต้อง', confirmButtonColor: '#f59e0b' }); return; }
    
    pasteBetGroups.push({ id: Date.now(), items: items });
    renderPasteBetItems();
    document.getElementById('pasteInput').value = '';
}

function renderPasteBetItems() {
    const tbody = document.getElementById('pasteBetItemsBody');
    if (pasteBetGroups.length === 0) {
        tbody.innerHTML = '<div class="p-6 text-center text-gray-400">ยังไม่มีรายการ</div>';
        document.getElementById('pasteTotalAmount').textContent = '0.00';
        return;
    }
    let total = 0;
    tbody.innerHTML = pasteBetGroups.map(g => {
        let cost = g.items.reduce((s, i) => s + i.amount, 0);
        total += cost;
        return `<div class="flex p-2 hover:bg-gray-50 items-center border-b relative">
            <div class="flex-1 text-[12px] text-gray-700">${g.items.length} รายการ</div>
            <div class="text-sm font-bold text-[#1565c0] mr-8">${cost.toFixed(2)}</div>
            <button onclick="pasteBetGroups=pasteBetGroups.filter(x=>x.id!==${g.id});renderPasteBetItems();" class="absolute right-2 text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
        </div>`;
    }).join('');
    document.getElementById('pasteTotalAmount').textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function clearPasteBets() { pasteBetGroups = []; renderPasteBetItems(); }

async function savePasteBet() {
    let allItems = [];
    pasteBetGroups.forEach(g => allItems.push(...g.items));
    if (allItems.length === 0) { Swal.fire({ icon: 'warning', title: 'ยังไม่มีรายการ', confirmButtonColor: '#f59e0b' }); return; }
    await doSaveBet(allItems, document.getElementById('pasteBetNote')?.value || '', () => {
        pasteBetGroups = []; renderPasteBetItems();
        if (document.getElementById('pasteBetNote')) document.getElementById('pasteBetNote').value = '';
    });
}

// ==========================================
// Event Listeners
// ==========================================
document.getElementById('numInput')?.addEventListener('input', handleNumInput);
// Paste event: รองรับวางเลขหลายตัวพร้อมกัน
document.getElementById('numInput')?.addEventListener('paste', function(e) {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text');
    if (text) {
        handleMultiPaste(text);
        this.value = '';
    }
});
document.getElementById('numInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        if (selectedNums.length > 0) {
            const top = document.getElementById('topAmount');
            if (!top.value) top.focus();
            else addBetItem();
        } else {
            const top = document.getElementById('topAmount');
            if (!top.value) top.focus();
            else addBetItem();
        }
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

// Classic mode: auto-advance rows
document.getElementById('classicBody')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const inputs = Array.from(document.querySelectorAll('#classicBody input'));
        const idx = inputs.indexOf(e.target);
        if (idx >= 0 && idx < inputs.length - 1) inputs[idx + 1].focus();
        else addClassicRow();
    }
});

// ==========================================
// Restore saved state on page load
// ==========================================
if (selectedNums.length > 0 || betGroups.length > 0) {
    renderSelectedNumbers();
    renderBetItems();
    // Restore bet type button highlight (without clearing selectedNums)
    document.querySelectorAll('.bet-type-btn').forEach(b => { b.className = 'bet-type-btn px-4 py-1.5 rounded text-sm font-medium bg-[#fff8e1] text-[#f57f17] border border-[#ffca28] hover:bg-[#ffca28] hover:text-yellow-900'; });
    const btn = document.getElementById('btn-type-' + currentBetType);
    if (btn) btn.className = 'bet-type-btn px-4 py-1.5 rounded text-sm font-bold bg-[#ffca28] text-yellow-900 border border-[#ffca28]';
}

async function deleteBill(billId) {
    const result = await Swal.fire({
        icon: 'warning', title: 'ยกเลิกโพยนี้?',
        text: 'โพยที่ยกเลิกแล้วจะไม่สามารถกู้คืนได้',
        showCancelButton: true, confirmButtonColor: '#e53935',
        cancelButtonColor: '#9e9e9e', confirmButtonText: 'ยืนยัน ยกเลิก', cancelButtonText: 'ไม่'
    });
    if (!result.isConfirmed) return;
    try {
        const res = await fetch('api.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'cancel_bet', bet_id: billId })
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'ยกเลิกโพยสำเร็จ', timer: 1500, showConfirmButton: false });
            setTimeout(() => location.reload(), 1500);
        } else {
            Swal.fire({ icon: 'error', title: data.error || 'เกิดข้อผิดพลาด' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
