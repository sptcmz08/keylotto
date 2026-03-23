<?php
/**
 * Migration: Add draw_schedule column to lottery_types
 * 
 * Values:
 *   "daily"            = ออกทุกวัน
 *   "mon,wed,fri"      = ออกเฉพาะวัน (จ,พ,ศ)
 *   "mon,tue,wed,thu,fri" = จ-ศ (หุ้น)
 *   "1,16"             = วันที่ 1 กับ 16 ของเดือน (หวยไทย)
 *
 * Run: php migrate_draw_schedule.php
 */
require_once __DIR__ . '/config.php';

echo "🔧 Adding draw_schedule column...\n";

// Add column if not exists
try {
    $pdo->exec("ALTER TABLE lottery_types ADD COLUMN draw_schedule VARCHAR(100) DEFAULT 'daily' AFTER close_time");
    echo "✅ Column added\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Column already exists\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Also add open_time if it doesn't exist
try {
    $pdo->exec("ALTER TABLE lottery_types ADD COLUMN open_time TIME DEFAULT '06:00:00' AFTER close_time");
    echo "✅ open_time column added\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  open_time already exists\n";
    }
}

echo "\n📋 Setting schedules based on lottery names...\n";

// Keyword-based schedule mapping (order matters: most specific first)
$scheduleRules = [
    // หวยไทย — วันที่ 1, 16 ของเดือน
    ['keywords' => ['รัฐบาล'], 'schedule' => '1,16'],
    ['keywords' => ['หวยออมสิน', 'ออมสิน'], 'schedule' => '1,16'],
    ['keywords' => ['ธกส'], 'schedule' => '1,16'],
    ['keywords' => ['หวยไทยชุด'], 'schedule' => '1,16'],
    ['keywords' => ['หวยไทย JACKPOT'], 'schedule' => '1,16'],
    ['keywords' => ['12 ราศี'], 'schedule' => '1,16'],
    
    // หุ้นไทย — จ-ศ
    ['keywords' => ['หุ้นไทย'], 'schedule' => 'mon,tue,wed,thu,fri'],
    
    // ลาวพัฒนา — จ, พ, ศ
    ['keywords' => ['ลาวพัฒนา'], 'schedule' => 'mon,wed,fri'],
    
    // หวย VIP ต่างๆ — จ-ส (6 วัน)
    ['keywords' => ['นิเคอิเช้า VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['นิเคอิบ่าย VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['จีนเช้า VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['จีนบ่าย VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['ฮั่งเส็งเช้า VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['ฮั่งเส็งบ่าย VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['เกาหลี VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['ไต้หวัน VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['สิงคโปร์ VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['อังกฤษ VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['เยอรมัน VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['ดาวโจนส์ VIP'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['รัสเซีย VIP', 'หุ้นรัสเซีย'], 'schedule' => 'mon,tue,wed,thu,fri'],
    
    // หุ้นทั้งหมด — จ-ศ
    ['keywords' => ['หุ้นดาวโจนส์'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นอังกฤษ'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นเยอรมัน'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นรัสเซีย'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นจีน'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นเกาหลี'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นไต้หวัน'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นสิงคโปร์'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นอินเดีย'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['หุ้นอียิปต์'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['นิเคอิ - เช้า', 'นิเคอิ - บ่าย'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['ฮั่งเส็ง - เช้า', 'ฮั่งเส็ง - บ่าย'], 'schedule' => 'mon,tue,wed,thu,fri'],
    ['keywords' => ['ดาวโจนส์ STAR'], 'schedule' => 'mon,tue,wed,thu,fri'],
    
    // มาเลย์ — พ, ส, อา
    ['keywords' => ['มาเลย์', 'มาเลเซีย'], 'schedule' => 'wed,sat,sun'],
];

$allLotteries = $pdo->query("SELECT id, name, draw_schedule FROM lottery_types")->fetchAll();
$updated = 0;

foreach ($allLotteries as $lt) {
    $matched = false;
    foreach ($scheduleRules as $rule) {
        foreach ($rule['keywords'] as $kw) {
            if (mb_strpos($lt['name'], $kw) !== false) {
                $pdo->prepare("UPDATE lottery_types SET draw_schedule = ? WHERE id = ?")->execute([$rule['schedule'], $lt['id']]);
                echo "  ✅ {$lt['name']} → {$rule['schedule']}\n";
                $updated++;
                $matched = true;
                break 2; // break both foreach loops
            }
        }
    }
    if (!$matched && $lt['draw_schedule'] === 'daily') {
        echo "  ⏭️  {$lt['name']} → daily (default)\n";
    }
}

echo "\n═══════════════════════════════════════\n";
echo "✅ Done! Updated $updated lotteries\n";
echo "═══════════════════════════════════════\n";
echo "\n💡 หมายเหตุ: หวยที่ไม่ได้ตั้ง schedule จะใช้ 'daily' เป็น default\n";
echo "   สามารถแก้ไขเพิ่มเติมใน admin → จัดการหวย ได้ครับ\n";
