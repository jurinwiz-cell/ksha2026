<?php
require_once __DIR__ . '/includes/db.php';

try {
    // 기존 운영사 이력을 통합 이력 테이블로 복사
    $count = $pdo->exec("
        INSERT INTO tb_social_housing_history (supply_id, field_name, old_value, new_value, change_reason, created_at)
        SELECT 
            supply_id, 
            '운영사' as field_name, 
            NULL as old_value, 
            COALESCE(member_id, operator_name_custom) as new_value, 
            change_reason, 
            COALESCE(start_date, created_at) as created_at
        FROM tb_social_housing_operator_history
    ");
    echo "총 {$count}건의 운영사 이력이 통합 관리 테이블로 이전되었습니다.\n";
} catch (Exception $e) {
    die("오류: " . $e->getMessage() . "\n");
}
