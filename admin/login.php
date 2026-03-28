<?php
require_once __DIR__ . '/../auth.php';

if (isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin') {
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
        if ($user['role'] !== 'admin') {
            $error = 'ไม่มีสิทธิ์เข้าถึงส่วนผู้ดูแลระบบ';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            header('Location: index.php');
            exit;
        }
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
    <title>Admin Login - imzshop97</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>* { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 50%, #15803d 100%);">
    <div class="w-full max-w-md mx-4">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-8 py-8 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-4 backdrop-blur">
                    <i class="fas fa-clover text-white text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-white">imzshop97</h1>
            </div>

            <!-- Form -->
            <div class="px-8 py-6">
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg text-sm mb-4 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 block mb-1">ชื่อผู้ใช้</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" required autofocus
                                   class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                                   placeholder="ชื่อผู้ใช้" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 block mb-1">รหัสผ่าน</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="password" required
                                   class="w-full pl-10 pr-10 py-3 border-2 border-gray-200 rounded-xl text-sm focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition"
                                   placeholder="รหัสผ่าน">
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3 rounded-xl font-bold text-sm hover:from-green-600 hover:to-emerald-700 transition transform hover:scale-[1.02] active:scale-[0.98] shadow-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>เข้าสู่ระบบ
                    </button>
                </form>

                <div class="text-center mt-6">
                    <a href="../index.php" class="text-sm text-gray-400 hover:text-green-600 transition">
                        <i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก
                    </a>
                </div>
            </div>
        </div>
        <p class="text-center text-white/50 text-xs mt-6">&copy; <?= date('Y') ?> imzshop97 Admin System</p>
    </div>

    <script>
    function togglePassword() {
        const p = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (p.type === 'password') { p.type = 'text'; icon.className = 'fas fa-eye-slash'; }
        else { p.type = 'password'; icon.className = 'fas fa-eye'; }
    }
    </script>
</body>
</html>
