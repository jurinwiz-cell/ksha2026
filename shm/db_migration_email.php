<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("ALTER TABLE tb_members ADD COLUMN email VARCHAR(100) NULL AFTER company_name");
    echo "tb_members 테이블에 email 컬럼 추가 완료!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "email 컬럼이 이미 존재합니다.\n";
    } else {
        echo "오류: " . $e->getMessage() . "\n";
    }
}
