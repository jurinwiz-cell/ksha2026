<?php
$API_KEY  = '39b51115717872f9630f0d8f64456e9eec660a40b94c327b847b741a7647656f';
$API_BASE = 'https://api.odcloud.kr/api/15084926/v1/uddi:bc7f22b9-b711-4a07-9de3-f0668c1d64c6';

$page    = isset($_GET['page'])    ? intval($_GET['page'])    : 1;
$perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;

$query = "page={$page}&perPage={$perPage}&serviceKey={$API_KEY}";

// $_GET은 cond[]를 배열로 파싱해 키가 깨짐
// QUERY_STRING 원본에서 cond[...]=... 부분을 직접 추출
$raw = $_SERVER['QUERY_STRING'] ?? '';
foreach (explode('&', $raw) as $part) {
    // cond[ 로 시작하는 파트 찾기 (인코딩된 경우 포함)
    if (preg_match('/^(cond%5B[^=]+=.*)$/i', $part, $m) ||
        preg_match('/^(cond\[[^=]+=.*)$/',   $part, $m)) {
        $query .= '&' . $m[1];
        break;
    }
}

$ch = curl_init($API_BASE . '?' . $query);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($error) {
    http_response_code(502);
    echo json_encode(['error' => 'ODCloud 연결 실패: ' . $error]);
} else {
    http_response_code($httpCode);
    echo $response;
}
