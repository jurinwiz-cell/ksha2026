<?php
// 검색 파라미터 디버그용 — 확인 후 삭제
header('Content-Type: text/plain; charset=utf-8');
echo "=== _GET 파싱 결과 ===\n";
foreach ($_GET as $k => $v) {
    echo "  [{$k}] = {$v}\n";
}
echo "\n=== QUERY_STRING 원본 ===\n";
echo $_SERVER['QUERY_STRING'] . "\n";
