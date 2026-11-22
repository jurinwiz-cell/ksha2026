<?php
/**
 * send_bulk_invoices.php - 일괄 납부 안내문 생성 및 이메일 발송
 *
 * POST actions:
 *   preview - 미납 정회원 목록 조회
 *   send    - 선택된 회원사에 메일 발송
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

$action     = $_POST['action']      ?? '';
$startYear  = (int)($_POST['start_year']  ?? date('Y'));
$startMonth = (int)($_POST['start_month'] ?? 1);
$endYear    = (int)($_POST['end_year']    ?? date('Y'));
$endMonth   = (int)($_POST['end_month']   ?? (int)date('m'));

$startDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
$endDate   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
$startYM   = substr($startDate, 0, 7);
$endYM     = substr($endDate, 0, 7);

// ─── 공통 함수 ───────────────────────────────────────────────────────────────

function _getFeeAt(array $history, string $date): int
{
    foreach ($history as $h) {
        if ($h['start_date'] <= $date) return (int)$h['monthly_fee'];
    }
    return 0;
}

/**
 * 조회기간 내 미납액(당기분) 계산
 */
function calcPeriodUnpaid(PDO $pdo, array $member, array $history, string $startDate, string $endDate): int
{
    $startYM = substr($startDate, 0, 7);
    $endYM   = substr($endDate, 0, 7);
    $nowYM   = date('Y-m');

    // 대상월 기준 납부액 합계
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tb_dues_payments
                            WHERE member_id=? AND target_month BETWEEN ? AND ?");
    $stmt->execute([$member['id'], $startYM, $endYM]);
    $totalPaid = (int)$stmt->fetchColumn();

    $totalExpected = 0;
    $curr = new DateTime(substr($startDate, 0, 7) . '-01');
    $end  = new DateTime(substr($endDate,   0, 7) . '-01');

    while ($curr <= $end) {
        $mKey       = $curr->format('Y-m');
        $monthFirst = $curr->format('Y-m-01');
        $monthLast  = $curr->format('Y-m-t');

        if ($mKey > $nowYM || $monthLast < '2022-01-01') {
            $curr->modify('+1 month'); continue;
        }
        $active = ($member['join_date'] && $member['join_date'] <= $monthLast)
               && (!$member['withdraw_date'] || $member['withdraw_date'] >= $monthFirst);

        if ($active) {
            $totalExpected += _getFeeAt($history, $curr->format('Y-m-d'));
        }
        $curr->modify('+1 month');
    }

    return max(0, $totalExpected - $totalPaid);
}

/**
 * 회원사 청구서 HTML 생성 (이메일 인라인 스타일 적용)
 */
function buildInvoiceHtml(PDO $pdo, array $member, array $history,
                           int $startYear, int $startMonth,
                           int $endYear,   int $endMonth): string
{
    $startDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
    $endDate   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $endYear, $endMonth)));
    $startYM   = substr($startDate, 0, 7);
    $endYM     = substr($endDate,   0, 7);
    $nowYM     = date('Y-m');
    $today     = date('Y년 m월 d일');

    // ── 납부 내역 맵 (target_month 기준) ─────────────────────────────────────
    $stmt = $pdo->prepare("SELECT target_month, SUM(amount) AS paid
                            FROM tb_dues_payments
                            WHERE member_id=? AND target_month BETWEEN ? AND ?
                            GROUP BY target_month");
    $stmt->execute([$member['id'], $startYM, $endYM]);
    $paymentsMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // ── 입금 현황 (payment_date 기준, 기간 내 입금) ───────────────────────────
    $dStmt = $pdo->prepare("SELECT payment_date, amount, target_month, category
                             FROM tb_dues_payments
                             WHERE member_id=? AND payment_date BETWEEN ? AND ?
                             ORDER BY payment_date ASC");
    $dStmt->execute([$member['id'], $startDate, $endDate]);
    $allDeposits = $dStmt->fetchAll();

    // ── 월별 부과/납부 집계 ───────────────────────────────────────────────────
    $reportData    = [];
    $totalExpected = 0;
    $totalPaid     = 0;

    $curr = new DateTime(substr($startDate, 0, 7) . '-01');
    $end  = new DateTime(substr($endDate,   0, 7) . '-01');

    while ($curr <= $end) {
        $mKey       = $curr->format('Y-m');
        $monthFirst = $curr->format('Y-m-01');
        $monthLast  = $curr->format('Y-m-t');

        if ($monthLast < '2022-01-01') { $curr->modify('+1 month'); continue; }

        $active = ($member['join_date'] && $member['join_date'] <= $monthLast)
               && (!$member['withdraw_date'] || $member['withdraw_date'] >= $monthFirst);

        $fee  = $active ? _getFeeAt($history, $curr->format('Y-m-d')) : 0;
        $paid = (int)($paymentsMap[$mKey] ?? 0);

        $reportData[] = ['month' => $mKey, 'expected' => $fee, 'paid' => $paid];
        $totalExpected += $fee;
        $totalPaid     += $paid;

        $curr->modify('+1 month');
    }

    // 당기 미납 (현재월까지만)
    $pastExpected = array_sum(array_column(
        array_filter($reportData, fn($r) => $r['month'] <= $nowYM),
        'expected'
    ));
    $periodUnpaid = max(0, $pastExpected - $totalPaid);

    // 조회기간 이전 미납액
    $preStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM tb_dues_payments
                               WHERE member_id=? AND target_month < ?");
    $preStmt->execute([$member['id'], $startYM]);
    $prePaid = (int)$preStmt->fetchColumn();

    $joinStr    = $member['join_date'] ? $member['join_date'] : '2022-01-01';
    $preCurr    = new DateTime(max(substr($joinStr, 0, 7), '2022-01') . '-01');
    $preEnd     = (new DateTime($startDate))->modify('-1 month');
    $preExpected = 0;
    while ($preCurr <= $preEnd) {
        $mf = $preCurr->format('Y-m-01');
        $ml = $preCurr->format('Y-m-t');
        $active = ($member['join_date'] && $member['join_date'] <= $ml)
               && (!$member['withdraw_date'] || $member['withdraw_date'] >= $mf);
        if ($active) $preExpected += _getFeeAt($history, $preCurr->format('Y-m-d'));
        $preCurr->modify('+1 month');
    }
    $prePeriodUnpaid = max(0, $preExpected - $prePaid)
                     + (int)$member['initial_unpaid']
                     + (int)$member['manual_unpaid'];

    // ── HTML 생성 (이메일용 인라인 스타일) ───────────────────────────────────
    $co   = htmlspecialchars($member['company_name']);
    $periodStr = "{$startYear}년 " . sprintf('%02d', $startMonth) . "월 ~ {$endYear}년 " . sprintf('%02d', $endMonth) . "월";

    // 월별 표 두 행 렌더링
    $renderHalfTable = function (array $rows) use ($nowYM): string {
        if (empty($rows)) return '';
        $headCells = '<td style="border:1px solid #ccc;padding:6px 4px;background:#f3f4f6;font-weight:bold;font-size:11px;text-align:center;width:48px;">납부월</td>';
        $bodyCells = '<td style="border:1px solid #ccc;padding:6px 4px;background:#f9f9f9;font-size:10px;text-align:center;">납부액</td>';
        foreach ($rows as $row) {
            $mon = (int)substr($row['month'], 5, 2);
            $isFuture = $row['month'] > $nowYM;
            $headCells .= "<td style=\"border:1px solid #ccc;padding:6px 4px;background:#f3f4f6;font-size:11px;text-align:center;\">{$mon}월</td>";
            if ($row['expected'] == 0 && $row['paid'] == 0) {
                $color = '#ccc'; $val = '-';
            } elseif ($row['paid'] > 0) {
                $val = number_format($row['paid']);
                $color = ($row['paid'] < $row['expected'] && !$isFuture) ? '#ef4444' : '#2563eb';
            } else {
                $val = '0'; $color = $isFuture ? '#9ca3af' : '#ef4444';
            }
            $bodyCells .= "<td style=\"border:1px solid #ccc;padding:6px 4px;color:{$color};font-weight:bold;font-size:11px;text-align:center;\">{$val}</td>";
        }
        return "<table style=\"width:100%;border-collapse:collapse;margin-bottom:6px;\"><thead><tr>{$headCells}</tr></thead><tbody><tr>{$bodyCells}</tr></tbody></table>";
    };

    $firstHalf  = array_values(array_filter($reportData, fn($r) => (int)substr($r['month'],5,2) <= 6));
    $secondHalf = array_values(array_filter($reportData, fn($r) => (int)substr($r['month'],5,2) >= 7));
    $monthTable = $renderHalfTable($firstHalf) . $renderHalfTable($secondHalf);

    // 입금 현황 행
    $depositRows = '';
    $depositTotal = 0;
    if (empty($allDeposits)) {
        $depositRows = '<tr><td colspan="4" style="border:1px solid #ddd;padding:8px;text-align:center;color:#9ca3af;">해당 기간 내 입금 내역 없음</td></tr>';
    } else {
        foreach ($allDeposits as $dp) {
            $depositTotal += $dp['amount'];
            $cat = htmlspecialchars($dp['category'] ?? '');
            $depositRows .= "<tr>
                <td style=\"border:1px solid #ddd;padding:7px 10px;text-align:center;\">{$dp['payment_date']}</td>
                <td style=\"border:1px solid #ddd;padding:7px 10px;text-align:right;color:#2563eb;font-weight:bold;\">" . number_format($dp['amount']) . "원</td>
                <td style=\"border:1px solid #ddd;padding:7px 10px;text-align:center;\">{$dp['target_month']}</td>
                <td style=\"border:1px solid #ddd;padding:7px 10px;\">{$cat}</td>
            </tr>";
        }
        $depositRows .= "<tr style=\"background:#f3f4f6;font-weight:bold;\">
            <td style=\"border:1px solid #ddd;padding:7px 10px;\">합 계</td>
            <td style=\"border:1px solid #ddd;padding:7px 10px;text-align:right;color:#1d4ed8;\">" . number_format($depositTotal) . "원</td>
            <td colspan=\"2\" style=\"border:1px solid #ddd;padding:7px 10px;\"></td>
        </tr>";
    }

    $prePeriodNote = ($prePeriodUnpaid > 0)
        ? "<div style=\"margin-top:8px;font-size:13px;color:#374151;\">
               미납금액 " . number_format($periodUnpaid) . "원
               (<span style=\"color:#dc2626;font-weight:bold;\">조회기간 이전 미납 " . number_format($prePeriodUnpaid) . "원 별도</span>)
           </div>"
        : '';

    $unpaidFormatted = number_format($periodUnpaid);
    $prePeriodNote = ($prePeriodUnpaid > 0)
        ? "<div style=\"margin-top:8px;font-size:13px;color:#374151;\">
               미납금액 {$unpaidFormatted}원
               (<span style=\"color:#dc2626;font-weight:bold;\">조회기간 이전 미납 " . number_format($prePeriodUnpaid) . "원 별도</span>)
           </div>"
        : '';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head><meta charset="UTF-8"><title>회비 납부 안내문</title></head>
<body style="font-family:'Malgun Gothic','Apple SD Gothic Neo',sans-serif;background:#f9f9f9;margin:0;padding:20px;">
<div style="background:white;max-width:680px;margin:0 auto;padding:40px;border:1px solid #e5e7eb;border-radius:8px;">

  <div style="text-align:center;font-size:24px;font-weight:bold;letter-spacing:8px;border-bottom:2px solid #222;padding-bottom:16px;margin-bottom:32px;">
    회비 납부 안내문
  </div>

  <div style="display:flex;justify-content:space-between;margin-bottom:24px;">
    <div>
      <strong>수신 :</strong> {$co} 귀하<br>
      <span style="font-size:13px;color:#6b7280;">조회기간 : {$periodStr}</span>
    </div>
    <div style="text-align:right;color:#6b7280;font-size:13px;">발송일 : {$today}</div>
  </div>

  <p style="margin-bottom:24px;line-height:1.8;color:#374151;">
    귀 사의 무궁한 발전을 기원합니다.<br>
    아래와 같이 미납 회비 현황을 안내해 드리오니, 확인 후 납부를 부탁드립니다.
  </p>

  <div style="background:#fef2f2;border:1px solid #fecaca;padding:24px;text-align:center;border-radius:8px;margin-bottom:24px;">
    <div style="color:#ef4444;font-size:13px;font-weight:bold;margin-bottom:8px;">당기 미납 금액</div>
    <div style="font-size:32px;font-weight:bold;color:#dc2626;">&#8361; {$unpaidFormatted}</div>
    {$prePeriodNote}
  </div>

  <div style="font-size:15px;font-weight:bold;border-left:4px solid #1e3a8a;padding-left:10px;margin-bottom:10px;">부과 및 납부 내역</div>
  {$monthTable}

  <div style="font-size:15px;font-weight:bold;border-left:4px solid #1e3a8a;padding-left:10px;margin:20px 0 10px;">기간 내 입금 현황</div>
  <table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
    <thead>
      <tr style="background:#f3f4f6;">
        <th style="border:1px solid #ddd;padding:8px 10px;">입금일</th>
        <th style="border:1px solid #ddd;padding:8px 10px;">입금액</th>
        <th style="border:1px solid #ddd;padding:8px 10px;">대상월</th>
        <th style="border:1px solid #ddd;padding:8px 10px;">구분</th>
      </tr>
    </thead>
    <tbody>{$depositRows}</tbody>
  </table>

  <div style="border-top:2px solid #374151;padding-top:20px;margin-top:8px;">
    <div style="font-weight:bold;font-size:15px;margin-bottom:8px;">납부 계좌 안내</div>
    <div style="background:#f3f4f6;padding:14px;border-radius:6px;text-align:center;font-size:15px;">
      <strong>우리은행 1005-502-844907</strong>
      <span style="color:#6b7280;margin-left:12px;">예금주: 한국사회주택협회</span>
    </div>
    <p style="font-size:12px;color:#6b7280;text-align:center;margin-top:8px;">* 입금 시 반드시 회원사명으로 입금해 주시기 바랍니다.</p>
  </div>

  <div style="text-align:right;margin-top:40px;font-size:16px;">
    사단법인 한국사회주택협회
    <span style="display:inline-block;width:52px;height:52px;border:2px solid red;border-radius:50%;color:red;text-align:center;line-height:48px;font-size:14px;font-weight:bold;margin-left:12px;vertical-align:middle;">직인</span>
  </div>

</div>
</body></html>
HTML;

    return $html;
}

// ─── action: preview ─────────────────────────────────────────────────────────
if ($action === 'preview') {
    set_time_limit(60);

    // 1) 모든 정회원 한 번에 조회
    $members = $pdo->query("
        SELECT m.id, m.member_no, m.company_name, d.email, m.join_date, m.withdraw_date,
               m.member_type, m.status, 
               IFNULL(iu.total, 0) AS initial_unpaid, 
               IFNULL(d.unpaid_before_2022, 0) AS manual_unpaid
        FROM tb_members m
        LEFT JOIN tb_member_details d ON m.id = d.member_id
        LEFT JOIN tb_initial_unpaid iu ON m.id = iu.member_id
        WHERE m.member_type = '정회원' AND m.status = '운영'
        ORDER BY m.member_no ASC
    ")->fetchAll();

    // 2) 회비 이력 한 번에 조회 (member_id를 첫 번째 컬럼으로 지정해야 FETCH_GROUP이 제대로 동작함)
    $feeHistoryRaw = $pdo->query(
        "SELECT member_id, t.* 
         FROM tb_member_fee_history t 
         ORDER BY member_id, start_date DESC"
    )->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // 3) 조회기간 내 납부액을 회원사별로 한 번에 합산
    $paidStmt = $pdo->prepare("
        SELECT member_id, COALESCE(SUM(amount), 0) AS total
        FROM tb_dues_payments
        WHERE target_month BETWEEN ? AND ?
        GROUP BY member_id
    ");
    $paidStmt->execute([$startYM, $endYM]);
    $paidMap = $paidStmt->fetchAll(PDO::FETCH_KEY_PAIR); // member_id => total

    $nowYM = date('Y-m');
    $list  = [];

    foreach ($members as $m) {
        $history   = $feeHistoryRaw[$m['id']] ?? [];
        $totalPaid = (int)($paidMap[$m['id']] ?? 0);

        // 부과액 계산 (PHP 루프, DB 쿼리 없음)
        $totalExpected = 0;
        $curr = new DateTime($startYM . '-01');
        $end  = new DateTime($endYM   . '-01');
        while ($curr <= $end) {
            $mKey       = $curr->format('Y-m');
            $monthFirst = $curr->format('Y-m-01');
            $monthLast  = $curr->format('Y-m-t');
            if ($mKey > $nowYM || $monthLast < '2022-01-01') {
                $curr->modify('+1 month'); continue;
            }
            $active = ($m['join_date'] && $m['join_date'] <= $monthLast)
                   && (!$m['withdraw_date'] || $m['withdraw_date'] >= $monthFirst);
            if ($active) {
                $totalExpected += _getFeeAt($history, $curr->format('Y-m-d'));
            }
            $curr->modify('+1 month');
        }

        $unpaid = max(0, $totalExpected - $totalPaid);
        if ($unpaid <= 0) continue;

        $list[] = [
            'id'           => $m['id'],
            'member_no'    => $m['member_no'],
            'company_name' => $m['company_name'],
            'email'        => $m['email'] ?? '',
            'has_email'    => !empty($m['email']),
            'unpaid'       => $unpaid,
            'status'       => $m['status'],
        ];
    }

    echo json_encode(['success' => true, 'list' => $list]);
    exit;
}


// ─── action: send ─────────────────────────────────────────────────────────────
if ($action === 'send') {
    $memberIds = json_decode($_POST['member_ids'] ?? '[]', true);
    if (empty($memberIds)) {
        echo json_encode(['success' => false, 'message' => '발송할 회원사가 없습니다.']);
        exit;
    }

    $feeHistoryRaw = $pdo->query(
        "SELECT member_id, t.* 
         FROM tb_member_fee_history t 
         ORDER BY member_id, start_date DESC"
    )->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    $results = [];
    foreach ($memberIds as $rawId) {
        $mid = (int)$rawId;

        $mStmt = $pdo->prepare("
            SELECT m.*, d.email, 
                   IFNULL(iu.total, 0) AS initial_unpaid, 
                   IFNULL(d.unpaid_before_2022, 0) AS manual_unpaid
            FROM tb_members m 
            LEFT JOIN tb_member_details d ON m.id = d.member_id 
            LEFT JOIN tb_initial_unpaid iu ON m.id = iu.member_id
            WHERE m.id = ? AND m.status = '운영'
        ");
        $mStmt->execute([$mid]);
        $member = $mStmt->fetch();
        if (!$member || empty($member['email'])) {
            $results[] = ['id' => $mid, 'success' => false,
                          'company_name' => $member['company_name'] ?? "ID:{$mid}",
                          'error' => '이메일 주소 없음'];
            continue;
        }

        $history = $feeHistoryRaw[$mid] ?? [];
        $html    = buildInvoiceHtml($pdo, $member, $history,
                                    $startYear, $startMonth, $endYear, $endMonth);

        $subject = "[한국사회주택협회] {$member['company_name']} 회비 납부 안내 ({$startYear}년 " . sprintf('%02d', $startMonth) . "월 ~ {$endYear}년 " . sprintf('%02d', $endMonth) . "월)";
        $result  = sendMail($member['email'], $member['company_name'], $subject, $html);

        $results[] = [
            'id'           => $mid,
            'company_name' => $member['company_name'],
            'email'        => $member['email'],
            'success'      => $result['success'],
            'error'        => $result['error'] ?? null,
        ];

        // 서버 과부하 방지 딜레이
        usleep(300000); // 0.3초
    }

    $successCount = count(array_filter($results, fn($r) => $r['success']));
    echo json_encode([
        'success' => true,
        'message' => "총 " . count($results) . "건 중 {$successCount}건 발송 완료.",
        'results' => $results,
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
