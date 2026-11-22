<?php
/**
 * member_supply.php - 자사 주택 공급현황 (회원사 전용)
 */
require_once __DIR__ . '/includes/member_auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '주택 공급현황';
$currentPage = 'housing_supply';

$memberId = $_SESSION['member_id'];
$success_msg = '';

// ─── POST 요청 처리 (회원사 수정) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_supply') {
    $id = (int)$_POST['id'];
    
    // 권한 확인 (본인 회사 주택인지)
    $check = $pdo->prepare("SELECT * FROM tb_social_housing_supply WHERE id = ? AND member_id = ?");
    $check->execute([$id, $memberId]);
    $oldData = $check->fetch();
    
    if ($oldData) {
        $bName = trim($_POST['building_name'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        $hCount = (int)($_POST['household_count'] ?? 0);
        $sType = trim($_POST['supply_type'] ?? '');
        $cDate = trim($_POST['completion_date'] ?? '');
        $oStatus = trim($_POST['operation_status'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE tb_social_housing_supply SET building_name=?, address=?, household_count=?, supply_type=?, completion_date=?, operation_status=? WHERE id=?");
        if ($stmt->execute([$bName, $addr, $hCount, $sType, $cDate, $oStatus, $id])) {
            // 변경 로그 기록
            $fields = [
                'building_name' => '건물명',
                'address' => '주소',
                'household_count' => '세대수',
                'supply_type' => '공급유형',
                'completion_date' => '준공년월',
                'operation_status' => '운영상태'
            ];
            $newData = [
                'building_name' => $bName,
                'address' => $addr,
                'household_count' => $hCount,
                'supply_type' => $sType,
                'completion_date' => $cDate,
                'operation_status' => $oStatus
            ];
            
            foreach ($fields as $key => $label) {
                if ($oldData[$key] != $newData[$key]) {
                    $logStmt = $pdo->prepare("INSERT INTO tb_member_history (member_id, field_name, old_value, new_value, changed_by, is_read) VALUES (?, ?, ?, ?, ?, 0)");
                    $logStmt->execute([$memberId, "[공급현황] " . $oldData['building_name'] . " - " . $label, $oldData[$key], $newData[$key], ($_SESSION['user_name'] ?? '담당자') . " (회원사)"]);
                }
            }
            $success_msg = "주택 정보가 수정되었습니다.";
        }
    }
}

// 회원사 정보 및 주택 목록 조회
$stmt = $pdo->prepare("SELECT company_name FROM tb_members WHERE id = ?");
$stmt->execute([$memberId]);
$company = $stmt->fetch();

$supplyStmt = $pdo->prepare("SELECT * FROM tb_social_housing_supply WHERE member_id = ? ORDER BY completion_date DESC");
$supplyStmt->execute([$memberId]);
$supplies = $supplyStmt->fetchAll();

// 통계 계산
$totalHouses = count($supplies);
$totalHouseholds = 0;
foreach ($supplies as $s) {
    $totalHouseholds += (int)$s['household_count'];
}

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/member_sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <h1 class="text-xl font-bold text-gray-800">주택 공급현황</h1>
        <div class="text-sm text-gray-500">
            <span class="font-bold text-primary"><?= htmlspecialchars($company['company_name']) ?></span>
        </div>
    </header>

    <main class="flex-1 overflow-auto bg-gray-50 p-6 lg:p-8">
        
        <?php if ($success_msg): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm">
                <i class="ph-fill ph-check-circle text-lg"></i> <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <!-- 요약 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">총 공급 주택 수</p>
                    <h3 class="text-2xl font-bold text-gray-800"><?= number_format($totalHouses) ?><span class="text-sm font-normal text-gray-500 ml-1">개소</span></h3>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center">
                    <i class="ph ph-buildings text-2xl"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium mb-1">총 공급 세대 수</p>
                    <h3 class="text-2xl font-bold text-primary"><?= number_format($totalHouseholds) ?><span class="text-sm font-normal text-gray-500 ml-1">세대</span></h3>
                </div>
                <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center">
                    <i class="ph ph-users text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- 안내 메시지 -->
        <div class="bg-white border border-gray-200 p-4 rounded-xl mb-6 shadow-sm flex items-start gap-3">
            <div class="bg-blue-100 p-2 rounded-lg">
                <i class="ph ph-info text-blue-600 text-lg"></i>
            </div>
            <div class="text-sm text-gray-600 leading-relaxed">
                <p class="font-bold text-gray-800 mb-0.5">공급현황 데이터 안내</p>
                <p>표시된 현황은 협회에 등록된 재고(Stock) 데이터 기준입니다. 정보가 수정되면 관리자에게 알림이 전송됩니다. 새로운 주택 등록이나 삭제는 협회 사무국으로 요청해 주시기 바랍니다.</p>
            </div>
        </div>

        <!-- 공급 현황 테이블 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="font-bold text-gray-800">상세 공급 목록</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-400 uppercase bg-gray-50 border-b border-gray-200 font-bold">
                        <tr>
                            <th class="px-6 py-4">유형</th>
                            <th class="px-6 py-4">건물명</th>
                            <th class="px-6 py-4">주소</th>
                            <th class="px-6 py-4 text-center">세대수</th>
                            <th class="px-6 py-4">준공년월</th>
                            <th class="px-6 py-4">운영상태</th>
                            <th class="px-6 py-4 text-center">수정</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($supplies)): ?>
                            <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">등록된 공급 주택 정보가 없습니다.</td></tr>
                        <?php else: ?>
                            <?php foreach ($supplies as $s): ?>
                                <tr class="hover:bg-gray-50/50 transition group">
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-md text-[10px] font-bold bg-gray-100 text-gray-600 uppercase">
                                            <?= htmlspecialchars($s['supply_type'] ?: '미분류') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($s['building_name']) ?></td>
                                    <td class="px-6 py-4 text-gray-500 text-xs truncate max-w-xs" title="<?= htmlspecialchars($s['address']) ?>"><?= htmlspecialchars($s['address']) ?></td>
                                    <td class="px-6 py-4 text-center font-medium text-gray-700"><?= number_format($s['household_count']) ?></td>
                                    <td class="px-6 py-4 text-gray-500 text-xs"><?= htmlspecialchars($s['completion_date']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php 
                                            $status = $s['operation_status'];
                                            $statusColor = 'bg-gray-100 text-gray-500';
                                            if ($status === '운영중' || $status === '운영') $statusColor = 'bg-green-100 text-green-700';
                                            elseif ($status === '준공') $statusColor = 'bg-blue-100 text-blue-700';
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-[10px] font-medium <?= $statusColor ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick='openEditModal(<?= json_encode($s) ?>)' class="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition opacity-0 group-hover:opacity-100">
                                            <i class="ph ph-pencil-simple text-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- 수정 모달 -->
<div id="editModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800">주택 정보 수정</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <form action="member_supply.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_supply">
            <input type="hidden" name="id" id="editId">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 mb-1">건물명</label>
                    <input type="text" name="building_name" id="editBuildingName" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 mb-1">주소</label>
                    <input type="text" name="address" id="editAddress" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">세대수</label>
                    <input type="number" name="household_count" id="editHouseholdCount" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">공급 유형</label>
                    <input type="text" name="supply_type" id="editSupplyType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">준공년월</label>
                    <input type="text" name="completion_date" id="editCompletionDate" placeholder="예: 2024-01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">운영 상태</label>
                    <select name="operation_status" id="editOperationStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                        <option value="운영중">운영중</option>
                        <option value="준공">준공</option>
                        <option value="준비중">준비중</option>
                        <option value="종료">종료</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2 pt-4">
                <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50 transition">취소</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-blue-800 transition">수정 요청 저장</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editBuildingName').value = data.building_name;
    document.getElementById('editAddress').value = data.address;
    document.getElementById('editHouseholdCount').value = data.household_count;
    document.getElementById('editSupplyType').value = data.supply_type;
    document.getElementById('editCompletionDate').value = data.completion_date;
    document.getElementById('editOperationStatus').value = data.operation_status;
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

</body>
</html>
