<?php
/**
 * fetch_data.php
 * Google Sheets CSV 프록시 스크립트
 * 
 * 브라우저가 구글 시트에 직접 접근하는 대신, 이 서버가 대신 다운로드하여
 * 방문자에게 전달합니다. 공공기관/보안망에서의 구글 차단 문제를 우회합니다.
 */

$CSV_URL = "https://docs.google.com/spreadsheets/d/1yX14R6IwkXMXel1TB7W1X5HPLwWoImYJus_pitE1lgg/export?format=csv&gid=1763307000";

// CORS 및 캐시 헤더 설정
header('Content-Type: text/csv; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
// 5분간 캐시 (구글에 너무 자주 요청하지 않도록)
header('Cache-Control: public, max-age=300');

// 서버→구글 시트 요청
$context = stream_context_create([
    'http' => [
        'timeout'         => 15,
        'follow_location' => true,
        'user_agent'      => 'Mozilla/5.0 (compatible; PHP-Proxy)',
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ]
]);

$data = @file_get_contents($CSV_URL, false, $context);

if ($data === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => '구글 시트 데이터를 가져올 수 없습니다. 잠시 후 다시 시도해주세요.']);
    exit;
}

echo $data;
