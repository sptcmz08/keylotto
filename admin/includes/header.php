<?php
$adminPage = $adminPage ?? '';
$adminTitle = $adminTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $adminTitle ?> - คีย์หวย Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { font-family: 'Prompt', sans-serif; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #b0bec5; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #90a4ae; }
        
        .sidebar { background-color: #e8f5e9; } /* Very light green */
        .sidebar-menu-btn { 
            background-color: #2e7d32; 
            color: white; 
            border-bottom: 1px solid #1b5e20;
        }
        .sidebar-menu-btn:hover { background-color: #1b5e20; }
        .sidebar-submenu { background-color: #dcedc8; }
        .sidebar-submenu a { 
            display: block; 
            padding: 10px 15px 10px 40px; 
            color: #333; 
            font-size: 13px;
            border-bottom: 1px solid #c5e1a5;
        }
        .sidebar-submenu a:hover { background-color: #c5e1a5; }
        .sidebar-submenu a.active { background-color: #c5e1a5; font-weight: bold; }
        
        .topbar { background-color: #00a65a; }
    </style>
</head>
<body class="bg-white min-h-screen text-[13px] text-gray-800 flex overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 sidebar shadow-md flex-shrink-0 flex flex-col h-screen relative z-20">
        <!-- Timestamp -->
        <div class="px-4 py-3 text-[#d32f2f] text-xs font-medium border-b border-[#c5e1a5]">
            <span id="realtimeClock"><?= date('d') ?> มีนาคม <?= date('Y')+543 ?>, <?= date('H:i:s') ?></span>
        </div>
        
        <!-- Profile Box -->
        <div class="p-4 border-b border-[#c5e1a5] flex flex-col">
            <div class="flex justify-center mb-3">
                <div class="w-16 h-16 rounded-full bg-gray-300 flex items-center justify-center border-2 border-white shadow-sm overflow-hidden">
                    <i class="fas fa-user text-gray-500 text-3xl mt-2"></i>
                </div>
            </div>
            <div class="space-y-1 text-xs">
                <?php
                    $memberCount = 0;
                    try { $memberCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(); } catch (Exception $e) {}
                ?>
                <div class="flex justify-between"><span>สมาชิก</span><span class="font-bold"><?= $memberCount ?></span></div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto">
            <!-- Normal Links -->
            <a href="index.php" class="sidebar-menu-btn flex items-center px-4 py-3 text-sm font-medium">
                <i class="fas fa-desktop w-6 text-center"></i> หน้าหลัก
            </a>
            
            <!-- Expanded Menu (Reports) -->
            <div>
                <button onclick="toggleMenu('reportMenu')" class="sidebar-menu-btn w-full flex items-center justify-between px-4 py-3 text-sm font-medium focus:outline-none <?= in_array($adminPage, ['win_lose', 'bets', 'bills']) ? 'bg-[#4caf50]' : '' ?>">
                    <div class="flex items-center"><i class="fas fa-file-invoice w-6 text-center"></i> รายงานการแทง</div>
                    <i class="fas fa-chevron-down text-xs transition-transform" id="reportMenuIcon"></i>
                </button>
                <div id="reportMenu" class="sidebar-submenu <?= in_array($adminPage, ['win_lose', 'bets', 'bills']) ? 'block' : 'hidden' ?>">
                    <a href="win_lose.php" class="flex items-center <?= $adminPage === 'win_lose' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> ดูของรวม/คาดคะเน ได้-เสีย</a>
                    <a href="bets.php" class="flex items-center <?= $adminPage === 'bets' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> รายการโพย/ยกเลิกโพย</a>
                    <a href="blocked_numbers.php" class="flex items-center <?= $adminPage === 'blocked' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> เลขปิดรับ/เลขอั้น</a>
                </div>
            </div>

            <div>
                <button onclick="toggleMenu('settingMenu')" class="sidebar-menu-btn w-full flex items-center justify-between px-4 py-3 text-sm font-medium focus:outline-none">
                    <div class="flex items-center"><i class="fas fa-cogs w-6 text-center"></i> ตั้งค่างวดหวย</div>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                <div id="settingMenu" class="sidebar-submenu <?= in_array($adminPage, ['lottery', 'rates', 'results', 'links', 'rate_settings']) ? 'block' : 'hidden' ?>">
                    <a href="lottery_types.php" class="<?= $adminPage === 'lottery' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> จัดการหวย</a>
                    <a href="pay_rates.php" class="<?= $adminPage === 'rates' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> อัตราจ่าย</a>
                    <a href="rate_settings.php" class="<?= $adminPage === 'rate_settings' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> ตั้งค่าอัตราเกิน</a>
                    <a href="results_manage.php" class="<?= $adminPage === 'results' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> ผลรางวัล</a>
                    <a href="result_links.php" class="<?= $adminPage === 'links' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> ลิงค์ดูผล</a>
                </div>
            </div>
            <div>
                <button onclick="toggleMenu('memberMenu')" class="sidebar-menu-btn w-full flex items-center justify-between px-4 py-3 text-sm font-medium focus:outline-none <?= $adminPage === 'users' ? 'bg-[#4caf50]' : '' ?>">
                    <div class="flex items-center"><i class="fas fa-users w-6 text-center"></i> จัดการสมาชิก</div>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                <div id="memberMenu" class="sidebar-submenu <?= $adminPage === 'users' ? 'block' : 'hidden' ?>">
                    <a href="users.php" class="<?= $adminPage === 'users' ? 'active' : '' ?>"><i class="fas fa-chevron-right text-[10px] w-5 text-gray-500"></i> รายชื่อสมาชิก</a>
                </div>
            </div>

        </nav>
        
        <script>
            function toggleMenu(id) {
                const el = document.getElementById(id);
                if(el.classList.contains('hidden')) el.classList.remove('hidden');
                else el.classList.add('hidden');
            }
            setInterval(() => {
                const d = new Date();
                const months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                document.getElementById('realtimeClock').innerText = 
                    d.getDate() + ' ' + months[d.getMonth()+1] + ' ' + (d.getFullYear() + 543) + ', ' + 
                    String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')+':'+String(d.getSeconds()).padStart(2,'0');
            }, 1000);
        </script>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden bg-white">
        <!-- Topbar -->
        <header class="topbar h-12 text-white flex items-center justify-between px-4 flex-shrink-0 z-10 shadow-sm">
            <div class="flex items-center space-x-3 text-sm">
                <span class="font-bold flex items-center"><i class="fas fa-shield-alt mr-2"></i> สถานะพิเศษ</span>
                <span class="flex items-center"><i class="fas fa-circle text-green-300 text-[8px] mr-1"></i> งวดวันที่ <?= date('d-m-Y') ?></span>
            </div>
            <div class="flex items-center space-x-4">
                <?php
                    $topMemberCount = 0;
                    try { $topMemberCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(); } catch (Exception $e) {}
                ?>
                <div class="bg-white/90 text-green-800 px-3 py-0.5 rounded-full text-xs font-bold shadow-inner">
                    <i class="fas fa-users text-green-500 mr-1"></i> สมาชิก <?= $topMemberCount ?>
                </div>
                <a href="?logout=1" class="flex items-center cursor-pointer hover:opacity-80 transition">
                    <div class="w-8 h-8 rounded-full bg-green-700 flex items-center justify-center mr-2 border border-green-500">
                        <i class="fas fa-user text-white text-xs"></i>
                    </div>
                    <span class="text-sm font-medium text-white/90">Admin <i class="fas fa-caret-down text-[10px] ml-1"></i></span>
                </a>
            </div>
        </header>

        <!-- Page Content Context -->
        <main class="flex-1 overflow-y-auto w-full bg-[#f4f6f9]">
            <div class="p-3">
