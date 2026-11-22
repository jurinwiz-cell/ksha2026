<?php
require_once __DIR__ . '/includes/db.php';

try {
    $count = $pdo->exec("
        UPDATE tb_social_housing_supply 
        SET supply_type2 = JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.type2')), 
            public_entity = JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.public_entity')) 
        WHERE raw_data IS NOT NULL
    ");
    echo "총 {$count}건의 데이터가 보정되었습니다.\n";
} catch (Exception $e) {
    die("오류: " . $e->getMessage() . "\n");
}
