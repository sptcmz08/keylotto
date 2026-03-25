<?php
require_once __DIR__ . '/config.php';

$today = date('Y-m-d');
$stocks = ['หุ้นไต้หวัน', 'หุ้นเกาหลี'];
$count = 0;

foreach ($stocks as $stock_name) {
    // ดึง ID
    $stmt = $pdo->prepare("SELECT id FROM lottery_types WHERE name = ?");
    $stmt->execute([$stock_name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        // ลบผลของวันนี้
        $delStmt = $pdo->prepare("DELETE FROM results WHERE lottery_type_id = ? AND draw_date = ?");
        $delStmt->execute([$id, $today]);
        $deleted = $delStmt->rowCount();
        
        if ($deleted > 0) {
            echo "✅ ลบผลผิดของ {$stock_name} (งวด {$today}) สำเร็จ\n";
            $count++;
            
            // เปลี่ยนสถานะโพยกลับเป็น pending
            $updateStmt = $pdo->prepare("UPDATE bets SET status = 'pending', win_amount = 0 WHERE lottery_type_id = ? AND draw_date = ? AND status != 'cancelled'");
            $updateStmt->execute([$id, $today]);
            $betsReverted = $updateStmt->rowCount();
            if ($betsReverted > 0) {
                echo "   🔄 คืนสถานะโพย {$betsReverted} ใบ กลับเป็น 'รอผล'\n";
            }
        } else {
            echo "ℹ️ ไม่มีผลของ {$stock_name} ในระบบสำหรับวันนี้\n";
        }
    }
}

if ($count > 0) {
    echo "\n⚠️ กรุณารอ Cron ทำงาน (ประมาณ 1 นาที) เพื่อดึงผลที่ถูกต้อง หรือรัน:\nphp cron_scrape.php smart\n";
} else {
    echo "\n✅ ไม่พบผลผิดในระบบ (อาจจะถูกลบไปแล้วหรือยังไม่ออกผล)\n";
}
