<?php
/**
 * Migration: Add draw_schedule column to lottery_types
 * 
 * ตารางออกรางวัลที่ตรวจสอบแล้วจากแหล่งออนไลน์:
 * 
 * === หวยไทย ===
 *   รัฐบาลไทย     → 1,16   (วันที่ 1 และ 16 ของเดือน)
 *   ออมสิน         → 1,16   (วันที่ 1 และ 16 ของเดือน)
 *   ธกส            → 16     (เฉพาะวันที่ 16 ของเดือน)
 *   หวย 12 ราศี    → 1,16   (ตามหวยรัฐบาล)
 *
 * === หวยลาว ===
 *   ลาวพัฒนา       → mon,wed,fri  (จ,พ,ศ ถ่ายทอด 20:00 น.)
 *   ลาวชุด/JACKPOT → mon,wed,fri  (ตามลาวพัฒนา)
 *   ลาวประตูชัย    → daily
 *   ลาวสันติภาพ    → daily
 *   ประชาชนลาว     → daily
 *   ลาว EXTRA      → daily
 *   ลาว TV         → daily
 *   ลาว HD         → daily
 *   ลาวสตาร์       → daily
 *   ลาวใต้         → daily
 *   ลาวสามัคคี     → daily
 *   ลาวอาเซียน     → daily
 *   ลาว VIP        → daily
 *   ลาวกาชาด       → daily
 *
 * === หวยฮานอย ===
 *   ฮานอยทุกตัว    → daily  (ออกทุกวัน ไม่เว้นวันหยุด)
 *
 * === หวยหุ้น ===
 *   นิเคอิ (ญี่ปุ่น)   → mon,tue,wed,thu,fri (จ-ศ)
 *   ฮั่งเส็ง (ฮ่องกง)  → mon,tue,wed,thu,fri
 *   ไต้หวัน            → mon,tue,wed,thu,fri
 *   เกาหลี            → mon,tue,wed,thu,fri
 *   สิงคโปร์          → mon,tue,wed,thu,fri
 *   จีน               → mon,tue,wed,thu,fri
 *   อินเดีย           → mon,tue,wed,thu,fri
 *   อียิปต์           → mon,tue,wed,thu,fri (Sun-Thu จริง แต่ในไทยจับ จ-ศ)
 *   อังกฤษ            → mon,tue,wed,thu,fri
 *   เยอรมัน           → mon,tue,wed,thu,fri
 *   รัสเซีย           → mon,tue,wed,thu,fri
 *   ดาวโจนส์          → mon,tue,wed,thu,fri
 *   หุ้นไทย           → mon,tue,wed,thu,fri
 *
 * === หวยมาเลเซีย ===
 *   มาเลเซีย          → wed,sat,sun (พ,ส,อา)
 *
 * === หวยชุด ===
 *   หวยไทยชุด/JACKPOT  → ตามรัฐบาล 1,16
 *   ฮานอยชุด/JACKPOT   → daily (ตามฮานอย)
 *
 * Run: php migrate_draw_schedule.php
 */
require_once __DIR__ . '/config.php';

echo "═══════════════════════════════════════\n";
echo "🔧 Migration: draw_schedule\n";
echo "═══════════════════════════════════════\n\n";

// Add column if not exists
try {
    $pdo->exec("ALTER TABLE lottery_types ADD COLUMN draw_schedule VARCHAR(100) DEFAULT 'daily' AFTER close_time");
    echo "✅ Column draw_schedule added\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Column draw_schedule already exists\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Also add open_time if it doesn't exist
try {
    $pdo->exec("ALTER TABLE lottery_types ADD COLUMN open_time TIME DEFAULT '06:00:00' AFTER close_time");
    echo "✅ Column open_time added\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Column open_time already exists\n";
    }
}

echo "\n📋 Setting draw schedules...\n\n";

// =============================================
// Schedule rules (ORDER MATTERS - most specific first)
// =============================================
$WD = 'mon,tue,wed,thu,fri'; // weekdays shorthand
$scheduleRules = [
    // === หวยไทย (1,16 หรือ เฉพาะ 16) ===
    ['keywords' => ['ธกส', 'ธ.ก.ส'], 'schedule' => '16'],          // ธกส ออกเฉพาะวันที่ 16!
    ['keywords' => ['รัฐบาล'], 'schedule' => '1,16'],
    ['keywords' => ['ออมสิน'], 'schedule' => '16'],              // ออมสิน 1 ปี ออกเฉพาะวันที่ 16
    ['keywords' => ['หวยไทยชุด'], 'schedule' => '1,16'],
    ['keywords' => ['หวยไทย JACKPOT'], 'schedule' => '1,16'],
    ['keywords' => ['12 ราศี'], 'schedule' => '1,16'],
    
    // === ลาวพัฒนา + ชุดลาว (จ,พ,ศ) ===
    ['keywords' => ['ลาวพัฒนา'], 'schedule' => 'mon,wed,fri'],
    ['keywords' => ['หวยลาวชุด'], 'schedule' => 'mon,wed,fri'],
    
    // === มาเลเซีย (พ,ส,อา) ===
    ['keywords' => ['มาเลย์', 'มาเลเซีย'], 'schedule' => 'wed,sat,sun'],
    
    // === หุ้น VIP (ตาม stock exchange จ-ศ) ===
    ['keywords' => ['นิเคอิ'], 'schedule' => $WD],
    ['keywords' => ['ฮั่งเส็ง'], 'schedule' => $WD],
    ['keywords' => ['จีนเช้า', 'จีนบ่าย', 'หุ้นจีน'], 'schedule' => $WD],
    ['keywords' => ['เกาหลี'], 'schedule' => $WD],
    ['keywords' => ['ไต้หวัน'], 'schedule' => $WD],
    ['keywords' => ['สิงคโปร์'], 'schedule' => $WD],
    ['keywords' => ['อินเดีย'], 'schedule' => $WD],
    ['keywords' => ['อียิปต์'], 'schedule' => $WD],
    ['keywords' => ['หุ้นไทย'], 'schedule' => $WD],
    
    // === หุ้น + ดาวโจนส์ต่างประเทศ (จ-ศ) ===
    ['keywords' => ['หุ้นดาวโจนส์'], 'schedule' => $WD],
    ['keywords' => ['หุ้นอังกฤษ'], 'schedule' => $WD],
    ['keywords' => ['หุ้นเยอรมัน'], 'schedule' => $WD],
    ['keywords' => ['หุ้นรัสเซีย'], 'schedule' => $WD],
    ['keywords' => ['ดาวโจนส์ STAR'], 'schedule' => $WD],
    ['keywords' => ['ดาวโจนส์อเมริกา'], 'schedule' => $WD],
    ['keywords' => ['ดาวโจนส์ VIP'], 'schedule' => $WD],
    ['keywords' => ['อังกฤษ VIP'], 'schedule' => $WD],
    ['keywords' => ['เยอรมัน VIP'], 'schedule' => $WD],
    ['keywords' => ['รัสเซีย VIP'], 'schedule' => $WD],
    
    // === ทุกอย่างที่ไม่จับ = daily (ฮานอย, ลาวรายวัน, VIP, One, ฯลฯ) ===
];

$allLotteries = $pdo->query("SELECT id, name FROM lottery_types ORDER BY id")->fetchAll();
$updated = 0;
$daily = 0;

foreach ($allLotteries as $lt) {
    $matched = false;
    foreach ($scheduleRules as $rule) {
        foreach ($rule['keywords'] as $kw) {
            if (mb_strpos($lt['name'], $kw) !== false) {
                $pdo->prepare("UPDATE lottery_types SET draw_schedule = ? WHERE id = ?")->execute([$rule['schedule'], $lt['id']]);
                echo "  ✅ [{$lt['id']}] {$lt['name']} → {$rule['schedule']}\n";
                $updated++;
                $matched = true;
                break 2;
            }
        }
    }
    if (!$matched) {
        $pdo->prepare("UPDATE lottery_types SET draw_schedule = 'daily' WHERE id = ?")->execute([$lt['id']]);
        echo "  📅 [{$lt['id']}] {$lt['name']} → daily\n";
        $daily++;
    }
}

echo "\n═══════════════════════════════════════\n";
echo "✅ Complete!\n";
echo "   Schedules set: $updated | Daily: $daily\n";
echo "═══════════════════════════════════════\n";
echo "\n📌 ตารางสรุป:\n";
echo "   หวยไทย (รัฐบาล/ออมสิน) = วันที่ 1,16\n";
echo "   ธกส = เฉพาะวันที่ 16\n";
echo "   ลาวพัฒนา = จ,พ,ศ\n";
echo "   หุ้นทั้งหมด = จ-ศ\n";
echo "   มาเลเซีย = พ,ส,อา\n";
echo "   ลาว/ฮานอยรายวัน = ทุกวัน\n";
