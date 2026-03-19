<?php
// =============================================
// Database Configuration
// =============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'key_lotto');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Site config
define('SITE_NAME', 'คีย์หวย');
define('SITE_URL', '/');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PDO Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper functions
function formatThaiDate($date) {
    if (!$date) return '';
    $d = new DateTime($date);
    return $d->format('d-m-') . ($d->format('Y'));
}

function formatDateDisplay($date) {
    if (!$date) return '';
    $d = new DateTime($date);
    return $d->format('d-m-Y');
}

function formatMoney($amount) {
    return number_format((float)$amount, 2);
}

function getFlagForCountry($emoji) {
    $flags = [
        '🇹🇭' => 'https://flagcdn.com/w40/th.png',
        '🇻🇳' => 'https://flagcdn.com/w40/vn.png',
        '🇱🇦' => 'https://flagcdn.com/w40/la.png',
        '🇺🇸' => 'https://flagcdn.com/w40/us.png',
        '🇩🇪' => 'https://flagcdn.com/w40/de.png',
        '🇷🇺' => 'https://flagcdn.com/w40/ru.png',
        '🇬🇧' => 'https://flagcdn.com/w40/gb.png',
        '🇭🇰' => 'https://flagcdn.com/w40/hk.png',
        '🇯🇵' => 'https://flagcdn.com/w40/jp.png',
        '🇰🇷' => 'https://flagcdn.com/w40/kr.png',
        '🇨🇳' => 'https://flagcdn.com/w40/cn.png',
        '🇲🇾' => 'https://flagcdn.com/w40/my.png',
        '🇸🇬' => 'https://flagcdn.com/w40/sg.png',
        '🏳️' => 'https://flagcdn.com/w40/xx.png',
    ];
    return $flags[$emoji] ?? 'https://flagcdn.com/w40/xx.png';
}

function getBetTypeLabel($type) {
    $labels = [
        '3top' => '3 ตัวบน',
        '3tod' => '3 ตัวโต๊ด',
        '2top' => '2 ตัวบน',
        '2bot' => '2 ตัวล่าง',
        'run_top' => 'วิ่งบน',
        'run_bot' => 'วิ่งล่าง',
    ];
    return $labels[$type] ?? $type;
}
