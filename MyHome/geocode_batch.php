<?php
// 전체 임대주택 주소 일괄 지오코딩 + 캐시 저장
// 브라우저로 열면 자동 실행 — 오래 걸리므로 CLI 권장: php geocode_batch.php
// 재실행 시 캐시된 건 스킵 (중단 후 재개 가능)

set_time_limit(0);
ini_set('memory_limit', '256M');

$ODCLOUD_KEY = '39b51115717872f9630f0d8f64456e9eec660a40b94c327b847b741a7647656f';
$KAKAO_KEY   = 'd6d485c9b3903c9120374e3612bf4e9f';
$CACHE_FILE  = __DIR__ . '/geocode_cache.json';
$DATA_FILE   = __DIR__ . '/housing_data.json';

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    ob_implicit_flush(true);
    ob_end_flush();
}

function log_msg($msg) {
    echo $msg . "\n";
    if (php_sapi_name() !== 'cli') flush();
}

// 1. 캐시 로드
$cache = file_exists($CACHE_FILE) ? json_decode(file_get_contents($CACHE_FILE), true) : [];
log_msg("캐시 로드: " . count($cache) . "건");

// 2. 전체 데이터 수집
$allData = [];
$page = 1; $perPage = 100;
log_msg("ODCloud에서 전체 데이터 수집 중...");

while (true) {
    $url = "https://api.odcloud.kr/api/15084926/v1/uddi:bc7f22b9-b711-4a07-9de3-f0668c1d64c6"
         . "?page={$page}&perPage={$perPage}&serviceKey={$ODCLOUD_KEY}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $res  = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    $data = $json['data'] ?? [];
    if (empty($data)) break;

    $allData = array_merge($allData, $data);
    $total   = $json['totalCount'] ?? 0;
    log_msg("  수집: {$page}페이지 / " . ceil($total / $perPage) . "페이지 (누적 " . count($allData) . "건)");

    if (count($allData) >= $total) break;
    $page++;
    usleep(200000); // 0.2초 대기
}

log_msg("전체 " . count($allData) . "건 수집 완료");

// 전체 데이터 저장
file_put_contents($DATA_FILE, json_encode($allData, JSON_UNESCAPED_UNICODE));
log_msg("housing_data.json 저장 완료");

// 3. 지오코딩
$done = 0; $skip = 0; $fail = 0;

foreach ($allData as $item) {
    $addr = $item['도로명주소'] ?? '';
    if (!$addr) { $fail++; continue; }
    if (isset($cache[$addr])) { $skip++; continue; }

    // 카카오 주소 검색 API
    $kakaoUrl = 'https://dapi.kakao.com/v2/local/search/address.json?query=' . urlencode($addr);
    $ch = curl_init($kakaoUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ["Authorization: KakaoAK {$KAKAO_KEY}"],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        log_msg("  API 오류 {$code}: {$addr}");
        $fail++;
        usleep(500000);
        continue;
    }

    $geo = json_decode($res, true);
    if (!empty($geo['documents'][0])) {
        $doc = $geo['documents'][0];
        $cache[$addr] = [
            'lat' => (float)$doc['y'],
            'lng' => (float)$doc['x'],
        ];
        $done++;
    } else {
        // 지번주소로 재시도 — 도로명 실패 시
        $cache[$addr] = null;
        $fail++;
    }

    if ($done % 50 === 0 && $done > 0) {
        file_put_contents($CACHE_FILE, json_encode($cache, JSON_UNESCAPED_UNICODE));
        log_msg("  중간저장: done={$done}, skip={$skip}, fail={$fail}");
    }
    usleep(100000); // 0.1초 대기 (카카오 쿼터 보호)
}

// 최종 저장
file_put_contents($CACHE_FILE, json_encode($cache, JSON_UNESCAPED_UNICODE));
log_msg("\n=== 완료 ===");
log_msg("성공: {$done}, 스킵(캐시): {$skip}, 실패: {$fail}");
log_msg("geocode_cache.json 저장 완료");


