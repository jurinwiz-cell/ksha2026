<?php
$d = json_decode(file_get_contents('/var/www/html/z/MyHome/housing_data.json'), true);
$t = [];
foreach ($d as $i) {
    $v = $i['임대종류'] ?? '';
    if ($v) $t[$v] = ($t[$v] ?? 0) + 1;
}
arsort($t);
foreach ($t as $k => $c) echo "$k: $c\n";
