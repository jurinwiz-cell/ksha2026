<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("ALTER TABLE tb_admins ADD COLUMN email VARCHAR(100) AFTER username");
    echo "Column email added.\n";
} catch (Exception $e) {
    echo "Column may already exist.\n";
}

try {
    $pdo->exec("UPDATE tb_admins SET email='admin@shm.or.kr' WHERE username='admin'");
    echo "Admin email updated.\n";
} catch (Exception $e) {
    echo "Error updating: " . $e->getMessage() . "\n";
}
