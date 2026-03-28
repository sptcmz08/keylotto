<?php
// =============================================
// Database Configuration
// =============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'lotto');
define('DB_USER', 'lotto');
define('DB_PASS', '43q5r*j8U');
define('DB_CHARSET', 'utf8mb4');

// Site config
define('SITE_NAME', 'คีย์หวย');
define('SITE_URL', '/');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set Timezone
date_default_timezone_set('Asia/Bangkok');

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
function formatThaiDate($date)
{
    if (!$date)
        return '';
    $d = new DateTime($date);
    return $d->format('d-m-') . ($d->format('Y'));
}

function formatDateDisplay($date)
{
    if (!$date)
        return '';
    $d = new DateTime($date);
    return $d->format('d-m-Y');
}

function formatMoney($amount)
{
    return number_format((float) $amount, 2);
}

function getFlagForCountry($emoji, $lotteryName = '')
{
    // Flag emoji → URL mapping
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
        '🇹🇼' => 'https://flagcdn.com/w40/tw.png',
        '🇮🇳' => 'https://flagcdn.com/w40/in.png',
        '🇪🇬' => 'https://flagcdn.com/w40/eg.png',
    ];

    // 1) Try direct emoji match
    if ($emoji && isset($flags[$emoji])) {
        return $flags[$emoji];
    }

    // 2) Fallback: detect country from lottery name
    $name = $lotteryName ?: $emoji;
    $nameMap = [
        'ลาว'       => 'la',
        'ฮานอย'     => 'vn',
        'เวียดนาม'  => 'vn',
        'ดาวโจนส์'  => 'us',
        'นิเคอิ'    => 'jp',
        'จีน'       => 'cn',
        'ฮั่งเส็ง'  => 'hk',
        'ไต้หวัน'   => 'tw',
        'เกาหลี'    => 'kr',
        'สิงคโปร์'  => 'sg',
        'อินเดีย'   => 'in',
        'อียิปต์'   => 'eg',
        'อังกฤษ'    => 'gb',
        'เยอรมัน'   => 'de',
        'รัสเซีย'   => 'ru',
        'มาเลย์'    => 'my',
        'ไทย'       => 'th',
        'รัฐบาล'    => 'th',
        'ออมสิน'    => 'th',
        'ธกส'       => 'th',
        'ราศี'      => 'th',
        'หุ้นไทย'   => 'th',
    ];

    foreach ($nameMap as $keyword => $code) {
        if (mb_strpos($name, $keyword) !== false) {
            return "https://flagcdn.com/w40/{$code}.png";
        }
    }

    // 3) Default
    return 'https://flagcdn.com/w40/xx.png';
}

function getBetTypeLabel($type)
{
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

// =============================================
// CSRF Protection
// =============================================
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

function validateCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// =============================================
// Draw Schedule — คำนวณวันที่งวดปัจจุบัน
// =============================================
/**
 * แปลง draw_schedule จาก format ใหม่เป็น format มาตรฐาน
 * 1st_16th → 1,16 | 16th → 16 | mon_wed_fri → mon,wed,fri | weekday → mon,tue,wed,thu,fri
 */
function normalizeSchedule($schedule) {
    if (empty($schedule) || $schedule === 'daily') return $schedule;
    
    // weekday → mon-fri
    if ($schedule === 'weekday') return 'mon,tue,wed,thu,fri';
    
    // sun_thu → sun-thu (Egypt stock)
    if ($schedule === 'sun_thu') return 'sun,mon,tue,wed,thu';
    
    // 1st_16th → 1,16 | 16th → 16
    if (preg_match('/\d+(st|nd|rd|th)/', $schedule)) {
        $parts = preg_split('/[_,]/', $schedule);
        $days = [];
        foreach ($parts as $p) {
            $d = intval(preg_replace('/[^0-9]/', '', $p));
            if ($d > 0) $days[] = $d;
        }
        return implode(',', $days);
    }
    
    // mon_wed_fri → mon,wed,fri (underscore → comma)
    if (preg_match('/^[a-z]{3}(_[a-z]{3})*$/i', $schedule)) {
        return str_replace('_', ',', strtolower($schedule));
    }
    
    return $schedule;
}

/**
 * คำนวณวันที่งวดปัจจุบันหรือล่าสุด ตาม draw_schedule
 */
function getCurrentDrawDate($schedule, $refDate = null) {
    $schedule = normalizeSchedule($schedule);
    $ref = $refDate ? strtotime($refDate) : time();
    $refYmd = date('Y-m-d', $ref);
    
    // daily = ทุกวัน
    if (empty($schedule) || $schedule === 'daily') {
        return $refYmd;
    }
    
    // Monthly format: "1,16" = วันที่ของเดือน
    if (preg_match('/^\d+(,\d+)*$/', $schedule)) {
        $days = array_map('intval', explode(',', $schedule));
        sort($days);
        $currentDay = intval(date('d', $ref));
        $currentMonth = intval(date('m', $ref));
        $currentYear = intval(date('Y', $ref));
        
        // หาวัน draw ล่าสุด (รวมวันนี้)
        foreach (array_reverse($days) as $d) {
            if ($d <= $currentDay) {
                // ตรวจสอบว่าวันนั้นมีจริงในเดือนนี้
                $daysInMonth = intval(date('t', $ref));
                if ($d <= $daysInMonth) {
                    return sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $d);
                }
            }
        }
        
        // ยังไม่ถึงวัน draw แรกของเดือน → ใช้วัน draw สุดท้ายของเดือนก่อน
        $prevMonth = $currentMonth - 1;
        $prevYear = $currentYear;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $lastDay = end($days);
        $daysInPrevMonth = intval(date('t', mktime(0, 0, 0, $prevMonth, 1, $prevYear)));
        if ($lastDay > $daysInPrevMonth) $lastDay = $daysInPrevMonth;
        return sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, $lastDay);
    }
    
    // Weekday format: "mon,wed,fri" = วันในสัปดาห์
    $dayMap = ['mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6,'sun'=>7];
    $scheduleDays = array_map('trim', explode(',', strtolower($schedule)));
    $allowedDays = [];
    foreach ($scheduleDays as $sd) {
        if (isset($dayMap[$sd])) $allowedDays[] = $dayMap[$sd];
    }
    
    if (empty($allowedDays)) return $refYmd;
    sort($allowedDays);
    
    // ตรวจสอบวันนี้ก่อน แล้วย้อนหลังไปจนเจอวัน draw
    for ($i = 0; $i <= 7; $i++) {
        $checkTs = strtotime("-{$i} days", $ref);
        $checkDow = intval(date('N', $checkTs)); // 1=Mon, 7=Sun
        if (in_array($checkDow, $allowedDays)) {
            return date('Y-m-d', $checkTs);
        }
    }
    
    return $refYmd;
}

/**
 * คำนวณวันที่งวดถัดไป
 */
function getNextDrawDate($schedule, $refDate = null) {
    $schedule = normalizeSchedule($schedule);
    $ref = $refDate ? strtotime($refDate) : time();
    
    if (empty($schedule) || $schedule === 'daily') {
        return date('Y-m-d', strtotime('+1 day', $ref));
    }
    
    // Monthly
    if (preg_match('/^\d+(,\d+)*$/', $schedule)) {
        $days = array_map('intval', explode(',', $schedule));
        sort($days);
        $currentDay = intval(date('d', $ref));
        $currentMonth = intval(date('m', $ref));
        $currentYear = intval(date('Y', $ref));
        
        foreach ($days as $d) {
            if ($d > $currentDay) {
                return sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $d);
            }
        }
        // ถัดไปคือวันแรกของเดือนหน้า
        $nextMonth = $currentMonth + 1;
        $nextYear = $currentYear;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        return sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, $days[0]);
    }
    
    // Weekday
    $dayMap = ['mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6,'sun'=>7];
    $scheduleDays = array_map('trim', explode(',', strtolower($schedule)));
    $allowedDays = [];
    foreach ($scheduleDays as $sd) {
        if (isset($dayMap[$sd])) $allowedDays[] = $dayMap[$sd];
    }
    if (empty($allowedDays)) return date('Y-m-d', strtotime('+1 day', $ref));
    
    for ($i = 1; $i <= 7; $i++) {
        $checkTs = strtotime("+{$i} days", $ref);
        if (in_array(intval(date('N', $checkTs)), $allowedDays)) {
            return date('Y-m-d', $checkTs);
        }
    }
    
    return date('Y-m-d', strtotime('+1 day', $ref));
}
