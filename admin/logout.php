<?php
require_once __DIR__ . '/../config.php';

// ล้าง session ทั้งหมด
session_unset();
session_destroy();

// Redirect ไปหน้า Login หลัก (ใช้ร่วมกัน)
header('Location: ../login.php');
exit;
