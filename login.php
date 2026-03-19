<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        // Admins can be redirected to admin panel or stay on front? Let's keep them on public index for consistency.
        header('Location: index.php');
        exit;
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - คีย์หวย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>* { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center bg-[#f4f7ec]">
    <div class="w-full max-w-md mx-4">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-[#d0e6d3]">
            <!-- Header -->
            <div class="bg-[#1b5e20] px-8 py-8 text-center text-white relative">
                <div class="absolute -bottom-4 left-1/2 -translate-x-1/2 w-16 h-16 bg-white rounded-full flex items-center justify-center border-4 border-[#1b5e20]">
                    <i class="fas fa-clover text-[#1b5e20] text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold tracking-tight">คีย์หวย</h1>
                <p class="text-white/80 text-sm mt-1">เข้าสู่ระบบเพื่อใช้งาน</p>
            </div>

            <!-- Form -->
            <div class="px-8 pt-10 pb-8">
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm mb-4">
                    <i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 block mb-1">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" required autofocus
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:border-[#2e7d32] hover:border-[#81c784] outline-none transition"
                                   placeholder="กรอกชื่อผู้ใช้งาน">
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 block mb-1">รหัสผ่าน (Password)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" required
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:border-[#2e7d32] hover:border-[#81c784] outline-none transition"
                                   placeholder="กรอกรหัสผ่าน">
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-[#ffca28] text-gray-900 py-3 rounded-xl font-bold text-base hover:bg-yellow-500 transition shadow-sm mt-2">
                        เข้าสู่ระบบ
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
