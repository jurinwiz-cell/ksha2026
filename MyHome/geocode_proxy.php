<?php
// 지도 페이지용 데이터 API
// ?level=nation            → 광역시도별 집계
// ?level=region&sido=서울  → 시군구별 집계
// ?level=local&sigungu=중구&sido=서울 → 단지 목록 + 좌표

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$CACHE_FILE = __DIR__ . '/geocode_cache.json';
$DATA_FILE  = __DIR__ . '/housing_data.json';

if (!file_exists($DATA_FILE)) {
    echo json_encode(['error' => 'housing_data.json 없음. geocode_batch.php 먼저 실행하세요.']);
    exit;
}

$allData = json_decode(file_get_contents($DATA_FILE), true);
$cache   = file_exists($CACHE_FILE) ? json_decode(file_get_contents($CACHE_FILE), true) : [];

$level   = $_GET['level']   ?? 'nation';
$sido    = $_GET['sido']    ?? '';
$sigungu = $_GET['sigungu'] ?? '';

// 일반 필터 파라미터 (index.html, map.html 사이드바 공용)
$filterRegion  = $_GET['region'] ?? '';
$filterSigungu = $_GET['filterSigungu'] ?? '';
$types         = isset($_GET['types']) && $_GET['types'] !== '' ? explode(',', $_GET['types']) : [];
$rentMin       = $_GET['rentMin'] ?? '';
$rentMax       = $_GET['rentMax'] ?? '';
$depMin        = $_GET['depMin']  ?? '';
$depMax        = $_GET['depMax']  ?? '';
$unitMin       = $_GET['unitMin'] ?? '';
$unitMax       = $_GET['unitMax'] ?? '';

// 데이터 필터링 적용
$filteredData = [];
foreach ($allData as $item) {
    if ($filterRegion && ($item['광역시도'] ?? '') !== $filterRegion) continue;
    if ($filterSigungu && mb_stripos($item['시군구'] ?? '', $filterSigungu, 0, 'UTF-8') === false) continue;
    if (!empty($types) && !in_array($item['임대종류'] ?? '', $types)) continue;
    
    $rent = intval($item['월임대료'] ?? 0);
    if ($rentMin !== '' && $rent < intval($rentMin)) continue;
    if ($rentMax !== '' && $rent > intval($rentMax)) continue;

    $dep = intval($item['임대보증금'] ?? 0);
    if ($depMin !== '' && $dep < intval($depMin)) continue;
    if ($depMax !== '' && $dep > intval($depMax)) continue;

    $unit = intval($item['세대수'] ?? 0);
    if ($unitMin !== '' && $unit < intval($unitMin)) continue;
    if ($unitMax !== '' && $unit > intval($unitMax)) continue;

    $filteredData[] = $item;
}

// 이후 $allData 대신 $filteredData를 사용
$allData = $filteredData;

// ── 전국 뷰: 광역시도별 세대수 합계 ──────────────────────────
if ($level === 'nation') {
    $agg = [];
    foreach ($allData as $item) {
        $s = $item['광역시도'] ?? '미상';
        if (!isset($agg[$s])) $agg[$s] = ['sido' => $s, 'count' => 0, 'households' => 0];
        $agg[$s]['count']++;
        $agg[$s]['households'] += (int)($item['세대수'] ?? 0);
    }
    // 광역시도 중심 좌표 (고정값)
    $centers = [
        '서울특별시'   => [37.5665, 126.9780],
        '부산광역시'   => [35.1796, 129.0756],
        '대구광역시'   => [35.8714, 128.6014],
        '인천광역시'   => [37.4563, 126.7052],
        '광주광역시'   => [35.1595, 126.8526],
        '대전광역시'   => [36.3504, 127.3845],
        '울산광역시'   => [35.5384, 129.3114],
        '세종특별자치시' => [36.4800, 127.2890],
        '경기도'       => [37.4138, 127.5183],
        '강원특별자치도' => [37.8228, 128.1555],
        '충청북도'     => [36.8000, 127.7000],
        '충청남도'     => [36.5184, 126.8000],
        '전북특별자치도' => [35.7175, 127.1530],
        '전라남도'     => [34.8679, 126.9910],
        '경상북도'     => [36.4919, 128.8889],
        '경상남도'     => [35.4606, 128.2132],
        '제주특별자치도' => [33.4996, 126.5312],
    ];
    $result = [];
    foreach ($agg as $s => $v) {
        $coords = $centers[$s] ?? [36.5, 127.5];
        $result[] = array_merge($v, ['lat' => $coords[0], 'lng' => $coords[1]]);
    }
    usort($result, fn($a,$b) => $b['households'] - $a['households']);
    echo json_encode(['level' => 'nation', 'data' => $result]);
    exit;
}

// ── 광역 뷰: 시군구별 건수 + 세대수 합계 ─────────────────────
if ($level === 'region' && $sido) {
    $agg = [];
    foreach ($allData as $item) {
        if (($item['광역시도'] ?? '') !== $sido) continue;
        $sg = $item['시군구'] ?? '미상';
        if (!isset($agg[$sg])) $agg[$sg] = ['sigungu' => $sg, 'sido' => $sido, 'count' => 0, 'households' => 0];
        $agg[$sg]['count']++;
        $agg[$sg]['households'] += (int)($item['세대수'] ?? 0);
    }
    // 시군구 대표 주소로 좌표 추정 (캐시에서 첫 번째 매칭 건 사용)
    foreach ($allData as $item) {
        if (($item['광역시도'] ?? '') !== $sido) continue;
        $sg   = $item['시군구'] ?? '미상';
        $addr = $item['도로명주소'] ?? '';
        if (isset($agg[$sg]) && !isset($agg[$sg]['lat']) && isset($cache[$addr]) && $cache[$addr]) {
            $agg[$sg]['lat'] = $cache[$addr]['lat'];
            $agg[$sg]['lng'] = $cache[$addr]['lng'];
        }
    }
    $result = array_values($agg);
    usort($result, fn($a,$b) => $b['households'] - $a['households']);
    echo json_encode(['level' => 'region', 'sido' => $sido, 'data' => $result]);
    exit;
}

// ── 지자체 뷰: 단지 목록 + 좌표 ─────────────────────────────
if ($level === 'local' && $sigungu) {
    $result = [];
    foreach ($allData as $item) {
        if ($sido && ($item['광역시도'] ?? '') !== $sido) continue;
        if (($item['시군구'] ?? '') !== $sigungu) continue;
        $addr = $item['도로명주소'] ?? '';
        $coords = isset($cache[$addr]) && $cache[$addr] ? $cache[$addr] : null;
        $result[] = [
            '단지명'    => $item['단지명'] ?? '-',
            '도로명주소' => $addr,
            '임대종류'  => $item['임대종류'] ?? '-',
            '임대사업자' => $item['임대사업자'] ?? '-',
            '세대수'    => (int)($item['세대수'] ?? 0),
            '임대보증금' => (int)($item['임대보증금'] ?? 0),
            '월임대료'  => (int)($item['월임대료'] ?? 0),
            'lat'       => $coords['lat'] ?? null,
            'lng'       => $coords['lng'] ?? null,
        ];
    }
    echo json_encode(['level' => 'local', 'sigungu' => $sigungu, 'count' => count($result), 'data' => $result]);
    exit;
}

echo json_encode(['error' => '잘못된 파라미터']);


