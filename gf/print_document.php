<?php
/**
 * print_document.php - 영수증 및 안내문 인쇄용 페이지
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$type = $_GET['type'] ?? 'receipt'; // 'receipt' (영수증) 또는 'invoice' (안내문)
$memberId = (int)($_GET['member_id'] ?? 0);
$startYear = (int)($_GET['start_year'] ?? date('Y'));
$startMonth = (int)($_GET['start_month'] ?? 1);
$endYear = (int)($_GET['end_year'] ?? date('Y'));
$endMonth = (int)($_GET['end_month'] ?? (int)date('m'));

$startDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
$endDate = sprintf('%04d-%02d-01', $endYear, $endMonth);

$paymentId = (int)($_GET['payment_id'] ?? 0);

if ($paymentId > 0) {
    // 단일 결제 내역 영수증 출력
    $stmt = $pdo->prepare("SELECT * FROM tb_dues_payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) die("결제 내역을 찾을 수 없습니다.");
    
    $memberId = $payment['member_id'];
}

if ($memberId <= 0) {
    die("회원사를 선택해주세요.");
}

// 회원 정보 조회
$stmt = $pdo->prepare("
    SELECT m.*, IFNULL(iu.total, 0) as initial_unpaid, IFNULL(d.unpaid_before_2022, 0) as manual_unpaid
    FROM tb_members m
    LEFT JOIN tb_initial_unpaid iu ON m.id = iu.member_id
    LEFT JOIN tb_member_details d ON m.id = d.member_id
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("회원 정보를 찾을 수 없습니다.");
}

// 회비 이력 정보
$stmtHistory = $pdo->prepare("SELECT * FROM tb_member_fee_history WHERE member_id = ? ORDER BY start_date DESC");
$stmtHistory->execute([$memberId]);
$history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

function getFeeAtDate($history, $date) {
    foreach ($history as $h) {
        if ($h['start_date'] <= $date) return (int)$h['monthly_fee'];
    }
    return 0; // 이력 시작일보다 이전이면 0 (과거에는 회비 책정 안됨)
}

if ($paymentId > 0) {
    // 단일 결제인 경우, $paymentDetails와 $summary를 즉시 설정
    $paymentDetails = [[
        'payment_date' => $payment['payment_date'],
        'amount' => $payment['amount'],
        'target_month' => $payment['target_month'],
        'category' => $payment['category']
    ]];
    $summary = ['expected' => 0, 'paid' => $payment['amount'], 'unpaid' => 0];
    
    // 대상기간 표기를 위해 시작/종료 연월 설정
    $startYear = substr($payment['target_month'], 0, 4);
    $startMonth = (int)substr($payment['target_month'], 5, 2);
    $endYear = $startYear;
    $endMonth = $startMonth;
} else {
    // 납부 내역
    $stmtPayments = $pdo->prepare("
        SELECT target_month, SUM(amount) as total_paid 
        FROM tb_dues_payments 
        WHERE member_id = ? AND target_month >= ? AND target_month <= ?
        GROUP BY target_month
    ");
    $stmtPayments->execute([$memberId, substr($startDate, 0, 7), substr($endDate, 0, 7)]);
    $paymentsMap = $stmtPayments->fetchAll(PDO::FETCH_KEY_PAIR);

    // 상세 내역도 가져오기 (영수증용)
    $stmtPaymentDetails = $pdo->prepare("
        SELECT payment_date, amount, target_month, category 
        FROM tb_dues_payments 
        WHERE member_id = ? AND target_month >= ? AND target_month <= ?
        ORDER BY payment_date ASC, target_month ASC
    ");
    $stmtPaymentDetails->execute([$memberId, substr($startDate, 0, 7), substr($endDate, 0, 7)]);
    $paymentDetails = $stmtPaymentDetails->fetchAll(PDO::FETCH_ASSOC);
}

if ($paymentId == 0) {
    $reportData = [];
    $summary = ['expected' => 0, 'paid' => 0, 'unpaid' => 0];

    $curr = new DateTime($startDate);
    $end = new DateTime($endDate);

    $earliestHistoryDate = count($history) > 0 ? substr(end($history)['start_date'], 0, 7) . '-01' : null;

    while ($curr <= $end) {
        $mKey = $curr->format('Y-m');
        $monthFirst = $curr->format('Y-m-01');
        $monthLast = $curr->format('Y-m-t');

        // 2021년 12월 이전 기간은 초기 이월 데이터(initial_unpaid)에서 미납금을 관리하므로 개별 월 청구에서 제외
        if ($monthLast < '2022-01-01') {
            $curr->modify('+1 month');
            continue;
        }

        // 책정 이력 시작월보다 이전 달은 아예 표시하지 않음
        if ($earliestHistoryDate && $monthLast < $earliestHistoryDate) {
            $curr->modify('+1 month');
            continue;
        }

        $isMemberActive = ($member['join_date'] && $member['join_date'] <= $monthLast) && 
                          (!$member['withdraw_date'] || $member['withdraw_date'] >= $monthFirst);

        $fee = ($isMemberActive) ? getFeeAtDate($history, $curr->format('Y-m-d')) : 0;
        $paid = (int)($paymentsMap[$mKey] ?? 0);
        
        $reportData[] = [
            'month' => $mKey,
            'expected' => $fee,
            'paid' => $paid,
            'diff' => $fee - $paid
        ];
        
        $summary['expected'] += $fee;
        $summary['paid'] += $paid;
        
        $curr->modify('+1 month');
    }
    $summary['unpaid'] = max(0, $summary['expected'] - $summary['paid']) + (int)$member['initial_unpaid'] + (int)$member['manual_unpaid'];
}

// 출력 일자
$today = date('Y년 m월 d일');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= $type === 'receipt' ? '영수증' : '납부 안내문' ?></title>
    <!-- Tailwind CSS (CDN for simple print page) -->
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <style>
        @page { size: A4; margin: 20mm; }
        body { font-family: 'Malgun Gothic', 'Apple SD Gothic Neo', sans-serif; background: #f9f9f9; }
        .page-container { background: white; max-width: 210mm; min-height: 297mm; margin: 0 auto; padding: 40px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        @media print {
            body { background: white; }
            .page-container { box-shadow: none; margin: 0; padding: 0; width: 100%; min-height: auto; }
            .no-print { display: none !important; }
        }
        .title { text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 10px; border-bottom: 2px solid #222; padding-bottom: 20px; margin-bottom: 40px; }
        .section-title { font-size: 16px; font-weight: bold; margin-bottom: 10px; border-left: 4px solid #1e3a8a; padding-left: 10px; }
        .data-table { w-full border-collapse mb-8; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 10px 15px; text-align: center; font-size: 14px; }
        .data-table th { background-color: #f3f4f6; font-weight: bold; }
        .sign-area { text-align: right; margin-top: 60px; font-size: 18px; }
        .stamp { display: inline-block; width: 60px; height: 60px; border: 2px solid red; border-radius: 50%; color: red; text-align: center; line-height: 56px; font-size: 18px; font-weight: bold; margin-left: 20px; vertical-align: middle; }
    </style>
</head>
<body>

<div class="no-print p-4 bg-gray-800 text-white flex justify-between items-center fixed top-0 left-0 w-full z-50">
    <div>
        <span class="font-bold"><?= $type === 'receipt' ? '영수증' : '납부 안내문' ?> 미리보기</span>
        <span class="text-sm text-gray-400 ml-4">확인 후 인쇄 버튼을 눌러주세요.</span>
    </div>
    <div class="space-x-2">
        <button onclick="window.close()" class="px-4 py-2 border border-gray-600 rounded hover:bg-gray-700 transition text-sm">닫기</button>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm font-bold">인쇄하기</button>
    </div>
</div>

<div class="pt-20 no-print"></div>

<div class="page-container">
    <?php if ($type === 'receipt'): ?>
        <!-- 영수증 양식 -->
        <div class="title">영 수 증</div>
        
        <div class="flex justify-between mb-8">
            <div class="text-lg">
                <strong>회원사명 :</strong> <?= htmlspecialchars($member['company_name']) ?><br>
                <div class="mt-2 text-sm text-gray-600">대상기간 : <?= $startYear ?>.<?= sprintf('%02d', $startMonth) ?> ~ <?= $endYear ?>.<?= sprintf('%02d', $endMonth) ?></div>
            </div>
            <div class="text-right text-gray-500 text-sm">
                발행일 : <?= $today ?>
            </div>
        </div>

        <div class="bg-gray-50 border border-gray-200 p-6 text-center mb-8 rounded">
            <div class="text-gray-500 text-sm mb-2">총 납부 금액</div>
            <div class="text-4xl font-bold text-gray-800">&#8361; <?= number_format($summary['paid']) ?></div>
            <div class="mt-2 text-sm text-gray-500">위 금액을 정히 영수하였습니다.</div>
        </div>

        <div class="section-title">상세 납부 내역</div>
        <table class="w-full data-table">
            <thead>
                <tr>
                    <th>입금일자</th>
                    <th>납부대상월</th>
                    <th>구분</th>
                    <th>금액</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paymentDetails)): ?>
                <tr>
                    <td colspan="4" class="py-4 text-gray-400">선택된 기간 내에 납부 내역이 없습니다.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($paymentDetails as $pd): ?>
                    <tr>
                        <td><?= $pd['payment_date'] ?></td>
                        <td><?= $pd['target_month'] ?></td>
                        <td><?= $pd['category'] ?></td>
                        <td class="text-right"><?= number_format($pd['amount']) ?>원</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-gray-50 font-bold">
                    <td colspan="3">합 계</td>
                    <td class="text-right text-blue-600"><?= number_format($summary['paid']) ?>원</td>
                </tr>
            </tfoot>
        </table>

    <?php else: ?>
        <!-- 안내문 양식 -->
        <div class="title">회비 납부 안내문</div>

        <div class="flex justify-between mb-8">
            <div class="text-lg">
                <strong>수신 :</strong> <?= htmlspecialchars($member['company_name']) ?> 귀하<br>
                <div class="mt-2 text-sm text-gray-600">조회기간 : <?= $startYear ?>.<?= sprintf('%02d', $startMonth) ?> ~ <?= $endYear ?>.<?= sprintf('%02d', $endMonth) ?></div>
            </div>
            <div class="text-right text-gray-500 text-sm">
                발송일 : <?= $today ?>
            </div>
        </div>

        <p class="mb-6 leading-relaxed">
            귀 사의 무궁한 발전을 기원합니다.<br>
            아래와 같이 미납 회비 현황을 안내해 드리오니, 확인 후 납부를 부탁드립니다.
        </p>

        <div class="bg-red-50 border border-red-200 p-6 text-center mb-8 rounded">
            <div class="text-red-500 text-sm mb-2 font-bold">총 미납 금액</div>
            <div class="text-4xl font-bold text-red-600">&#8361; <?= number_format($summary['unpaid']) ?></div>
        </div>

        <div class="section-title">월별 부과 및 미납 상세 내역</div>
        <table class="w-full data-table">
            <thead>
                <tr>
                    <th>대상월</th>
                    <th>책정 회비</th>
                    <th>납부 금액</th>
                    <th>미납액</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $hasUnpaid = false;
                foreach ($reportData as $row): 
                    if ($row['diff'] <= 0 && $row['expected'] == 0) continue; // 회비 발생 없고 미납 없는 달은 생략
                    $hasUnpaid = true;
                ?>
                <tr>
                    <td><?= $row['month'] ?></td>
                    <td class="text-right"><?= number_format($row['expected']) ?>원</td>
                    <td class="text-right text-blue-600"><?= number_format($row['paid']) ?>원</td>
                    <td class="text-right <?= $row['diff'] > 0 ? 'text-red-500 font-bold' : '' ?>">
                        <?= $row['diff'] > 0 ? number_format($row['diff']).'원' : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <!-- 초기/이전 이월 데이터 표시 -->
                <?php if ($member['initial_unpaid'] > 0 || $member['manual_unpaid'] > 0): ?>
                <tr>
                    <td class="bg-yellow-50 text-left px-4">이전 미납 이월액 합계</td>
                    <td colspan="2" class="bg-yellow-50 text-right">-</td>
                    <td class="bg-yellow-50 text-right text-red-500 font-bold"><?= number_format($member['initial_unpaid'] + $member['manual_unpaid']) ?>원</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-gray-50 font-bold">
                    <td>합 계</td>
                    <td class="text-right"><?= number_format($summary['expected']) ?>원</td>
                    <td class="text-right text-blue-600"><?= number_format($summary['paid']) ?>원</td>
                    <td class="text-right text-red-600"><?= number_format($summary['unpaid']) ?>원</td>
                </tr>
            </tfoot>
        </table>

        <div class="mt-8 border-t-2 border-gray-800 pt-6">
            <h4 class="font-bold text-lg mb-3">납부 계좌 안내</h4>
            <div class="bg-gray-100 p-4 rounded text-center text-lg">
                <strong>(은행명) 000-000-000000</strong> <span class="text-gray-500 ml-2">예금주: 협회명</span>
            </div>
            <p class="text-sm text-gray-500 mt-2 text-center">* 입금 시 반드시 회원사명으로 입금해 주시기 바랍니다.</p>
        </div>

    <?php endif; ?>

    <!-- 공통 직인 영역 -->
    <div class="sign-area">
        사단법인 OOO 협회 <div class="stamp">직인</div>
    </div>
</div>

</body>
</html>

