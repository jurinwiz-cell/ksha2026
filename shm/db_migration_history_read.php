<?php
/**
 * db_migration_history_read.php
 * tb_member_history 테이블에 is_read 컬럼 추가
 */
require_once __DIR__ . '/includes/db.php';

try {
    // 1. is_read 컬럼 추가
    $pdo->exec("ALTER TABLE tb_member_history ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER changed_by");
    echo "tb_member_history 테이블에 is_read 컬럼이 성공적으로 추가되었습니다.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "이미 is_read 컬럼이 존재합니다.\n";
    } else {
        echo "오류 발생: " . $e->getMessage() . "\n";
    }
}
