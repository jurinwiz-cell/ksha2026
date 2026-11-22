<?php
// 통합된 housing_data.json을 읽어 API 형태로 반환하는 로컬 프록시
// (기존 ODCloud 외부 API 호출을 대체하여, 새로 추가된 LH 데이터까지 통합 제공)

$DATA_FILE = __DIR__ . '/housing_data.json';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (!file_exists($DATA_FILE)) {
    http_response_code(500);
    echo json_encode(['error' => '데이터 파일(housing_data.json)을 찾을 수 없습니다.']);
    exit;
}

// 1. 파라미터 파싱
$page    = isset($_GET['page'])    ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['perPage']) ? max(1, intval($_GET['perPage'])) : 10;

// 일반 필터 파라미터
$region  = $_GET['region'] ?? '';
$sigungu = $_GET['sigungu'] ?? '';
$types   = isset($_GET['types']) && $_GET['types'] !== '' ? explode(',', $_GET['types']) : [];
$rentMin = $_GET['rentMin'] ?? '';
$rentMax = $_GET['rentMax'] ?? '';
$depMin  = $_GET['depMin']  ?? '';
$depMax  = $_GET['depMax']  ?? '';
$unitMin = $_GET['unitMin'] ?? '';
$unitMax = $_GET['unitMax'] ?? '';

// 텍스트 검색 조건 (cond[필드명::LIKE]=검색어)
$conditions = [];
$raw = $_SERVER['QUERY_STRING'] ?? '';
foreach (explode('&', $raw) as $part) {
    if (preg_match('/^(cond%5B|cond\[)([^%\]]+)(%5D::LIKE|\]::LIKE)=(.*)$/i', $part, $m)) {
        $field = urldecode($m[2]);
        $value = urldecode($m[4]);
        $conditions[$field] = $value;
    }
}

// 2. 데이터 로드
$allData = json_decode(file_get_contents($DATA_FILE), true);
if (!is_array($allData)) $allData = [];

// 3. 검색 필터 적용
$filteredData = [];
foreach ($allData as $item) {
    // 사이드바 필터 체크
    if ($region && ($item['광역시도'] ?? '') !== $region) continue;
    if ($sigungu && mb_stripos($item['시군구'] ?? '', $sigungu, 0, 'UTF-8') === false) continue;
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

    // 텍스트 검색 체크 (cond[...::LIKE])
    $match = true;
    foreach ($conditions as $field => $kw) {
        $val = $item[$field] ?? '';
        if (mb_stripos($val, $kw, 0, 'UTF-8') === false) {
            $match = false;
            break;
        }
    }
    
    if ($match) {
        $filteredData[] = $item;
    }
}

// 4. 정렬 처리
$sortField = $_GET['sort'] ?? '';
$sortOrder = strtolower($_GET['order'] ?? 'asc');
if ($sortField !== '') {
    $isNumericField = in_array($sortField, ['세대수', '월임대료', '임대보증금', '전환보증금', '공급면적(전용)', '공급면적(공용)']);
    usort($filteredData, function($a, $b) use ($sortField, $sortOrder, $isNumericField) {
        $valA = $a[$sortField] ?? '';
        $valB = $b[$sortField] ?? '';
        
        $isEmptyA = ($valA === '' || $valA === null || $valA === '-');
        $isEmptyB = ($valB === '' || $valB === null || $valB === '-');
        
        if ($isEmptyA && !$isEmptyB) return 1;
        if (!$isEmptyA && $isEmptyB) return -1;
        if ($isEmptyA && $isEmptyB) return 0;
        
        if ($isNumericField) {
            $numA = floatval(str_replace(',', '', $valA));
            $numB = floatval(str_replace(',', '', $valB));
            $cmp = $numA <=> $numB;
        } else {
            $cmp = strcmp((string)$valA, (string)$valB);
        }
        return $sortOrder === 'desc' ? -$cmp : $cmp;
    });
}

// 5. 지오코드 캐시 매핑
$CACHE_FILE = __DIR__ . '/geocode_cache.json';
$cache = file_exists($CACHE_FILE) ? json_decode(file_get_contents($CACHE_FILE), true) : [];

if (isset($_GET['mapMode']) && $_GET['mapMode'] === 'true') {
    $mapData = [];
    foreach ($filteredData as $item) {
        $addr = $item['도로명주소'] ?? '';
        $coords = isset($cache[$addr]) && $cache[$addr] ? $cache[$addr] : null;
        if ($coords) {
            $mapData[] = [
                'name'    => $item['단지명'] ?? '-',
                'address' => $addr,
                'type'    => $item['임대종류'] ?? '-',
                'units'   => (int)($item['세대수'] ?? 0),
                'lat'     => $coords['lat'],
                'lng'     => $coords['lng']
            ];
        }
    }
    echo json_encode([
        'mapMode'    => true,
        'totalCount' => count($mapData),
        'data'       => $mapData
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. 페이징 처리 (일반 목록 모드)
$totalCount = count($filteredData);
$offset     = ($page - 1) * $perPage;
$pagedData  = array_slice($filteredData, $offset, $perPage);

// pagedData에 좌표 정보 추가
foreach ($pagedData as &$item) {
    $addr = $item['도로명주소'] ?? '';
    $coords = isset($cache[$addr]) && $cache[$addr] ? $cache[$addr] : null;
    $item['lat'] = $coords['lat'] ?? null;
    $item['lng'] = $coords['lng'] ?? null;
}
unset($item);

// 7. 결과 반환 (기존 ODCloud 응답 포맷과 호환)
$response = [
    'currentCount' => count($pagedData),
    'data'         => $pagedData,
    'matchCount'   => $totalCount,
    'page'         => $page,
    'perPage'      => $perPage,
    'totalCount'   => count($allData)
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
