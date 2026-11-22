<?php
/**
 * dues_management.php - 회비 관리 (구글 시트 연동 + 현황 매트릭스)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle   = '회비 관리';
$currentPage = 'dues';
$year        = (int)($_GET['year'] ?? date('Y'));
$basis       = $_GET['basis'] ?? 'target'; // target: 부과월, deposit: 입금월
$response    = null; // AJAX 응답용

// ─────────────────────────────────────────────
// AJAX: 구글 시트 데이터 가져오기 + DB 저장
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json; charset=utf-8');

    // ── 1) 구글 시트 불러오기 ──────────────────
    if ($_POST['action'] === 'import_gsheet') {
        $sheetUrl = trim($_POST['sheet_url'] ?? '');
        if (!$sheetUrl) {
            echo json_encode(['success' => false, 'message' => 'URL을 입력해주세요.']);
            exit;
        }

        // Google 스프레드시트 공유 URL → CSV 다운로드 URL 변환
        // 지원 URL 형식:
        //   https://docs.google.com/spreadsheets/d/{ID}/edit#gid={GID}
        //   https://docs.google.com/spreadsheets/d/{ID}/edit?gid={GID}
        //   https://docs.google.com/spreadsheets/d/{ID}/pub...
        if (!preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $sheetUrl, $m)) {
            echo json_encode(['success' => false, 'message' => '올바른 구글 스프레드시트 URL이 아닙니다.']);
            exit;
        }
        $sheetId = $m[1];
        $gid     = 0;
        if (preg_match('/[#&?]gid=(\d+)/', $sheetUrl, $gm)) {
            $gid = (int)$gm[1];
        }
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";

        // CSV 다운로드
        $ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => true, 'user_agent' => 'Mozilla/5.0']]);
        $raw = @file_get_contents($csvUrl, false, $ctx);
        if ($raw === false) {
            echo json_encode(['success' => false, 'message' => '구글 시트를 불러올 수 없습니다. 시트가 "링크 공유" 상태인지 확인해주세요.']);
            exit;
        }

        // CSV 파싱
        $rows = array_map('str_getcsv', explode("\n", trim($raw)));
        if (count($rows) < 2) {
            echo json_encode(['success' => false, 'message' => '시트에 데이터가 없거나 헤더만 있습니다.']);
            exit;
        }

        // 헤더 행 정규화 (공백·BOM 제거)
        $headers = array_map(fn($h) => trim(preg_replace('/[\x00-\x1F\xEF\xBB\xBF]+/u', '', $h)), $rows[0]);

        // 필수 필드 확인 (유연한 컬럼명 매핑)
        $colMap   = [];
        $colAlias = [
            'member_no'    => ['회원번호', '번호', 'no'],
            'payment_date' => ['날짜', '입금일', '거래일', 'date', 'payment_date'],
            'name'         => ['내용', '입금자', '적요', '거래내용', 'name', 'content', '회원사'],
            'amount'       => ['금액', '입금액', '수입', 'amount', '입금금액'],
            'target_month' => ['납부월', '대상월', '회비월', 'target_month', 'month'],
            'account_info' => ['계좌', '입금계좌', 'account', 'account_info'],
            'category'     => ['적요', '구분', '종류', 'category', '항목'],
            'memo'         => ['메모', '비고', 'memo', 'note'],
        ];
        foreach ($colAlias as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search(mb_strtolower($alias), array_map('mb_strtolower', $headers));
                if ($idx !== false) { $colMap[$field] = $idx; break; }
            }
        }

        if (!isset($colMap['name']) || !isset($colMap['amount'])) {
            echo json_encode(['success' => false,
                'message' => "필수 컬럼(입금자명, 금액)을 찾을 수 없습니다.\n현재 헤더: " . implode(', ', $headers)]);
            exit;
        }

        // 회원사 목록 캐시 (이름 및 번호 매칭용)
        $memberStmt = $pdo->query("SELECT id, company_name, member_no FROM tb_members WHERE status != '탈퇴'");
        $memberMapByName  = [];
        $memberMapByNo = [];
        foreach ($memberStmt->fetchAll() as $r) {
            $memberMapByName[mb_strtolower($r['company_name'])] = $r['id'];
            if ($r['member_no']) {
                $memberMapByNo[mb_strtolower(trim($r['member_no']))] = $r['id'];
            }
        }

        // 데이터 행 처리 - INSERT (중복 무시)
        $insStmt = $pdo->prepare("
            INSERT IGNORE INTO tb_dues_payments
                (member_id, payment_date, account_info, category, amount, target_month, memo)
            VALUES
                (:member_id, :payment_date, :account_info, :category, :amount, :target_month, :memo)
        ");

        $saved = 0; $skipped = 0; $unmatched = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < 2) continue;

            $rawName   = trim($row[$colMap['name']]   ?? '');
            $rawAmount = trim($row[$colMap['amount']]  ?? '');
            if (!$rawName || !$rawAmount) continue;

            $amount = (int)preg_replace('/[^0-9]/', '', $rawAmount);
            if ($amount <= 0) continue;

            // 날짜 처리
            $paymentDate = null;
            if (isset($colMap['payment_date'])) {
                $d = trim($row[$colMap['payment_date']] ?? '');
                // "2026.01.15" → "2026-01-15"
                $d = preg_replace('/[.\/]/', '-', $d);
                $paymentDate = $d ?: date('Y-m-d');
            } else {
                $paymentDate = date('Y-m-d');
            }

            // 납부월 처리
            $targetMonth = null;
            if (isset($colMap['target_month'])) {
                $tm = trim($row[$colMap['target_month']] ?? '');
                $tm = preg_replace('/[.\/]/', '-', $tm);
                $targetMonth = $tm ?: null;
            }
            // 납부월 없으면 입금일 기준 년-월
            if (!$targetMonth) {
                $targetMonth = substr($paymentDate, 0, 7);
            }

            // 회원 매칭 (번호 우선, 그다음 이름 부분 매칭)
            $memberId = null;
            $rawMemberNo = isset($colMap['member_no']) ? trim($row[$colMap['member_no']] ?? '') : '';
            
            if ($rawMemberNo && isset($memberMapByNo[mb_strtolower($rawMemberNo)])) {
                $memberId = $memberMapByNo[mb_strtolower($rawMemberNo)];
            }
            
            if (!$memberId) {
                $lowerName = mb_strtolower($rawName);
                // 완전 일치 우선
                if (isset($memberMapByName[$lowerName])) {
                    $memberId = $memberMapByName[$lowerName];
                } else {
                    // 부분 일치
                    foreach ($memberMapByName as $mName => $mId) {
                        if (mb_strpos($lowerName, $mName) !== false || mb_strpos($mName, $lowerName) !== false) {
                            $memberId = $mId;
                            break;
                        }
                    }
                }
            }

            if (!$memberId) {
                $unmatched[] = $rawName;
                // 미매칭도 member_id = NULL 로 저장 (수동 매칭 가능하도록)
            }

            $insStmt->execute([
                ':member_id'   => $memberId,
                ':payment_date' => $paymentDate,
                ':account_info' => isset($colMap['account_info']) ? trim($row[$colMap['account_info']] ?? '') : '',
                ':category'    => isset($colMap['category']) ? trim($row[$colMap['category']] ?? '') : '월회비',
                ':amount'      => $amount,
                ':target_month' => $targetMonth,
                ':memo'        => isset($colMap['memo']) ? trim($row[$colMap['memo']] ?? '') : '',
            ]);
            if ($pdo->lastInsertId()) $saved++;
            else $skipped++;
        }

        $msg = "총 {$saved}건 저장 완료.";
        if ($skipped > 0) $msg .= " ({$skipped}건 중복 스킵)";
        if ($unmatched) $msg .= "\n⚠ 미매칭 입금자: " . implode(', ', array_unique($unmatched));
        echo json_encode(['success' => true, 'message' => $msg, 'saved' => $saved, 'unmatched' => array_unique($unmatched)]);
        exit;
    }

    // ── 2) 개별 납부 내역 직접 등록 ───────────────
    if ($_POST['action'] === 'add_payment') {
        $memberId    = (int)($_POST['member_id'] ?? 0);
        $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
        $amount      = (int)preg_replace('/[^0-9]/', '', $_POST['amount'] ?? '0');
        $targetMonth = $_POST['target_month'] ?? substr($paymentDate, 0, 7);
        $category    = $_POST['category'] ?? '월회비';
        $accountInfo = $_POST['account_info'] ?? '';
        $memo        = $_POST['memo'] ?? '';

        if (!$memberId || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => '필수 항목(회원사, 금액)을 입력해주세요.']);
            exit;
        }
        $pdo->prepare("INSERT INTO tb_dues_payments (member_id, payment_date, account_info, category, amount, target_month, memo)
                       VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$memberId, $paymentDate, $accountInfo, $category, $amount, $targetMonth, $memo]);
        echo json_encode(['success' => true, 'message' => '납부 내역이 등록되었습니다.']);
        exit;
    }

    // ── 3) 납부 내역 삭제 ─────────────────────────
    if ($_POST['action'] === 'delete_payment') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM tb_dues_payments WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        }
        exit;
    }

    // ── 4) 납부 내역 수정 ─────────────────────────
    if ($_POST['action'] === 'edit_payment') {
        $id          = (int)($_POST['id'] ?? 0);
        $amount      = (int)preg_replace('/[^0-9]/', '', $_POST['amount'] ?? '0');
        $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
        $targetMonth = $_POST['target_month'] ?? substr($paymentDate, 0, 7);
        $category    = $_POST['category'] ?? '월회비';
        $accountInfo = $_POST['account_info'] ?? '';
        $memo        = $_POST['memo'] ?? '';

        if (!$id || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => '필수 항목을 확인해주세요.']);
            exit;
        }
        $pdo->prepare("UPDATE tb_dues_payments SET amount=?, payment_date=?, target_month=?, category=?, account_info=?, memo=? WHERE id=?")
            ->execute([$amount, $paymentDate, $targetMonth, $category, $accountInfo, $memo, $id]);
        echo json_encode(['success' => true, 'message' => '납부 내역이 수정되었습니다.']);
        exit;
    }

    // ── 3) 초기 이월 미납 데이터 가져오기 (2017~2021) ──────────────────
    if ($_POST['action'] === 'import_initial_unpaid') {
        $sheetUrl = trim($_POST['sheet_url'] ?? '');
        if (!preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $sheetUrl, $m)) {
            echo json_encode(['success' => false, 'message' => '올바른 구글 시트 URL이 아닙니다.']);
            exit;
        }
        $sheetId = $m[1];
        $gid     = 0;
        if (preg_match('/[#&?]gid=(\d+)/', $sheetUrl, $gm)) $gid = (int)$gm[1];
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";

        $raw = @file_get_contents($csvUrl);
        if ($raw === false) {
            echo json_encode(['success' => false, 'message' => '시트를 불러올 수 없습니다.']);
            exit;
        }

        $rows = array_map('str_getcsv', explode("\n", trim($raw)));
        $headers = array_map(fn($h) => trim(preg_replace('/[\x00-\x1F\xEF\xBB\xBF]+/u', '', $h)), $rows[0]);

        $colMap = [];
        $colAlias = [
            'member_no' => ['회원번호', '번호', 'no'],
            'y2017' => ['2017'],
            'y2018' => ['2018'],
            'y2019' => ['2019'],
            'y2020' => ['2020'],
            'y2021' => ['2021'],
            'total' => ['합계', '미납총액', 'total']
        ];
        foreach ($colAlias as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search(mb_strtolower($alias), array_map('mb_strtolower', $headers));
                if ($idx !== false) { $colMap[$field] = $idx; break; }
            }
        }

        if (!isset($colMap['member_no'])) {
            echo json_encode(['success' => false, 'message' => '회원번호 컬럼을 찾을 수 없습니다.']);
            exit;
        }

        $memberStmt = $pdo->query("SELECT id, member_no FROM tb_members");
        $memberNoMap = [];
        foreach ($memberStmt->fetchAll() as $r) { $memberNoMap[trim($r['member_no'])] = $r['id']; }

        $pdo->beginTransaction();
        $insStmt = $pdo->prepare("
            INSERT INTO tb_initial_unpaid (member_id, year_2017, year_2018, year_2019, year_2020, year_2021, total)
            VALUES (:mid, :y17, :y18, :y19, :y20, :y21, :total)
            ON DUPLICATE KEY UPDATE 
            year_2017=VALUES(year_2017), year_2018=VALUES(year_2018), year_2019=VALUES(year_2019),
            year_2020=VALUES(year_2020), year_2021=VALUES(year_2021), total=VALUES(total)
        ");

        $count = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $mNo = trim($row[$colMap['member_no']] ?? '');
            if (!$mNo || !isset($memberNoMap[$mNo])) continue;

            $y17 = (int)preg_replace('/[^0-9-]/', '', $row[$colMap['y2017']] ?? '0');
            $y18 = (int)preg_replace('/[^0-9-]/', '', $row[$colMap['y2018']] ?? '0');
            $y19 = (int)preg_replace('/[^0-9-]/', '', $row[$colMap['y2019']] ?? '0');
            $y20 = (int)preg_replace('/[^0-9-]/', '', $row[$colMap['y2020']] ?? '0');
            $y21 = (int)preg_replace('/[^0-9-]/', '', $row[$colMap['y2021']] ?? '0');
            $total = (int)preg_replace('/[^0-9-]/', '', $row[$colMap['total']] ?? '0');

            // 합계가 명시되지 않은 경우 수동 계산
            if ($total === 0) $total = $y17 + $y18 + $y19 + $y20 + $y21;

            $insStmt->execute([
                ':mid' => $memberNoMap[$mNo],
                ':y17' => $y17, ':y18' => $y18, ':y19' => $y19, ':y20' => $y20, ':y21' => $y21,
                ':total' => $total
            ]);
            $count++;
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "{$count}건의 초기 이월 데이터가 연동되었습니다."]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => '알 수 없는 요청입니다.']);
    exit;
}

// ─────────────────────────────────────────────
// 회비 현황판 데이터 조회
// ─────────────────────────────────────────────
$currentMonth = (int)date('m');

// [수정] 탈퇴일 기준 필터링: 해당 연도(year)에 활동했던 회원만 표시 (가입일 <= 연말 AND (탈퇴일 없거나 >= 연초))
$members = $pdo->prepare("
    SELECT m.*, iu.total as initial_unpaid, d.unpaid_before_2022 as manual_unpaid,
           d.biz_no, d.address, d.email, d.homepage, d.established_date
    FROM tb_members m
    LEFT JOIN tb_initial_unpaid iu ON m.id = iu.member_id
    LEFT JOIN tb_member_details d ON m.id = d.member_id
    WHERE m.join_date <= :end_of_year
      AND (m.withdraw_date IS NULL OR m.withdraw_date >= :start_of_year)
    ORDER BY m.member_no ASC
");
$members->execute([
    ':end_of_year' => $year . '-12-31',
    ':start_of_year' => $year . '-01-01'
]);
$members = $members->fetchAll();

// 해당 연도 납부 내역 전체 조회 (월×회원사 피벗용)
if ($basis === 'deposit') {
    $paymentsRaw = $pdo->prepare("
        SELECT id, member_id, target_month, amount, memo, payment_date, category, account_info
        FROM tb_dues_payments
        WHERE LEFT(payment_date, 4) = ?
    ");
} else {
    $paymentsRaw = $pdo->prepare("
        SELECT id, member_id, target_month, amount, memo, payment_date, category, account_info
        FROM tb_dues_payments
        WHERE LEFT(target_month, 4) = ?
    ");
}
$paymentsRaw->execute([$year]);
$paymentsMap = []; // [member_id][YYYY-MM] => [ {id, amount, memo, ...}, ... ]
foreach ($paymentsRaw->fetchAll() as $p) {
    if ($basis === 'deposit') {
        $monthKey = substr($p['payment_date'], 0, 7);
    } else {
        $monthKey = $p['target_month'];
    }
    $paymentsMap[$p['member_id']][$monthKey][] = $p;
}

// 회비 이력 전체 조회 (계산용 캐시)
$feeHistoryRaw = $pdo->query("SELECT * FROM tb_member_fee_history ORDER BY member_id, start_date DESC")->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
// $feeHistoryRaw[member_id] = [ {start_date, monthly_fee, ...}, ... ]

/**
 * 특정 월에 적용되는 회비 산출 함수
 */
function getFeeForMonth($history, $year, $month) {
    if (empty($history)) return 0;
    $targetDate = sprintf('%04d-%02d-01', $year, $month);
    foreach ($history as $h) {
        if ($h['start_date'] <= $targetDate) {
            return (int)$h['monthly_fee'];
        }
    }
    // 이력이 타겟 날짜보다 다 미래에 있다면 0 반환 (과거에는 회비 책정 안됨)
    return 0;
}

// 회원사 목록 (개별 납부 등록 모달용)
$memberList = $pdo->query("SELECT id, company_name FROM tb_members WHERE status != '탈퇴' ORDER BY member_no ASC")->fetchAll();

$months = range(1, 12);

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<!-- 메인 컨텐츠 -->
<div class="flex-1 flex flex-col overflow-hidden">

    <!-- 헤더 -->
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-bold text-gray-800">회비 관리</h1>
            <!-- 연도 선택 -->
            <div class="flex items-center gap-2">
                <a href="?year=<?= $year - 1 ?>&basis=<?= $basis ?>" class="p-1 text-gray-400 hover:text-gray-700"><i class="ph ph-caret-left text-lg"></i></a>
                <span class="text-lg font-semibold text-primary w-12 text-center"><?= $year ?></span>
                <a href="?year=<?= $year + 1 ?>&basis=<?= $basis ?>" class="p-1 text-gray-400 hover:text-gray-700"><i class="ph ph-caret-right text-lg"></i></a>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="openAddModal()" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition flex items-center gap-1.5">
                <i class="ph ph-plus text-base"></i> 납부 등록
            </button>
            <button onclick="importInitialUnpaid()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-50 transition flex items-center gap-2">
                <i class="ph ph-database"></i> 초기이월데이터 연동
            </button>
        </div>
    </header>

    <main class="flex-1 overflow-auto bg-gray-100 p-6 lg:p-8">

        <!-- 구글 시트 연동 패널 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">
            <h2 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#0F9D58"/><path d="M7 6h10v2H7V6zm0 4h10v2H7v-2zm0 4h7v2H7v-2z" fill="white"/></svg>
                구글 시트에서 입금 내역 가져오기
            </h2>
            <div class="flex flex-col md:flex-row gap-3">
                <input type="url" id="sheetUrlInput" placeholder="구글 스프레드시트 공유 URL (예: https://docs.google.com/spreadsheets/d/...)"
                       class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary">
                <button onclick="importGSheet()"
                        class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition flex items-center gap-2 whitespace-nowrap">
                    <i class="ph ph-cloud-arrow-down text-base"></i> 데이터 가져오기
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                <i class="ph ph-info text-blue-400"></i>
                시트가 <strong>"링크가 있는 모든 사용자"</strong>에게 공유되어 있어야 합니다.
                헤더 컬럼: <code class="bg-gray-100 px-1 rounded">날짜 / 내용(입금자명) / 금액</code> (+ 납부월, 계좌 등 선택)
            </p>
            <div id="importResult" class="mt-3 hidden"></div>
        </div>

        <!-- 탭 메뉴 -->
        <div class="flex border-b border-gray-200 mb-6">
            <a href="?year=<?= $year ?>&basis=target" class="px-6 py-3 font-bold text-sm <?= $basis === 'target' ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="ph ph-calendar-blank mr-1"></i> 부과월 기준 (대상월)
            </a>
            <a href="?year=<?= $year ?>&basis=deposit" class="px-6 py-3 font-bold text-sm <?= $basis === 'deposit' ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="ph ph-bank mr-1"></i> 입금월 기준 (거래일)
            </a>
        </div>

        <!-- 필터바 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-gray-500 mb-1">회원사 검색</label>
                <div class="relative">
                    <input type="text" id="filterSearch" onkeyup="applyFilters()" placeholder="번호 또는 명칭 입력"
                           class="w-full border border-gray-300 rounded-lg pl-9 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary">
                    <i class="ph ph-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                </div>
            </div>
            <div class="w-32">
                <label class="block text-xs font-bold text-gray-500 mb-1">회원 구분</label>
                <select id="filterType" onchange="applyFilters()" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                    <option value="">전체</option>
                    <option value="정회원">정회원</option>
                    <option value="준회원">준회원</option>
                    <option value="특별회원">특별회원</option>
                </select>
            </div>
            <div class="w-32">
                <label class="block text-xs font-bold text-gray-500 mb-1">운영 상태</label>
                <select id="filterStatus" onchange="applyFilters()" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                    <option value="">전체</option>
                    <option value="운영">운영</option>
                    <option value="비운영">비운영</option>
                    <option value="탈퇴">탈퇴</option>
                </select>
            </div>
            <div class="w-40">
                <label class="block text-xs font-bold text-gray-500 mb-1">특정월 미납 필터</label>
                <select id="filterUnpaidMonth" onchange="applyFilters()" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                    <option value="">전체 보기</option>
                    <?php foreach ($months as $m): ?>
                        <option value="<?= $m ?>"><?= $m ?>월 미납사만</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer bg-white border border-gray-300 rounded-lg px-3 py-2">
                <input type="checkbox" id="showWithdrawn" onchange="applyFilters()" class="rounded text-primary focus:ring-primary">
                탈퇴 포함
            </label>
            <div class="relative group z-30">
                <button class="bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm hover:bg-gray-50 transition shadow-sm flex items-center gap-1">
                    <i class="ph ph-columns"></i> 표시 필드
                </button>
                <div class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all p-3">
                    <div id="colToggleContainer" class="space-y-1"></div>
                </div>
            </div>
            <button onclick="resetFilters()" class="px-3 py-2 text-sm text-gray-500 hover:text-primary transition">
                <i class="ph ph-arrows-counter-clockwise"></i> 초기화
            </button>
        </div>

        <!-- 회비 현황 매트릭스 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="ph-fill ph-table text-primary"></i>
                    <?= $year ?>년 월별 회비 현황판 <span class="text-xs text-gray-500 font-normal">(<?= $basis === 'target' ? '부과월 기준' : '입금월 기준' ?>)</span>
                </h2>
                <button onclick="exportMatrix()" class="text-sm bg-white border border-gray-300 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                    <i class="ph ph-file-xls text-green-600"></i> 엑셀 다운로드
                </button>
            </div>
            <div class="overflow-auto" style="max-height: calc(100vh - 340px);">
                <table id="matrixTable" class="w-full text-xs text-left whitespace-nowrap border-collapse">
                    <thead class="sticky top-0 z-10">
                        <!-- 합계 행 (제목행 상단) -->
                        <tr id="matrixTotalRow" class="bg-blue-50 text-gray-800 text-[11px] font-bold border-b border-blue-200">
                            <th class="col-no px-3 py-2 border border-gray-200 sticky left-0 bg-blue-50 z-20 min-w-[40px]"></th>
                            <th class="col-name px-3 py-2 border border-gray-200 sticky left-10 bg-blue-50 z-20 min-w-[180px] text-center text-blue-700">월별 합계액</th>
                            <th class="col-ceo px-3 py-2 border border-gray-200 min-w-[80px]"></th>
                            <th class="col-contact px-3 py-2 border border-gray-200 min-w-[100px]"></th>
                            <th class="col-type px-3 py-2 border border-gray-200 text-center min-w-[60px]"></th>
                            <th class="col-join px-3 py-2 border border-gray-200 text-center min-w-[80px]"></th>
                            <th class="col-withdraw px-3 py-2 border border-gray-200 text-center min-w-[80px]"></th>
                            <th class="col-biz px-3 py-2 border border-gray-200 min-w-[100px]"></th>
                            <th class="col-est px-3 py-2 border border-gray-200 min-w-[80px]"></th>
                            <th class="col-email px-3 py-2 border border-gray-200 min-w-[120px]"></th>
                            <th class="col-addr px-3 py-2 border border-gray-200 min-w-[200px]"></th>
                            <th class="col-status px-3 py-2 border border-gray-200 text-center min-w-[60px]"></th>
                            <th class="col-fee px-3 py-2 border border-gray-200 text-right min-w-[70px]"></th>
                            <?php foreach ($months as $m): ?>
                                <th class="px-2 py-2 border border-gray-200 text-right text-blue-700 min-w-[60px]" id="sum-month-<?= $m ?>">0</th>
                            <?php endforeach; ?>
                            <th class="px-3 py-2 border border-gray-200 text-right text-blue-700 min-w-[70px]" id="sum-total-paid">0</th>
                            <th class="px-3 py-2 border border-gray-200 text-right text-red-600 min-w-[70px]" id="sum-total-unpaid">0</th>
                            <th class="px-3 py-2 border border-gray-200 text-center min-w-[70px]"></th>
                        </tr>
                        <tr class="bg-gray-100 text-gray-700 uppercase text-[11px]">
                            <th class="col-no px-3 py-3 border border-gray-200 font-semibold sticky left-0 bg-gray-100 z-20 min-w-[40px]">번호</th>
                            <th class="col-name px-3 py-3 border border-gray-200 font-semibold sticky left-10 bg-gray-100 z-20 min-w-[180px]">조직명</th>
                            <th class="col-ceo px-3 py-3 border border-gray-200 font-semibold min-w-[80px]">대표자</th>
                            <th class="col-contact px-3 py-3 border border-gray-200 font-semibold min-w-[100px]">연락처</th>
                            <th class="col-type px-3 py-3 border border-gray-200 font-semibold text-center min-w-[60px]">구분</th>
                            <th class="col-join px-3 py-3 border border-gray-200 font-semibold text-center min-w-[80px]">가입일</th>
                            <th class="col-withdraw px-3 py-3 border border-gray-200 font-semibold text-center min-w-[80px]">탈퇴일</th>
                            <th class="col-biz px-3 py-3 border border-gray-200 font-semibold min-w-[100px]">사업자번호</th>
                            <th class="col-est px-3 py-3 border border-gray-200 font-semibold min-w-[80px]">설립일</th>
                            <th class="col-email px-3 py-3 border border-gray-200 font-semibold min-w-[120px]">이메일</th>
                            <th class="col-addr px-3 py-3 border border-gray-200 font-semibold min-w-[200px]">주소</th>
                            <th class="col-status px-3 py-3 border border-gray-200 font-semibold text-center min-w-[60px]">상태</th>
                            <th class="col-fee px-3 py-3 border border-gray-200 font-semibold text-right min-w-[70px]">책정</th>
                            <?php foreach ($months as $m): ?>
                                <th class="px-2 py-3 border border-gray-200 font-semibold text-right min-w-[60px]
                                    <?= ($m === $currentMonth && $year === (int)date('Y')) ? 'bg-blue-50' : '' ?>">
                                    <?= $m ?>월
                                </th>
                            <?php endforeach; ?>
                            <th class="px-3 py-3 border border-gray-200 font-semibold text-right min-w-[70px] bg-blue-50">납부합계</th>
                            <th class="px-3 py-3 border border-gray-200 font-semibold text-right min-w-[70px] bg-red-50 text-red-600">누적미납</th>
                            <th class="px-3 py-3 border border-gray-200 font-semibold text-center min-w-[70px] bg-gray-50">관리</th>
                        </tr>
                    </thead>
                    <tbody id="matrixBody" class="divide-y divide-gray-100">
                    <?php foreach ($members as $member):
                        $mid        = $member['id'];
                        $isActive   = ($member['status'] === '운영');
                        $mType      = $member['member_type'] ?? '정회원';
                        $history    = $feeHistoryRaw[$mid] ?? [];
                        
                        $totalPaid      = 0;
                        $totalExpected  = 0;
                        $rowMonths      = [];
                        $unpaidMonths   = [];

                        foreach ($months as $m):
                            $monthKey   = sprintf('%04d-%02d', $year, $m);
                            $monthPayments = $paymentsMap[$mid][$monthKey] ?? [];
                            $paid       = array_reduce($monthPayments, fn($sum, $p) => $sum + $p['amount'], 0);
                            $totalPaid += $paid;

                            // 해당 월의 회비 기준값 찾기
                            $monthlyFee = getFeeForMonth($history, $year, $m);

                            // 미납 계산: 현재 월까지만
                            $isPast     = ($year < (int)date('Y')) || ($m <= $currentMonth);
                            
                            // 가입일/탈퇴일 기준 해당 월 활동 여부 판별
                            $monthFirst = sprintf('%04d-%02d-01', $year, $m);
                            $monthLast  = date('Y-m-t', strtotime($monthFirst));
                            
                            $isMemberActiveInMonth = ($member['join_date'] && $member['join_date'] <= $monthLast) && 
                                                     (!$member['withdraw_date'] || $member['withdraw_date'] >= $monthFirst);

                            // 2021년 12월 이전은 개별 발생 안 함 (초기이월내역 활용)
                            if ($year < 2022) {
                                $expected = 0;
                            } else {
                                $expected = ($isMemberActiveInMonth && $isPast) ? $monthlyFee : 0;
                            }
                            
                            $totalExpected += $expected;
                            
                            $diff       = $expected - $paid;
                            if ($diff > 0) $unpaidMonths[] = $m;

                            $rowMonths[] = [
                                'paid'     => $paid, 
                                'expected' => $expected, 
                                'diff'     => $diff, 
                                'fee'      => $monthlyFee,
                                'payments' => $monthPayments
                            ];
                        endforeach;

                        $totalUnpaid = max(0, $totalExpected - $totalPaid) + (int)($member['initial_unpaid'] ?? 0) + (int)($member['manual_unpaid'] ?? 0);
                    ?>
                        <tr class="matrix-row hover:bg-blue-50/30 transition-colors <?= !$isActive ? 'opacity-60' : '' ?>"
                            data-mid="<?= $mid ?>"
                            data-name="<?= htmlspecialchars($member['company_name']) ?>"
                            data-no="<?= htmlspecialchars($member['member_no']) ?>"
                            data-type="<?= htmlspecialchars($mType) ?>"
                            data-status="<?= htmlspecialchars($member['status']) ?>"
                            data-unpaid-months="<?= implode(',', $unpaidMonths) ?>"
                            data-total-paid="<?= $totalPaid ?>"
                            data-total-unpaid="<?= $totalUnpaid ?>"
                            <?php foreach ($months as $idx => $m): ?>
                                data-paid-<?= $m ?>="<?= $rowMonths[$idx]['paid'] ?>"
                            <?php endforeach; ?>
                        >
                            <td class="col-no px-3 py-2 border border-gray-100 text-gray-400 sticky left-0 bg-white z-10 text-center"><?= htmlspecialchars($member['member_no']) ?></td>
                            <td class="col-name px-3 py-2 border border-gray-100 font-medium text-gray-800 sticky left-10 bg-white z-10"><?= htmlspecialchars($member['company_name']) ?></td>
                            <td class="col-ceo px-3 py-2 border border-gray-100 text-gray-600"><?= htmlspecialchars($member['ceo_name'] ?? '') ?></td>
                            <td class="col-contact px-3 py-2 border border-gray-100 text-gray-600"><?= htmlspecialchars($member['contact'] ?? '') ?></td>
                            <td class="col-type px-3 py-2 border border-gray-100 text-center text-gray-600"><?= htmlspecialchars($mType) ?></td>
                            <td class="col-join px-3 py-2 border border-gray-100 text-center text-gray-500"><?= htmlspecialchars($member['join_date'] ?? '') ?></td>
                            <td class="col-withdraw px-3 py-2 border border-gray-100 text-center text-gray-500"><?= htmlspecialchars($member['withdraw_date'] ?? '') ?></td>
                            <td class="col-biz px-3 py-2 border border-gray-100 text-gray-500 font-mono"><?= htmlspecialchars($member['biz_no'] ?? '') ?></td>
                            <td class="col-est px-3 py-2 border border-gray-100 text-gray-500"><?= htmlspecialchars($member['established_date'] ?? '') ?></td>
                            <td class="col-email px-3 py-2 border border-gray-100 text-gray-500"><?= htmlspecialchars($member['email'] ?? '') ?></td>
                            <td class="col-addr px-3 py-2 border border-gray-100 text-gray-500 truncate max-w-[200px]" title="<?= htmlspecialchars($member['address'] ?? '') ?>"><?= htmlspecialchars($member['address'] ?? '') ?></td>
                            <td class="col-status px-3 py-2 border border-gray-100 text-center">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium
                                    <?= $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                    <?= htmlspecialchars($member['status']) ?>
                                </span>
                            </td>
                             <td class="col-fee px-3 py-2 border border-gray-100 text-right text-gray-400 italic">
                                <!-- 현재/대표 회비 표시 대신 툴팁 등으로 처리 가능하지만 여기선 유지 -->
                                <?= number_format($member['monthly_fee']) ?>
                            </td>

                            <?php foreach ($rowMonths as $idx => $rm): ?>
                                <?php
                                    $m = $months[$idx];
                                    $isCurrentMonth = ($m === $currentMonth && $year === (int)date('Y'));
                                    
                                    // [수정] 메모 및 클릭 정보 준비
                                    $hasMemo = false;
                                    $memoText = "";
                                    foreach ($rm['payments'] as $p) {
                                        if ($p['memo']) {
                                            $hasMemo = true;
                                            $memoText .= "· " . $p['memo'] . "\n";
                                        }
                                    }
                                    if (count($rm['payments']) === 1) {
                                        $pJson = htmlspecialchars(json_encode($rm['payments'][0]), ENT_QUOTES);
                                        $clickAttr = "onclick='openEditPaymentModal({$pJson}, \"".htmlspecialchars($member['company_name'], ENT_QUOTES)."\")'";
                                    } elseif (count($rm['payments']) > 1) {
                                        $pJson = htmlspecialchars(json_encode($rm['payments']), ENT_QUOTES);
                                        $clickAttr = "onclick='openPaymentListModal({$pJson}, \"".htmlspecialchars($member['company_name'], ENT_QUOTES)."\")'";
                                    } else {
                                        $clickAttr = "";
                                    }

                                    if ($basis === 'deposit') {
                                        if ($rm['paid'] > 0):
                                            $cellClass = 'text-blue-700 font-semibold';
                                            $cellBg    = $isCurrentMonth ? 'bg-blue-50' : 'bg-blue-50/40';
                                        else:
                                            $cellClass = 'text-gray-300';
                                            $cellBg    = $isCurrentMonth ? 'bg-blue-50/40' : '';
                                        endif;
                                    } else {
                                        if ($rm['paid'] > 0 && $rm['diff'] <= 0):
                                            $cellClass = 'text-blue-700 font-semibold';
                                            $cellBg    = $isCurrentMonth ? 'bg-blue-50' : 'bg-blue-50/40';
                                        elseif ($rm['expected'] > 0 && $rm['diff'] > 0):
                                            $cellClass = 'text-red-500 font-semibold';
                                            $cellBg    = $isCurrentMonth ? 'bg-red-50' : 'bg-red-50/30';
                                        else:
                                            $cellClass = 'text-gray-300';
                                            $cellBg    = $isCurrentMonth ? 'bg-blue-50/40' : '';
                                        endif;
                                    }
                                ?>
                                <td class="px-2 py-2 border border-gray-100 text-right <?= $cellBg ?> relative group cursor-pointer"
                                    <?= $clickAttr ?> title="<?= htmlspecialchars($memoText) ?>">
                                    <div class="<?= $cellClass ?> leading-tight">
                                        <?= $rm['paid'] > 0 ? number_format($rm['paid']) : '-' ?>
                                    </div>
                                    <?php
                                    if ($basis === 'target' && $rm['paid'] > 0) {
                                        $badges = [];
                                        foreach ($rm['payments'] as $p) {
                                            if (substr($p['payment_date'], 0, 7) !== $p['target_month']) {
                                                $shortDate = date('y/m/d', strtotime($p['payment_date']));
                                                $badges[] = "<div class=\"text-[9px] bg-yellow-100 text-yellow-800 rounded px-1 mt-0.5 inline-block font-normal\">{$shortDate}</div>";
                                            }
                                        }
                                        if (!empty($badges)) {
                                            echo '<div class="flex flex-col items-end gap-0.5">' . implode('', $badges) . '</div>';
                                        }
                                    }
                                    ?>
                                    <?php if ($hasMemo): ?>
                                        <span class="absolute top-1 right-1 w-1.5 h-1.5 bg-yellow-400 rounded-full"></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>

                            <td class="px-3 py-2 border border-gray-100 text-right bg-blue-50/60 font-bold text-gray-700">
                                <?= $totalPaid > 0 ? number_format($totalPaid) : '-' ?>
                            </td>
                            <td class="px-3 py-2 border border-gray-100 text-right bg-red-50/60 font-bold
                                <?= $totalUnpaid > 0 ? 'text-red-600' : 'text-gray-300' ?>">
                                <?= $totalUnpaid > 0 ? number_format($totalUnpaid) : '-' ?>
                            </td>
                            <td class="px-3 py-2 border border-gray-100 text-center bg-gray-50/50">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick="openPrintWindow('receipt', <?= $mid ?>)" title="영수증 인쇄" class="w-6 h-6 rounded bg-white border border-gray-200 text-gray-400 hover:text-blue-600 hover:border-blue-300 transition shadow-sm flex items-center justify-center">
                                        <i class="ph ph-receipt text-xs"></i>
                                    </button>
                                    <button onclick="openPrintWindow('invoice', <?= $mid ?>)" title="청구서 인쇄" class="w-6 h-6 rounded bg-white border border-gray-200 text-gray-400 hover:text-red-500 hover:border-red-300 transition shadow-sm flex items-center justify-center">
                                        <i class="ph ph-file-text text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- 개별 납부 등록 모달 -->
<div id="addModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 border-b bg-gray-50">
            <h3 class="font-bold text-gray-800 flex items-center gap-2"><i class="ph ph-money text-primary"></i> 납부 내역 등록</h3>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <form id="addPaymentForm" class="px-6 py-5 grid grid-cols-2 gap-4">
            <div class="col-span-2 relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">회원사 <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="text" id="memberSearchInput" placeholder="회원사 검색 및 선택" 
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary pl-9"
                           onfocus="showMemberList()" onkeyup="filterMemberList(this.value)">
                    <i class="ph ph-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                    <input type="hidden" name="member_id" id="selectedMemberId" required>
                </div>
                <!-- 커스텀 드롭다운 리스트 -->
                <div id="memberDropdown" class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-48 overflow-y-auto hidden">
                    <?php foreach ($memberList as $m): ?>
                        <div class="px-4 py-2 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-50 last:border-0"
                             onclick="selectMember(<?= $m['id'] ?>, '<?= htmlspecialchars($m['company_name']) ?>')">
                            <?= htmlspecialchars($m['company_name']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">입금일 <span class="text-red-500">*</span></label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">납부대상월 <span class="text-red-500">*</span></label>
                <input type="month" name="target_month" value="<?= date('Y-m') ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">금액 (원) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" placeholder="30000" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">구분</label>
                <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary">
                    <option value="월회비">월회비</option>
                    <option value="가입비">가입비</option>
                    <option value="특별회비">특별회비</option>
                    <option value="기타">기타</option>
                </select>
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">메모</label>
                <input type="text" name="memo" placeholder="비고 사항" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary">
            </div>
        </form>
        <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-2">
            <button onclick="closeAddModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50 transition">취소</button>
            <button onclick="submitAddPayment()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-blue-800 transition">저장하기</button>
        </div>
    </div>
</div>

<!-- 납부 내역 수정 모달 -->
<div id="editPaymentModal" class="fixed inset-0 bg-black/50 z-[100] flex items-center justify-center hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-fade-in-up">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800 flex items-center gap-2"><i class="ph ph-pencil-simple text-primary"></i> 납부 정보 수정</h3>
            <button onclick="closeEditPaymentModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <form id="editPaymentForm" class="px-6 py-5 space-y-4">
            <input type="hidden" name="id" id="edit_p_id">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">회원사</label>
                <input type="text" id="edit_p_member_name" disabled class="w-full bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">입금일</label>
                    <input type="date" name="payment_date" id="edit_p_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">납부대상월</label>
                    <input type="month" name="target_month" id="edit_p_target" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">금액 (원)</label>
                    <input type="number" name="amount" id="edit_p_amount" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">구분</label>
                    <select name="category" id="edit_p_category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="월회비">월회비</option>
                        <option value="가입비">가입비</option>
                        <option value="특별회비">특별회비</option>
                        <option value="기타">기타</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">계좌 정보</label>
                <input type="text" name="account_info" id="edit_p_account" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">메모 (비고)</label>
                <textarea name="memo" id="edit_p_memo" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="미납분 납부 등 상세 내용"></textarea>
            </div>
        </form>
        <div class="px-6 py-4 border-t bg-gray-50 flex justify-between gap-2">
            <button onclick="deletePaymentInEdit()" class="text-sm text-red-500 hover:text-red-700 flex items-center gap-1"><i class="ph ph-trash"></i> 삭제</button>
            <div class="flex gap-2">
                <button type="button" onclick="printSingleReceipt()" class="px-4 py-2 border border-blue-300 rounded-lg text-sm text-blue-600 bg-blue-50 hover:bg-blue-100 transition flex items-center gap-1"><i class="ph ph-receipt"></i> 영수증 출력</button>
                <button onclick="closeEditPaymentModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50 transition">취소</button>
                <button onclick="submitEditPayment()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-blue-800 transition">수정완료</button>
            </div>
        </div>
    </div>
</div>

<!-- 다중 납부 내역 선택 모달 -->
<div id="paymentListModal" class="fixed inset-0 bg-black/50 z-[100] flex items-center justify-center hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-fade-in-up">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800 flex items-center gap-2"><i class="ph ph-list-bullets text-primary"></i> <span id="paymentListMemberName"></span> 납부 내역 선택</h3>
            <button onclick="closePaymentListModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-500 mb-4">확인하거나 수정할 납부 내역을 선택하세요.</p>
            <div id="paymentListContainer" class="space-y-3 max-h-60 overflow-y-auto pr-1">
                <!-- Javascript에서 렌더링 -->
            </div>
        </div>
        <div class="px-6 py-4 border-t bg-gray-50 flex justify-end">
            <button onclick="closePaymentListModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50 transition">닫기</button>
        </div>
    </div>
</div>

<script>
    const availableCols = {
        'no': '회원번호',
        'name': '조직명',
        'ceo': '대표자',
        'contact': '연락처',
        'type': '회원구분',
        'join': '가입일',
        'withdraw': '탈퇴일',
        'biz': '사업자번호',
        'est': '설립일',
        'email': '이메일',
        'addr': '주소',
        'status': '상태',
        'fee': '책정회비'
    };

    let visibleCols = JSON.parse(localStorage.getItem('duesVisibleCols')) || ['no', 'name', 'status', 'fee'];

    function initColToggles() {
        const container = document.getElementById('colToggleContainer');
        container.innerHTML = '';
        for (const [key, labelText] of Object.entries(availableCols)) {
            const label = document.createElement('label');
            label.className = "flex items-center gap-2 text-sm text-gray-700 cursor-pointer p-1 hover:bg-gray-50 rounded";
            label.innerHTML = `<input type="checkbox" value="${key}" class="col-toggle rounded text-primary focus:ring-primary" ${visibleCols.includes(key) ? 'checked' : ''}> ${labelText}`;
            label.querySelector('input').addEventListener('change', (e) => {
                if (e.target.checked) visibleCols.push(key);
                else visibleCols = visibleCols.filter(c => c !== key);
                localStorage.setItem('duesVisibleCols', JSON.stringify(visibleCols));
                applyColVisibility();
            });
            container.appendChild(label);
        }
        applyColVisibility();
    }

    function applyColVisibility() {
        Object.keys(availableCols).forEach(col => {
            const els = document.querySelectorAll('.col-' + col);
            if (visibleCols.includes(col)) {
                els.forEach(el => el.classList.remove('hidden'));
            } else {
                els.forEach(el => el.classList.add('hidden'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initColToggles();
        applyFilters();
    });

// 구글 시트 가져오기
async function importGSheet() {
    const url = document.getElementById('sheetUrlInput').value.trim();
    if (!url) { alert('구글 시트 URL을 입력해주세요.'); return; }

    const resultEl = document.getElementById('importResult');
    resultEl.className = 'mt-3 bg-blue-50 border border-blue-200 text-blue-700 text-sm px-4 py-3 rounded-lg';
    resultEl.innerHTML = '<i class="ph ph-spinner animate-spin mr-1"></i> 데이터를 불러오는 중...';
    resultEl.classList.remove('hidden');

    const fd = new FormData();
    fd.append('action', 'import_gsheet');
    fd.append('sheet_url', url);

    try {
        const res  = await fetch('dues_management.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            resultEl.className = 'mt-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg whitespace-pre-line';
            resultEl.innerHTML = '<i class="ph ph-check-circle mr-1"></i>' + data.message;
            setTimeout(() => location.reload(), 2000);
        } else {
            resultEl.className = 'mt-3 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg whitespace-pre-line';
            resultEl.innerHTML = '<i class="ph ph-warning-circle mr-1"></i>' + data.message;
        }
    } catch (e) {
        alert('오류가 발생했습니다.');
    }
}

// 초기 이월 데이터 가져오기
async function importInitialUnpaid() {
    const url = prompt('초기 이월 데이터(2017~2021)가 포함된 구글 시트 URL을 입력하세요.');
    if (!url) return;

    const formData = new FormData();
    formData.append('action', 'import_initial_unpaid');
    formData.append('sheet_url', url);

    try {
        const res = await fetch('dues_management.php', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) location.reload();
    } catch (e) {
        alert('오류가 발생했습니다.');
    }
}

// 개별 납부 등록 모달
function openAddModal()  { document.getElementById('addModal').classList.remove('hidden'); }
function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }

async function submitAddPayment() {
    const form = document.getElementById('addPaymentForm');
    const fd   = new FormData(form);
    fd.append('action', 'add_payment');
    const res  = await fetch('dues_management.php', { method: 'POST', body: fd });
    const data = await res.json();
    alert(data.message);
    if (data.success) { closeAddModal(); location.reload(); }
}

// 수정 모달 관련
function openEditPaymentModal(p, memberName) {
    document.getElementById('edit_p_id').value = p.id;
    document.getElementById('edit_p_member_name').value = memberName;
    document.getElementById('edit_p_date').value = p.payment_date;
    document.getElementById('edit_p_target').value = p.target_month;
    document.getElementById('edit_p_amount').value = p.amount;
    document.getElementById('edit_p_category').value = p.category;
    document.getElementById('edit_p_account').value = p.account_info;
    document.getElementById('edit_p_memo').value = p.memo;
    
    document.getElementById('editPaymentModal').classList.remove('hidden');
}

function closeEditPaymentModal() {
    document.getElementById('editPaymentModal').classList.add('hidden');
}

function printSingleReceipt() {
    const paymentId = document.getElementById('edit_p_id').value;
    if (paymentId) {
        window.open('print_document.php?type=receipt&payment_id=' + paymentId, '_blank');
    } else {
        alert('출력할 결제 내역이 없습니다.');
    }
}

// 다중 납부 내역 선택 모달
function openPaymentListModal(payments, memberName) {
    document.getElementById('paymentListMemberName').innerText = memberName;
    const container = document.getElementById('paymentListContainer');
    container.innerHTML = '';
    
    payments.forEach(p => {
        const div = document.createElement('div');
        div.className = 'p-3 border border-gray-200 rounded-lg hover:border-primary hover:bg-blue-50 cursor-pointer transition';
        div.onclick = () => {
            closePaymentListModal();
            openEditPaymentModal(p, memberName);
        };
        
        const dateStr = p.payment_date || '날짜없음';
        const amountStr = Number(p.amount).toLocaleString() + '원';
        const memoStr = p.memo ? `<p class="text-xs text-gray-500 mt-1">${p.memo}</p>` : '';
        
        div.innerHTML = `
            <div class="flex justify-between items-center">
                <span class="font-bold text-gray-800">${amountStr}</span>
                <span class="text-xs text-gray-500">${dateStr}</span>
            </div>
            ${memoStr}
        `;
        container.appendChild(div);
    });
    
    document.getElementById('paymentListModal').classList.remove('hidden');
}

function closePaymentListModal() {
    document.getElementById('paymentListModal').classList.add('hidden');
}

async function submitEditPayment() {
    const form = document.getElementById('editPaymentForm');
    const fd = new FormData(form);
    fd.append('action', 'edit_payment');
    const res = await fetch('dues_management.php', { method: 'POST', body: fd });
    const data = await res.json();
    alert(data.message);
    if (data.success) location.reload();
}

async function deletePaymentInEdit() {
    if (!confirm('이 납부 내역을 삭제하시겠습니까?')) return;
    const id = document.getElementById('edit_p_id').value;
    const fd = new FormData();
    fd.append('action', 'delete_payment');
    fd.append('id', id);
    const res = await fetch('dues_management.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
}

// 필터 적용 로직
function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase();
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const unpaidMonth = document.getElementById('filterUnpaidMonth').value;
    const showWithdrawn = document.getElementById('showWithdrawn').checked;

    let sumMonths = new Array(13).fill(0);
    let sumTotalPaid = 0;
    let sumTotalUnpaid = 0;

    const rows = document.querySelectorAll('.matrix-row');
    rows.forEach(row => {
        const name = row.getAttribute('data-name').toLowerCase();
        const no = row.getAttribute('data-no').toLowerCase();
        const rType = row.getAttribute('data-type');
        const rStatus = row.getAttribute('data-status');
        const unpaidMonths = row.getAttribute('data-unpaid-months').split(',');

        let visible = true;
        if (search && !name.includes(search) && !no.includes(search)) visible = false;
        if (type && rType !== type) visible = false;
        if (status && rStatus !== status) visible = false;
        if (unpaidMonth && !unpaidMonths.includes(unpaidMonth)) visible = false;
        
        // 탈퇴 회원 배제 로직
        if (!showWithdrawn && rStatus === '탈퇴') visible = false;

        row.style.display = visible ? '' : 'none';

        if (visible) {
            sumTotalPaid += parseInt(row.getAttribute('data-total-paid') || 0);
            sumTotalUnpaid += parseInt(row.getAttribute('data-total-unpaid') || 0);
            for (let i = 1; i <= 12; i++) {
                sumMonths[i] += parseInt(row.getAttribute('data-paid-' + i) || 0);
            }
        }
    });

    // 합계 DOM 업데이트
    document.getElementById('sum-total-paid').innerText = sumTotalPaid > 0 ? sumTotalPaid.toLocaleString() : '0';
    document.getElementById('sum-total-unpaid').innerText = sumTotalUnpaid > 0 ? sumTotalUnpaid.toLocaleString() : '0';
    for (let i = 1; i <= 12; i++) {
        const mSum = sumMonths[i];
        document.getElementById('sum-month-' + i).innerText = mSum > 0 ? mSum.toLocaleString() : '0';
    }
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterType').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterUnpaidMonth').value = '';
    document.getElementById('showWithdrawn').checked = false;
    applyFilters();
}

// 회원사 검색 선택 (커스텀 드롭다운)
function showMemberList() {
    document.getElementById('memberDropdown').classList.remove('hidden');
}

function filterMemberList(val) {
    const filter = val.toLowerCase();
    const dropdown = document.getElementById('memberDropdown');
    const items = dropdown.querySelectorAll('div');
    items.forEach(item => {
        const text = item.innerText.toLowerCase();
        item.style.display = text.includes(filter) ? '' : 'none';
    });
    dropdown.classList.remove('hidden');
}

function selectMember(id, name) {
    document.getElementById('selectedMemberId').value = id;
    document.getElementById('memberSearchInput').value = name;
    document.getElementById('memberDropdown').classList.add('hidden');
}

// 클릭 외부 시 드롭다운 닫기
document.addEventListener('click', function(e) {
    const container = document.getElementById('memberSearchInput').parentElement;
    const dropdown = document.getElementById('memberDropdown');
    if (!container.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

// 엑셀 다운로드 (현황판 테이블 기준 - 필터링된 결과만 다운로드되도록 table_to_sheet는 보일 때만 긁어옴)
function exportMatrix() {
    const table = document.getElementById('matrixTable');
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table, { display: true });
    XLSX.utils.book_append_sheet(wb, ws, '<?= $year ?>년 회비현황');
    XLSX.writeFile(wb, '<?= $year ?>년_회비현황판.xlsx');
}

function openPrintWindow(type, memberId) {
    const startYear = <?= $year ?>;
    const endYear = <?= $year ?>;
    const url = `print_document.php?type=${type}&member_id=${memberId}&start_year=${startYear}&start_month=1&end_year=${endYear}&end_month=12`;
    window.open(url, 'printWindow', 'width=850,height=900,scrollbars=yes');
}
</script>

</body>
</html>
