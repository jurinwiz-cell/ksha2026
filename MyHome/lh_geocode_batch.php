<?php
// LH 임대주택 CSV 파일 파싱 + 지오코딩 + 기존 housing_data.json에 추가
// 브라우저로 열면 실행됩니다. (예: http://localhost/Z/MyHome/lh_geocode_batch.php)

set_time_limit(0);
ini_set('memory_limit', '512M');

$VWORLD_KEY = ' C15F78DF-8CB0-394F-B6F1-51B35D1A69FB'; // 브이월드 오픈 API 인증키 발급 후 여기에 입력하세요
$CACHE_FILE = __DIR__ . '/geocode_cache.json';
$DATA_FILE = __DIR__ . '/housing_data.json';
$CSV_FILE = __DIR__ . '/한국토지주택공사_임대주택단지정보_건설_20250918.csv';

// 지오코딩 API 호출 여부 (데이터 목록 및 작동 테스트를 위해 임시로 false 설정)
$ENABLE_GEOCODING = false;

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    ob_implicit_flush(true);
    if (ob_get_level() > 0)
        ob_end_flush();
}

function log_msg($msg)
{
    echo $msg . "\n";
    if (php_sapi_name() !== 'cli')
        flush();
}

// 1. 기존 데이터 로드 및 중복 확인용 해시 테이블 구성
if (!file_exists($DATA_FILE)) {
    log_msg("오류: housing_data.json 파일이 없습니다. 먼저 geocode_batch.php를 실행하세요.");
    exit;
}
$allData = json_decode(file_get_contents($DATA_FILE), true);
$initialCount = count($allData);
log_msg("기존 데이터 로드: " . $initialCount . "건");

$shNames = [];
$shAddresses = [];

foreach ($allData as $item) {
    if (!empty($item['단지명'])) {
        $shNames[trim($item['단지명'])] = true;
    }
    if (!empty($item['도로명주소'])) {
        // 괄호 부분 제거하여 비교
        $addrParts = explode('(', $item['도로명주소']);
        $shAddresses[trim($addrParts[0])] = true;
    }
}

$lhData = [];

// 2. CSV 파싱
log_msg("LH CSV 데이터 읽는 중 및 중복 제거...");
if (!file_exists($CSV_FILE)) {
    log_msg("오류: CSV 파일이 없습니다.");
    exit;
}

$csvContent = file_get_contents($CSV_FILE);
$csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'EUC-KR');

$stream = fopen("php://memory", "r+");
fwrite($stream, $csvContent);
rewind($stream);

$headers = fgetcsv($stream);
$idx = array_flip($headers);

$skippedCount = 0;

while (($row = fgetcsv($stream)) !== false) {
    if (count($row) < count($headers))
        continue;

    $addr = trim($row[$idx['주소']] ?? '');
    $name = trim($row[$idx['단지명']] ?? '');

    $addrParts = explode('(', $addr);
    $cleanAddr = trim($addrParts[0]);

    // 중복 체크 (단지명 또는 주소 일치 시)
    if (isset($shNames[$name]) || isset($shAddresses[$cleanAddr])) {
        $skippedCount++;
        continue;
    }

    $parts = explode(' ', $addr);
    $rawSido = $parts[0] ?? '';

    // 시도명 정규화 매핑 테이블
    $sidoMap = [
        '서울' => '서울특별시',
        '서울특별시' => '서울특별시',
        '부산' => '부산광역시',
        '부산광역시' => '부산광역시',
        '대구' => '대구광역시',
        '대구광역시' => '대구광역시',
        '대구시' => '대구광역시',
        '인천' => '인천광역시',
        '인천광역시' => '인천광역시',
        '인천직할시' => '인천광역시',
        '광주' => '광주광역시',
        '광주광역시' => '광주광역시',
        '대전' => '대전광역시',
        '대전광역시' => '대전광역시',
        '울산' => '울산광역시',
        '울산광역시' => '울산광역시',
        '세종' => '세종특별자치시',
        '세종특별자치시' => '세종특별자치시',
        '세종시' => '세종특별자치시',
        '경기' => '경기도',
        '경기도' => '경기도',
        '강원' => '강원특별자치도',
        '강원도' => '강원특별자치도',
        '강원특별자치도' => '강원특별자치도',
        '충북' => '충청북도',
        '충청북도' => '충청북도',
        '충남' => '충청남도',
        '충청남도' => '충청남도',
        '전북' => '전북특별자치도',
        '전라북도' => '전북특별자치도',
        '전북특별자치도' => '전북특별자치도',
        '전남' => '전라남도',
        '전라남도' => '전라남도',
        '경북' => '경상북도',
        '경상북도' => '경상북도',
        '경남' => '경상남도',
        '경상남도' => '경상남도',
        '제주' => '제주특별자치도',
        '제주도' => '제주특별자치도',
        '제주특별자치도' => '제주특별자치도'
    ];

    $sido = $sidoMap[$rawSido] ?? '';
    $sigungu = '';

    if ($sido) {
        $sigungu = $parts[1] ?? '';
        // 예외 처리: 세종특별자치시는 시군구가 없음
        if ($sido === '세종특별자치시') {
            $sigungu = '세종특별자치시';
        }
    } else {
        // 주소 형식이 올바르지 않아 매핑 실패한 경우
        $sido = '미상';
        $sigungu = '미상';
    }

    $lhData[] = [
        '광역시도' => $sido,
        '시군구' => $sigungu,
        '단지명' => $name,
        '도로명주소' => $addr,
        '임대종류' => trim($row[$idx['공급유형']] ?? ''),
        '임대사업자' => 'LH공사',
        '세대수' => (int) ($row[$idx['세대수']] ?? 0),
        '임대보증금' => 0,
        '월임대료' => 0,
        '제공기관명' => 'LH공사',
        '주택종류' => '공공임대',
        '주택유형' => trim($row[$idx['구분']] ?? ''),
        '준공일자' => trim($row[$idx['준공일자']] ?? ''),
        '공급면적(전용)' => trim($row[$idx['공급면적']] ?? '')
    ];
}
fclose($stream);

log_msg("LH 데이터 파싱 완료. (추가할 신규 데이터: " . count($lhData) . "건, 중복으로 스킵된 레코드: {$skippedCount}건)");

// 3. 캐시 로드 및 지오코딩 (신규 데이터 대상)
$cache = file_exists($CACHE_FILE) ? json_decode(file_get_contents($CACHE_FILE), true) : [];
log_msg("캐시 로드: " . count($cache) . "건");

$done = 0;
$skip = 0;
$fail = 0;

if (count($lhData) > 0) {
    log_msg("지오코딩 시작 (신규 데이터 " . count($lhData) . "건 대상)...");

    foreach ($lhData as $item) {
        $addr = $item['도로명주소'] ?? '';
        if (!$addr) {
            $fail++;
            continue;
        }
        if (isset($cache[$addr]) && $cache[$addr] !== false) {
            $skip++;
            continue;
        }

        $geo = null;
        if ($ENABLE_GEOCODING) {
            // 국토교통부 브이월드(Vworld) 주소 검색 API
            $vworldUrl = 'https://api.vworld.kr/req/address?' . http_build_query([
                'service' => 'address',
                'request' => 'getcoord',
                'version' => '2.0',
                'crs' => 'epsg:4326',
                'address' => $addr,
                'refine' => 'true',
                'simple' => 'false',
                'format' => 'json',
                'type' => 'road',
                'key' => $VWORLD_KEY
            ]);

            $ch = curl_init($vworldUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200) {
                log_msg("  API 오류 {$code}: {$addr}");
                $fail++;
                usleep(500000);
                continue;
            }
            $geo = json_decode($res, true);
            usleep(100000); // 0.1초 대기
        }

        if (!empty($geo['response']['status']) && $geo['response']['status'] === 'OK' && !empty($geo['response']['result']['point'])) {
            $point = $geo['response']['result']['point'];
            $cache[$addr] = [
                'lat' => (float) $point['y'],
                'lng' => (float) $point['x'],
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

    // 최종 캐시 저장
    file_put_contents($CACHE_FILE, json_encode($cache, JSON_UNESCAPED_UNICODE));
    log_msg("지오코딩 완료 - 성공: {$done}, 스킵(캐시): {$skip}, 실패: {$fail}");
    log_msg("geocode_cache.json 갱신 완료");

    // 4. 기존 데이터와 병합 (지오코딩 완료 후)
    log_msg("데이터 병합 시작...");
    $allData = array_merge($allData, $lhData);
    file_put_contents($DATA_FILE, json_encode($allData, JSON_UNESCAPED_UNICODE));
    log_msg("housing_data.json 갱신 완료. (총 " . count($allData) . "건)");
} else {
    log_msg("새로 추가할 데이터가 없으므로 지오코딩 및 병합을 생략합니다.");
}

log_msg("\n=== 최종 완료 ===");
?>