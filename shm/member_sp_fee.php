<?php
/**
 * member_sp_fee.php - 특별회비 현황 (회원사 전용)
 */
require_once __DIR__ . '/includes/member_auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '특별회비 내역';
$currentPage = 'special_dues';

$memberId = $_SESSION['member_id'];

// 1. 회원사 정보 조회
$stmt = $pdo->prepare("SELECT company_name FROM tb_members WHERE id = ?");
$stmt->execute([$memberId]);
$company = $stmt->fetch();

if (!$company) {
    die("회원사 정보를 찾을 수 없습니다.");
}
$companyName = $company['company_name'];

// 2. 해당 회원사의 특별회비 데이터 조회 (sp_fee 테이블 연동)
$feeStmt = $pdo->prepare("SELECT * FROM sp_fee WHERE company_name = ? ORDER BY id ASC");
$feeStmt->execute([$companyName]);
$specialFees = $feeStmt->fetchAll();

// 3. 통계 계산
$totalProjects = count($specialFees);
$totalFeeAmt = 0;
$totalPaidAmt = 0;

foreach ($specialFees as $f) {
    if (!in_array($f['status_category'], ['후원금', '이사회 조정'])) {
        $totalFeeAmt += (int)($f['total_fee'] ?? 0);
    }
    $totalPaidAmt += (int)($f['paid_amount'] ?? 0);
}
$totalUnpaidAmt = max(0, $totalFeeAmt - $totalPaidAmt);

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/member_sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <h1 class="text-xl font-bold text-gray-800">특별회비 내역</h1>
        <div class="text-sm text-gray-500">
            <span class="font-bold text-primary"><?= htmlspecialchars($companyName) ?></span>
        </div>
    </header>

    <main class="flex-1 overflow-auto bg-gray-50 p-6 lg:p-8">
        <!-- 안내 메시지 -->
        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg flex items-start gap-2 mb-6">
            <i class="ph-fill ph-info text-lg mt-0.5"></i>
            <div class="text-sm">
                <p class="font-bold mb-1">특별회비 조회 안내</p>
                <p>회원사가 진행 중인 사업(임대운영 등)에 대해 책정된 특별회비 청구 및 납부 현황입니다. 상세 내용은 협회 사무국으로 문의 바랍니다.</p>
            </div>
        </div>

        <!-- 요약 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 border-t-4 border-t-slate-500">
                <p class="text-sm text-gray-500 font-medium mb-1 flex items-center gap-1"><i class="ph ph-buildings"></i> 해당 사업 수</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= number_format($totalProjects) ?><span class="text-sm font-normal text-gray-500 ml-1">건</span></h3>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 border-t-4 border-t-blue-500">
                <p class="text-sm text-gray-500 font-medium mb-1 flex items-center gap-1"><i class="ph ph-money"></i> 총 책정 회비</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= number_format($totalFeeAmt) ?><span class="text-sm font-normal text-gray-500 ml-1">원</span></h3>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 border-t-4 border-t-green-500">
                <p class="text-sm text-gray-500 font-medium mb-1 flex items-center gap-1"><i class="ph ph-check-circle"></i> 누적 납부액</p>
                <h3 class="text-2xl font-bold text-green-600"><?= number_format($totalPaidAmt) ?><span class="text-sm font-normal text-gray-500 ml-1">원</span></h3>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 border-t-4 border-t-red-500">
                <p class="text-sm text-gray-500 font-medium mb-1 flex items-center gap-1"><i class="ph ph-warning-circle"></i> 잔여 미납액</p>
                <h3 class="text-2xl font-bold text-red-500"><?= number_format($totalUnpaidAmt) ?><span class="text-sm font-normal text-gray-500 ml-1">원</span></h3>
            </div>
        </div>

        <!-- 특별회비 내역 테이블 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-800 flex items-center gap-2"><i class="ph ph-list-bullets text-primary"></i> 사업별 세부 현황</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 font-bold">건물 정보</th>
                            <th class="px-6 py-3 font-bold">사업유형 / 운영방식</th>
                            <th class="px-6 py-3 font-bold text-center">세대수</th>
                            <th class="px-6 py-3 font-bold text-right">총 회비</th>
                            <th class="px-6 py-3 font-bold text-right">납부 금액</th>
                            <th class="px-6 py-3 font-bold text-center">상태</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($specialFees)): ?>
                            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">등록된 특별회비 사업 내역이 없습니다.</td></tr>
                        <?php else: ?>
                            <?php foreach ($specialFees as $f): 
                                $status = $f['status_category'] ?? '';
                                $badgeClass = 'bg-gray-100 text-gray-600';
                                if ($status === '납부완료') $badgeClass = 'bg-green-100 text-green-700 font-bold';
                                elseif ($status === '청구완료') $badgeClass = 'bg-blue-100 text-blue-700';
                                elseif ($status === '추후청구') $badgeClass = 'bg-yellow-100 text-yellow-700';
                            ?>
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-800"><?= htmlspecialchars($f['building_name'] ?? '-') ?></div>
                                        <div class="text-xs text-gray-400 mt-0.5">지역: <?= htmlspecialchars($f['region'] ?? '-') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-gray-700"><?= htmlspecialchars($f['project_type'] ?? '-') ?></div>
                                        <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($f['operation_type'] ?? '-') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-medium text-gray-700">
                                        <?= (int)($f['units'] ?? 0) ?> 세대
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-700 text-right">
                                        <?= number_format($f['total_fee'] ?? 0) ?>원
                                    </td>
                                    <td class="px-6 py-4 font-bold text-blue-600 text-right">
                                        <?= number_format($f['paid_amount'] ?? 0) ?>원
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-block px-2.5 py-1 rounded text-xs <?= $badgeClass ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
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
</body>
</html>
