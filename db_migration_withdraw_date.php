<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("ALTER TABLE tb_members ADD COLUMN IF NOT EXISTS withdraw_date DATE AFTER join_date");
    echo "Column withdraw_date added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
