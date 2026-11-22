<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tb_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sns_provider VARCHAR(50) NOT NULL,
            sns_id VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            name VARCHAR(100),
            role VARCHAR(20) DEFAULT 'company_user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_sns (sns_provider, sns_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS tb_user_company_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            member_id INT NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            memo TEXT,
            requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            approved_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES tb_users(id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES tb_members(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "SNS Login Tables created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
