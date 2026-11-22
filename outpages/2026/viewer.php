<?php
// viewer.php
$url = $_GET['url'];
if (!$url || strpos($url, 'stibee.com') === false) {
    die("유효하지 않은 주소입니다.");
}

$options = [
    'http' => [
        'method' => "GET",
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
    ]
];
$context = stream_context_create($options);
$content = file_get_contents($url, false, $context);

// 상대 경로로 된 이미지/스타일을 절대 경로로 수정
$content = str_replace('href="/', 'href="https://stibee.com/', $content);
$content = str_replace('src="/', 'src="https://stibee.com/', $content);

// 스티비의 보안 스크립트가 부모 창을 건드리지 못하도록 방어 코드를 삽입하거나 그대로 출력
echo $content;
?>