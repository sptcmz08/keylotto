<?php
require __DIR__ . '/../config.php';

global $pdo;

echo "Syncing all lotteries to result_links...\n";

$lotteries = $pdo->query("SELECT * FROM lottery_types")->fetchAll();

$syncStmt = $pdo->prepare("
    INSERT INTO result_links 
    (category_id, name, flag_emoji, close_time, result_time, result_url, result_label, sort_order, is_active) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
    category_id=VALUES(category_id), 
    flag_emoji=VALUES(flag_emoji),
    close_time=VALUES(close_time), 
    result_time=VALUES(result_time), 
    result_url=VALUES(result_url), 
    result_label=VALUES(result_label), 
    sort_order=VALUES(sort_order), 
    is_active=VALUES(is_active)
");

$count = 0;
foreach ($lotteries as $lt) {
    if (empty(trim($lt['name']))) continue; // ข้ามตัวที่ชื่อดึงมาเปล่าๆ
    
    $syncStmt->execute([
        $lt['category_id'], 
        $lt['name'], 
        $lt['flag_emoji'], 
        $lt['close_time'], 
        $lt['result_time'], 
        $lt['result_url'], 
        $lt['result_label'], 
        $lt['sort_order'], 
        $lt['is_active']
    ]);
    $count++;
}

echo "Successfully synced {$count} lotteries to result_links!\n";
