<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("ALTER TABLE tb_member_details ADD COLUMN unpaid_before_2022 INT DEFAULT 0");
    echo "Successfully added unpaid_before_2022 column to tb_member_details table.\n";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
}
