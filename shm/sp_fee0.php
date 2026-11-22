<?php
// 에러 출력을 켜서 500 에러의 구체적인 원인을 확인합니다.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================================
// 1. 데이터베이스 연결 설정
// ==========================================
require_once __DIR__ . '/shm/ncludes/db.php';

// ==========================================
// 2. 검색 및 필터링 파라미터 수신 (GET 방식)
// ==========================================
$search = $_GET['search'] ?? '';
$selectedCompany = $_GET['company'] ?? '모든 회원사';

// ==========================================
// 3. 데이터 쿼리 실행
// ==========================================

// 드롭다운을 위한 고유 회원사 목록 추출
$stmt = $pdo->query("SELECT DISTINCT company_name FROM sp_fee WHERE company_name IS NOT NULL ORDER BY company_name");
$companies = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 상단 KPI 지표 계산 쿼리
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

// 메인 테이블 필터링 데이터 쿼리
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

// 테이블 데이터 서머리 계산 (불러온 필터링 데이터 기준)
$totalUnits = 0;
$totalFilteredFee = 0;
$totalFilteredPaid = 0;

foreach ($filteredData as $row) {
    $totalUnits += (int)($row['units'] ?? 0);
    $totalFilteredFee += (int)($row['total_fee'] ?? 0);
    $totalFilteredPaid += (int)($row['paid_amount'] ?? 0);
}

// ==========================================
// 4. 뷰를 위한 헬퍼 함수 및 설정
// ==========================================
function formatCurrency($num) {
    return number_format($num);
}

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

// KPI 카드 데이터 배열
$kpiCards = [
    ['icon' => 'building', 'label' => '전체 사업 수', 'value' => formatCurrency($totalProjects), 'color' => 'text-teal-600', 'bg' => 'bg-teal-50'],
    ['icon' => 'trending-up', 'label' => '회비 총액', 'value' => '₩' . formatCurrency($totalFeeAmt), 'color' => 'text-orange-500', 'bg' => 'bg-orange-50'],
    ['icon' => 'check-circle', 'label' => '회비 입금', 'value' => '₩' . formatCurrency($paidFeeAmt), 'color' => 'text-green-600', 'bg' => 'bg-green-50'],
    ['icon' => 'heart', 'label' => '후원금', 'value' => '₩' . formatCurrency($donationAmt), 'color' => 'text-purple-500', 'bg' => 'bg-purple-50'],
    ['icon' => 'refresh-cw', 'label' => '상계 금액', 'value' => '₩' . formatCurrency($offsetAmt), 'color' => 'text-indigo-500', 'bg' => 'bg-indigo-50'],
    ['icon' => 'credit-card', 'label' => '총 수납액', 'value' => '₩' . formatCurrency($totalRevenue), 'color' => 'text-blue-600', 'bg' => 'bg-blue-50'],
];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KSHA 특별회비 관리</title>
    <!-- Tailwind CSS (CDN) -->
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <!-- Lucide Icons (CDN) -->
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
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-[#0f766e] text-white font-medium transition-colors">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>전체 회원사</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 hover:text-white transition-colors">
                <i data-lucide="users" class="w-5 h-5"></i>
                <span>회원사별</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 hover:text-white transition-colors">
                <i data-lucide="file-text" class="w-5 h-5"></i>
                <span>총괄 보고서</span>
            </a>
            <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 hover:text-white transition-colors">
                <i data-lucide="pie-chart" class="w-5 h-5"></i>
                <span>현황 보고서</span>
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center gap-3 px-4 py-2">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-sm font-bold text-white">
                    관
                </div>
                <div>
                    <p class="text-sm font-medium text-white">채준배</p>
                    <p class="text-xs text-slate-500">관리자</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden">
        
        <!-- Header -->
        <header class="h-20 flex items-center justify-between px-8 bg-white border-b border-slate-200 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">전체 회원사 목록</h1>
                <div class="flex items-center gap-2 mt-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Data Connected: MySQL socialhousing_db</span>
                </div>
            </div>
            <button class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors border border-slate-200">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                <span>로그아웃</span>
            </button>
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

            <!-- Filter & Action Bar (Form) -->
            <form method="GET" action="" id="filterForm" class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex flex-1 gap-4 w-full md:w-auto">
                    <div class="relative w-full md:w-96">
                        <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 w-[18px] h-[18px]"></i>
                        <input 
                            type="text" 
                            name="search"
                            placeholder="건물명 또는 회원사번호 검색... (Enter 입력)" 
                            value="<?= htmlspecialchars($search) ?>"
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] focus:bg-white transition-all text-sm"
                        />
                    </div>
                    <div class="relative w-full md:w-64">
                        <i data-lucide="filter" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 w-[18px] h-[18px]"></i>
                        <select 
                            name="company"
                            onchange="document.getElementById('filterForm').submit();"
                            class="w-full pl-10 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] focus:bg-white appearance-none text-sm cursor-pointer"
                        >
                            <option value="모든 회원사">모든 회원사</option>
                            <?php foreach($companies as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $selectedCompany === $c ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <button type="button" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-lg font-medium text-sm transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span>CSV 대량 동기화</span>
                    </button>
                    <button type="button" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-5 py-2.5 bg-[#0f172a] text-white hover:bg-[#1e293b] rounded-lg font-medium text-sm transition-colors shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>신규 사업 등록</span>
                    </button>
                </div>
            </form>

            <!-- Data Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <!-- Table Summary Header -->
                <div class="bg-[#0f766e] text-white px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                    <div class="text-sm font-medium opacity-90">필터 집계</div>
                    <div class="flex gap-12 text-right">
                        <div>
                            <div class="text-xs text-teal-100 mb-1">세대수 합계</div>
                            <div class="font-bold"><?= formatCurrency($totalUnits) ?>세대</div>
                        </div>
                        <div>
                            <div class="text-xs text-teal-100 mb-1">회비내역 합계</div>
                            <div class="font-bold">₩<?= formatCurrency($totalFilteredFee) ?></div>
                            <div class="text-xs text-teal-200 mt-0.5">수납합계: ₩<?= formatCurrency($totalFilteredPaid) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Table Columns -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4"><div class="flex items-center gap-1 cursor-pointer hover:text-slate-700">건물 정보 <i data-lucide="arrow-up-down" class="w-3 h-3"></i></div></th>
                                <th class="px-6 py-4"><div class="flex items-center gap-1 cursor-pointer hover:text-slate-700">회원사 <i data-lucide="arrow-up-down" class="w-3 h-3"></i></div></th>
                                <th class="px-6 py-4"><div class="flex items-center gap-1 cursor-pointer hover:text-slate-700">유형/운영 <i data-lucide="arrow-up-down" class="w-3 h-3"></i></div></th>
                                <th class="px-6 py-4 text-center"><div class="flex items-center justify-center gap-1 cursor-pointer hover:text-slate-700">세대수 <i data-lucide="arrow-up-down" class="w-3 h-3"></i></div></th>
                                <th class="px-6 py-4 text-right"><div class="flex items-center justify-end gap-1 cursor-pointer hover:text-slate-700">회비내역 <i data-lucide="arrow-up-down" class="w-3 h-3"></i></div></th>
                                <th class="px-6 py-4 text-center"><div class="flex items-center justify-center gap-1 cursor-pointer hover:text-slate-700">상태 <i data-lucide="arrow-up-down" class="w-3 h-3"></i></div></th>
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
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                            <?= htmlspecialchars($row['company_name']) ?>
                                        </span>
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
                                            <?php if ($row['status_category'] === '납부완료'): ?>
                                                <i data-lucide="check-circle" class="w-3 h-3"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($row['status_category']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                        검색 결과가 없습니다.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </main>
    <!-- Init Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>

