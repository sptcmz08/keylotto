<?php
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'imzshop97' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { font-family: 'Prompt', sans-serif; }
        body { background-color: #f1f8f3; }
        .nav-block {
            background-color: #67cf8a; 
            color: white;
            transition: all 0.2s;
            text-align: center;
        }
        .nav-block:hover {
            background-color: #5bbd7c;
        }
        .nav-block.active {
            background-color: #1aa34a;
        }
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        .card-outline {
            border: 1px solid #1aa34a;
            background-color: white;
            border-radius: 4px;
            overflow: hidden;
        }
    </style>
</head>
<body class="min-h-screen">
    
    <!-- Top Navigation (Desktop) -->
    <div class="max-w-6xl mx-auto px-2 pt-4 hidden md:block">
        <div class="flex gap-1">
            <?php
            $navItems = [
                ['href' => 'index.php', 'icon' => 'fa-home', 'label' => 'หน้าหลัก', 'key' => 'home'],
                ['href' => 'bet.php', 'icon' => 'fa-trophy', 'label' => 'แทงหวย', 'key' => 'bet'],
                ['href' => 'bills.php', 'icon' => 'fa-list', 'label' => 'รายการโพย', 'key' => 'bills'],

                ['href' => 'results.php', 'icon' => 'fa-star', 'label' => 'ตรวจผล', 'key' => 'results'],
                ['href' => 'result_links.php', 'icon' => 'fa-link', 'label' => 'ลิงค์ดูผล', 'key' => 'links'],
                ['href' => 'logout.php', 'icon' => 'fa-sign-out-alt', 'label' => 'ออกระบบ', 'key' => 'logout'],
            ];
            foreach ($navItems as $item):
                $isActive = $currentPage === $item['key'];
            ?>
            <a href="<?= $item['href'] ?>" class="nav-block flex-1 py-3 rounded-t-lg <?= $isActive ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas <?= $item['icon'] ?>"></i></div>
                <div class="text-sm font-medium"><?= $item['label'] ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mobile Header -->
    <div class="md:hidden bg-[#1aa34a] text-white p-3 flex items-center justify-between shadow-md">
        <div class="font-bold text-lg"><i class="fas fa-clover mr-2"></i>imzshop97</div>
        <div class="flex items-center space-x-3 text-sm">
            <span><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?> <i class="fas fa-user-circle"></i></span>
            <a href="logout.php" title="ออกจากระบบ" class="bg-red-500 bg-opacity-80 px-2 py-1 rounded hover:bg-red-600 transition"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>

    <!-- Mobile Bottom Nav -->
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 flex shadow-[0_-4px_20px_rgba(0,0,0,0.1)]">
        <?php 
        $mobileNav = [
            ['href' => 'index.php', 'icon' => 'fa-home', 'label' => 'หน้าหลัก', 'key' => 'home'],
            ['href' => 'bet.php', 'icon' => 'fa-trophy', 'label' => 'แทงหวย', 'key' => 'bet'],
            ['href' => 'bills.php', 'icon' => 'fa-list', 'label' => 'โพยหวย', 'key' => 'bills'],
            ['href' => 'results.php', 'icon' => 'fa-star', 'label' => 'ตรวจผล', 'key' => 'results'],
            ['href' => 'result_links.php', 'icon' => 'fa-link', 'label' => 'ลิงค์ดูผล', 'key' => 'links'],
        ];
        foreach ($mobileNav as $item):
            $isActive = $currentPage === $item['key'];
        ?>
        <a href="<?= $item['href'] ?>" class="flex-1 flex flex-col items-center py-2 text-xs font-medium <?= $isActive ? 'text-[#1aa34a]' : 'text-gray-400' ?>">
            <i class="fas <?= $item['icon'] ?> text-lg mb-0.5"></i>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-2 py-4 pb-20 md:pb-8">
