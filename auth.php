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

