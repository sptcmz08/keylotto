<?php
require __DIR__ . '/../config.php';
global $pdo;
$pdo->exec('DELETE rl FROM result_links rl LEFT JOIN lottery_types lt ON rl.name = lt.name WHERE lt.id IS NULL');
echo "Clear orphaned links done!";
