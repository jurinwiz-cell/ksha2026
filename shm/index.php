<?php
/**
 * index.php - 대시보드 (동적 데이터 연동)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle   = '대시보드';
$currentPage = 'dashboard';

// ─── 기준 시점(기간) 설정 ──────────────────────────
$period = $_GET['period'] ?? 'this_month';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$showWithdrawn = isset($_GET['show_withdrawn']) && $_GET['show_withdrawn'] === '1';

$today = new DateTime();
$thisMonthStr = $today->format('Y-m');

switch ($period) {
    case 'prev_year':
        $y = (int)$today->format('Y') - 1;
        $startDate = "$y-01-01";
        $endDate = "$y-12-31";
        $periodLabel = "{$y}년 전체";
        break;
    case 'this_year':
        $y = $today->format('Y');
        $startDate = "$y-01-01";
        $endDate = "$y-12-31";
        $periodLabel = "{$y}년 전체";
        break;
    case 'prev_quarter':
        $month = (int)$today->format('n');
        $quarter = ceil($month / 3) - 1;
        if ($quarter == 0) {
            $quarter = 4;
            $year = (int)$today->format('Y') - 1;
        } else {
            $year = (int)$today->format('Y');
        }
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;
        $startDate = sprintf("%d-%02d-01", $year, $startMonth);
        $endDate = (new DateTime(sprintf("%d-%02d-01", $year, $endMonth)))->format('Y-m-t');
        $periodLabel = "{$year}년 {$quarter}분기";
        break;
    case 'prev_month':
        $d = clone $today;
        $d->modify('first day of last month');
        $startDate = $d->format('Y-m-01');
        $endDate = $d->format('Y-m-t');
        $periodLabel = $d->format('Y년 n월');
        break;
    case 'year_to_prev_month':
        $y = $today->format('Y');
        $startDate = "$y-01-01";
        $d = clone $today;
        $d->modify('first day of last month');
        $endDate = $d->format('Y-m-t');
        $periodLabel = $today->format('Y년') . " 1월 ~ " . $d->format('n월');
        break;
    case 'prev_year_to_prev_month':
    default:
        $prevYear = (int)$today->format('Y') - 1;
        $startDate = "$prevYear-01-01";
        $d = clone $today;
        $d->modify('first day of last month');
        $endDate = $d->format('Y-m-t');
        $periodLabel = "{$prevYear}년 1월 ~ " . $d->format('y년 n월');
        $period = 'prev_year_to_prev_month';
        break;
    case 'custom':
        if (!$startDate || !$endDate) {
            $startDate = $today->format('Y-m-01');
            $endDate = $today->format('Y-m-t');
        }
        $periodLabel = "$startDate ~ $endDate";
        break;
    case 'this_month':
        $startDate = $today->format('Y-m-01');
        $endDate = $today->format('Y-m-t');
        $periodLabel = $today->format('Y년 n월');
        break;
}

// target_month range (YYYY-MM format)
$startMonth = substr($startDate, 0, 7);
$endMonth = substr($endDate, 0, 7);

// ─── 요약 카드 쿼리 ───────────────────────────────
// [수정] 기준 시점(기간)에 활동했던 회원만 카운트 (가입일 <= 종료일 AND (탈퇴일 없거나 >= 시작일))
$baseWhere = "join_date IS NOT NULL AND join_date <= :end_date AND (withdraw_date IS NULL OR withdraw_date >= :start_date)";
if (!$showWithdrawn) {
    // 운영 상태인 경우만 (탈퇴일이 명시된 경우라도 현재 시점 기준 필터링은 위 조건에서 수행됨)
    $baseWhere .= " AND status != '탈퇴'";
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM tb_members WHERE $baseWhere");
$stmtTotal->execute([':start_date' => $startDate, ':end_date' => $endDate]);
$totalMembers = (int)$stmtTotal->fetchColumn();

$stmtActive = $pdo->prepare("SELECT COUNT(*) FROM tb_members WHERE $baseWhere AND status = '운영'");
$stmtActive->execute([':start_date' => $startDate, ':end_date' => $endDate]);
$activeMembers = (int)$stmtActive->fetchColumn();

// 해당 기간 수입 합계
$stmtIncome = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM tb_dues_payments WHERE target_month BETWEEN ? AND ?");
$stmtIncome->execute([$startMonth, $endMonth]);
$periodIncome = (int)$stmtIncome->fetchColumn();

// 해당 기간 미납 회원사 수 (기준: 마지막 달 기준 또는 기간 내 1회 이상 미납?)
// 여기서는 '기준 시점(종료일)' 시점의 미납으로 정의하는 것이 "기준시점 보기"에 적합함
$referenceMonth = substr($endDate, 0, 7);
$referenceDate  = $endDate;

$stmtUnpaid = $pdo->prepare("
    SELECT COUNT(*) FROM tb_members m
    WHERE (m.join_date IS NOT NULL AND m.join_date <= :ref_date)
      AND (m.withdraw_date IS NULL OR m.withdraw_date >= :ref_month_start)
      AND (
          SELECT monthly_fee FROM tb_member_fee_history 
          WHERE member_id = m.id AND start_date <= :ref_date
          ORDER BY start_date DESC LIMIT 1
      ) > 0
      AND NOT EXISTS (
          SELECT 1 FROM tb_dues_payments p
          WHERE p.member_id = m.id AND p.target_month = :ref_month
      )
");
$stmtUnpaid->execute([
    ':ref_date' => $referenceDate, 
    ':ref_month' => $referenceMonth,
    ':ref_month_start' => $referenceMonth . '-01'
]);
$unpaidCount = (int)$stmtUnpaid->fetchColumn();

// ─── 최근 6개월 수입 추이 ─────────────────────────
$monthLabels  = [];
$monthAmounts = [];
for ($i = 5; $i >= 0; $i--) {
    $m    = date('Y-m', strtotime("-{$i} months"));
    $label = date('n월', strtotime("-{$i} months"));
    $s    = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM tb_dues_payments WHERE target_month = ?");
    $s->execute([$m]);
    $monthLabels[]  = $label;
    $monthAmounts[] = (int)$s->fetchColumn();
}

// ─── 회원 구분 비율 ────────────────────────────────
$memberTypeData = $pdo->query("
    SELECT member_type, COUNT(*) AS cnt
    FROM tb_members
    GROUP BY member_type
")->fetchAll();
$typeLabels  = array_column($memberTypeData, 'member_type');
$typeCounts  = array_column($memberTypeData, 'cnt');

// ─── 미납 회원사 리스트 (기준 시점 기준) ─────────────
$stmtList = $pdo->prepare("
    SELECT m.id, m.member_no, m.company_name, m.ceo_name, m.contact, d.email,
           (SELECT monthly_fee FROM tb_member_fee_history 
            WHERE member_id = m.id AND start_date <= :ref_date 
            ORDER BY start_date DESC LIMIT 1) AS current_fee
    FROM tb_members m
    LEFT JOIN tb_member_details d ON m.id = d.member_id
    WHERE m.status = '운영'
      AND (m.join_date IS NULL OR m.join_date <= :ref_date)
      AND (m.withdraw_date IS NULL OR m.withdraw_date >= :ref_month_start)
      AND (
          SELECT monthly_fee FROM tb_member_fee_history 
          WHERE member_id = m.id AND start_date <= :ref_date
          ORDER BY start_date DESC LIMIT 1
      ) > 0
      AND NOT EXISTS (
          SELECT 1 FROM tb_dues_payments p
          WHERE p.member_id = m.id AND p.target_month = :ref_month
      )
    ORDER BY m.member_no ASC
    LIMIT 20
");
$stmtList->execute([
    ':ref_date' => $referenceDate, 
    ':ref_month' => $referenceMonth,
    ':ref_month_start' => $referenceMonth . '-01'
]);
$unpaidMembers = $stmtList->fetchAll();

// ─── 최근 납부 내역 ────────────────────────────────
$recentPayments = $pdo->query("
    SELECT p.payment_date, p.amount, p.target_month, p.category,
           m.company_name
    FROM tb_dues_payments p
    LEFT JOIN tb_members m ON p.member_id = m.id
    ORDER BY p.created_at DESC
    LIMIT 10
")->fetchAll();

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">

    <!-- 헤더 -->
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-bold text-gray-800">대시보드</h1>
            
            <form action="index.php" method="GET" id="periodForm" class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <select name="period" onchange="toggleCustomDates(this.value); this.form.submit()" class="text-sm border-gray-300 rounded-md bg-gray-50 px-3 py-1.5 focus:ring-primary focus:border-primary">
                        <option value="prev_year_to_prev_month" <?= $period == 'prev_year_to_prev_month' ? 'selected' : '' ?>>전년도~직전월(기본)</option>
                        <option value="this_month" <?= $period == 'this_month' ? 'selected' : '' ?>>금월</option>
                        <option value="prev_month" <?= $period == 'prev_month' ? 'selected' : '' ?>>직전월</option>
                        <option value="prev_quarter" <?= $period == 'prev_quarter' ? 'selected' : '' ?>>직전분기</option>
                        <option value="this_year" <?= $period == 'this_year' ? 'selected' : '' ?>>해당년도</option>
                        <option value="prev_year" <?= $period == 'prev_year' ? 'selected' : '' ?>>직전년도</option>
                        <option value="year_to_prev_month" <?= $period == 'year_to_prev_month' ? 'selected' : '' ?>>금년 직전월까지</option>
                        <option value="custom" <?= $period == 'custom' ? 'selected' : '' ?>>사용자정의</option>
                    </select>
                    
                    <div id="customDates" class="<?= $period == 'custom' ? 'flex' : 'hidden' ?> items-center gap-1">
                        <input type="date" name="start_date" value="<?= $startDate ?>" class="text-xs border-gray-300 rounded-md px-2 py-1">
                        <span class="text-gray-400">~</span>
                        <input type="date" name="end_date" value="<?= $endDate ?>" class="text-xs border-gray-300 rounded-md px-2 py-1">
                        <button type="submit" class="bg-gray-200 px-2 py-1 rounded text-xs hover:bg-gray-300">적용</button>
                    </div>
                </div>

                <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
                    <input type="checkbox" name="show_withdrawn" value="1" <?= $showWithdrawn ? 'checked' : '' ?> onchange="this.form.submit()" class="rounded text-primary focus:ring-primary">
                    탈퇴 회원 포함
                </label>
            </form>
        </div>
        <div class="text-sm text-gray-400 font-medium">
            <i class="ph ph-calendar-check mr-1"></i> <?= $periodLabel ?> 현황
        </div>
    </header>

    <script>
    function toggleCustomDates(val) {
        const div = document.getElementById('customDates');
        if (val === 'custom') div.classList.replace('hidden', 'flex');
        else div.classList.replace('flex', 'hidden');
    }
    </script>

    <!-- 본문 -->
    <main class="flex-1 overflow-auto bg-gray-100 p-6 lg:p-8">

        <!-- 요약 카드 -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <?php
            $cards = [
                ['label' => '전체 회원사',    'value' => number_format($totalMembers).'개사',  'icon' => 'ph-fill ph-buildings',     'color' => 'bg-blue-50 text-secondary'],
                ['label' => '운영중 회원사',   'value' => number_format($activeMembers).'개사', 'icon' => 'ph-fill ph-check-circle',  'color' => 'bg-green-50 text-green-500'],
                ['label' => '기간 수입 합계',  'value' => number_format($periodIncome).'원',   'icon' => 'ph-fill ph-coins',         'color' => 'bg-yellow-50 text-accent'],
                ['label' => '기준시점 미납',  'value' => number_format($unpaidCount).'개사',  'icon' => 'ph-fill ph-warning-circle','color' => 'bg-red-50 text-red-500'],
            ];
            foreach ($cards as $card): ?>
                <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1"><?= $card['label'] ?></p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= $card['value'] ?></h3>
                    </div>
                    <div class="w-11 h-11 rounded-full <?= $card['color'] ?> flex items-center justify-center">
                        <i class="<?= $card['icon'] ?> text-2xl"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 차트 + 미납 리스트 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            <!-- 월별 수입 추이 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 lg:col-span-2">
                <h2 class="text-base font-bold text-gray-800 mb-4">최근 6개월 회비 수입 추이</h2>
                <div class="h-56"><canvas id="trendChart"></canvas></div>
            </div>

            <!-- 회원 구분 도넛 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-base font-bold text-gray-800 mb-3">회원 유형 비율</h2>
                <div class="h-44 flex items-center justify-center"><canvas id="typeChart"></canvas></div>
                <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 justify-center text-xs text-gray-500">
                    <?php
                    $typeColors = ['#3b82f6','#f59e0b','#10b981','#ef4444','#8b5cf6','#6b7280'];
                    foreach ($typeLabels as $ti => $tl): ?>
                        <span class="flex items-center gap-1">
                            <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:<?= $typeColors[$ti % count($typeColors)] ?>"></span>
                            <?= htmlspecialchars($tl) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 당월 미납 + 최근 납부 내역 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- 당월 미납 리스트 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex justify-between items-center bg-red-50">
                    <h2 class="font-bold text-red-600 flex items-center gap-1.5 text-sm">
                        <i class="ph-fill ph-warning-circle"></i><?= substr($referenceMonth, 5) ?>월 미납 회원사 (<?= $unpaidCount ?>개)
                    </h2>
                    <a href="dues_management.php" class="text-xs text-gray-500 hover:underline">회비 관리 →</a>
                </div>
                <?php if (empty($unpaidMembers)): ?>
                    <div class="p-8 text-center text-gray-400 text-sm"><i class="ph ph-check-circle text-green-400 text-2xl mb-1 block"></i>미납 회원사가 없습니다!</div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($unpaidMembers as $u): ?>
                            <li class="flex items-center justify-between px-5 py-3 hover:bg-red-50/40 transition">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($u['company_name']) ?></p>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($u['contact'] ?? '-') ?></p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-bold text-red-500"><?= number_format($u['current_fee']) ?>원</span>
                                    <div class="flex gap-1 border-l border-red-200 pl-3">
                                        <button onclick="openPrintWindow('invoice', <?= $u['id'] ?>)" title="안내문 인쇄" class="w-7 h-7 rounded bg-white border border-gray-200 text-gray-500 flex items-center justify-center hover:bg-gray-50 hover:text-blue-600 transition shadow-sm">
                                            <i class="ph ph-printer"></i>
                                        </button>
                                        <button onclick="sendMail('<?= htmlspecialchars($u['company_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>', <?= $u['current_fee'] ?>)" title="메일 발송" class="w-7 h-7 rounded bg-white border border-gray-200 text-gray-500 flex items-center justify-center hover:bg-gray-50 hover:text-green-600 transition shadow-sm">
                                            <i class="ph ph-envelope-simple"></i>
                                        </button>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- 최근 납부 내역 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                    <h2 class="font-bold text-gray-800 flex items-center gap-1.5 text-sm"><i class="ph ph-clock-counter-clockwise text-primary"></i> 최근 납부 내역</h2>
                </div>
                <?php if (empty($recentPayments)): ?>
                    <div class="p-8 text-center text-gray-400 text-sm">등록된 납부 내역이 없습니다.</div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($recentPayments as $p): ?>
                            <li class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition">
                                <div>
                                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($p['company_name'] ?? '미매칭') ?></p>
                                    <p class="text-xs text-gray-400"><?= $p['payment_date'] ?> · <?= htmlspecialchars($p['target_month']) ?> <?= htmlspecialchars($p['category']) ?></p>
                                </div>
                                <span class="text-sm font-bold text-blue-600"><?= number_format($p['amount']) ?>원</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <footer class="text-center text-xs text-gray-400 border-t border-gray-200 pt-4 pb-2">
            © 2026 (사)한국사회주택협회. All rights reserved.
        </footer>
    </main>
</div>

<script>
function openPrintWindow(type, memberId) {
    const startYear = new Date().getFullYear();
    const endYear = new Date().getFullYear();
    const url = `print_document.php?type=${type}&member_id=${memberId}&start_year=${startYear}&start_month=1&end_year=${endYear}&end_month=12`;
    window.open(url, 'printWindow', 'width=850,height=900,scrollbars=yes');
}

function sendMail(companyName, email, fee) {
    const subject = encodeURIComponent(`[안내] ${companyName} 귀하, 회비 납부 안내의 건`);
    const body = encodeURIComponent(`${companyName} 귀하\n\n무궁한 발전을 기원합니다.\n당월 미납된 회비 내역(기준 금액: ${fee.toLocaleString()}원)을 확인해 주시고, 
아래 계좌로 납부해 주시면 감사하겠습니다.\n\n* 계좌번호: (은행명) 000-000-000000\n* 예금주: 협회명\n\n감사합니다.`);
    
    if(!email) {
        alert("등록된 이메일 주소가 없습니다. 기본 메일 클라이언트가 열리면 수신자를 직접 입력해주세요.");
    }
    
    window.location.href = `mailto:${email || ''}?subject=${subject}&body=${body}`;
}

// 월별 수입 추이 차트
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: '수입 (원)',
            data: <?= json_encode($monthAmounts) ?>,
            backgroundColor: 'rgba(59,130,246,0.15)',
            borderColor: '#3b82f6',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }},
        scales: { y: { beginAtZero: true, ticks: { callback: v => (v/1000).toLocaleString()+'천' } } }
    }
});

// 회원 유형 도넛 차트
const typeCtx = document.getElementById('typeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($typeLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode($typeCounts) ?>,
            backgroundColor: ['#3b82f6','#f59e0b','#10b981','#ef4444','#8b5cf6','#6b7280'],
            borderWidth: 0, hoverOffset: 4
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '68%',
        plugins: { legend: { display: false } }
    }
});
</script>
</body>
</html>
