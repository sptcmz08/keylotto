<?php
// Auth guard for admin pages
require_once __DIR__ . '/config.php';

// =============================================
// Subdomain detection: agent.xxx / member.xxx
// =============================================
function getSubdomain() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (preg_match('/^(agent|member)\./i', $host, $m)) {
        return strtolower($m[1]);
    }
    return ''; // main domain
}

// =============================================
// Main domain → redirect to member.xxx
// =============================================
$_currentSubdomain = getSubdomain();
if ($_currentSubdomain === '') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // ไม่ redirect สำหรับ api.php, cron, scripts
    $skipRedirect = (strpos($script, 'api.php') !== false || strpos($script, 'cron') !== false);
    if (!$skipRedirect && !empty($host)) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: {$proto}://member.{$host}{$uri}", true, 302);
        exit;
    }
}

function requireLogin() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $isAdminDir = strpos($script, '/admin/') !== false;
    $subdomain = getSubdomain();

    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        // agent subdomain → admin login
        if ($subdomain === 'agent' && !$isAdminDir) {
            header('Location: admin/login.php');
            exit;
        }
        $loginUrl = $isAdminDir ? '../login.php' : 'login.php';
        header("Location: $loginUrl");
        exit;
    }

    // Restrict admin area to admins only
    if ($isAdminDir && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
        header('Location: ../index.php');
        exit;
    }

    // member subdomain → block admin pages
    if ($subdomain === 'member' && $isAdminDir) {
        header('Location: ../index.php');
        exit;
    }
}

function getCurrentUser() {
    return $_SESSION['username'] ?? 'user';
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

