<?php
$pageTitle = 'คีย์หวย - รายการโพย';
$currentPage = 'bills';
require_once 'auth.php';
requireLogin();

// Fetch bets with lottery info
$stmt = $pdo->query("
    SELECT b.*, lt.name as lottery_name, lc.name as category_name
    FROM bets b
    JOIN lottery_types lt ON b.lottery_type_id = lt.id
    JOIN lottery_categories lc ON lt.category_id = lc.id
    ORDER BY b.created_at DESC
    LIMIT 100
");
$bets = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="bg-white card-outline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px] text-gray-600 font-medium">
            <thead class="bg-white border-b border-gray-200">
                <tr>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">เลขที่</th>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">วันที่</th>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">ชนิดหวย</th>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">งวด</th>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">รายการ</th>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">ยอด</th>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">ส่วนลด</th>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">รวม</th>
                    <th class="px-3 py-3 text-center font-bold text-gray-700">ถูกรางวัล</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bets)): ?>
                <tr>
                    <td colspan="9" class="px-3 py-12 text-center text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-3 block"></i>
                        ยังไม่มีรายการโพย
                    </td>
                </tr>
                <?php else: foreach ($bets as $i => $b):
                    $isCancelled = $b['status'] === 'cancelled';
                    $rowClass = $isCancelled ? 'bg-[#fbeaea]' : ($i % 2 === 0 ? 'bg-[#f5f6f8]' : 'bg-white');
                    
                    $statusBadge = '';
                    switch ($b['status']) {
                        case 'won': $statusBadge = '<span class="text-[#2196f3]">ถูกรางวัล</span>'; break;
                        case 'lost': $statusBadge = '<span class="text-[#ef5350]">ไม่ถูกรางวัล</span>'; break;
                        case 'cancelled': $statusBadge = '<span class="text-[#ef5350]">ยกเลิก</span>'; break;
                        default: $statusBadge = ''; break; // Pending is blank
                    }
                ?>
                <tr class="<?= $rowClass ?> border-b border-gray-100 hover:bg-gray-50 transition">
                    <td class="px-3 py-3 text-center">
                        <?php if ($isCancelled): ?><i class="fas fa-times text-[#ef5350] mr-1"></i><?php endif; ?>
                        <?= htmlspecialchars($b['bet_number']) ?>
                    </td>
                    <td class="px-3 py-3 text-center"><?= date('d/m/Y H:i:s', strtotime($b['created_at'])) ?></td>
                    <td class="px-3 py-3">[<?= htmlspecialchars($b['category_name']) ?>] - <br><?= htmlspecialchars($b['lottery_name']) ?></td>
                    <td class="px-3 py-3 text-center"><?= date('Y-m-d', strtotime($b['draw_date'])) ?></td>
                    <td class="px-3 py-3 text-center"><?= $b['total_items'] ?></td>
                    <td class="px-3 py-3 text-center text-[#2196f3]"><?= formatMoney($b['total_amount']) ?></td>
                    <td class="px-3 py-3 text-center text-[#ef5350]"><?= $b['discount_amount'] > 0 ? formatMoney($b['discount_amount']) : '-' ?></td>
                    <td class="px-3 py-3 text-center text-[#2196f3]"><?= formatMoney($b['net_amount']) ?></td>
                    <td class="px-3 py-3 text-center"><?= $statusBadge ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
