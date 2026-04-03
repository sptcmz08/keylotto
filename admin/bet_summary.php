<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'bet_summary';
$adminTitle = 'สรุปยอดทั้งหมด';

function fetchBetPeriodSummary(PDO $pdo, string $whereSql = '', array $params = []): array
{
    $where = "b.status != 'cancelled'";
    if ($whereSql !== '') {
        $where .= " AND {$whereSql}";
    }

    $summary = [
        'bill_count' => 0,
        'pending_count' => 0,
        'net_amount' => 0.0,
        'payout_amount' => 0.0,
        'profit_amount' => 0.0,
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS bill_count,
                SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                COALESCE(SUM(b.net_amount), 0) AS net_amount,
                COALESCE(SUM(CASE WHEN b.status = 'won' THEN COALESCE(b.win_amount, 0) ELSE 0 END), 0) AS payout_amount
            FROM bets b
            WHERE {$where}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
    } catch (Exception $e) {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS bill_count,
                SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                COALESCE(SUM(b.net_amount), 0) AS net_amount
            FROM bets b
            WHERE {$where}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
    }

    $summary['bill_count'] = (int) ($row['bill_count'] ?? 0);
    $summary['pending_count'] = (int) ($row['pending_count'] ?? 0);
    $summary['net_amount'] = (float) ($row['net_amount'] ?? 0);
    $summary['payout_amount'] = (float) ($row['payout_amount'] ?? 0);
    $summary['profit_amount'] = $summary['net_amount'] - $summary['payout_amount'];

    return $summary;
}

$periodSummaries = [
    [
        'label' => 'รายวัน',
        'range' => date('d-m-Y'),
        'data' => fetchBetPeriodSummary($pdo, 'DATE(b.created_at) = CURDATE()'),
    ],
    [
        'label' => 'รายสัปดาห์',
        'range' => date('d-m-Y', strtotime('monday this week')) . ' - ' . date('d-m-Y'),
        'data' => fetchBetPeriodSummary($pdo, 'YEARWEEK(b.created_at, 1) = YEARWEEK(CURDATE(), 1)'),
    ],
    [
        'label' => 'รายเดือน',
        'range' => date('m/Y'),
        'data' => fetchBetPeriodSummary($pdo, 'YEAR(b.created_at) = YEAR(CURDATE()) AND MONTH(b.created_at) = MONTH(CURDATE())'),
    ],
    [
        'label' => 'ทั้งหมด',
        'range' => 'ทุกข้อมูลในระบบ',
        'data' => fetchBetPeriodSummary($pdo),
    ],
];

require_once 'includes/header.php';
?>

<div class="mb-5">
    <div class="bg-white rounded-xl shadow-sm border p-4 md:p-5">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-lg font-bold text-gray-800">สรุปยอดทั้งหมด</h1>
                <p class="text-sm text-gray-500 mt-1">ดูยอดรับ ยอดจ่าย กำไร และจำนวนบิล แยกรายวัน รายสัปดาห์ รายเดือน และรวมทั้งหมด</p>
            </div>
            <div class="text-xs text-gray-400">
                อัปเดตตามข้อมูลโพยที่มีอยู่ในระบบ
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php foreach ($periodSummaries as $period): ?>
    <?php $summary = $period['data']; ?>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-base font-bold text-gray-800"><?= htmlspecialchars($period['label']) ?></p>
                <p class="text-[11px] text-gray-400 mt-1"><?= htmlspecialchars($period['range']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-[11px] text-gray-400">จำนวนบิล</p>
                <p class="text-xl font-bold text-gray-700"><?= number_format($summary['bill_count']) ?></p>
            </div>
        </div>

        <div class="mt-4 space-y-2">
            <div class="rounded-lg bg-green-50 border border-green-100 px-3 py-3 flex items-center justify-between gap-3">
                <span class="text-sm text-green-700">ยอดรับ</span>
                <span class="text-lg font-bold text-green-700">฿<?= number_format($summary['net_amount'], 2) ?></span>
            </div>
            <div class="rounded-lg bg-red-50 border border-red-100 px-3 py-3 flex items-center justify-between gap-3">
                <span class="text-sm text-red-600">ยอดจ่าย</span>
                <span class="text-lg font-bold text-red-600">฿<?= number_format($summary['payout_amount'], 2) ?></span>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-3 flex items-center justify-between gap-3">
                <span class="text-sm text-blue-600">กำไร</span>
                <span class="text-lg font-bold <?= $summary['profit_amount'] >= 0 ? 'text-blue-700' : 'text-red-600' ?>">฿<?= number_format($summary['profit_amount'], 2) ?></span>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between text-xs">
            <span class="text-gray-400">บิลรอผล <?= number_format($summary['pending_count']) ?> รายการ</span>
            <span class="font-medium <?= $summary['profit_amount'] >= 0 ? 'text-green-600' : 'text-red-500' ?>">
                <?= $summary['profit_amount'] >= 0 ? 'กำไร' : 'ขาดทุน' ?>
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
