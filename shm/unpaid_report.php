<?php
/**
 * unpaid_report.php - 장기 미납 회원사 현황 보고서
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle   = '장기 미납 현황';
$currentPage = 'unpaid_report';

// 파라미터 받기 (기본값: 전년도)
$targetYear = (int)($_GET['target_year'] ?? date('Y') - 1);
$reportDate = $_GET['report_date'] ?? date('Y-m-d');

$targetDateEndObj = new DateTime("$targetYear-12-31");
$reportDateObj = new DateTime($reportDate);
$reportMonthKey = $reportDateObj->format('Y-m');
$reportMonthEndObj = new DateTime($reportDateObj->format('Y-m-t'));

// 1. 정회원 목록 및 초기/수기 미납액 조회 (탈퇴 제외)
$stmtMembers = $pdo->query("
    SELECT m.id, m.member_no, m.company_name, m.join_date, m.withdraw_date, 
           IFNULL(iu.total, 0) as initial_unpaid, 
           IFNULL(d.unpaid_before_2022, 0) as manual_unpaid
    FROM tb_members m
    LEFT JOIN tb_initial_unpaid iu ON m.id = iu.member_id
    LEFT JOIN tb_member_details d ON m.id = d.member_id
    WHERE m.status != '탈퇴' AND m.member_type = '정회원'
    ORDER BY m.member_no ASC
");
$members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

// 2. 모든 회원의 회비 이력 조회
$stmtHistory = $pdo->query("SELECT * FROM tb_member_fee_history ORDER BY member_id, start_date DESC");
$historyRaw = $stmtHistory->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

function getFeeAtDate($history, $date) {
    if (empty($history)) return 0;
    foreach ($history as $h) {
        if ($h['start_date'] <= $date) return (int)$h['monthly_fee'];
    }
    return 0;
}

// 3. 납부 내역 조회 (보고 일자 및 보고월 기준)
$stmtPayments = $pdo->prepare("
    SELECT member_id, target_month, SUM(amount) as total_paid 
    FROM tb_dues_payments 
    WHERE target_month <= ? AND (payment_date IS NULL OR payment_date <= ?)
    GROUP BY member_id, target_month
");
$stmtPayments->execute([$reportMonthKey, $reportDate . ' 23:59:59']);
$paymentsRaw = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

$paymentsMap = [];
foreach ($paymentsRaw as $p) {
    $paymentsMap[$p['member_id']][$p['target_month']] = (int)$p['total_paid'];
}

$unpaidList = [];

foreach ($members as $m) {
    $mid = $m['id'];
    $mHistory = $historyRaw[$mid] ?? [];
    $mPayments = $paymentsMap[$mid] ?? [];
    
    $totalExpected = 0;
    $totalPaid = 0;
    
    $targetUnpaidMonthsCount = 0;
    $targetUnpaidAmount = 0;
    $targetUnpaidDetails = [];
    
    // 가입월부터 보고월 말일까지 루프 (전체 누적 계산을 위해)
    $joinDateStr = $m['join_date'] ? $m['join_date'] : '2022-01-01';
    $curr = new DateTime(substr($joinDateStr, 0, 7) . '-01');
    if ($curr < new DateTime('2022-01-01')) {
        $curr = new DateTime('2022-01-01');
    }
    
    while ($curr <= $reportMonthEndObj) {
        $mKey = $curr->format('Y-m');
        $monthFirst = $curr->format('Y-m-01');
        $monthLast  = $curr->format('Y-m-t');
        $currYear = (int)$curr->format('Y');
        
        $isMemberActive = ($m['join_date'] && $m['join_date'] <= $monthLast) && 
                          (!$m['withdraw_date'] || $m['withdraw_date'] >= $monthFirst);
        
        if ($isMemberActive) {
            $fee = getFeeAtDate($mHistory, $curr->format('Y-m-d'));
            $paid = $mPayments[$mKey] ?? 0;
            
            $totalExpected += $fee;
            $totalPaid += $paid;
            
            // 대상 연도(전년도 등) 내에서의 미납 계산
            if ($currYear === $targetYear) {
                if ($fee > 0 && ($fee - $paid) > 0) {
                    $targetUnpaidMonthsCount++;
                    $targetUnpaidAmount += ($fee - $paid);
                    $targetUnpaidDetails[] = [
                        'month' => $curr->format('n') . '월',
                        'amount' => $fee - $paid
                    ];
                }
            }
        }
        $curr->modify('+1 month');
    }
    
    $pre2022Unpaid = (int)$m['initial_unpaid'] + (int)$m['manual_unpaid'];
    $totalUnpaidAmount = $totalExpected + $pre2022Unpaid - $totalPaid;
    
    // 조건: 대상 연도 내 미납 개월 수 6개월 이상
    if ($targetUnpaidMonthsCount >= 6) {
        $unpaidList[] = [
            'id' => $mid,
            'member_no' => $m['member_no'],
            'company_name' => $m['company_name'],
            'target_unpaid_months' => $targetUnpaidMonthsCount,
            'target_unpaid_amount' => $targetUnpaidAmount,
            'total_unpaid_amount' => max(0, $totalUnpaidAmount),
            'is_long_term' => ($targetUnpaidMonthsCount >= 12),
            'details' => $targetUnpaidDetails
        ];
    }
}

// 미납 개월수 내림차순 정렬
usort($unpaidList, function($a, $b) {
    return $b['target_unpaid_months'] <=> $a['target_unpaid_months'];
});

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10">
        <h1 class="text-xl font-bold text-gray-800">장기 미납 회원사 현황</h1>
    </header>

    <main class="flex-1 overflow-auto bg-gray-100 p-6 lg:p-8">
        <!-- 필터 영역 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">대상 연도 (기본: 전년도)</label>
                    <select name="target_year" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <?php for($y=date('Y'); $y>=2022; $y--): ?>
                            <option value="<?= $y ?>" <?= $y == $targetYear ? 'selected' : '' ?>><?= $y ?>년</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">보고 일자</label>
                    <input type="date" name="report_date" value="<?= htmlspecialchars($reportDate) ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <button type="submit" class="bg-gray-800 text-white px-5 py-1.5 rounded text-sm font-bold hover:bg-black transition">조회</button>
            </form>
        </div>

        <form action="unpaid_print.php" method="POST" target="_blank">
            <input type="hidden" name="target_year" value="<?= $targetYear ?>">
            <input type="hidden" name="report_date" value="<?= htmlspecialchars($reportDate) ?>">

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h2 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="ph ph-list-dashes text-primary text-xl"></i>
                        대상 회원 목록
                        <span class="text-xs font-normal text-gray-500 ml-2">(<?= $targetYear ?>년 기준 6개월 이상 미납 정회원: 총 <?= count($unpaidList) ?>개사)</span>
                    </h2>
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition flex items-center gap-1.5">
                        <i class="ph ph-file-text text-base"></i> 보고서 미리보기 (인쇄)
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-center border-collapse">
                        <thead class="bg-gray-50 text-gray-500 text-xs font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 w-12"><input type="checkbox" id="checkAll" checked class="rounded text-primary focus:ring-primary"></th>
                                <th class="px-4 py-3">회원번호</th>
                                <th class="px-4 py-3 text-left">회원사명</th>
                                <th class="px-4 py-3"><?= $targetYear ?>년 미납 개월수</th>
                                <th class="px-4 py-3 text-right"><?= $targetYear ?>년 미납금액</th>
                                <th class="px-4 py-3">비고 (장기미납)</th>
                                <th class="px-4 py-3 text-right bg-blue-50">전체 누적 미납금액</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($unpaidList)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-gray-400">조건에 해당하는 미납 회원사가 없습니다.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($unpaidList as $row): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" name="member_ids[]" value="<?= $row['id'] ?>" checked class="row-checkbox rounded text-primary focus:ring-primary">
                                        </td>
                                        <td class="px-4 py-3 text-gray-500"><?= $row['member_no'] ?></td>
                                        <td class="px-4 py-3 text-left font-bold text-gray-800">
                                            <a href="member_report.php?member_id=<?= $row['id'] ?>" target="_blank" class="hover:text-primary hover:underline transition-colors flex items-center gap-1">
                                                <?= htmlspecialchars($row['company_name']) ?>
                                                <i class="ph ph-arrow-square-out text-gray-400 text-xs"></i>
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 font-bold <?= $row['is_long_term'] ? 'text-red-600' : 'text-orange-500' ?>"><?= $row['target_unpaid_months'] ?>개월</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-700 relative group cursor-help">
                                            <?= number_format($row['target_unpaid_amount']) ?>원
                                            
                                            <!-- 툴팁 -->
                                            <div class="absolute right-0 top-full mt-1 w-40 bg-white border border-gray-200 shadow-xl rounded-lg p-3 z-50 hidden group-hover:block text-left text-xs font-normal">
                                                <div class="font-bold text-gray-800 mb-2 border-b pb-1"><?= $targetYear ?>년 미납 상세</div>
                                                <ul class="space-y-1">
                                                    <?php foreach($row['details'] as $d): ?>
                                                        <li class="flex justify-between items-center">
                                                            <span class="text-gray-500"><?= $d['month'] ?></span>
                                                            <span class="text-red-500 font-bold"><?= number_format($d['amount']) ?>원</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($row['is_long_term']): ?>
                                                <span class="bg-red-100 text-red-600 px-2 py-0.5 rounded-full text-xs font-bold">장기미납</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-blue-700 bg-blue-50/30"><?= number_format($row['total_unpaid_amount']) ?>원</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </main>
</div>

<script>
    document.getElementById('checkAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
</script>
</body>
</html>
