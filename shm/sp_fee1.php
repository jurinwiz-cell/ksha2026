<?php
// 에러 출력을 켜서 500 에러의 구체적인 원인을 확인합니다.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================================
// 1. 인증 및 데이터베이스 연결 설정
// ==========================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// ==========================================
// 2. 검색 및 필터링 파라미터 수신 (GET 방식)
// ==========================================
$view = $_GET['view'] ?? 'all';
$search = $_GET['search'] ?? '';
$selectedCompany = $_GET['company'] ?? '모든 회원사';

// ==========================================
// 3. 데이터 쿼리 실행 (공통)
// ==========================================
$stmt = $pdo->query("SELECT DISTINCT company_name FROM sp_fee WHERE company_name IS NOT NULL ORDER BY company_name");
$companies = $stmt->fetchAll(PDO::FETCH_COLUMN);

$kpiSql = "SELECT 
        COUNT(CASE WHEN status_category NOT IN ('후원금', '이사회 조정') THEN 1 END) as totalProjects,
        SUM(CASE WHEN status_category NOT IN ('후원금', '이사회 조정') THEN total_fee ELSE 0 END) as totalFeeAmt,
        SUM(CASE WHEN status_category = '납부완료' THEN paid_amount ELSE 0 END) as paidFeeAmt,
        SUM(CASE WHEN status_category = '후원금' THEN total_fee ELSE 0 END) as donationAmt,
        SUM(CASE WHEN status_category = '상계' THEN total_fee ELSE 0 END) as offsetAmt
    FROM sp_fee";
$kpiResult = $pdo->query($kpiSql)->fetch();

$totalProjects = $kpiResult['totalProjects'] ?? 0;
$totalFeeAmt = $kpiResult['totalFeeAmt'] ?? 0;
$paidFeeAmt = $kpiResult['paidFeeAmt'] ?? 0;
$donationAmt = $kpiResult['donationAmt'] ?? 0;
$offsetAmt = $kpiResult['offsetAmt'] ?? 0;
$totalRevenue = $paidFeeAmt + $donationAmt + $offsetAmt;

// ==========================================
// 4. 뷰별 데이터 로드
// ==========================================
if ($view === 'all') {
    $query = "SELECT * FROM sp_fee WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $query .= " AND (building_name LIKE :search OR id LIKE :search_id)";
        $params[':search'] = "%$search%";
        $params[':search_id'] = "%$search%";
    }
    if ($selectedCompany !== '모든 회원사') {
        $query .= " AND company_name = :company";
        $params[':company'] = $selectedCompany;
    }
    $query .= " ORDER BY id ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $filteredData = $stmt->fetchAll();
    
    $totalUnits = 0; $totalFilteredFee = 0; $totalFilteredPaid = 0;
    foreach ($filteredData as $row) {
        $totalUnits += (int)($row['units'] ?? 0);
        $totalFilteredFee += (int)($row['total_fee'] ?? 0);
        $totalFilteredPaid += (int)($row['paid_amount'] ?? 0);
    }
} elseif ($view === 'company') {
    $stmtCompany = $pdo->query("SELECT company_name, 
        COUNT(id) as projects_count, 
        SUM(units) as total_units, 
        SUM(total_fee) as fee_sum, 
        SUM(paid_amount) as paid_sum 
    FROM sp_fee 
    WHERE status_category NOT IN ('후원금', '이사회 조정')
    GROUP BY company_name 
    ORDER BY company_name");
    $companyData = $stmtCompany->fetchAll();
} elseif ($view === 'summary') {
    $stmtType = $pdo->query("SELECT project_type, COUNT(id) as count, SUM(total_fee) as fee_sum FROM sp_fee WHERE status_category NOT IN ('후원금', '이사회 조정') GROUP BY project_type ORDER BY fee_sum DESC");
    $typeData = $stmtType->fetchAll();
    
    $stmtStatus = $pdo->query("SELECT status_category, COUNT(id) as count, SUM(total_fee) as fee_sum, SUM(paid_amount) as paid_sum FROM sp_fee GROUP BY status_category ORDER BY count DESC");
    $statusData = $stmtStatus->fetchAll();
} elseif ($view === 'status') {
    $stmtStatusList = $pdo->query("SELECT * FROM sp_fee 
        WHERE status_category IN ('청구완료', '추후청구') OR (total_fee > paid_amount AND status_category NOT IN ('종결', '후원금', '이사회 조정'))
        ORDER BY total_fee DESC");
    $unpaidData = $stmtStatusList->fetchAll();
}

function formatCurrency($num) { return number_format((float)$num); }
function getStatusColor($status) {
    switch ($status) {
        case '납부완료': return 'bg-green-50 text-green-700 border-green-200';
        case '청구완료': return 'bg-blue-50 text-blue-700 border-blue-200';
        case '추후청구': return 'bg-yellow-50 text-yellow-700 border-yellow-200';
        case '상계': return 'bg-purple-50 text-purple-700 border-purple-200';
        case '후원금': return 'bg-pink-50 text-pink-700 border-pink-200';
        case '종결': return 'bg-gray-50 text-gray-700 border-gray-200';
        case '이사회 조정': return 'bg-orange-50 text-orange-700 border-orange-200';
        default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

$kpiCards = [
    ['icon' => 'building', 'label' => '전체 사업 수', 'value' => formatCurrency($totalProjects), 'color' => 'text-teal-600', 'bg' => 'bg-teal-50'],
    ['icon' => 'trending-up', 'label' => '회비 총액', 'value' => '₩' . formatCurrency($totalFeeAmt), 'color' => 'text-orange-500', 'bg' => 'bg-orange-50'],
    ['icon' => 'check-circle', 'label' => '회비 입금', 'value' => '₩' . formatCurrency($paidFeeAmt), 'color' => 'text-green-600', 'bg' => 'bg-green-50'],
    ['icon' => 'heart', 'label' => '후원금', 'value' => '₩' . formatCurrency($donationAmt), 'color' => 'text-purple-500', 'bg' => 'bg-purple-50'],
    ['icon' => 'refresh-cw', 'label' => '상계 금액', 'value' => '₩' . formatCurrency($offsetAmt), 'color' => 'text-indigo-500', 'bg' => 'bg-indigo-50'],
    ['icon' => 'credit-card', 'label' => '총 수납액', 'value' => '₩' . formatCurrency($totalRevenue), 'color' => 'text-blue-600', 'bg' => 'bg-blue-50'],
];

$pageTitle = '전체 회원사 목록';
if ($view === 'company') $pageTitle = '회원사별 특별회비 현황';
elseif ($view === 'summary') $pageTitle = '특별회비 총괄 보고서';
elseif ($view === 'status') $pageTitle = '특별회비 청구 현황 보고서';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KSHA 특별회비 관리</title>
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 h-screen overflow-hidden flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-[#111827] text-slate-300 flex flex-col transition-all duration-300 shrink-0">
        <div class="h-20 flex items-center px-6 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded bg-[#0f766e] flex items-center justify-center text-white">
                    <i data-lucide="building" class="w-[18px] h-[18px]"></i>
                </div>
                <span class="text-white font-bold text-lg tracking-wide">KSHA 특별회비</span>
            </div>
        </div>

        <nav class="flex-1 py-6 px-4 space-y-2">
            <a href="/shm/" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 hover:text-white transition-colors mb-4 border border-slate-700">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                <span>메인 포털 이동</span>
            </a>
            <a href="?view=all" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $view === 'all' ? 'bg-[#0f766e] text-white font-medium' : 'hover:bg-slate-800 hover:text-white' ?> transition-colors">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>전체 회원사</span>
            </a>
            <a href="?view=company" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $view === 'company' ? 'bg-[#0f766e] text-white font-medium' : 'hover:bg-slate-800 hover:text-white' ?> transition-colors">
                <i data-lucide="users" class="w-5 h-5"></i>
                <span>회원사별</span>
            </a>
            <a href="?view=summary" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $view === 'summary' ? 'bg-[#0f766e] text-white font-medium' : 'hover:bg-slate-800 hover:text-white' ?> transition-colors">
                <i data-lucide="file-text" class="w-5 h-5"></i>
                <span>총괄 보고서</span>
            </a>
            <a href="?view=status" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $view === 'status' ? 'bg-[#0f766e] text-white font-medium' : 'hover:bg-slate-800 hover:text-white' ?> transition-colors">
                <i data-lucide="pie-chart" class="w-5 h-5"></i>
                <span>현황 보고서</span>
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center gap-3 px-4 py-2">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-sm font-bold text-white">
                    <?= mb_substr($_SESSION['admin_name'] ?? '관', 0, 1) ?>
                </div>
                <div>
                    <p class="text-sm font-medium text-white"><?= htmlspecialchars($_SESSION['admin_name'] ?? '관리자') ?></p>
                    <p class="text-xs text-slate-500">관리자</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="h-20 flex items-center justify-between px-8 bg-white border-b border-slate-200 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><?= $pageTitle ?></h1>
                <div class="flex items-center gap-2 mt-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Data Connected: MySQL socialhousing_db</span>
                </div>
            </div>
            <a href="/shm/logout.php" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors border border-slate-200">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                <span>로그아웃</span>
            </a>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            
            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
                <?php foreach($kpiCards as $card): ?>
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 flex flex-col justify-between group hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center <?= $card['bg'] ?> <?= $card['color'] ?>">
                            <i data-lucide="<?= $card['icon'] ?>" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1"><?= $card['label'] ?></p>
                        <h3 class="text-2xl font-bold text-slate-900 truncate"><?= $card['value'] ?></h3>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($view === 'all'): ?>
            <!-- All Filter & Action Bar (Form) -->
            <form method="GET" action="" id="filterForm" class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <input type="hidden" name="view" value="all">
                <div class="flex flex-1 gap-4 w-full md:w-auto">
                    <div class="relative w-full md:w-96">
                        <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 w-[18px] h-[18px]"></i>
                        <input type="text" name="search" placeholder="건물명 또는 회원사번호 검색..." value="<?= htmlspecialchars($search) ?>" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] focus:bg-white transition-all text-sm" />
                    </div>
                    <div class="relative w-full md:w-64">
                        <i data-lucide="filter" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 w-[18px] h-[18px]"></i>
                        <select name="company" onchange="document.getElementById('filterForm').submit();" class="w-full pl-10 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] focus:bg-white appearance-none text-sm cursor-pointer">
                            <option value="모든 회원사">모든 회원사</option>
                            <?php foreach($companies as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $selectedCompany === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <button type="button" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-lg font-medium text-sm transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i><span>CSV 대량 동기화</span>
                    </button>
                    <button type="button" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-5 py-2.5 bg-[#0f172a] text-white hover:bg-[#1e293b] rounded-lg font-medium text-sm transition-colors shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i><span>신규 사업 등록</span>
                    </button>
                </div>
            </form>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-[#0f766e] text-white px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                    <div class="text-sm font-medium opacity-90">필터 집계</div>
                    <div class="flex gap-12 text-right">
                        <div><div class="text-xs text-teal-100 mb-1">세대수 합계</div><div class="font-bold"><?= formatCurrency($totalUnits) ?>세대</div></div>
                        <div><div class="text-xs text-teal-100 mb-1">회비내역 합계</div><div class="font-bold">₩<?= formatCurrency($totalFilteredFee) ?></div><div class="text-xs text-teal-200 mt-0.5">수납합계: ₩<?= formatCurrency($totalFilteredPaid) ?></div></div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4">건물 정보</th>
                                <th class="px-6 py-4">회원사</th>
                                <th class="px-6 py-4">유형/운영</th>
                                <th class="px-6 py-4 text-center">세대수</th>
                                <th class="px-6 py-4 text-right">회비내역</th>
                                <th class="px-6 py-4 text-center">상태</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (count($filteredData) > 0): ?>
                                <?php foreach ($filteredData as $row): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-900 group-hover:text-[#0f766e] transition-colors"><?= htmlspecialchars($row['building_name']) ?></div>
                                        <div class="text-xs text-slate-500 mt-1">#<?= htmlspecialchars($row['id']) ?> · <?= htmlspecialchars($row['region'] ?? '-') ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200"><?= htmlspecialchars($row['company_name']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-slate-700"><?= htmlspecialchars($row['project_type'] ?? '-') ?></div>
                                        <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($row['operation_type'] ?? '-') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="font-medium text-slate-700"><?= $row['units'] > 0 ? $row['units'] . '세대' : '-' ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="font-bold text-slate-900">₩<?= formatCurrency($row['total_fee']) ?></div>
                                        <div class="text-xs text-emerald-600 font-medium mt-1">수납: ₩<?= formatCurrency($row['paid_amount']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border <?= getStatusColor($row['status_category']) ?>">
                                            <?= htmlspecialchars($row['status_category']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">검색 결과가 없습니다.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php elseif ($view === 'company'): ?>
            <!-- Company View -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="font-bold text-slate-800 text-lg flex items-center gap-2"><i data-lucide="users" class="text-[#0f766e] w-5 h-5"></i> 회원사별 집계 현황</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4">회원사명</th>
                                <th class="px-6 py-4 text-center">사업장 수</th>
                                <th class="px-6 py-4 text-center">총 세대수</th>
                                <th class="px-6 py-4 text-right">총 책정 회비</th>
                                <th class="px-6 py-4 text-right">총 수납액</th>
                                <th class="px-6 py-4 text-right">잔여금(미납)</th>
                                <th class="px-6 py-4 text-center">납부율</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($companyData as $c): 
                                $rem = $c['fee_sum'] - $c['paid_sum'];
                                $rem = $rem > 0 ? $rem : 0;
                                $pct = $c['fee_sum'] > 0 ? floor(($c['paid_sum'] / $c['fee_sum']) * 100) : 0;
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-900"><?= htmlspecialchars($c['company_name']) ?></td>
                                <td class="px-6 py-4 text-center text-slate-600"><?= $c['projects_count'] ?>건</td>
                                <td class="px-6 py-4 text-center text-slate-600"><?= formatCurrency($c['total_units']) ?>세대</td>
                                <td class="px-6 py-4 text-right font-medium">₩<?= formatCurrency($c['fee_sum']) ?></td>
                                <td class="px-6 py-4 text-right font-bold text-emerald-600">₩<?= formatCurrency($c['paid_sum']) ?></td>
                                <td class="px-6 py-4 text-right font-bold text-rose-500">₩<?= formatCurrency($rem) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-24 bg-slate-200 rounded-full h-2">
                                            <div class="bg-[#0f766e] h-2 rounded-full" style="width: <?= $pct > 100 ? 100 : $pct ?>%"></div>
                                        </div>
                                        <span class="text-xs font-bold text-slate-700 w-8 text-right"><?= $pct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($view === 'summary'): ?>
            <!-- Summary View -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Type Summary -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                        <h2 class="font-bold text-slate-800 flex items-center gap-2"><i data-lucide="pie-chart" class="text-indigo-600 w-5 h-5"></i> 사업 유형별 비중</h2>
                    </div>
                    <table class="w-full text-left">
                        <thead class="text-xs font-semibold text-slate-500 bg-slate-50 border-b border-slate-200">
                            <tr><th class="px-6 py-3">사업 유형</th><th class="px-6 py-3 text-center">건수</th><th class="px-6 py-3 text-right">책정 회비 합계</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($typeData as $t): ?>
                            <tr>
                                <td class="px-6 py-3 font-medium text-slate-700"><?= htmlspecialchars($t['project_type'] ?? '미분류') ?></td>
                                <td class="px-6 py-3 text-center"><?= $t['count'] ?></td>
                                <td class="px-6 py-3 text-right font-bold">₩<?= formatCurrency($t['fee_sum']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Status Summary -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                        <h2 class="font-bold text-slate-800 flex items-center gap-2"><i data-lucide="bar-chart-2" class="text-orange-600 w-5 h-5"></i> 상태별 현황</h2>
                    </div>
                    <table class="w-full text-left">
                        <thead class="text-xs font-semibold text-slate-500 bg-slate-50 border-b border-slate-200">
                            <tr><th class="px-6 py-3">상태</th><th class="px-6 py-3 text-center">건수</th><th class="px-6 py-3 text-right">금액 합계</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($statusData as $s): ?>
                            <tr>
                                <td class="px-6 py-3">
                                    <span class="inline-flex px-2 py-1 rounded text-xs font-semibold border <?= getStatusColor($s['status_category']) ?>"><?= htmlspecialchars($s['status_category']) ?></span>
                                </td>
                                <td class="px-6 py-3 text-center"><?= $s['count'] ?></td>
                                <td class="px-6 py-3 text-right font-bold">
                                    <?php if ($s['status_category'] === '납부완료'): ?>
                                        <span class="text-emerald-600">₩<?= formatCurrency($s['paid_sum']) ?></span>
                                    <?php else: ?>
                                        ₩<?= formatCurrency($s['fee_sum']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($view === 'status'): ?>
            <!-- Status View -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                    <h2 class="font-bold text-slate-800 text-lg flex items-center gap-2"><i data-lucide="alert-circle" class="text-rose-600 w-5 h-5"></i> 미납 / 청구 대상 집중 관리</h2>
                    <span class="text-xs text-slate-500">납부완료 및 종결 처리되지 않은 잔여 금액 보유 건</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4">상태</th>
                                <th class="px-6 py-4">회원사명</th>
                                <th class="px-6 py-4">건물 정보</th>
                                <th class="px-6 py-4 text-right">총 회비</th>
                                <th class="px-6 py-4 text-right">미납 금액 (잔여)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(count($unpaidData) > 0): ?>
                                <?php foreach ($unpaidData as $u): 
                                    $rem = $u['total_fee'] - $u['paid_amount'];
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 rounded text-xs font-semibold border <?= getStatusColor($u['status_category']) ?>"><?= htmlspecialchars($u['status_category']) ?></span>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-slate-700"><?= htmlspecialchars($u['company_name']) ?></td>
                                    <td class="px-6 py-4 font-bold text-slate-900"><?= htmlspecialchars($u['building_name']) ?></td>
                                    <td class="px-6 py-4 text-right">₩<?= formatCurrency($u['total_fee']) ?></td>
                                    <td class="px-6 py-4 text-right font-bold text-rose-600 text-lg">₩<?= formatCurrency($rem) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">현재 미납되거나 청구 대기중인 내역이 없습니다. 🎉</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </main>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>

