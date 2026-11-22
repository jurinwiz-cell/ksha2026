<?php
// 테스트할 후보군 URL 리스트
$candidates = [
    "Candidate 1" => "https://page.stibee.com/api/v1.0/lists/63562/contents",
    "Candidate 2 (변형)" => "https://page.stibee.com/api/v1.0/pages/sohot/archives?limit=20", 
    "Candidate 3" => "https://page.stibee.com/archives/63562/values",
    "Candidate 4 (직접 찾으신 것)" => "https://api.stibee.com/v1/pages/sohot/archives?limit=20"
];

echo "<h3>API 주소 테스트 결과</h3><ul>";

foreach ($candidates as $name => $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 봇 차단 방지 헤더
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "<li><strong>{$name}</strong><br>";
    echo "URL: {$url}<br>";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        if ($data) {
            echo "<span style='color:green; font-weight:bold;'>✅ 성공! (데이터가 수신되었습니다)</span><br>";
            // 데이터 구조 살짝 보여주기
            $count = 0;
            if(isset($data['items'])) $count = count($data['items']);
            elseif(isset($data['data']['list'])) $count = count($data['data']['list']);
            elseif(is_array($data)) $count = count($data);
            
            echo "발견된 항목 수: {$count}개";
        } else {
            echo "<span style='color:orange;'>⚠️ 접속은 됐으나 JSON이 아닙니다.</span>";
        }
    } else {
        echo "<span style='color:red;'>❌ 실패 (HTTP Code: {$http_code})</span>";
    }
    echo "</li><hr>";
}
echo "</ul>";
?>