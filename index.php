<?php
$pageTitle = 'คีย์หวย - หน้าหลัก';
$currentPage = 'home';
require_once 'auth.php';
requireLogin();

// =============================================
// กำหนดรายชื่อหวยที่จะแสดงบนหน้าแรก
// แบ่งเป็น 3 กลุ่ม ตามลำดับที่ต้องการ
// =============================================
$LOTTERY_GROUPS = [
    [
        'label' => 'หวยรายวัน',
        'color' => '#2e7d32',
        'bg'    => '#e8f5e9',
        'names' => [
            // 🇱🇦 ลาว
            'ลาวประตูชัย', 'ลาวสันติภาพ', 'ประชาชนลาว', 'ลาว EXTRA',
            'ลาว TV', 'ลาว HD', 'ลาวสตาร์', 'ลาวใต้',
            'ลาวสามัคคี', 'ลาวอาเซียน', 'ลาว VIP', 'ลาวสามัคคี VIP',
            'ลาวกาชาด',
            // 🇻🇳 ฮานอย
            'ฮานอยอาเซียน', 'ฮานอย HD', 'ฮานอยสตาร์', 'ฮานอย TV',
            'ฮานอยกาชาด', 'ฮานอยพิเศษ', 'ฮานอยสามัคคี', 'ฮานอยปกติ',
            'ฮานอยตรุษจีน', 'ฮานอย VIP', 'ฮานอยพัฒนา', 'ฮานอย EXTRA',
            // 🇯🇵 ญี่ปุ่น VIP
            'นิเคอิเช้า VIP', 'นิเคอิบ่าย VIP',
            // 🇨🇳 จีน VIP
            'จีนเช้า VIP', 'จีนบ่าย VIP',
            // 🇭🇰 ฮ่องกง VIP
            'ฮั่งเส็งเช้า VIP', 'ฮั่งเส็งบ่าย VIP',
            // 🇹🇼🇰🇷🇸🇬 เอเชีย VIP
            'ไต้หวัน VIP', 'เกาหลี VIP', 'สิงคโปร์ VIP',
            // 🇬🇧🇩🇪🇷🇺🇺🇸 ยุโรป+อเมริกา VIP
            'อังกฤษ VIP', 'เยอรมัน VIP', 'รัสเซีย VIP', 'ดาวโจนส์ VIP',
            // 🇱🇦 ลาวสตาร์ VIP
            'ลาวสตาร์ VIP',
        ],
    ],
    [
        'label' => 'หวยหุ้น',
        'color' => '#f57f17',
        'bg'    => '#fffde7',
        'names' => [
            // 🇯🇵 ญี่ปุ่น
            'นิเคอิ - เช้า', 'นิเคอิ - บ่าย',
            // 🇨🇳 จีน
            'หุ้นจีน - เช้า', 'หุ้นจีน - บ่าย',
            // 🇭🇰 ฮ่องกง
            'ฮั่งเส็ง - เช้า', 'ฮั่งเส็ง - บ่าย',
            // 🇹🇼🇰🇷🇸🇬 เอเชีย
            'หุ้นไต้หวัน', 'หุ้นเกาหลี', 'หุ้นสิงคโปร์',
            // 🇹🇭🇮🇳🇪🇬 อื่นๆ
            'หุ้นไทย - เย็น', 'หุ้นอินเดีย', 'หุ้นอียิปต์',
            'ลาวพัฒนา', 'หวย 12 ราศี',
            // 🇬🇧🇩🇪🇷🇺🇺🇸 ยุโรป+อเมริกา
            'หุ้นอังกฤษ', 'หุ้นเยอรมัน', 'หุ้นรัสเซีย',
            'ดาวโจนส์ STAR', 'หุ้นดาวโจนส์',
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

// =============================================
// ดึงข้อมูลหวยทั้งหมดจาก DB + ผลล่าสุด
// =============================================
$stmt = $pdo->query("
    SELECT 
        lt.id,
        lt.name,
        lt.flag_emoji,
        lt.draw_date,
        lt.open_time,
        lt.close_time,
        lt.result_time,
        lt.bet_closed,
        lt.category_id,
        r.three_top,
        r.two_bot,
        r.draw_date as result_date,
        r.created_at as result_created_at
    FROM lottery_types lt
    LEFT JOIN results r ON r.lottery_type_id = lt.id 
        AND r.draw_date = (
            SELECT MAX(r2.draw_date) 
            FROM results r2 
            WHERE r2.lottery_type_id = lt.id
        )
    WHERE lt.is_active = 1
");
$allLotteries = $stmt->fetchAll();

// =============================================
// คำนวณสถานะ โดยอิงจากงวด (round lifecycle)
// หลักการ: งวดจะ reset เมื่อถึง open_time ของวันใหม่
//   - ถ้าตอนนี้ผ่าน open_time ของวันนี้ → งวดวันนี้
//   - ผลที่มีจะเป็นงวดปัจจุบันก็ต่อเมื่อ result_date = วันนี้
//   - ถ้าผลเป็นของเก่า → ถือว่างวดใหม่ยังไม่มีผล
// =============================================
$now = time();
$today = date('Y-m-d');

$lotteryMap = [];
$recentlyResulted = [];
foreach ($allLotteries as &$l) {
    // กำหนด "งวดปัจจุบัน" โดยดูจาก open_time
    // ถ้าตอนนี้ผ่าน open_time ของวันนี้ → งวดวันนี้
    // ถ้ายังไม่ถึง open_time → ยังอยู่ในงวดเมื่อวาน (สำหรับหวยดึก เช่น ดาวโจนส์)
    $openTime = !empty($l['open_time']) ? strtotime(date('Y-m-d') . ' ' . $l['open_time']) : strtotime(date('Y-m-d') . ' 06:00:00');
    $isPastOpenTime = $now >= $openTime;
    
    // งวดปัจจุบัน
    $currentRoundDate = $isPastOpenTime ? $today : date('Y-m-d', strtotime('-1 day'));
    
    // เช็คว่าผลล่าสุดเป็นของงวดปัจจุบันหรือไม่
    $resultDate = $l['result_date'] ?? null;
    $hasResultForCurrentRound = !empty($l['three_top']) && $resultDate === $currentRoundDate;
    $hasAnyResult = !empty($l['three_top']);
    
    // คำนวณอายุผล (สำหรับ recently resulted section)
    $resultAge = $hasResultForCurrentRound && !empty($l['result_created_at']) 
        ? ($now - strtotime($l['result_created_at'])) 
        : PHP_INT_MAX;
    
    if ($hasResultForCurrentRound && $resultAge < 3600) {
        // ออกผลงวดนี้ไม่เกิน 1 ชม. → ย้ายไปด้านล่าง
        $recentlyResulted[] = $l['name'];
        $l['smart_status'] = 'resulted';
    } elseif ($hasResultForCurrentRound && $resultAge >= 3600) {
        // ผลงวดนี้ออกเกิน 1 ชม. → ยังอยู่ในงวดนี้ (จ่ายเงินแล้ว/ประมวลผล)
        $l['smart_status'] = 'resulted_old';
    } else {
        // ยังไม่มีผลของงวดนี้ → เปิดรับ
        $l['smart_status'] = 'open';
    }
    
    // เก็บข้อมูลงวดปัจจุบันไว้ใช้ในส่วน display
    $l['current_round_date'] = $currentRoundDate;
    $l['has_result_current_round'] = $hasResultForCurrentRound;
    
    $lotteryMap[$l['name']] = $l;
}
unset($l);

// =============================================
// ดึงสถานะ bets สำหรับคำนวณการจ่ายเงิน
// =============================================
$pendingBets = [];
$paidBets = [];

$betsStmt = $pdo->query("
    SELECT 
        b.lottery_type_id,
        b.draw_date,
        b.status,
        COUNT(*) as cnt
    FROM bets b
    WHERE b.draw_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY b.lottery_type_id, b.draw_date, b.status
");

foreach ($betsStmt->fetchAll() as $bs) {
    $key = $bs['lottery_type_id'] . '_' . $bs['draw_date'];
    if ($bs['status'] === 'pending') {
        $pendingBets[$key] = ($pendingBets[$key] ?? 0) + $bs['cnt'];
    } elseif (in_array($bs['status'], ['won', 'lost'])) {
        $paidBets[$key] = ($paidBets[$key] ?? 0) + $bs['cnt'];
    }
}

require_once 'includes/header.php';
?>

<style>
    .results-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .results-table th {
        background: linear-gradient(135deg, #b8e6c8 0%, #8fd4a4 100%);
        color: #1a5c2e;
        padding: 8px 12px;
        text-align: center;
        font-weight: 600;
        font-size: 12px;
        border: 1px solid #7cc895;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .results-table td {
        padding: 6px 10px;
        border: 1px solid #d0e6d3;
        vertical-align: middle;
    }
    .results-table tr:nth-child(even) td:not(.cat-header) {
        background-color: #f8fcf9;
    }
    .results-table tr:hover td:not(.cat-header) {
        background-color: #eef7f0;
    }
    .cat-header {
        font-weight: 700;
        font-size: 13px;
        padding: 8px 12px !important;
        border: 1px solid #7cc895 !important;
    }
    .num-box {
        background: linear-gradient(135deg, #4fc3f7 0%, #29b6f6 100%);
        color: white;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 6px;
        display: inline-block;
        min-width: 42px;
        text-align: center;
        font-size: 14px;
        letter-spacing: 1px;
        box-shadow: 0 2px 4px rgba(41, 182, 246, 0.3);
    }
    .status-paid {
        background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        display: inline-block;
        white-space: nowrap;
    }
    .status-waiting {
        background: linear-gradient(135deg, #42a5f5 0%, #1e88e5 100%);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        display: inline-block;
        white-space: nowrap;
    }
    .status-processing {
        background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        display: inline-block;
        white-space: nowrap;
        animation: pulse-glow 2s infinite;
    }
    .status-closed {
        background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        display: inline-block;
        white-space: nowrap;
    }
    .status-suspended {
        background: linear-gradient(135deg, #9e9e9e 0%, #757575 100%);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        display: inline-block;
        white-space: nowrap;
    }
    @keyframes pulse-glow {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    .flag-img {
        width: 28px;
        height: 18px;
        object-fit: cover;
        border-radius: 3px;
        border: 1px solid #ddd;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    .lottery-name {
        font-weight: 500;
        color: #2c3e50;
        font-size: 13px;
    }
    .section-title {
        background: linear-gradient(135deg, #1aa34a 0%, #15803d 100%);
        color: white;
        padding: 10px 16px;
        font-weight: 700;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<div class="card-outline">
    <!-- Header -->
    <div class="section-title">
        <i class="fas fa-trophy"></i>
        ผลหวยล่าสุด
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="results-table">
            <thead>
                <tr>
                    <th style="width: 35%">หวย</th>
                    <th style="width: 18%">งวด</th>
                    <th style="width: 14%">3 ตัวบน</th>
                    <th style="width: 14%">2 ตัวล่าง</th>
                    <th style="width: 19%">สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($LOTTERY_GROUPS as $group): 
                    // Filter out recently-resulted lotteries from main display
                    $mainNames = array_filter($group['names'], fn($n) => !in_array($n, $recentlyResulted));
                    if (empty($mainNames)) continue;
                ?>
                <!-- Category Header -->
                <tr>
                    <td colspan="5" class="cat-header" style="background: <?= $group['bg'] ?>; color: <?= $group['color'] ?>;">
                        <?= $group['label'] ?>
                    </td>
                </tr>
                <?php foreach ($mainNames as $lotteryName):
                    $lt = $lotteryMap[$lotteryName] ?? null;
                    if (!$lt) continue;

                    $flagUrl = getFlagForCountry($lt['flag_emoji'], $lotteryName);
                    $hasResultForRound = $lt['has_result_current_round'];
                    $hasAnyResult = !empty($lt['three_top']);
                    
                    // แสดงวันที่งวดปัจจุบัน
                    $displayDate = date('d-m-Y', strtotime($lt['current_round_date']));
                    
                    // สถานะ realtime 5 ระดับ (อิงจากงวดปัจจุบัน):
                    // 1. ปิดรับแทง (แดง) = Admin กดปิด
                    // 2. จ่ายเงินแล้ว (เขียว) = มีผลงวดนี้ + ไม่มี pending
                    // 3. กำลังประมวลผล (ส้ม) = เลย close_time ≤ 2 ชม. / หรือมีผลแต่ยัง pending
                    // 4. งดออกผล (เทา) = เลย close_time > 2 ชม. ยังไม่มีผลงวดนี้
                    // 5. รอออกผล (ฟ้า) = ยังไม่ถึงเวลาปิดรับ
                    $betKey = $lt['id'] . '_' . $lt['current_round_date'];
                    $hasPending = isset($pendingBets[$betKey]) && $pendingBets[$betKey] > 0;
                    $hasPaid = isset($paidBets[$betKey]) && $paidBets[$betKey] > 0;
                    $isBetClosed = !empty($lt['bet_closed']);
                    
                    // สร้าง close_time เป็น full datetime ของวันนี้
                    $closeTime = !empty($lt['close_time']) ? strtotime(date('Y-m-d') . ' ' . $lt['close_time']) : null;
                    $pastCloseTime = $closeTime && $now > $closeTime;
                    $hoursPastClose = $closeTime ? ($now - $closeTime) / 3600 : 0;
                    
                    if ($isBetClosed && !$hasResultForRound) {
                        // Admin ปิดรับแทง
                        $statusClass = 'status-closed'; $statusLabel = 'ปิดรับแทง';
                    } elseif ($hasResultForRound && !$hasPending) {
                        // มีผลงวดนี้ + ไม่มี pending = จ่ายเงินแล้ว
                        $statusClass = 'status-paid'; $statusLabel = 'จ่ายเงินแล้ว';
                    } elseif ($hasResultForRound && $hasPending) {
                        // มีผลงวดนี้แต่ยังมี pending = กำลังประมวลผล
                        $statusClass = 'status-processing'; $statusLabel = 'กำลังประมวลผล';
                    } elseif ($pastCloseTime && !$hasResultForRound && $hoursPastClose > 2) {
                        // เลยเวลา > 2 ชม. ยังไม่มีผลงวดนี้ = งดออกผล
                        $statusClass = 'status-suspended'; $statusLabel = 'งดออกผล';
                    } elseif ($pastCloseTime && !$hasResultForRound) {
                        // เลยเวลา ≤ 2 ชม. = กำลังประมวลผล
                        $statusClass = 'status-processing'; $statusLabel = 'กำลังประมวลผล';
                    } else {
                        // ยังไม่ถึงเวลาปิดรับ = รอออกผล
                        $statusClass = 'status-waiting'; $statusLabel = 'รอออกผล';
                    }
                ?>
                <tr>
                    <td>
                        <a href="bet.php?id=<?= $lt['id'] ?>" class="flex items-center space-x-2 hover:opacity-80">
                            <img src="<?= $flagUrl ?>" alt="flag" class="flag-img">
                            <span class="lottery-name"><?= htmlspecialchars($lotteryName) ?></span>
                        </a>
                    </td>
                    <td class="text-center text-gray-600 text-[12px]"><?= $displayDate ?></td>
                    <td class="text-center">
                        <?php if ($hasResultForRound): ?>
                            <span class="num-box"><?= htmlspecialchars($lt['three_top']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($hasResultForRound && !empty($lt['two_bot'])): ?>
                            <span class="num-box"><?= htmlspecialchars($lt['two_bot']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="<?= $statusClass ?>"><?= $statusLabel ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>

                <?php 
                // =============================================
                // หวยที่ออกผลแล้ว (1 ชม.) → แสดงด้านล่างสุด
                // =============================================
                if (!empty($recentlyResulted)):
                ?>
                <tr>
                    <td colspan="5" class="cat-header" style="background: #f5f5f5; color: #757575;">
                        <i class="fas fa-clock mr-1"></i> หวยที่ออกผลแล้ว (รอเปิดรับงวดใหม่)
                    </td>
                </tr>
                <?php foreach ($recentlyResulted as $lotteryName):
                    $lt = $lotteryMap[$lotteryName] ?? null;
                    if (!$lt) continue;
                    $flagUrl = getFlagForCountry($lt['flag_emoji'], $lotteryName);
                    $resultDate = $lt['result_date'] ? date('d-m-Y', strtotime($lt['result_date'])) : date('d-m-Y');
                ?>
                <tr class="opacity-60">
                    <td>
                        <a href="bet.php?id=<?= $lt['id'] ?>" class="flex items-center space-x-2 hover:opacity-80">
                            <img src="<?= $flagUrl ?>" alt="flag" class="flag-img">
                            <span class="lottery-name text-gray-400"><?= htmlspecialchars($lotteryName) ?></span>
                        </a>
                    </td>
                    <td class="text-center text-gray-400 text-[12px]"><?= $resultDate ?></td>
                    <td class="text-center">
                        <span class="num-box" style="background: #bdbdbd; box-shadow: none;"><?= htmlspecialchars($lt['three_top']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if (!empty($lt['two_bot'])): ?>
                            <span class="num-box" style="background: #bdbdbd; box-shadow: none;"><?= htmlspecialchars($lt['two_bot']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="status-paid" style="background: #9e9e9e;">ออกผลแล้ว</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

