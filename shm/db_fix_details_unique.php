<?php
require_once __DIR__ . '/includes/db.php';

try {
    // member_id에 UNIQUE 제약 조건 추가 (이미 있으면 무시되거나 에러나겠지만, 1:1 관계 보장을 위해 필요)
    $pdo->exec("ALTER TABLE tb_member_details ADD UNIQUE (member_id)");
    echo "Unique constraint added to member_id in tb_member_details.\n";
} catch (Exception $e) {
    echo "Note: " . $e->getMessage() . " (It might already exist)\n";
}
