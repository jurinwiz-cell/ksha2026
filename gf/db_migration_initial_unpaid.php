<?php
require_once __DIR__ . '/includes/db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS tb_initial_unpaid (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        year_2017 INT DEFAULT 0,
        year_2018 INT DEFAULT 0,
        year_2019 INT DEFAULT 0,
        year_2020 INT DEFAULT 0,
        year_2021 INT DEFAULT 0,
        total INT DEFAULT 0,
        UNIQUE KEY (member_id),
        FOREIGN KEY (member_id) REFERENCES tb_members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "Successfully created tb_initial_unpaid table.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
