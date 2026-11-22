<?php
/**
 * status_report.php - 총괄 납부현황 보고서
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle   = '월회비 납부현황';
$currentPage = 'status_report'; // 사이드바 활성화용

// 파라미터 받기
$reportYear  = (int)($_GET['year'] ?? date('Y'));
$reportMonth = (int)($_GET['month'] ?? date('m'));
$startDate   = $_GET['start_date'] ?? ($reportYear - 1) . '-01-01';
$endDate     = $_GET['end_date'] ?? ($reportYear - 1) . '-12-31';

// -------------------------------------------------------------
// 1) 당해년도 납부현황 데이터 계산
// -------------------------------------------------------------
$startOfThisYearDate = sprintf('%04d-01-01', $reportYear);
$endOfReportMonthDate = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $reportYear, $reportMonth)));
$startOfThisYearTarget = sprintf('%04d-01', $reportYear);
$endOfReportMonthTarget = sprintf('%04d-%02d', $reportYear, $reportMonth);

// A. 전년도분 납부액 (당해년도 1월 1일부터 선택한 월 말일까지 입금된 것 중, 대상월이 당해년도 1월보다 과거인 것)
$stmtA = $pdo->prepare("
    SELECT COUNT(DISTINCT member_id) as cnt, SUM(amount) as amt 
    FROM tb_dues_payments 
    WHERE payment_date >= ? AND payment_date <= ? AND target_month < ?
");
$stmtA->execute([$startOfThisYearDate, $endOfReportMonthDate, $startOfThisYearTarget]);
$dataA = $stmtA->fetch(PDO::FETCH_ASSOC);
$cntA = (int)$dataA['cnt'];
$amtA = (int)$dataA['amt'];

// B. 당해년도 납부액 (당해년도 1월 1일부터 선택한 월 말일까지 입금된 것 중, 대상월이 당해년도 1월 ~ 선택한 월 사이인 것)
$stmtB = $pdo->prepare("
    SELECT COUNT(DISTINCT member_id) as cnt, SUM(amount) as amt 
    FROM tb_dues_payments 
    WHERE payment_date >= ? AND payment_date <= ? AND target_month >= ? AND target_month <= ?
");
$stmtB->execute([$startOfThisYearDate, $endOfReportMonthDate, $startOfThisYearTarget, $endOfReportMonthTarget]);
$dataB = $stmtB->fetch(PDO::FETCH_ASSOC);
$cntB = (int)$dataB['cnt'];
$amtB = (int)$dataB['amt'];

// 총 입금 (A + B)
// A와 B의 조건을 합친 교집합 인원수를 구하기 위해 별도 쿼리 실행
$stmtTotal = $pdo->prepare("
    SELECT COUNT(DISTINCT member_id) as cnt
    FROM tb_dues_payments 
    WHERE payment_date >= ? AND payment_date <= ? 
      AND target_month <= ?
");
$stmtTotal->execute([$startOfThisYearDate, $endOfReportMonthDate, $endOfReportMonthTarget]);
$dataTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
$cntTotal = (int)$dataTotal['cnt'];
$amtTotal = $amtA + $amtB;

// C. 전년도분 미납액 계산
$prevYear = $reportYear - 1;
$members = $pdo->query("SELECT id, member_type, join_date, withdraw_date FROM tb_members WHERE status != '탈퇴' OR withdraw_date >= '{$prevYear}-01-01'")->fetchAll(PDO::FETCH_ASSOC);
$feeHistoryRaw = $pdo->query("SELECT * FROM tb_member_fee_history ORDER BY member_id, start_date DESC")->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

function getFeeAtDate($history, $date) {
    if (empty($history)) return 0;
    foreach ($history as $h) {
        if ($h['start_date'] <= $date) return (int)$h['monthly_fee'];
    }
    return 0;
}

$prevYearUnpaidTotal = 0;
$prevYearUnpaidCnt = 0;
$prevYearUnpaidRegCnt = 0;
$prevYearUnpaidRegAmt = 0;

foreach ($members as $m) {
    $mid = $m['id'];
    $history = $feeHistoryRaw[$mid] ?? [];
    
    // 전년도 기대 회비
    $expected = 0;
    for ($mth = 1; $mth <= 12; $mth++) {
        $monthFirst = sprintf('%04d-%02d-01', $prevYear, $mth);
        $monthLast  = date('Y-m-t', strtotime($monthFirst));
        $isMemberActive = ($m['join_date'] && $m['join_date'] <= $monthLast) && 
                          (!$m['withdraw_date'] || $m['withdraw_date'] >= $monthFirst);
        if ($isMemberActive) {
            $expected += getFeeAtDate($history, $monthFirst);
        }
    }
    
    // 전년도분 납부액 (전년도 말 기준)
    $stmtPaid = $pdo->prepare("SELECT SUM(amount) FROM tb_dues_payments WHERE member_id = ? AND LEFT(target_month, 4) = ? AND payment_date <= ?");
    $stmtPaid->execute([$mid, $prevYear, "{$prevYear}-12-31"]);
    $paid = (int)$stmtPaid->fetchColumn();
    
    $diff = $expected - $paid;
    if ($diff > 0) {
        $prevYearUnpaidTotal += $diff;
        $prevYearUnpaidCnt++;
        if ($m['member_type'] === '정회원') {
            $prevYearUnpaidRegCnt++;
            $prevYearUnpaidRegAmt += $diff;
        }
    }
}

// -------------------------------------------------------------
// 2) 기준액별 납부현황 계산 (선택기간내)
// -------------------------------------------------------------
$stmtT2 = $pdo->prepare("
    SELECT member_id, SUM(amount) as amt 
    FROM tb_dues_payments 
    WHERE payment_date >= ? AND payment_date <= ?
    GROUP BY member_id
");
$stmtT2->execute([$startDate, $endDate]);
$memberPayments = $stmtT2->fetchAll(PDO::FETCH_ASSOC);

$baseFeeStats = [];
$t2TotalCnt = 0;
$t2TotalAmt = 0;

foreach ($memberPayments as $mp) {
    $mid = $mp['member_id'];
    $amt = (int)$mp['amt'];
    
    // 현재 (또는 조회 종료일 기준) 기준액 가져오기
    $history = $feeHistoryRaw[$mid] ?? [];
    $currentFee = getFeeAtDate($history, $endDate); 
    if ($currentFee == 0) continue; // 기준액 0원은 통계에서 제외 (혹은 필요시 포함)
    
    if (!isset($baseFeeStats[$currentFee])) {
        $baseFeeStats[$currentFee] = ['cnt' => 0, 'amt' => 0];
    }
    $baseFeeStats[$currentFee]['cnt']++;
    $baseFeeStats[$currentFee]['amt'] += $amt;
    
    $t2TotalCnt++;
    $t2TotalAmt += $amt;
}
krsort($baseFeeStats); // 기준액 내림차순 정렬

// -------------------------------------------------------------
// HTML 출력부
// -------------------------------------------------------------
require_once __DIR__ . '/includes/html_head.php';
?>
<style>
    /* 보고서 전용 인쇄 및 화면 스타일 */
    @media print {
        @page { size: A4 portrait; margin: 15mm; }
        body { background: #fff !important; }
        .no-print { display: none !important; }
        .print-container { width: 100% !important; max-width: none !important; padding: 0 !important; box-shadow: none !important; }
    }
    .report-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px; }
    .report-table th, .report-table td { border: 1px solid #111; padding: 10px; text-align: center; }
    .report-table th { background-color: #fae3b4; font-weight: bold; }
    .report-table td.bg-light-purple { background-color: #e4d7f5; }
    .report-table td.bg-light-yellow { background-color: #fff2cc; }
    .report-table td.text-right { text-align: right; }
    .report-table td.text-left { text-align: left; }
    .report-title { font-size: 24px; font-weight: bold; text-align: center; margin-bottom: 10px; }
    .report-subtitle { text-align: right; margin-bottom: 20px; font-size: 14px; color: #333; }
</style>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0 no-print">
        <h1 class="text-xl font-bold text-gray-800">월회비 납부현황 보고서</h1>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition flex items-center gap-1.5">
                <i class="ph ph-printer text-base"></i> 인쇄 / PDF 저장
            </button>
        </div>
    </header>

    <main class="flex-1 overflow-auto bg-gray-100 p-6 lg:p-8">
        <!-- 상단 필터부 (인쇄시 숨김) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6 no-print max-w-4xl mx-auto">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">기준 년/월 (1번 표)</label>
                    <div class="flex gap-2">
                        <select name="year" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
                            <?php for($y=date('Y')+1; $y>=2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $y == $reportYear ? 'selected' : '' ?>><?= $y ?>년</option>
                            <?php endfor; ?>
                        </select>
                        <select name="month" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == $reportMonth ? 'selected' : '' ?>><?= $m ?>월</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">납부기간 (2번 표)</label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
                        <span>~</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="border border-gray-300 rounded px-3 py-1.5 text-sm">
                    </div>
                </div>
                <button type="submit" class="bg-gray-800 text-white px-5 py-1.5 rounded text-sm font-bold hover:bg-black transition">적용</button>
            </form>
        </div>

        <!-- 보고서 본문 -->
        <div class="print-container bg-white rounded-xl shadow-sm border border-gray-200 p-10 max-w-4xl mx-auto text-black">
            
            <div class="report-title">한국사회주택협회 월회비 납부현황</div>
            <div class="report-subtitle"><?= $reportYear ?>년 <?= $reportMonth ?>월 기준</div>

            <!-- Table 1 -->
            <table class="report-table" style="border-top: 2px dashed #0055ff;">
                <thead>
                    <tr>
                        <th class="text-left" style="width: 40%;"><?= $reportYear ?>년 월회비 납부현황(<?= $reportMonth ?>월분까지)</th>
                        <th>회원사 수</th>
                        <th>금액</th>
                        <th>비고</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left"><?= $reportYear ?>년 입금</td>
                        <td><?= number_format($cntTotal) ?></td>
                        <td class="text-right"><?= number_format($amtTotal) ?></td>
                        <td><?= $reportYear ?>년 <?= $reportMonth ?>월분</td>
                    </tr>
                    <tr>
                        <td class="text-left bg-light-yellow font-bold"><?= $reportYear ?>년 입금 총액(A+B)</td>
                        <td class="bg-light-yellow font-bold"><?= number_format($cntTotal) ?></td>
                        <td class="text-right bg-light-yellow font-bold"><?= number_format($amtTotal) ?></td>
                        <td class="bg-light-yellow"></td>
                    </tr>
                    <tr>
                        <td class="text-left">전년도분 납부액(A)</td>
                        <td><?= number_format($cntA) ?></td>
                        <td class="text-right"><?= number_format($amtA) ?></td>
                        <td class="text-sm text-gray-600"><?= substr($prevYear, 2) ?>년 미납분 납부</td>
                    </tr>
                    <tr>
                        <td class="text-left"><?= $reportYear ?>년분 납부액(B)</td>
                        <td><?= number_format($cntB) ?></td>
                        <td class="text-right"><?= number_format($amtB) ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td class="text-left" style="border-bottom: 2px dashed #0055ff;"><?= $prevYear ?>년분 미납액</td>
                        <td style="border-bottom: 2px dashed #0055ff;"><?= number_format($prevYearUnpaidCnt) ?></td>
                        <td class="text-right" style="border-bottom: 2px dashed #0055ff;"><?= number_format($prevYearUnpaidTotal) ?></td>
                        <td class="text-sm" style="border-bottom: 2px dashed #0055ff;">정회원 <?= number_format($prevYearUnpaidRegAmt) ?>원(<?= $prevYearUnpaidRegCnt ?>곳)</td>
                    </tr>
                </tbody>
            </table>

            <!-- Table 2 -->
            <div class="mt-8 mb-2 font-bold text-gray-800" style="font-size: 16px;">
                기준액별 납부현황 (선택기간: <?= $startDate ?> ~ <?= $endDate ?>)
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>기준액</th>
                        <th>회원수</th>
                        <th>납부금액</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($baseFeeStats)): ?>
                    <tr>
                        <td colspan="3" class="text-gray-400 py-4">해당 기간 내 납부 내역이 없습니다.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($baseFeeStats as $fee => $stat): ?>
                        <tr>
                            <td><?= number_format($fee) ?></td>
                            <td><?= number_format($stat['cnt']) ?></td>
                            <td class="text-right"><?= number_format($stat['amt']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="bg-light-yellow font-bold">합계</td>
                        <td class="bg-light-yellow font-bold"><?= number_format($t2TotalCnt) ?></td>
                        <td class="text-right bg-light-yellow font-bold"><?= number_format($t2TotalAmt) ?></td>
                    </tr>
                </tfoot>
            </table>

            <!-- 이미지에 있는 다른 테이블들은 프롬프트에서 생략되었으나,
                 사용자가 "아래 방식으로 납부현황보고서를 생성해줘"라고 했으므로
                 가장 핵심적인 두 가지 표를 동적으로 구현함 -->
            
            <div class="mt-10 text-sm text-gray-500 text-center no-print">
                <i class="ph ph-info"></i> 위 데이터는 현재 시스템의 납부 내역 및 기준액 설정 이력을 바탕으로 실시간 계산된 결과입니다.
            </div>

        </div>
    </main>
</div>

</body>
</html>
