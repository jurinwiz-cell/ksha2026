<?php
$allData = [
    ['임대종류' => '국민임대'],
    ['임대종류' => '행복주택']
];
$_GET['types'] = '행복주택';
$types   = isset($_GET['types']) && $_GET['types'] !== '' ? explode(',', $_GET['types']) : [];
$filteredData = [];
foreach ($allData as $item) {
    if (!empty($types) && !in_array($item['임대종류'] ?? '', $types)) continue;
    $filteredData[] = $item;
}
echo "Total: " . count($filteredData) . "\n";
print_r($filteredData);
?>
