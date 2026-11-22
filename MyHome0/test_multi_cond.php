<?php
// 다중 cond 지원 여부 테스트
$API_KEY  = '39b51115717872f9630f0d8f64456e9eec660a40b94c327b847b741a7647656f';
$API_BASE = 'https://api.odcloud.kr/api/15084926/v1/uddi:bc7f22b9-b711-4a07-9de3-f0668c1d64c6';

// 단지명 OR 도로명주소 둘 다 LIKE 서울 — 다중 cond 테스트
$query = "page=1&perPage=3&serviceKey={$API_KEY}"
       . "&cond[단지명::LIKE]=서울"
       . "&cond[도로명주소::LIKE]=서울";

$ch = curl_init($API_BASE . '?' . $query);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: text/plain; charset=utf-8');
$json = json_decode($res, true);
echo "HTTP: {$code}\n";
echo "matchCount: " . ($json['matchCount'] ?? 'N/A') . "\n";
echo "totalCount: " . ($json['totalCount'] ?? 'N/A') . "\n";
echo "data count: " . count($json['data'] ?? []) . "\n\n";
echo "첫번째 결과:\n";
if (!empty($json['data'][0])) {
    echo "  단지명: " . $json['data'][0]['단지명'] . "\n";
    echo "  도로명주소: " . $json['data'][0]['도로명주소'] . "\n";
}
