<?php
require_once __DIR__ . '/../config.php';

// ล้าง session ทั้งหมด
session_unset();
session_destroy();

// Redirect ไปหน้า Admin Login
header('Location: login.php');
exit;
