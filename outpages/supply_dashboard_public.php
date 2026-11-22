<?php
/**
 * supply_dashboard_public.php - 사회주택 공급현황 대시보드 (외부페이지용)
 */
require_once __DIR__ . '/../shm/includes/db.php';

// 필터 파라미터 수집
$status = $_GET['status'] ?? '';
$region = $_GET['region'] ?? '';
$type = $_GET['type'] ?? '';
$public = $_GET['public'] ?? '';
$showAll = isset($_GET['show_all']) && $_GET['show_all'] === '1';

$mainStatuses = ['준공', '미준공', '종료', '미착공'];

$where = ["1=1"];
$params = [];

// 1. 공급현황(상태) 필터
if ($status) {
    if ($status === '미진행') {
        $where[] = "completion_status NOT IN ('" . implode("','", $mainStatuses) . "')";
    } else {
        $where[] = "completion_status = ?";
        $params[] = $status;
    }
} elseif (!$showAll) {
    $where[] = "completion_status IN ('" . implode("','", $mainStatuses) . "')";
}

// 2. 광역 필터
if ($region) {
    $where[] = "location_si = ?";
    $params[] = $region;
}

// 3. 대분류 필터
if ($type) {
    $where[] = "supply_type = ?";
    $params[] = $type;
}

// 4. 공공주체 필터 (키워드 확장 검색)
if ($public) {
    $publicMap = [
        'SH' => ['SH', '서울주택', '서울시'],
        'LH' => ['LH', '한국토지'],
        '서울시' => ['서울시', '서울특별시'],
        'HUG' => ['HUG', '주택도시보증'],
        '경기도' => ['경기', '경기도시', 'GH'],
        '인천시' => ['인천', 'iH']
    ];

    $keywords = $publicMap[$public] ?? [$public];
    $subWhere = [];
    foreach ($keywords as $k) {
        $subWhere[] = "JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.public_entity')) LIKE ?";
        $params[] = "%$k%";
    }
    $where[] = "(" . implode(" OR ", $subWhere) . ")";
}

$whereClause = implode(" AND ", $where);

// --- 필터별 독립적 통계 산출 (Faceted Search) ---

// 1. 광역 필터용 통계
$whereRegion = ["s.deleted_at IS NULL"];
$paramsRegion = [];
if ($public) {
    $whereRegion[] = "JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.public_entity')) LIKE ?";
    $paramsRegion[] = "%$public%";
}
if ($type) {
    $whereRegion[] = "s.supply_type = ?";
    $paramsRegion[] = $type;
}
if ($status) {
    $whereRegion[] = ($status === '미진행') ? "s.completion_status NOT IN ('" . implode("','", $mainStatuses) . "')" : "s.completion_status = ?";
    if ($status !== '미진행')
        $paramsRegion[] = $status;
} elseif (!$showAll) {
    $whereRegion[] = "s.completion_status IN ('" . implode("','", $mainStatuses) . "')";
}
$whereRegionClause = implode(" AND ", $whereRegion);
$regions = $pdo->prepare("SELECT s.location_si, COUNT(*) as p_count, SUM(s.household_count) as h_count FROM tb_social_housing_supply s WHERE s.location_si != '' AND $whereRegionClause GROUP BY s.location_si ORDER BY s.location_si");
$regions->execute($paramsRegion);
$regions = $regions->fetchAll(PDO::FETCH_ASSOC);

// 2. 대분류 필터용 통계
$whereType = ["s.deleted_at IS NULL"];
$paramsType = [];
if ($region) {
    $whereType[] = "s.location_si = ?";
    $paramsType[] = $region;
}
if ($public) {
    $whereType[] = "JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.public_entity')) LIKE ?";
    $paramsType[] = "%$public%";
}
if ($status) {
    $whereType[] = ($status === '미진행') ? "s.completion_status NOT IN ('" . implode("','", $mainStatuses) . "')" : "s.completion_status = ?";
    if ($status !== '미진행')
        $paramsType[] = $status;
} elseif (!$showAll) {
    $whereType[] = "s.completion_status IN ('" . implode("','", $mainStatuses) . "')";
}
$whereTypeClause = implode(" AND ", $whereType);
$types = $pdo->prepare("SELECT s.supply_type, COUNT(*) as p_count, SUM(s.household_count) as h_count FROM tb_social_housing_supply s WHERE s.supply_type != '' AND $whereTypeClause GROUP BY s.supply_type ORDER BY s.supply_type");
$types->execute($paramsType);
$types = $types->fetchAll(PDO::FETCH_ASSOC);

// 3. 공급현황 필터용 통계
$whereStatus = ["s.deleted_at IS NULL"];
$paramsStatus = [];
if ($region) {
    $whereStatus[] = "s.location_si = ?";
    $paramsStatus[] = $region;
}
if ($public) {
    $whereStatus[] = "JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.public_entity')) LIKE ?";
    $paramsStatus[] = "%$public%";
}
if ($type) {
    $whereStatus[] = "s.supply_type = ?";
    $paramsStatus[] = $type;
}
$whereStatusClause = implode(" AND ", $whereStatus);
$statusStats = $pdo->prepare("SELECT s.completion_status, COUNT(*) as p_count, SUM(s.household_count) as h_count FROM tb_social_housing_supply s WHERE $whereStatusClause GROUP BY s.completion_status");
$statusStats->execute($paramsStatus);
$statusStats = $statusStats->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

// 4. 공공주체 필터용 통계
$wherePublic = ["s.deleted_at IS NULL"];
$paramsPublic = [];
if ($region) {
    $wherePublic[] = "s.location_si = ?";
    $paramsPublic[] = $region;
}
if ($type) {
    $wherePublic[] = "s.supply_type = ?";
    $paramsPublic[] = $type;
}
if ($status) {
    $wherePublic[] = ($status === '미진행') ? "s.completion_status NOT IN ('" . implode("','", $mainStatuses) . "')" : "s.completion_status = ?";
    if ($status !== '미진행')
        $paramsPublic[] = $status;
} elseif (!$showAll) {
    $wherePublic[] = "s.completion_status IN ('" . implode("','", $mainStatuses) . "')";
}
$wherePublicClause = implode(" AND ", $wherePublic);

$publicsList = ['SH', 'LH', '서울시', 'HUG', '경기도', '인천시', '기타'];
$publicStats = [];
$publicMap = [
    'SH' => ['SH', '서울주택', '서울시'],
    'LH' => ['LH', '한국토지'],
    '서울시' => ['서울시', '서울특별시'],
    'HUG' => ['HUG', '주택도시보증'],
    '경기도' => ['경기', '경기도시', 'GH'],
    '인천시' => ['인천', 'iH']
];

foreach ($publicsList as $p) {
    $keywords = $publicMap[$p] ?? [$p];
    $subWhere = [];
    $subParams = [];
    foreach ($keywords as $k) {
        $subWhere[] = "JSON_UNQUOTE(JSON_EXTRACT(s.raw_data, '$.public_entity')) LIKE ?";
        $subParams[] = "%$k%";
    }
    $finalSubWhere = "(" . implode(" OR ", $subWhere) . ")";

    $stmt = $pdo->prepare("SELECT COUNT(*) as p_count, SUM(household_count) as h_count FROM tb_social_housing_supply s WHERE $finalSubWhere AND $wherePublicClause");
    $stmt->execute(array_merge($subParams, $paramsPublic));
    $publicStats[$p] = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 1. 기초 통계 데이터 조회
$stmtBase = $pdo->prepare("
    SELECT 
        COUNT(*) as total_projects,
        SUM(household_count) as total_households,
        COUNT(CASE WHEN completion_status = '준공' THEN 1 END) as completed_projects
    FROM tb_social_housing_supply
    WHERE $whereClause
");
$stmtBase->execute($params);
$baseStats = $stmtBase->fetch();

$totalProjects = $baseStats['total_projects'];
$totalHouseholds = $baseStats['total_households'];
$completionRate = $totalProjects > 0 ? round(($baseStats['completed_projects'] / $totalProjects) * 100, 1) : 0;

$stmtPublic = $pdo->prepare("
    SELECT COUNT(*) as public_count 
    FROM tb_social_housing_supply 
    WHERE JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.public_entity')) NOT LIKE '%민간%' 
      AND JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.public_entity')) != ''
      AND $whereClause
");
$stmtPublic->execute($params);
$publicStats_single = $stmtPublic->fetch();
$publicRatio = $totalProjects > 0 ? round(($publicStats_single['public_count'] / $totalProjects) * 100, 1) : 0;

// 2. 유형별 통계 (차트용)
$stmtType = $pdo->prepare("
    SELECT supply_type, SUM(household_count) as h_count 
    FROM tb_social_housing_supply 
    WHERE $whereClause
    GROUP BY supply_type 
    ORDER BY h_count DESC
");
$stmtType->execute($params);
$typeStats = $stmtType->fetchAll();

// 3. 지역별-유형별 통계 (차트용)
$stmtRegionRaw = $pdo->prepare("
    SELECT location_si, supply_type, SUM(household_count) as h_count 
    FROM tb_social_housing_supply 
    WHERE location_si != '' AND $whereClause
    GROUP BY location_si, supply_type 
");
$stmtRegionRaw->execute($params);
$regionRaw = $stmtRegionRaw->fetchAll(PDO::FETCH_ASSOC);

$stmtRegionTotal = $pdo->prepare("
    SELECT location_si, SUM(household_count) as total_h 
    FROM tb_social_housing_supply 
    WHERE location_si != '' AND $whereClause
    GROUP BY location_si 
    ORDER BY total_h DESC
");
$stmtRegionTotal->execute($params);
$regionLabels = array_column($stmtRegionTotal->fetchAll(PDO::FETCH_ASSOC), 'location_si');

// 4. 연도별-유형별 공급 추이 (차트용)
$stmtYearRaw = $pdo->prepare("
    SELECT 
        CASE 
            WHEN completion_date REGEXP '^[0-9]{4}' THEN SUBSTRING(completion_date, 1, 4)
            WHEN completion_date REGEXP '^[0-9]{2}년' THEN CONCAT('20', SUBSTRING(completion_date, 1, 2))
            ELSE '기타'
        END as c_year,
        supply_type,
        SUM(household_count) as h_count
    FROM tb_social_housing_supply
    WHERE $whereClause
    GROUP BY c_year, supply_type
    HAVING c_year != '기타'
");
$stmtYearRaw->execute($params);
$yearRaw = $stmtYearRaw->fetchAll(PDO::FETCH_ASSOC);

$stmtYearLabels = $pdo->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN completion_date REGEXP '^[0-9]{4}' THEN SUBSTRING(completion_date, 1, 4)
            WHEN completion_date REGEXP '^[0-9]{2}년' THEN CONCAT('20', SUBSTRING(completion_date, 1, 2))
            ELSE '기타'
        END as c_year
    FROM tb_social_housing_supply
    WHERE $whereClause
    HAVING c_year != '기타'
    ORDER BY c_year ASC
");
$stmtYearLabels->execute($params);
$yearLabels = array_column($stmtYearLabels->fetchAll(PDO::FETCH_ASSOC), 'c_year');

// 차트 데이터셋 생성
$allTypes = array_column($typeStats, 'supply_type');
$typeColors = ['#1e3a8a', '#d97706', '#059669', '#7c3aed', '#db2777', '#4b5563', '#0284c7', '#ea580c', '#16a34a', '#9333ea'];
$typeColorMap = [];
foreach ($allTypes as $idx => $t) {
    $typeColorMap[$t] = $typeColors[$idx % count($typeColors)];
}

$regionDatasets = [];
$yearDatasets = [];
foreach ($allTypes as $t) {
    $rData = [];
    foreach ($regionLabels as $r) {
        $val = 0;
        foreach ($regionRaw as $row) {
            if ($row['location_si'] === $r && $row['supply_type'] === $t) {
                $val = $row['h_count'];
                break;
            }
        }
        $rData[] = $val;
    }
    $regionDatasets[] = [
        'label' => $t,
        'data' => $rData,
        'backgroundColor' => $typeColorMap[$t],
        'borderRadius' => 4
    ];

    $yData = [];
    foreach ($yearLabels as $y) {
        $val = 0;
        foreach ($yearRaw as $row) {
            if ($row['c_year'] === $y && $row['supply_type'] === $t) {
                $val = $row['h_count'];
                break;
            }
        }
        $yData[] = $val;
    }
    $yearDatasets[] = [
        'label' => $t,
        'data' => $yData,
        'backgroundColor' => $typeColorMap[$t],
        'borderRadius' => 4
    ];
}
?>

<link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
<script src="/static/vendor/js/phosphor-icons.js"></script>

<div class="bg-gray-50 text-gray-800 font-sans antialiased">
    <!-- 필터 섹션 -->
    <div class="bg-white border-b border-gray-200 px-6 py-4 rounded-t-xl">
        <form action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?')) ?>" method="GET"
            class="flex flex-wrap items-center gap-4">
            <?php if (!empty($_GET['mid'])): ?>
                <input type="hidden" name="mid" value="<?= htmlspecialchars($_GET['mid']) ?>">
            <?php endif; ?>

            <!-- 광역 필터 -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-gray-400 uppercase ml-1">광역</label>
                <select name="region" onchange="this.form.submit()"
                    class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary outline-none bg-white min-w-[150px]">
                    <option value="">모든 지역</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= $r['location_si'] ?>" <?= $region === $r['location_si'] ? 'selected' : '' ?>>
                            <?= $r['location_si'] ?> (<?= $r['p_count'] ?>건, <?= number_format($r['h_count']) ?>세대)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 공공주체 필터 -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-gray-400 uppercase ml-1">공공주체</label>
                <select name="public" onchange="this.form.submit()"
                    class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary outline-none bg-white min-w-[150px]">
                    <option value="">전체 주체</option>
                    <?php foreach ($publicStats as $name => $stat): ?>
                        <option value="<?= $name ?>" <?= $public === $name ? 'selected' : '' ?>>
                            <?= $name ?> (<?= $stat['p_count'] ?>건, <?= number_format($stat['h_count'] ?: 0) ?>세대)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 대분류 필터 -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-gray-400 uppercase ml-1">대분류(유형)</label>
                <select name="type" onchange="this.form.submit()"
                    class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary outline-none bg-white min-w-[180px]">
                    <option value="">모든 유형</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t['supply_type'] ?>" <?= $type === $t['supply_type'] ? 'selected' : '' ?>>
                            <?= $t['supply_type'] ?> (<?= $t['p_count'] ?>건, <?= number_format($t['h_count']) ?>세대)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 공급현황 필터 -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold text-gray-400 uppercase ml-1">공급현황</label>
                <select name="status" onchange="this.form.submit()"
                    class="border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-primary outline-none bg-white min-w-[150px]">
                    <option value="">기본 (준공/미준공..)</option>
                    <option value="준공" <?= $status === '준공' ? 'selected' : '' ?>>준공
                        (<?= $statusStats['준공']['p_count'] ?? 0 ?>건,
                        <?= number_format($statusStats['준공']['h_count'] ?? 0) ?>세대)</option>
                    <option value="미준공" <?= $status === '미준공' ? 'selected' : '' ?>>미준공
                        (<?= $statusStats['미준공']['p_count'] ?? 0 ?>건,
                        <?= number_format($statusStats['미준공']['h_count'] ?? 0) ?>세대)</option>
                    <option value="종료" <?= $status === '종료' ? 'selected' : '' ?>>종료
                        (<?= $statusStats['종료']['p_count'] ?? 0 ?>건,
                        <?= number_format($statusStats['종료']['h_count'] ?? 0) ?>세대)</option>
                    <option value="미착공" <?= $status === '미착공' ? 'selected' : '' ?>>미착공
                        (<?= $statusStats['미착공']['p_count'] ?? 0 ?>건,
                        <?= number_format($statusStats['미착공']['h_count'] ?? 0) ?>세대)</option>
                </select>
            </div>

            <div class="flex items-center gap-4 mt-4">
                <label class="flex items-center gap-1.5 text-xs font-bold text-gray-500 cursor-pointer">
                    <input type="checkbox" name="show_all" value="1" <?= $showAll ? 'checked' : '' ?>
                        onchange="this.form.submit()" class="w-4 h-4 rounded border-gray-300 text-blue-600">
                    모든 상태 표시
                </label>
                <a href="<?= htmlspecialchars($_SERVER['SCRIPT_NAME'] . (!empty($_GET['mid']) ? '?mid=' . urlencode($_GET['mid']) : '')) ?>"
                    class="text-xs font-bold text-gray-400 hover:text-gray-600 flex items-center gap-1">
                    <i class="ph ph-arrows-counter-clockwise"></i> 필터 초기화
                </a>
            </div>
        </form>
    </div>

    <main class="overflow-auto p-6 lg:p-8 rounded-b-xl border-x border-b border-gray-200 bg-gray-50">

        <!-- KPI 카드 섹션 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group">
                <div class="absolute right-[-10px] top-[-10px] opacity-5 group-hover:scale-110 transition-transform">
                    <i class="ph ph-house-line text-8xl"></i>
                </div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">총 공급 세대수</p>
                <div class="flex items-baseline gap-1">
                    <h3 class="text-3xl font-black text-gray-800"><?= number_format($totalHouseholds) ?></h3>
                    <span class="text-sm text-gray-500">세대</span>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-600">누적 현황</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group">
                <div class="absolute right-[-10px] top-[-10px] opacity-5 group-hover:scale-110 transition-transform">
                    <i class="ph ph-stack text-8xl text-orange-500"></i>
                </div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">필터링 사업수</p>
                <div class="flex items-baseline gap-1">
                    <h3 class="text-3xl font-black text-orange-500"><?= number_format($totalProjects) ?></h3>
                    <span class="text-sm text-gray-500">개소</span>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-orange-50 text-orange-600">등록 사업
                        기준</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group">
                <div class="absolute right-[-10px] top-[-10px] opacity-5 group-hover:scale-110 transition-transform">
                    <i class="ph ph-check-circle text-8xl text-green-600"></i>
                </div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">공급 완료율 (준공)</p>
                <div class="flex items-baseline gap-1">
                    <h3 class="text-3xl font-black text-green-600"><?= $completionRate ?>%</h3>
                </div>
                <div class="mt-4 w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-green-500 h-full" style="width: <?= $completionRate ?>%"></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden group">
                <div class="absolute right-[-10px] top-[-10px] opacity-5 group-hover:scale-110 transition-transform">
                    <i class="ph ph-users-three text-8xl text-blue-600"></i>
                </div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">공공 협력 비중</p>
                <div class="flex items-baseline gap-1">
                    <h3 class="text-3xl font-black text-blue-600"><?= $publicRatio ?>%</h3>
                </div>
                <div class="mt-4 w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-blue-600 h-full" style="width: <?= $publicRatio ?>%"></div>
                </div>
            </div>
        </div>

        <!-- 차트 섹션 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- 유형별 세대수 (Donut) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h4 class="font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <i class="ph-fill ph-chart-pie-slice text-blue-600"></i> 유형별 세대수 비중
                </h4>
                <div class="h-64 flex items-center justify-center">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>

            <!-- 지역별 세대수 (Horizontal Bar) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h4 class="font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <i class="ph-fill ph-chart-bar text-orange-500"></i> 지역별 공급 현황 (세대수)
                </h4>
                <div class="h-64">
                    <canvas id="regionChart"></canvas>
                </div>
            </div>

            <!-- 연도별 공급 추이 (Line/Bar) -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:col-span-2">
                <h4 class="font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <i class="ph-fill ph-trend-up text-green-600"></i> 연도별 공급 추이
                </h4>
                <div class="h-80">
                    <canvas id="yearChart"></canvas>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Chart.js -->
<script src="/static/vendor/js/chart.min.js"></script>
<script src="/static/vendor/js/chartjs-plugin-datalabels.min.js"></script>
<script>
    function initDashboardCharts() {
        if (typeof Chart === 'undefined' || typeof ChartDataLabels === 'undefined') {
            setTimeout(initDashboardCharts, 100);
            return;
        }
        Chart.register(ChartDataLabels);

        // ─── 유형별 차트 ───────────────────────────────
        const typeEl = document.getElementById('typeChart');
        if (typeEl) {
            new Chart(typeEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($typeStats, 'supply_type') ?: []) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($typeStats, 'h_count') ?: []) ?>,
                        backgroundColor: <?= json_encode(array_values($typeColorMap) ?: []) ?>,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 10, font: { size: 11 } } },
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold', size: 11 },
                            formatter: (value, ctx) => {
                                let total = ctx.chart._metasets[ctx.datasetIndex].total;
                                if (!total) return '';
                                return (value * 100 / total).toFixed(1) + "%";
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }

        // ─── 지역별 차트 ───────────────────────────────
        const regionEl = document.getElementById('regionChart');
        if (regionEl) {
            new Chart(regionEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($regionLabels ?: []) ?>,
                    datasets: <?= json_encode($regionDatasets ?: []) ?>
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 10, font: { size: 10 } } },
                        datalabels: { display: false }
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 } } },
                        y: { stacked: true, grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } }
                    }
                }
            });
        }

        // ─── 연도별 차트 ───────────────────────────────
        const yearEl = document.getElementById('yearChart');
        if (yearEl) {
            new Chart(yearEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($yearLabels ?: []) ?>,
                    datasets: <?= json_encode($yearDatasets ?: []) ?>
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 10, font: { size: 10 } } },
                        datalabels: { display: false }
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, beginAtZero: true, grid: { color: '#f3f4f6' } }
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardCharts);
    } else {
        initDashboardCharts();
    }
</script>