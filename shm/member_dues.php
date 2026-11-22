<?php
/**
 * member_dues.php - 자사 회비 납부 현황 (회원사 전용)
 */
require_once __DIR__ . '/includes/member_auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '회비 납부 내역';
$currentPage = 'dues_history';

$memberId = $_SESSION['member_id'];

// 1. 회원사 기본 정보 및 현재 월회비 조회
$stmt = $pdo->prepare("SELECT company_name, monthly_fee FROM tb_members WHERE id = ?");
$stmt->execute([$memberId]);
$company = $stmt->fetch();

if (!$company) {
    die("회원사 정보를 찾을 수 없습니다.");
}

// 2. 납부 내역 조회 (최신순 정렬)
$payStmt = $pdo->prepare("SELECT * FROM tb_dues_payments WHERE member_id = ? ORDER BY payment_date DESC, target_month DESC");
$payStmt->execute([$memberId]);
$payments = $payStmt->fetchAll();

// 3. 통계 계산 (올해 납부 합계, 총 납부 합계 등)
$currentYear = date('Y');
$totalAmount = 0;
$thisYearAmount = 0;

foreach ($payments as $p) {
    $totalAmount += $p['amount'];
    if (strpos($p['payment_date'], $currentYear) === 0) {
        $thisYearAmount += $p['amount'];
    }
}

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/member_sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <h1 class="text-xl font-bold text-gray-800">회비 납부 내역</h1>
        <div class="text-sm text-gray-500">
            <span class="font-bold text-primary"><?= htmlspecialchars($company['company_name']) ?></span>
        </div>
    </header>

    <main class="flex-1 overflow-auto bg-gray-50 p-6 lg:p-8">
        <!-- 요약 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <p class="text-sm text-gray-500 font-medium mb-1">현재 적용 월회비</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= number_format($company['monthly_fee']) ?><span class="text-sm font-normal text-gray-500 ml-1">원</span></h3>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <p class="text-sm text-gray-500 font-medium mb-1"><?= $currentYear ?>년도 납부 총액</p>
                <h3 class="text-2xl font-bold text-blue-600"><?= number_format($thisYearAmount) ?><span class="text-sm font-normal text-gray-500 ml-1">원</span></h3>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <p class="text-sm text-gray-500 font-medium mb-1">총 납부 건수</p>
                <h3 class="text-2xl font-bold text-green-600"><?= number_format(count($payments)) ?><span class="text-sm font-normal text-gray-500 ml-1">건</span></h3>
            </div>
        </div>

        <!-- 기간별 영수증 출력 폼 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="ph-fill ph-printer"></i> 납부 기간별 영수증 출력</h3>
            <form action="member_print_receipt.php" target="_blank" method="GET" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="type" value="receipt">
                <div class="flex items-center gap-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">시작 연월</label>
                        <input type="month" name="start_month" value="<?= date('Y-01') ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm" required>
                    </div>
                    <span class="text-gray-400 pb-1">~</span>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">종료 연월</label>
                        <input type="month" name="end_month" value="<?= date('Y-m') ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm" required>
                    </div>
                </div>
                <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-900 transition flex items-center gap-2">
                    영수증 일괄 출력
                </button>
            </form>
        </div>

        <!-- 안내 메시지 -->
        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg flex items-start gap-2 mb-6">
            <i class="ph-fill ph-info text-lg mt-0.5"></i>
            <div class="text-sm">
                <p class="font-bold mb-1">회비 납부 안내</p>
                <p>본 페이지에 표시된 내역은 협회 통장에 입금 확인된 내역입니다. 미납 내역이나 회비 산정 기준 변경이 필요하신 경우 협회 사무국으로 문의해 주시기 바랍니다.</p>
            </div>
        </div>

        <!-- 납부 내역 테이블 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-800">상세 납부 내역</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 font-bold">입금일자</th>
                            <th class="px-6 py-3 font-bold">대상월 (귀속월)</th>
                            <th class="px-6 py-3 font-bold text-right">납부 금액</th>
                            <th class="px-6 py-3 font-bold">입금자명 / 적요</th>
                            <th class="px-6 py-3 font-bold text-center">출력</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400">회비 납부 내역이 없습니다.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-4 font-medium text-gray-800">
                                         <a href="member_print_receipt.php?type=receipt&payment_date=<?= urlencode($p['payment_date']) ?>" target="_blank" class="text-blue-600 hover:underline cursor-pointer flex items-center gap-1" title="이 날짜의 모든 납부내역 영수증 출력">
                                             <i class="ph ph-copy text-gray-400"></i>
                                             <?= htmlspecialchars($p['payment_date']) ?>
                                         </a>
                                     </td>
                                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($p['target_month']) ?></td>
                                    <td class="px-6 py-4 font-bold text-blue-600 text-right"><?= number_format($p['amount']) ?>원</td>
                                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($p['note'] ?: '-') ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="member_print_receipt.php?type=receipt&payment_id=<?= $p['id'] ?>" target="_blank" class="inline-flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded transition">
                                            <i class="ph ph-printer"></i> 영수증
                                        </a>
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
