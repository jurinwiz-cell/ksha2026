<?php
/**
 * member_report.php - 회원사별 회비납부 개별 현황 및 발행
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle   = '회원사별 현황';
$currentPage = 'reports';

// 검색 파라미터
$memberId  = (int)($_GET['member_id'] ?? 0);

// 회원 목록 (검색용 - 탈퇴 회원 제외)
$memberList = $pdo->query("SELECT id, company_name, member_no, join_date FROM tb_members WHERE status != '탈퇴' ORDER BY member_no ASC")->fetchAll();

// 1-001 기본 선택 처리
if ($memberId === 0 && !empty($memberList)) {
    foreach ($memberList as $m) {
        if ($m['member_no'] === '1-001') {
            $memberId = $m['id'];
            break;
        }
    }
    // 없으면 첫번째 회원
    if ($memberId === 0) $memberId = $memberList[0]['id'];
}

$startYear = (int)($_GET['start_year'] ?? date('Y'));
$startMonth = (int)($_GET['start_month'] ?? 1);
$endYear   = (int)($_GET['end_year'] ?? date('Y'));
$endMonth  = (int)($_GET['end_month'] ?? (int)date('m'));

$startDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
$endDate   = sprintf('%04d-%02d-01', $endYear, $endMonth);

$member = null;
$reportData = [];
$summary = [
    'expected' => 0,
    'paid'     => 0,
    'unpaid'   => 0
];

if ($memberId > 0) {
    // 1. 회원 정보 조회
    $stmt = $pdo->prepare("
        SELECT m.*, IFNULL(iu.total, 0) as initial_unpaid, IFNULL(d.unpaid_before_2022, 0) as manual_unpaid
        FROM tb_members m
        LEFT JOIN tb_initial_unpaid iu ON m.id = iu.member_id
        LEFT JOIN tb_member_details d ON m.id = d.member_id
        WHERE m.id = ?
    ");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        // 2. 회비 이력 정보 조회 (계산용)
        $stmtHistory = $pdo->prepare("SELECT * FROM tb_member_fee_history WHERE member_id = ? ORDER BY start_date DESC");
        $stmtHistory->execute([$memberId]);
        $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

        // 헬퍼 함수
        function getFeeAtDate($history, $date) {
            foreach ($history as $h) {
                if ($h['start_date'] <= $date) return (int)$h['monthly_fee'];
            }
            return 0; // 이력 시작일보다 이전이면 0 (과거에는 회비 책정 안됨)
        }

        // 3. 납부 내역 조회
        $stmtPayments = $pdo->prepare("
            SELECT target_month, SUM(amount) as total_paid 
            FROM tb_dues_payments 
            WHERE member_id = ? AND target_month >= ? AND target_month <= ?
            GROUP BY target_month
        ");
        $stmtPayments->execute([$memberId, substr($startDate, 0, 7), substr($endDate, 0, 7)]);
        $paymentsMap = $stmtPayments->fetchAll(PDO::FETCH_KEY_PAIR);

        // 4. 기간 루프 돌며 데이터 생성
        $curr = new DateTime($startDate);
        $end  = new DateTime($endDate);
        
        $earliestHistoryDate = count($history) > 0 ? substr(end($history)['start_date'], 0, 7) . '-01' : null;

        while ($curr <= $end) {
            $mKey = $curr->format('Y-m');
            $monthFirst = $curr->format('Y-m-01');
            $monthLast  = $curr->format('Y-m-t');

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

            // [수정] 가입일/탈퇴일 기준 해당 월 청구 대상 여부 판별
            $isMemberActive = ($member['join_date'] && $member['join_date'] <= $monthLast) && 
                              (!$member['withdraw_date'] || $member['withdraw_date'] >= $monthFirst);

            $fee  = ($isMemberActive) ? getFeeAtDate($history, $curr->format('Y-m-d')) : 0;
            $paid = (int)($paymentsMap[$mKey] ?? 0);
            
            $reportData[] = [
                'month'    => $mKey,
                'expected' => $fee,
                'paid'     => $paid,
                'diff'     => $fee - $paid
            ];
            
        $summary['expected'] += $fee;
        $summary['paid']     += $paid;
        
        $curr->modify('+1 month');
    }
    // 초기 이월 데이터 및 매뉴얼 미납액 합산
    $summary['unpaid'] = max(0, $summary['expected'] - $summary['paid']) + (int)$member['initial_unpaid'] + (int)$member['manual_unpaid'];
    }
}

// 개별 납부 등록 모달(검색용 - 탈퇴 회원 제외)
$memberList = $pdo->query("SELECT id, company_name, member_no FROM tb_members WHERE status != '탈퇴' ORDER BY member_no ASC")->fetchAll();

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10">
            <h1 class="text-xl font-bold text-gray-800">회원사별 납부 현황</h1>
            <div class="flex gap-2">
                <?php if ($member): ?>
                    <button onclick="window.print()" class="bg-white border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 flex items-center gap-2">
                        <i class="ph ph-printer text-lg"></i> 인쇄하기
                    </button>
                    <button onclick="openReceiptModal()" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-800 flex items-center gap-2">
                        <i class="ph ph-article text-lg"></i> 영수증/안내문 발행
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <main class="flex-1 overflow-auto bg-gray-100 p-6 lg:p-8">
            <!-- 검색 필터 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6 no-print">
                <form method="GET" id="filterForm" class="flex flex-col lg:flex-row items-end gap-4">
                    <div class="w-full lg:w-80 relative">
                        <label class="block text-xs font-bold text-gray-500 mb-1">회원사 검색 및 선택</label>
                        <div class="relative">
                            <input type="text" id="memberSearch" placeholder="명칭 또는 번호 검색" value="<?= $member ? '['.$member['member_no'].'] '.$member['company_name'] : '' ?>"
                                   class="w-full border border-gray-300 rounded-lg pl-9 pr-4 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none"
                                   onfocus="showDropdown()" onkeyup="filterDropdown(this.value)">
                            <i class="ph ph-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                            <input type="hidden" name="member_id" id="member_id" value="<?= $memberId ?>">
                        </div>
                        <div id="memberDropdown" class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-50 max-h-60 overflow-y-auto hidden">
                            <?php foreach ($memberList as $m): ?>
                                <div class="member-item px-4 py-2.5 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-50 last:border-0 flex justify-between items-center"
                                     data-no="<?= $m['member_no'] ?>" data-name="<?= htmlspecialchars($m['company_name']) ?>" data-join="<?= $m['join_date'] ?>"
                                     onclick="selectMember(<?= $m['id'] ?>, '<?= $m['member_no'] ?>', '<?= htmlspecialchars($m['company_name']) ?>', '<?= $m['join_date'] ?>')">
                                    <span class="font-bold text-gray-800">[<?= $m['member_no'] ?>] <?= htmlspecialchars($m['company_name']) ?></span>
                                    <span class="text-[10px] text-gray-400">가입: <?= $m['join_date'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1 flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 mb-1">조회 기간</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex gap-1 items-center bg-gray-50 px-3 py-1.5 rounded-lg border">
                                <input type="number" name="start_year" id="start_year" value="<?= $startYear ?>" class="w-16 bg-transparent border-none p-0 text-sm font-bold focus:ring-0">
                                <span class="text-gray-400">년</span>
                                <select name="start_month" id="start_month" class="bg-transparent border-none p-0 text-sm font-bold focus:ring-0">
                                    <?php for($i=1; $i<=12; $i++): ?>
                                        <option value="<?= $i ?>" <?= $startMonth == $i ? 'selected' : '' ?>><?= $i ?>월</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <span class="text-gray-300">~</span>
                            <div class="flex gap-1 items-center bg-gray-50 px-3 py-1.5 rounded-lg border">
                                <input type="number" name="end_year" id="end_year" value="<?= $endYear ?>" class="w-16 bg-transparent border-none p-0 text-sm font-bold focus:ring-0">
                                <span class="text-gray-400">년</span>
                                <select name="end_month" id="end_month" class="bg-transparent border-none p-0 text-sm font-bold focus:ring-0">
                                    <?php for($i=1; $i<=12; $i++): ?>
                                        <option value="<?= $i ?>" <?= $endMonth == $i ? 'selected' : '' ?>><?= $i ?>월</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <!-- 퀵 필터 단추 -->
                            <div class="flex gap-1 ml-2">
                                <button type="button" onclick="setPeriod('this_month')" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded text-[11px] font-bold hover:bg-gray-50">금월</button>
                                <button type="button" onclick="setPeriod('prev_month')" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded text-[11px] font-bold hover:bg-gray-50">전월</button>
                                <button type="button" onclick="setPeriod('this_year')" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded text-[11px] font-bold hover:bg-gray-50">금년</button>
                                <button type="button" onclick="setPeriod('prev_year')" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded text-[11px] font-bold hover:bg-gray-50">전년</button>
                                <button type="button" onclick="setPeriod('year_to_date')" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded text-[11px] font-bold hover:bg-gray-50">금년 전월까지</button>
                                <button type="button" onclick="setPeriod('all')" id="btnAllPeriod" class="px-2.5 py-1.5 bg-white border border-gray-200 rounded text-[11px] font-bold hover:bg-gray-50">전체</button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full lg:w-auto bg-gray-800 text-white px-8 py-2.5 rounded-lg text-sm font-bold hover:bg-black transition shadow-sm">조회</button>
                </form>
            </div>

            <?php if (!$member): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-20 text-center">
                    <i class="ph ph-files text-6xl text-gray-200 mb-4 block"></i>
                    <p class="text-gray-500">회원사를 선택하고 조회 기간을 설정하여 <br>상세 납부 현황을 확인하세요.</p>
                </div>
            <?php else: ?>
                <!-- 리포트 본문 -->
                <div class="space-y-6 print:space-y-4">
                    <!-- 요약 카드 -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 no-print">
                        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                            <p class="text-xs font-bold text-gray-400 mb-1 uppercase">총 발생 회비</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?= number_format($summary['expected']) ?>원</h3>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                            <p class="text-xs font-bold text-gray-400 mb-1 uppercase">총 납부액</p>
                            <h3 class="text-2xl font-bold text-gray-800 text-green-600"><?= number_format($summary['paid']) ?>원</h3>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-red-500">
                            <p class="text-xs font-bold text-gray-400 mb-1 uppercase">총 미납액</p>
                            <h3 class="text-2xl font-bold text-red-600"><?= number_format($summary['unpaid']) ?>원</h3>
                        </div>
                    </div>

                    <!-- 상세 리스트 -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                            <h2 class="font-bold text-gray-800">
                                <span class="text-primary"><?= htmlspecialchars($member['company_name']) ?></span> 납부 상세 내역
                                <span class="text-xs font-normal text-gray-400 ml-2">(<?= $startYear ?>.<?= $startMonth ?> ~ <?= $endYear ?>.<?= $endMonth ?>)</span>
                            </h2>
                        </div>
                        <table class="w-full text-sm text-center border-collapse">
                            <thead class="bg-gray-50 text-gray-500 text-xs font-bold">
                                <tr>
                                    <th class="px-6 py-3 border-b border-gray-100">대상월</th>
                                    <th class="px-6 py-3 border-b border-gray-100 text-right">책정 회비</th>
                                    <th class="px-6 py-3 border-b border-gray-100 text-right">납부한 금액</th>
                                    <th class="px-6 py-3 border-b border-gray-100 text-right">미납액</th>
                                    <th class="px-6 py-3 border-b border-gray-100">상태</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($reportData as $row): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 font-medium text-gray-700"><?= $row['month'] ?></td>
                                        <td class="px-6 py-4 text-right text-gray-500"><?= number_format($row['expected']) ?>원</td>
                                        <td class="px-6 py-4 text-right font-bold text-blue-600"><?= number_format($row['paid']) ?>원</td>
                                        <td class="px-6 py-4 text-right font-bold <?= $row['diff'] > 0 ? 'text-red-500' : 'text-gray-300' ?>">
                                            <?= $row['diff'] > 0 ? number_format($row['diff']).'원' : '-' ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($row['diff'] <= 0 && $row['expected'] > 0): ?>
                                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-[10px] font-bold">완납</span>
                                            <?php elseif ($row['diff'] > 0): ?>
                                                <span class="bg-red-100 text-red-600 px-2 py-0.5 rounded-full text-[10px] font-bold">미납</span>
                                            <?php else: ?>
                                                <span class="text-gray-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-200">
                                <tr>
                                    <td class="px-6 py-4">합계</td>
                                    <td class="px-6 py-4 text-right"><?= number_format($summary['expected']) ?>원</td>
                                    <td class="px-6 py-4 text-right text-blue-600"><?= number_format($summary['paid']) ?>원</td>
                                    <td class="px-6 py-4 text-right text-red-600"><?= number_format($summary['unpaid']) ?>원</td>
                                    <td class="px-6 py-4 text-orange-500"><?= $summary['unpaid'] > 0 ? '미납 존재' : '전체 완납' ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- 발행 모달 (심플 구현) -->
    <div id="publishModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden no-print">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div class="flex justify-between items-center px-6 py-4 border-b">
                <h3 class="font-bold text-gray-800">증명서 및 안내문 발행</h3>
                <button onclick="closeReceiptModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-2xl"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <button class="w-full flex items-center justify-between p-4 border border-gray-200 rounded-xl hover:border-primary hover:bg-blue-50 transition group" onclick="printDocument('receipt')">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-white transition">
                            <i class="ph ph-receipt text-xl"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-sm font-bold text-gray-800">납부 영수증 발행</p>
                            <p class="text-xs text-gray-400">조회된 기간의 완납 내역에 대한 영수증</p>
                        </div>
                    </div>
                </button>
                <button class="w-full flex items-center justify-between p-4 border border-gray-200 rounded-xl hover:border-red-400 hover:bg-red-50 transition group" onclick="printDocument('invoice')">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-100 text-red-500 rounded-full flex items-center justify-center group-hover:bg-red-500 group-hover:text-white transition">
                            <i class="ph ph-megaphone text-xl"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-sm font-bold text-gray-800">회비 현황 안내문 발행</p>
                            <p class="text-xs text-gray-400">미납 내역 및 입금 계좌 정보 포함 공문</p>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <script>
        function openReceiptModal() { document.getElementById('publishModal').classList.remove('hidden'); }
        function closeReceiptModal() { document.getElementById('publishModal').classList.add('hidden'); }
        
        function printDocument(type) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('type', type);
            // 만약 member_id 파라미터가 없다면 현재 선택된 member_id를 사용
            if(!urlParams.has('member_id')) {
                urlParams.set('member_id', document.getElementById('member_id').value);
            }
            window.open('print_document.php?' + urlParams.toString(), '_blank');
        }
    </script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body, .flex, .flex-1, main { border: none !important; background: white !important; height: auto !important; overflow: visible !important; display: block !important; }
            .h-screen { height: auto !important; }
            header { display: none !important; }
        }
    </style>
<script>
    function showDropdown() {
        document.getElementById('memberDropdown').classList.remove('hidden');
    }

    function filterDropdown(query) {
        query = query.toLowerCase();
        const items = document.querySelectorAll('.member-item');
        items.forEach(item => {
            const no = item.getAttribute('data-no').toLowerCase();
            const name = item.getAttribute('data-name').toLowerCase();
            if (no.includes(query) || name.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function selectMember(id, no, name, joinDate) {
        document.getElementById('member_id').value = id;
        document.getElementById('memberSearch').value = `[${no}] ${name}`;
        document.getElementById('memberDropdown').classList.add('hidden');
        
        // 가입일 정보를 전체 기간 버튼에 저장
        document.getElementById('btnAllPeriod').setAttribute('data-join', joinDate);
    }

    function setPeriod(type) {
        const today = new DateTime();
        const y = today.getFullYear();
        const m = today.getMonth() + 1;

        let sy = y, sm = m, ey = y, em = m;

        switch(type) {
            case 'this_month':
                break;
            case 'prev_month':
                const prev = new Date(y, m - 2, 1);
                sy = ey = prev.getFullYear();
                sm = em = prev.getMonth() + 1;
                break;
            case 'this_year':
                sm = 1; em = 12;
                break;
            case 'prev_year':
                sy = ey = y - 1;
                sm = 1; em = 12;
                break;
            case 'year_to_date':
                const p = new Date(y, m - 2, 1);
                sm = 1; 
                ey = p.getFullYear();
                em = p.getMonth() + 1;
                break;
            case 'all':
                const joinStr = document.getElementById('btnAllPeriod').getAttribute('data-join') || '2000-01-01';
                const join = new Date(joinStr);
                sy = join.getFullYear();
                sm = join.getMonth() + 1;
                ey = y;
                em = m;
                break;
        }

        document.getElementById('start_year').value = sy;
        document.getElementById('start_month').value = sm;
        document.getElementById('end_year').value = ey;
        document.getElementById('end_month').value = em;
    }

    // 밖 클릭 시 드롭다운 닫기
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.relative')) {
            document.getElementById('memberDropdown').classList.add('hidden');
        }
    });

    // 페이지 로드 시 드롭다운 펼치기
    window.addEventListener('load', () => {
        // 만약 member_id가 방금 세팅된 초기값이면 검색창 포커스 및 리스트 노출
        const search = document.getElementById('memberSearch');
        if (search.value.includes('1-001')) {
            // search.focus();
            // showDropdown();
        }
    });

    // Helper for setPeriod
    class DateTime extends Date {
        constructor() { super(); }
    }
</script>
</body>

</html>