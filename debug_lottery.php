<?php
/**
 * Debug: ทำไม ลาวพัฒนา ไม่แสดงบนหน้าหลัก
 * รัน: php debug_lottery.php
 */
require_once 'config.php';

echo "=== Debug ลาวพัฒนา ===\n\n";

// 1. Check DB
$stmt = $pdo->query("
    SELECT lt.id, lt.name, lt.is_active, lt.draw_schedule, lt.category_id, lt.close_time,
           r.three_top, r.two_bot, r.draw_date as result_date, r.created_at as result_created_at
    FROM lottery_types lt
    LEFT JOIN results r ON r.lottery_type_id = lt.id 
        AND r.draw_date = (SELECT MAX(r2.draw_date) FROM results r2 WHERE r2.lottery_type_id = lt.id)
    WHERE lt.name LIKE '%ลาวพัฒนา%'
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "1️⃣ DB Query Results: " . count($rows) . " row(s)\n";
foreach ($rows as $row) {
    echo "   ID: {$row['id']}\n";
    echo "   Name: '{$row['name']}'\n";
    echo "   Name hex: " . bin2hex($row['name']) . "\n";
    echo "   is_active: {$row['is_active']}\n";
    echo "   draw_schedule: {$row['draw_schedule']}\n";
    echo "   category_id: {$row['category_id']}\n";
    echo "   close_time: {$row['close_time']}\n";
    echo "   result_date: " . ($row['result_date'] ?? 'NULL') . "\n";
    echo "   three_top: " . ($row['three_top'] ?? 'NULL') . "\n";
    echo "   result_created_at: " . ($row['result_created_at'] ?? 'NULL') . "\n";
}

// 2. Check getCurrentDrawDate
$schedule = 'mon,wed,fri';
$today = date('Y-m-d');
$currentRound = getCurrentDrawDate($schedule);
echo "\n2️⃣ getCurrentDrawDate('mon,wed,fri') = {$currentRound}\n";
echo "   Today = {$today}\n";
echo "   Day of week = " . date('l') . " (N=" . date('N') . ")\n";

// 3. Check name matching
$codeNames = ['ลาวพัฒนา'];
echo "\n3️⃣ Name Matching:\n";
foreach ($rows as $row) {
    foreach ($codeNames as $cn) {
        $match = ($row['name'] === $cn) ? '✅ MATCH' : '❌ NO MATCH';
        echo "   DB '{$row['name']}' vs Code '{$cn}' → {$match}\n";
        if ($row['name'] !== $cn) {
            echo "   DB hex:   " . bin2hex($row['name']) . "\n";
            echo "   Code hex: " . bin2hex($cn) . "\n";
        }
    }
}

// 4. Check full lottery map
$allStmt = $pdo->query("
    SELECT lt.id, lt.name, lt.is_active
    FROM lottery_types lt
    WHERE lt.is_active = 1
    ORDER BY lt.name
");
$allActive = $allStmt->fetchAll(PDO::FETCH_ASSOC);
echo "\n4️⃣ All active lotteries (" . count($allActive) . " total):\n";
$found = false;
foreach ($allActive as $a) {
    if (strpos($a['name'], 'ลาว') !== false || strpos($a['name'], 'พัฒนา') !== false) {
        echo "   #{$a['id']} '{$a['name']}'\n";
        if ($a['name'] === 'ลาวพัฒนา') $found = true;
    }
}
echo $found ? "   ✅ 'ลาวพัฒนา' found in active list\n" : "   ❌ 'ลาวพัฒนา' NOT found in active list\n";

// 5. Check duplicate results
$dupStmt = $pdo->query("
    SELECT lottery_type_id, draw_date, COUNT(*) as cnt
    FROM results
    WHERE lottery_type_id = (SELECT id FROM lottery_types WHERE name = 'ลาวพัฒนา' LIMIT 1)
    GROUP BY lottery_type_id, draw_date
    HAVING cnt > 1
    LIMIT 5
");
$dups = $dupStmt->fetchAll(PDO::FETCH_ASSOC);
echo "\n5️⃣ Duplicate results: " . count($dups) . "\n";
foreach ($dups as $d) {
    echo "   draw_date={$d['draw_date']} count={$d['cnt']}\n";
}

echo "\n=== Done ===\n";
