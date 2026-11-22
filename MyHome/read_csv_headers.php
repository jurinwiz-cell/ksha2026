<?php
$file = '한국토지주택공사_임대주택단지정보_건설_20250918.csv';
$csvData = file_get_contents($file);
$csvData = mb_convert_encoding($csvData, 'UTF-8', 'EUC-KR');
$lines = explode("\n", $csvData);
$header = str_getcsv($lines[0]);
$row1 = str_getcsv($lines[1]);
echo json_encode(['headers' => $header, 'row1' => $row1], JSON_UNESCAPED_UNICODE);
