<?php
/**
 * member_print_receipt.php - 회원사용 영수증 출력 페이지
 */
require_once __DIR__ . '/includes/member_auth.php';
require_once __DIR__ . '/includes/db.php';

$type = $_GET['type'] ?? 'receipt';
$memberId = $_SESSION['member_id']; // 현재 로그인된 회원사 ID
$paymentId = (int)($_GET['payment_id'] ?? 0);

// 연월 파싱 (GET으로 2026-01 형태가 들어옴)
$startMonthStr = $_GET['start_month'] ?? date('Y-01');
$endMonthStr = $_GET['end_month'] ?? date('Y-m');

$startYear = (int)substr($startMonthStr, 0, 4);
$startMonth = (int)substr($startMonthStr, 5, 2);
$endYear = (int)substr($endMonthStr, 0, 4);
$endMonth = (int)substr($endMonthStr, 5, 2);

$startDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
$endDate = sprintf('%04d-%02d-01', $endYear, $endMonth);

$paymentDate = $_GET['payment_date'] ?? '';

// 회원 정보 조회
$stmt = $pdo->prepare("SELECT * FROM tb_members WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("회원 정보를 찾을 수 없습니다.");
}

if ($paymentId > 0) {
    // 1. 단일 결제인 경우
    $stmt = $pdo->prepare("SELECT * FROM tb_dues_payments WHERE id = ? AND member_id = ?");
    $stmt->execute([$paymentId, $memberId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) die("결제 내역을 찾을 수 없거나 권한이 없습니다.");

    $paymentDetails = [[
        'payment_date' => $payment['payment_date'],
        'amount' => $payment['amount'],
        'target_month' => $payment['target_month'],
        'category' => $payment['category'] ?? '일반회비'
    ]];
    $summary = ['paid' => $payment['amount']];
    
    $startYear = (int)substr($payment['target_month'], 0, 4);
    $startMonth = (int)substr($payment['target_month'], 5, 2);
    $endYear = $startYear;
    $endMonth = $startMonth;
} elseif ($paymentDate) {
    // 2. 특정 입금일(거래일) 기준 모든 내역
    $stmtPaymentDetails = $pdo->prepare("
        SELECT payment_date, amount, target_month, category 
        FROM tb_dues_payments 
        WHERE member_id = ? AND payment_date = ?
        ORDER BY target_month ASC
    ");
    $stmtPaymentDetails->execute([$memberId, $paymentDate]);
    $paymentDetails = $stmtPaymentDetails->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$paymentDetails) die("해당 일자의 납부 내역이 없습니다.");

    $totalPaid = 0;
    $minTarget = '9999-99';
    $maxTarget = '0000-00';
    foreach ($paymentDetails as $pd) {
        $totalPaid += $pd['amount'];
        if ($pd['target_month'] < $minTarget) $minTarget = $pd['target_month'];
        if ($pd['target_month'] > $maxTarget) $maxTarget = $pd['target_month'];
    }
    $summary = ['paid' => $totalPaid];
    
    $startYear = (int)substr($minTarget, 0, 4);
    $startMonth = (int)substr($minTarget, 5, 2);
    $endYear = (int)substr($maxTarget, 0, 4);
    $endMonth = (int)substr($maxTarget, 5, 2);
} else {
    // 3. 다중 결제(기간별) 내역
    $stmtPaymentDetails = $pdo->prepare("
        SELECT payment_date, amount, target_month, category 
        FROM tb_dues_payments 
        WHERE member_id = ? AND target_month >= ? AND target_month <= ?
        ORDER BY payment_date ASC, target_month ASC
    ");
    $stmtPaymentDetails->execute([$memberId, substr($startDate, 0, 7), substr($endDate, 0, 7)]);
    $paymentDetails = $stmtPaymentDetails->fetchAll(PDO::FETCH_ASSOC);
    
    $totalPaid = 0;
    foreach ($paymentDetails as $pd) {
        $totalPaid += $pd['amount'];
    }
    $summary = ['paid' => $totalPaid];
}

// 출력 일자
$today = date('Y년 m월 d일');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>영수증 | 한국사회주택협회</title>
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
    <div class="flex items-center gap-4">
        <span class="font-bold">영수증 미리보기</span>
        <span class="text-sm text-gray-400">내역을 확인하신 후 인쇄 버튼을 눌러주세요.</span>
    </div>
    <div class="space-x-2">
        <button onclick="window.close()" class="px-4 py-2 border border-gray-600 rounded hover:bg-gray-700 transition text-sm">닫기</button>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm font-bold flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg> 인쇄하기
        </button>
    </div>
</div>

<div class="pt-20 no-print"></div>

<div class="page-container">
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
                    <td><?= $pd['category'] ?? '일반회비' ?></td>
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

    <!-- 공통 직인 영역 -->
    <div class="sign-area">
        사단법인 한국사회주택협회 <div class="stamp">직인</div>
    </div>
</div>

</body>
</html>

