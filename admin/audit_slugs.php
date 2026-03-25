<?php
/**
 * Admin Tool: ตรวจสอบ slug mapping ทั้งหมด
 * เช็คว่า SLUG_TO_LOTTERY_NAME ใน cron_scrape.php ตรงกับ lottery_types ในฐานข้อมูลหรือไม่
 */
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'audit';
$adminTitle = 'ตรวจสอบ Slug Mapping';

// Read SLUG_TO_LOTTERY_NAME from cron_scrape.php
$cronFile = file_get_contents(__DIR__ . '/../cron_scrape.php');
preg_match_all("/['\"]([a-z0-9\-]+)['\"]\s*=>\s*['\"](.+?)['\"]/", $cronFile, $matches, PREG_SET_ORDER);

$slugMap = [];
foreach ($matches as $m) {
    $slugMap[$m[1]] = $m[2];
}

// Get all DB lottery names
$dbTypes = $pdo->query("SELECT id, name, is_active FROM lottery_types ORDER BY id")->fetchAll();
$dbNames = [];
foreach ($dbTypes as $t) {
    $dbNames[$t['name']] = ['id' => $t['id'], 'is_active' => $t['is_active']];
}

// Audit
$issues = [];
$ok = [];
foreach ($slugMap as $slug => $mappedName) {
    if (isset($dbNames[$mappedName])) {
        $ok[] = [
            'slug' => $slug,
            'name' => $mappedName,
            'db_id' => $dbNames[$mappedName]['id'],
            'active' => $dbNames[$mappedName]['is_active'],
        ];
    } else {
        // Try fuzzy
        $found = false;
        foreach ($dbNames as $dbName => $info) {
            $norm1 = preg_replace('/[\s\-]+/', '', mb_strtolower($mappedName));
            $norm2 = preg_replace('/[\s\-]+/', '', mb_strtolower($dbName));
            if ($norm1 === $norm2) {
                $issues[] = [
                    'slug' => $slug,
                    'type' => 'fuzzy',
                    'mapped_name' => $mappedName,
                    'db_name' => $dbName,
                    'db_id' => $info['id'],
                ];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $issues[] = [
                'slug' => $slug,
                'type' => 'missing',
                'mapped_name' => $mappedName,
            ];
        }
    }
}

// Check DB types with no slug mapping
$mappedDbNames = array_values($slugMap);
$unmapped = [];
foreach ($dbTypes as $t) {
    if (!in_array($t['name'], $mappedDbNames) && $t['is_active']) {
        $unmapped[] = $t;
    }
}

require_once 'includes/header.php';
?>

<div class="bg-white rounded-xl shadow-sm border mb-6">
    <div class="px-4 py-3 border-b bg-blue-50">
        <span class="font-bold text-blue-700 text-sm"><i class="fas fa-search mr-1"></i>ตรวจสอบ Slug Mapping (<?= count($ok) ?> ตรง, <?= count($issues) ?> มีปัญหา)</span>
    </div>

    <?php if (!empty($issues)): ?>
    <div class="p-4 bg-red-50 border-b">
        <h3 class="text-sm font-bold text-red-700 mb-2">⚠️ Slug มีปัญหา (<?= count($issues) ?>)</h3>
        <table class="w-full text-xs">
            <thead><tr class="bg-red-100"><th class="px-2 py-1 text-left">Slug</th><th class="px-2 py-1 text-left">ชื่อที่ Map ไว้</th><th class="px-2 py-1 text-left">ปัญหา</th><th class="px-2 py-1 text-left">แนะนำ</th></tr></thead>
            <tbody>
            <?php foreach ($issues as $i): ?>
            <tr class="border-b border-red-200">
                <td class="px-2 py-1 font-mono"><?= htmlspecialchars($i['slug']) ?></td>
                <td class="px-2 py-1"><?= htmlspecialchars($i['mapped_name']) ?></td>
                <td class="px-2 py-1">
                    <?php if ($i['type'] === 'fuzzy'): ?>
                    <span class="text-yellow-600">⚠️ Fuzzy match → "<?= htmlspecialchars($i['db_name']) ?>" (id:<?= $i['db_id'] ?>)</span>
                    <?php else: ?>
                    <span class="text-red-600">❌ ไม่พบในฐานข้อมูล</span>
                    <?php endif; ?>
                </td>
                <td class="px-2 py-1">
                    <?php if ($i['type'] === 'fuzzy'): ?>
                    แก้เป็น "<?= htmlspecialchars($i['db_name']) ?>"
                    <?php else: ?>
                    เพิ่มใน lottery_types หรือลบ mapping
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($unmapped)): ?>
    <div class="p-4 bg-yellow-50 border-b">
        <h3 class="text-sm font-bold text-yellow-700 mb-2">📋 หวยในฐานข้อมูลที่ยังไม่มี Slug (<?= count($unmapped) ?>)</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-1 text-xs">
        <?php foreach ($unmapped as $u): ?>
            <div class="px-2 py-1 bg-yellow-100 rounded">[id:<?= $u['id'] ?>] <?= htmlspecialchars($u['name']) ?></div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="p-4">
        <h3 class="text-sm font-bold text-green-700 mb-2">✅ Slug ที่ตรงถูกต้อง (<?= count($ok) ?>)</h3>
        <table class="w-full text-xs">
            <thead><tr class="bg-green-50"><th class="px-2 py-1 text-left">Slug</th><th class="px-2 py-1 text-left">ชื่อ</th><th class="px-2 py-1 text-center">DB ID</th><th class="px-2 py-1 text-center">Active</th></tr></thead>
            <tbody>
            <?php foreach ($ok as $o): ?>
            <tr class="border-b hover:bg-green-50">
                <td class="px-2 py-1 font-mono"><?= htmlspecialchars($o['slug']) ?></td>
                <td class="px-2 py-1"><?= htmlspecialchars($o['name']) ?></td>
                <td class="px-2 py-1 text-center"><?= $o['db_id'] ?></td>
                <td class="px-2 py-1 text-center"><?= $o['active'] ? '✅' : '❌' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
