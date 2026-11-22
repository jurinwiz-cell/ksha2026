<?php
// 필드명 확인용 — 확인 후 삭제하세요
$API_KEY = '39b51115717872f9630f0d8f64456e9eec660a40b94c327b847b741a7647656f';
$url = "https://api.odcloud.kr/api/15084926/v1/uddi:bc7f22b9-b711-4a07-9de3-f0668c1d64c6?page=1&perPage=2&serviceKey={$API_KEY}";

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$response = curl_exec($ch);
curl_close($ch);

$json = json_decode($response, true);

header('Content-Type: text/plain; charset=utf-8');

if (!$json || !isset($json['data'][0])) {
    echo "응답 오류:\n" . $response;
} else {
    echo "=== 필드명 목록 ===\n";
    foreach (array_keys($json['data'][0]) as $key) {
        echo "  " . $key . " : " . $json['data'][0][$key] . "\n";
    }
    echo "\n=== 전체 응답 키 ===\n";
    foreach (array_keys($json) as $key) {
        echo "  " . $key . " : " . (is_array($json[$key]) ? '[array]' : $json[$key]) . "\n";
    }
}
