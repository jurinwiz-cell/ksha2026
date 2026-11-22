<?php
/**
 * unpaid_print.php - 장기 미납 회원사 현황 보고서 인쇄 미리보기
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// 파라미터 받기
$targetYear = (int)($_POST['target_year'] ?? date('Y') - 1);
$reportDate = $_POST['report_date'] ?? date('Y-m-d');
$memberIds = $_POST['member_ids'] ?? [];

if (empty($memberIds)) {
    die("<script>alert('선택된 회원사가 없습니다.'); window.close();</script>");
}

$targetDateEndObj = new DateTime("$targetYear-12-31");
$reportDateObj = new DateTime($reportDate);
$reportMonthKey = $reportDateObj->format('Y-m');
$reportMonthEndObj = new DateTime($reportDateObj->format('Y-m-t'));

// IN 절 처리를 위한 플레이스홀더 생성
$inQuery = implode(',', array_fill(0, count($memberIds), '?'));

// 1. 선택된 정회원 목록 및 초기/수기 미납액 조회
$stmtMembers = $pdo->prepare("
    SELECT m.id, m.member_no, m.company_name, m.join_date, m.withdraw_date, 
           IFNULL(iu.total, 0) as initial_unpaid, 
           IFNULL(d.unpaid_before_2022, 0) as manual_unpaid
    FROM tb_members m
    LEFT JOIN tb_initial_unpaid iu ON m.id = iu.member_id
    LEFT JOIN tb_member_details d ON m.id = d.member_id
    WHERE m.id IN ($inQuery)
    ORDER BY m.member_no ASC
");
$stmtMembers->execute($memberIds);
$members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

// 2. 모든 회원의 회비 이력 조회 (계산 편의상)
$stmtHistory = $pdo->query("SELECT * FROM tb_member_fee_history ORDER BY member_id, start_date DESC");
$historyRaw = $stmtHistory->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

function getFeeAtDate($history, $date) {
    if (empty($history)) return 0;
    foreach ($history as $h) {
        if ($h['start_date'] <= $date) return (int)$h['monthly_fee'];
    }
    return 0;
}

// 3. 납부 내역 조회 (선택된 회원, 보고 일자 및 보고월 기준)
$stmtPayments = $pdo->prepare("
    SELECT member_id, target_month, SUM(amount) as total_paid 
    FROM tb_dues_payments 
    WHERE target_month <= ? AND (payment_date IS NULL OR payment_date <= ?) AND member_id IN ($inQuery)
    GROUP BY member_id, target_month
");
$params = array_merge([$reportMonthKey, $reportDate . ' 23:59:59'], $memberIds);
$stmtPayments->execute($params);
$paymentsRaw = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

$paymentsMap = [];
foreach ($paymentsRaw as $p) {
    $paymentsMap[$p['member_id']][$p['target_month']] = (int)$p['total_paid'];
}

$unpaidList = [];
$totalTargetUnpaidAmountSum = 0;
$totalCumulativeUnpaidAmountSum = 0;

foreach ($members as $m) {
    $mid = $m['id'];
    $mHistory = $historyRaw[$mid] ?? [];
    $mPayments = $paymentsMap[$mid] ?? [];
    
    $totalExpected = 0;
    $totalPaid = 0;
    
    $targetUnpaidMonthsCount = 0;
    $targetUnpaidAmount = 0;
    
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
            
            if ($currYear === $targetYear) {
                if ($fee > 0 && ($fee - $paid) > 0) {
                    $targetUnpaidMonthsCount++;
                    $targetUnpaidAmount += ($fee - $paid);
                }
            }
        }
        $curr->modify('+1 month');
    }
    
    $pre2022Unpaid = (int)$m['initial_unpaid'] + (int)$m['manual_unpaid'];
    $totalUnpaidAmount = $totalExpected + $pre2022Unpaid - $totalPaid;
    
    $unpaidList[] = [
        'member_no' => $m['member_no'],
        'company_name' => $m['company_name'],
        'target_unpaid_months' => $targetUnpaidMonthsCount,
        'target_unpaid_amount' => $targetUnpaidAmount,
        'total_unpaid_amount' => max(0, $totalUnpaidAmount),
        'is_long_term' => ($targetUnpaidMonthsCount >= 12),
    ];
    
    $totalTargetUnpaidAmountSum += $targetUnpaidAmount;
    $totalCumulativeUnpaidAmountSum += max(0, $totalUnpaidAmount);
}

// 미납 개월수 내림차순 정렬
usort($unpaidList, function($a, $b) {
    return $b['target_unpaid_months'] <=> $a['target_unpaid_months'];
});

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>장기 미납 회원사 현황 보고서</title>
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <script src="/static/vendor/js/phosphor-icons.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Pretendard', sans-serif; background: #e5e7eb; }
        .page-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            padding: 20mm;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 10px 8px; text-align: center; }
        .report-table th { background-color: #f3f4f6; font-weight: bold; }
        .report-table th.bg-blue-50 { background-color: #eff6ff !important; }
        .report-table td.text-right { text-align: right; }
        .report-table td.text-left { text-align: left; }
        @media print {
            body { background: #fff !important; margin: 0; }
            .page-container { width: 100%; min-height: auto; margin: 0; padding: 10mm; box-shadow: none; }
            .no-print { display: none !important; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>

<div class="fixed top-4 right-4 no-print flex gap-2">
    <button onclick="window.close()" class="bg-gray-500 text-white px-4 py-2 rounded shadow hover:bg-gray-600 transition flex items-center gap-2">
        <i class="ph ph-x text-lg"></i> 닫기
    </button>
    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition flex items-center gap-2">
        <i class="ph ph-printer text-lg"></i> 인쇄하기
    </button>
</div>

<div class="page-container text-gray-900">
    <h1 class="text-3xl font-bold text-center mb-8 pb-4 border-b-2 border-gray-800">장기 미납 회원사 현황</h1>
    
    <div class="flex justify-between items-end mb-4 text-sm">
        <div>
            <p><span class="font-bold">보고일자:</span> <?= htmlspecialchars($reportDate) ?></p>
            <p><span class="font-bold">대상연도:</span> <?= $targetYear ?>년 기준</p>
        </div>
        <div class="text-right">
            <p><span class="font-bold">대상:</span> 선택된 장기/일반 미납 정회원 (총 <?= count($unpaidList) ?>개사)</p>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 8%;">No.</th>
                <th style="width: 25%;">회원사명</th>
                <th style="width: 14%;"><?= $targetYear ?>년<br>미납 개월수</th>
                <th style="width: 18%;"><?= $targetYear ?>년<br>미납금액</th>
                <th style="width: 15%;">비고</th>
                <th style="width: 20%;" class="bg-blue-50">전체 누적 미납금액</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($unpaidList as $idx => $row): ?>
            <tr>
                <td><?= $idx + 1 ?></td>
                <td class="text-left font-bold"><?= htmlspecialchars($row['company_name']) ?></td>
                <td class="font-bold <?= $row['is_long_term'] ? 'text-red-600' : '' ?>"><?= $row['target_unpaid_months'] ?>개월</td>
                <td class="text-right"><?= number_format($row['target_unpaid_amount']) ?>원</td>
                <td>
                    <?php if ($row['is_long_term']): ?>
                        <span class="font-bold text-red-600">장기미납</span>
                    <?php endif; ?>
                </td>
                <td class="text-right font-bold text-blue-800" style="background-color: #f8fafc;"><?= number_format($row['total_unpaid_amount']) ?>원</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3">합계</th>
                <th class="text-right"><?= number_format($totalTargetUnpaidAmountSum) ?>원</th>
                <th></th>
                <th class="text-right bg-blue-50 text-blue-800"><?= number_format($totalCumulativeUnpaidAmountSum) ?>원</th>
            </tr>
        </tfoot>
    </table>

    <div class="mt-8 text-sm text-gray-500">
        * 본 보고서는 <strong><?= $targetYear ?>년도</strong> 내의 미납 내역을 기준으로 6개월 이상 미납한 정회원을 산출한 결과입니다.<br>
        * 해당 연도 내 미납 개월 수가 12개월인 경우 '장기미납'으로 분류됩니다.<br>
        * '전체 누적 미납금액'은 가입일 이후부터 <?= $targetYear ?>년 말일까지 발생한 전체 미납액의 총합입니다.
    </div>
</div>

</body>
</html>

