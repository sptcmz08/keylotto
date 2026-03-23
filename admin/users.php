<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }

$adminPage = 'users';
$adminTitle = 'จัดการสมาชิก';
$msg = '';
$msgType = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    
    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $name = trim($_POST['name'] ?? '');
        
        if (empty($username) || empty($password)) {
            $msg = 'กรุณากรอก Username และ Password';
            $msgType = 'error';
        } else {
            // Check duplicate
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $msg = 'Username นี้มีอยู่แล้ว';
                $msgType = 'error';
            } else {
                $hashedPw = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, 'user')")->execute([$username, $hashedPw, $name]);
                $msg = 'เพิ่มสมาชิก "' . $username . '" สำเร็จ';
            }
        }
    } elseif ($action === 'edit_user') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!empty($password)) {
            $hashedPw = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET name = ?, password = ? WHERE id = ? AND role = 'user'")->execute([$name, $hashedPw, $id]);
        } else {
            $pdo->prepare("UPDATE users SET name = ? WHERE id = ? AND role = 'user'")->execute([$name, $id]);
        }
        $msg = 'แก้ไขข้อมูลสำเร็จ';
    } elseif ($action === 'toggle_active') {
        $id = intval($_POST['id']);
        try {
            $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'user'")->execute([$id]);
            $msg = 'เปลี่ยนสถานะสำเร็จ';
        } catch (Exception $e) {
            $pdo->prepare("UPDATE users SET role = role WHERE id = ?")->execute([$id]);
            $msg = 'กรุณารัน migration add_user_columns.sql ก่อน';
            $msgType = 'error';
        }
    } elseif ($action === 'delete_user') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'")->execute([$id]);
        $msg = 'ลบสมาชิกสำเร็จ';
    }
}

// Fetch users
$users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC")->fetchAll();
$totalUsers = count($users);

require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="mb-4 p-3 rounded-lg text-sm <?= $msgType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <i class="fas fa-<?= $msgType === 'error' ? 'exclamation-circle' : 'check-circle' ?> mr-1"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Header + Add Button -->
<div class="flex justify-between items-center mb-4">
    <div>
        <h1 class="text-lg font-bold text-gray-800"><i class="fas fa-users text-green-600 mr-2"></i>จัดการสมาชิก</h1>
        <p class="text-xs text-gray-400">สมาชิกทั้งหมด <?= $totalUsers ?> คน</p>
    </div>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
        <i class="fas fa-plus mr-1"></i> เพิ่มสมาชิก
    </button>
</div>

<!-- Users Table -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gradient-to-r from-green-500 to-green-600 text-white">
                <tr>
                    <th class="px-3 py-3 text-center text-xs font-bold w-12">#</th>
                    <th class="px-3 py-3 text-left text-xs font-bold">Username</th>
                    <th class="px-3 py-3 text-left text-xs font-bold">ชื่อ</th>
                    <th class="px-3 py-3 text-center text-xs font-bold">สถานะ</th>
                    <th class="px-3 py-3 text-center text-xs font-bold">วันที่สร้าง</th>
                    <th class="px-3 py-3 text-center text-xs font-bold w-36">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="6" class="px-3 py-8 text-center text-gray-400"><i class="fas fa-inbox text-2xl mb-2 block"></i>ยังไม่มีสมาชิก</td></tr>
                <?php else: foreach ($users as $i => $u): 
                    $isActive = isset($u['is_active']) ? $u['is_active'] : 1;
                ?>
                <tr class="border-b hover:bg-gray-50 <?= !$isActive ? 'opacity-50 bg-red-50' : '' ?>">
                    <td class="px-3 py-2 text-center text-xs text-gray-400"><?= $i + 1 ?></td>
                    <td class="px-3 py-2 font-bold"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars($u['name'] ?? '-') ?></td>
                    <td class="px-3 py-2 text-center">
                        <?php if ($isActive): ?>
                        <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-[10px] font-bold">ใช้งาน</span>
                        <?php else: ?>
                        <span class="bg-red-100 text-red-600 px-2 py-0.5 rounded-full text-[10px] font-bold">ปิดใช้งาน</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2 text-center text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                    <td class="px-3 py-2 text-center">
                        <div class="flex gap-1 justify-center">
                            <!-- Edit -->
                            <button onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['name'] ?? '') ?>')" class="w-7 h-7 bg-blue-50 hover:bg-blue-100 rounded border border-blue-200 text-blue-500 transition" title="แก้ไข">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <!-- Toggle Active -->
                            <form method="POST" class="inline">
                                <input type="hidden" name="form_action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="w-7 h-7 <?= $isActive ? 'bg-yellow-50 hover:bg-yellow-100 border-yellow-200 text-yellow-600' : 'bg-green-50 hover:bg-green-100 border-green-200 text-green-600' ?> rounded border transition" title="<?= $isActive ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>">
                                    <i class="fas fa-<?= $isActive ? 'ban' : 'check' ?> text-xs"></i>
                                </button>
                            </form>
                            <!-- Delete -->
                            <form method="POST" class="inline" onsubmit="return confirm('ลบสมาชิก <?= htmlspecialchars($u['username']) ?> ?')">
                                <input type="hidden" name="form_action" value="delete_user">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="w-7 h-7 bg-red-50 hover:bg-red-100 rounded border border-red-200 text-red-400 transition" title="ลบ">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="addModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
        <div class="p-4 border-b bg-green-50 rounded-t-xl flex justify-between items-center">
            <span class="font-bold text-green-700"><i class="fas fa-user-plus mr-1"></i> เพิ่มสมาชิกใหม่</span>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-4 space-y-3">
            <input type="hidden" name="form_action" value="add_user">
            <div>
                <label class="text-xs text-gray-500 block mb-1">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" required class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:border-green-400" placeholder="ชื่อผู้ใช้">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" required class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:border-green-400" placeholder="รหัสผ่าน">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ชื่อ-นามสกุล</label>
                <input type="text" name="name" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:border-green-400" placeholder="ชื่อ (ไม่จำเป็น)">
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300 transition">ยกเลิก</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium hover:bg-green-600 transition"><i class="fas fa-save mr-1"></i> บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
        <div class="p-4 border-b bg-blue-50 rounded-t-xl flex justify-between items-center">
            <span class="font-bold text-blue-700"><i class="fas fa-edit mr-1"></i> แก้ไขสมาชิก</span>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-4 space-y-3">
            <input type="hidden" name="form_action" value="edit_user">
            <input type="hidden" name="id" id="editUserId">
            <div>
                <label class="text-xs text-gray-500 block mb-1">Username</label>
                <input type="text" id="editUsername" disabled class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">ชื่อ-นามสกุล</label>
                <input type="text" name="name" id="editName" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">รหัสผ่านใหม่ <span class="text-gray-400 text-[10px]">(เว้นว่างถ้าไม่เปลี่ยน)</span></label>
                <input type="password" name="password" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400" placeholder="รหัสผ่านใหม่">
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300 transition">ยกเลิก</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition"><i class="fas fa-save mr-1"></i> บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(id, username, name) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editName').value = name;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>

<?php require_once 'includes/footer.php'; ?>
