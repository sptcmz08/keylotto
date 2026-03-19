<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$adminPage = 'win_lose';
$adminTitle = 'คาดคะเน ได้-เสีย';
require_once 'includes/header.php';
?>

<div class="mb-4">
    <h2 class="text-lg font-bold text-gray-800 mb-3">คาดคะเน ได้-เสีย</h2>
    
    <!-- Alert Box -->
    <div class="bg-[#f9f9f9] border border-gray-200 rounded p-3 mb-4 flex justify-between items-center">
        <div class="flex items-center text-sm text-gray-800">
            <i class="fas fa-exclamation-triangle text-red-500 mr-2 text-lg"></i>
            เฉพาะงวด <span class="text-blue-600 font-bold mx-1">[หวยต่างประเทศ] ฮานอยพิเศษ</span> วันที่ <span class="text-blue-600 font-bold mx-1">18/03/2026</span> <span class="text-gray-500 text-xs ml-1">(เปลี่ยนได้ที่แท็บเมนูด้านบน)</span>
        </div>
        <button class="bg-white border border-gray-300 text-teal-600 px-4 py-1.5 rounded text-sm hover:bg-gray-50">
            Refresh (293)
        </button>
    </div>

    <!-- Toolbar Filters -->
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <button class="bg-gray-100 border border-gray-300 px-4 py-1.5 text-xs text-gray-700 rounded hover:bg-gray-200">แสดง</button>
        <select class="border border-gray-300 px-3 py-1.5 text-xs rounded outline-none text-gray-700">
            <option>คาดคะเน ได้-เสีย</option>
        </select>
        <select class="border border-gray-300 px-3 py-1.5 text-xs rounded outline-none text-gray-700">
            <option>เรียงลำดับ คาดคะเนยอดเสีย</option>
        </select>
        <select class="border border-gray-300 px-3 py-1.5 text-xs rounded outline-none text-gray-700">
            <option>เรียงจาก มาก > น้อย</option>
        </select>
        <select class="border border-gray-300 px-3 py-1.5 text-xs rounded outline-none text-gray-700">
            <option>จำนวนแถว 50</option>
        </select>
    </div>

    <!-- Legend -->
    <div class="flex gap-2 mb-4">
        <div class="bg-[#e3f2fd] border border-[#90caf9] text-[#1565c0] text-xs px-3 py-1 rounded">พื้นหลังสีฟ้า = เต็มแล้ว</div>
        <div class="bg-[#fff9c4] border border-[#fff59d] text-[#f57f17] text-xs px-3 py-1 rounded">พื้นหลังสีเหลือง = ถูกรางวัล</div>
        <div class="bg-gray-200 border border-gray-300 text-gray-700 text-xs px-3 py-1 rounded">กด Ctrl+F เพื่อค้นหา</div>
    </div>

    <!-- Complex Data Table -->
    <div class="bg-white overflow-x-auto w-full">
        <table class="w-full text-xs font-sans text-gray-700 border-collapse border-2 border-[#4caf50]">
            <thead>
                <tr class="bg-white">
                    <th colspan="2" class="border border-[#81c784] py-2 text-[#2e7d32] font-bold">รวม</th>
                    <th colspan="2" class="border border-[#81c784] py-2 text-[#2e7d32] font-bold">3 ตัวบน</th>
                    <th colspan="2" class="border border-[#81c784] py-2 text-[#2e7d32] font-bold">3 ตัวโต๊ด</th>
                    <th colspan="2" class="border border-[#81c784] py-2 text-[#2e7d32] font-bold">2 ตัวบน</th>
                    <th colspan="2" class="border border-[#81c784] py-2 text-[#2e7d32] font-bold">2 ตัวล่าง</th>
                    <th colspan="2" class="border border-[#81c784] py-2 text-[#2e7d32] font-bold">วิ่งบน</th>
                    <th colspan="2" class="border border-[#81c784] py-2 text-[#2e7d32] font-bold">วิ่งล่าง</th>
                </tr>
            </thead>
            <tbody>
                <!-- Summary Rows -->
                <tr>
                    <td class="border border-[#81c784] px-2 py-1.5 font-bold">ซื้อ</td>
                    <td class="border border-[#81c784] px-2 py-1.5 text-right font-bold text-[#1565c0]">943.20</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">28.80</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">439.20</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">475.20</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">0.00</td>
                </tr>
                <tr>
                    <td class="border border-[#81c784] px-2 py-1.5 font-bold">คอมฯ</td>
                    <td class="border border-[#81c784] px-2 py-1.5 text-right font-bold text-red-600">-144</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">-144</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">0.00</td>
                </tr>
                <tr>
                    <td class="border border-[#81c784] px-2 py-1.5 font-bold">รับ</td>
                    <td class="border border-[#81c784] px-2 py-1.5 text-right font-bold text-[#1565c0]">941.76</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">27.36</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">439.20</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">475.20</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-[#1565c0]">0.00</td>
                </tr>
                <tr>
                    <td class="border border-[#81c784] px-2 py-1.5 font-bold">จ่าย</td>
                    <td class="border border-[#81c784] px-2 py-1.5 text-right font-bold text-red-600">-2,433.24</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">-347.64</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">-760.80</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">-1,324.80</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">0.00</td>
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-right text-red-600">0.00</td>
                </tr>
                <tr class="bg-gray-50">
                    <td class="border border-[#81c784] px-2 py-2 font-bold align-middle">ตั้งสู้</td>
                    <td class="border border-[#81c784] px-2 py-2 text-right">
                        <button class="bg-[#4caf50] text-white px-3 py-1 text-xs rounded shadow-sm hover:bg-green-600">บันทึก</button>
                    </td>
                    <td colspan="2" class="border border-[#81c784] p-0"><input type="text" value="0" class="w-full h-full min-h-[30px] text-center outline-none bg-transparent"></td>
                    <td colspan="2" class="border border-[#81c784] p-0"><input type="text" value="500" class="w-full h-full min-h-[30px] text-center outline-none bg-transparent text-gray-500"></td>
                    <td colspan="2" class="border border-[#81c784] p-0"><input type="text" value="1000" class="w-full h-full min-h-[30px] text-center outline-none bg-transparent text-gray-500"></td>
                    <td colspan="2" class="border border-[#81c784] p-0"><input type="text" value="1000" class="w-full h-full min-h-[30px] text-center outline-none bg-transparent text-gray-500"></td>
                    <td colspan="2" class="border border-[#81c784] p-0"><input type="text" value="2000" class="w-full h-full min-h-[30px] text-center outline-none bg-transparent text-gray-500"></td>
                    <td colspan="2" class="border border-[#81c784] p-0"><input type="text" value="2000" class="w-full h-full min-h-[30px] text-center outline-none bg-transparent text-gray-500"></td>
                </tr>

                <!-- Data Rows -->
                <?php
                // Mock data matches screenshot perfectly
                $mockRows = [
                    [
                        'id' => 1,
                        '3t' => ['num' => '000', 'amt' => '0.00', 'bg' => 'bg-[#e3f2fd]'],
                        '3tod' => ['num' => '024', 'amt' => '-347.64', 'bg' => ''],
                        '2t' => ['num' => '40', 'amt' => '-760.80', 'bg' => ''],
                        '2b' => ['num' => '88', 'amt' => '-1,324.80', 'bg' => ''],
                        'rt' => ['num' => '0', 'amt' => '0.00', 'bg' => ''],
                        'rb' => ['num' => '0', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 2,
                        '3t' => ['num' => '001', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '154', 'amt' => '-347.64', 'bg' => ''],
                        '2t' => ['num' => '06', 'amt' => '-640.80', 'bg' => ''],
                        '2b' => ['num' => '28', 'amt' => '-1,204.80', 'bg' => ''],
                        'rt' => ['num' => '1', 'amt' => '0.00', 'bg' => 'bg-[#fff9c4]'],
                        'rb' => ['num' => '1', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 3,
                        '3t' => ['num' => '002', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '161', 'amt' => '-347.64', 'bg' => 'bg-[#fff9c4]'],
                        '2t' => ['num' => '60', 'amt' => '-640.80', 'bg' => ''],
                        '2b' => ['num' => '22', 'amt' => '-1,024.80', 'bg' => ''],
                        'rt' => ['num' => '2', 'amt' => '0.00', 'bg' => ''],
                        'rb' => ['num' => '2', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 4,
                        '3t' => ['num' => '003', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '605', 'amt' => '-347.64', 'bg' => ''],
                        '2t' => ['num' => '00', 'amt' => '-460.80', 'bg' => ''],
                        '2b' => ['num' => '45', 'amt' => '-604.80', 'bg' => ''],
                        'rt' => ['num' => '3', 'amt' => '0.00', 'bg' => ''],
                        'rb' => ['num' => '3', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 5,
                        '3t' => ['num' => '004', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '860', 'amt' => '-347.64', 'bg' => ''],
                        '2t' => ['num' => '04', 'amt' => '-460.80', 'bg' => ''],
                        '2b' => ['num' => '54', 'amt' => '-604.80', 'bg' => ''],
                        'rt' => ['num' => '4', 'amt' => '0.00', 'bg' => ''],
                        'rb' => ['num' => '4', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 6,
                        '3t' => ['num' => '005', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '890', 'amt' => '-347.64', 'bg' => ''],
                        '2t' => ['num' => '11', 'amt' => '-460.80', 'bg' => ''],
                        '2b' => ['num' => '11', 'amt' => '-424.80', 'bg' => ''],
                        'rt' => ['num' => '5', 'amt' => '0.00', 'bg' => ''],
                        'rb' => ['num' => '5', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 7,
                        '3t' => ['num' => '006', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '507', 'amt' => '-197.64', 'bg' => ''],
                        '2t' => ['num' => '36', 'amt' => '-460.80', 'bg' => ''],
                        '2b' => ['num' => '36', 'amt' => '-424.80', 'bg' => ''],
                        'rt' => ['num' => '6', 'amt' => '0.00', 'bg' => 'bg-[#fff9c4]'],
                        'rb' => ['num' => '6', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 8,
                        '3t' => ['num' => '007', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '516', 'amt' => '-197.64', 'bg' => ''],
                        '2t' => ['num' => '63', 'amt' => '-460.80', 'bg' => ''],
                        '2b' => ['num' => '40', 'amt' => '-424.80', 'bg' => ''],
                        'rt' => ['num' => '7', 'amt' => '0.00', 'bg' => ''],
                        'rb' => ['num' => '7', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 9,
                        '3t' => ['num' => '008', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '612', 'amt' => '-197.64', 'bg' => ''],
                        '2t' => ['num' => '86', 'amt' => '-460.80', 'bg' => ''],
                        '2b' => ['num' => '55', 'amt' => '-424.80', 'bg' => ''],
                        'rt' => ['num' => '8', 'amt' => '0.00', 'bg' => ''],
                        'rb' => ['num' => '8', 'amt' => '0.00', 'bg' => 'bg-[#fff9c4]'],
                    ],
                    [
                        'id' => 10,
                        '3t' => ['num' => '009', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '725', 'amt' => '-197.64', 'bg' => ''],
                        '2t' => ['num' => '89', 'amt' => '-460.80', 'bg' => ''],
                        '2b' => ['num' => '63', 'amt' => '-424.80', 'bg' => ''],
                        'rt' => ['num' => '9', 'amt' => '0.00', 'bg' => ''],
                        'rb' => ['num' => '9', 'amt' => '0.00', 'bg' => ''],
                    ],
                    [
                        'id' => 11,
                        '3t' => ['num' => '010', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '751', 'amt' => '-197.64', 'bg' => ''],
                        '2t' => ['num' => '05', 'amt' => '-340.80', 'bg' => ''],
                        '2b' => ['num' => '66', 'amt' => '-424.80', 'bg' => ''],
                        'rt' => ['num' => '', 'amt' => '', 'bg' => ''],
                        'rb' => ['num' => '', 'amt' => '', 'bg' => ''],
                    ],
                    [
                        'id' => 12,
                        '3t' => ['num' => '011', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '804', 'amt' => '-197.64', 'bg' => ''],
                        '2t' => ['num' => '16', 'amt' => '-340.80', 'bg' => 'bg-[#fff9c4]'],
                        '2b' => ['num' => '89', 'amt' => '-424.80', 'bg' => ''],
                        'rt' => ['num' => '', 'amt' => '', 'bg' => ''],
                        'rb' => ['num' => '', 'amt' => '', 'bg' => ''],
                    ],
                    [
                        'id' => 13,
                        '3t' => ['num' => '012', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '000', 'amt' => '27.36', 'bg' => ''],
                        '2t' => ['num' => '24', 'amt' => '-340.80', 'bg' => ''],
                        '2b' => ['num' => '05', 'amt' => '-304.80', 'bg' => ''],
                        'rt' => ['num' => '', 'amt' => '', 'bg' => ''],
                        'rb' => ['num' => '', 'amt' => '', 'bg' => ''],
                    ],
                    [
                        'id' => 14,
                        '3t' => ['num' => '013', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '001', 'amt' => '27.36', 'bg' => ''],
                        '2t' => ['num' => '34', 'amt' => '-340.80', 'bg' => ''],
                        '2b' => ['num' => '08', 'amt' => '-304.80', 'bg' => ''],
                        'rt' => ['num' => '', 'amt' => '', 'bg' => ''],
                        'rb' => ['num' => '', 'amt' => '', 'bg' => ''],
                    ],
                    [
                        'id' => 15,
                        '3t' => ['num' => '014', 'amt' => '0.00', 'bg' => ''],
                        '3tod' => ['num' => '002', 'amt' => '27.36', 'bg' => ''],
                        '2t' => ['num' => '38', 'amt' => '-340.80', 'bg' => ''],
                        '2b' => ['num' => '15', 'amt' => '-304.80', 'bg' => ''],
                        'rt' => ['num' => '', 'amt' => '', 'bg' => ''],
                        'rb' => ['num' => '', 'amt' => '', 'bg' => ''],
                    ],
                ];

                foreach($mockRows as $row):
                    $renderCell = function($cell) {
                        if ($cell['num'] === '') {
                            return "<td class='border border-[#81c784] px-2 py-1.5'></td><td class='border border-[#81c784] px-2 py-1.5'></td>";
                        }
                        $numColor = 'text-[#1565c0]'; // Blue
                        $amtVal = str_replace(',', '', $cell['amt']);
                        $amtColor = (float)$amtVal < 0 ? 'text-red-600' : 'text-[#1565c0]';
                        $bg = $cell['bg'] ? $cell['bg'] : 'bg-white';
                        
                        return "<td class='border border-[#81c784] px-2 py-1.5 $bg $numColor'>{$cell['num']}</td>
                                <td class='border border-[#81c784] px-2 py-1.5 text-right $amtColor'>{$cell['amt']}</td>";
                    };
                ?>
                <tr class="hover:bg-gray-50">
                    <td colspan="2" class="border border-[#81c784] px-2 py-1.5 text-center text-gray-800"><?= $row['id'] ?></td>
                    <?= $renderCell($row['3t']) ?>
                    <?= $renderCell($row['3tod']) ?>
                    <?= $renderCell($row['2t']) ?>
                    <?= $renderCell($row['2b']) ?>
                    <?= $renderCell($row['rt']) ?>
                    <?= $renderCell($row['rb']) ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
