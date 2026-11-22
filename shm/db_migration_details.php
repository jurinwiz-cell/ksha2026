<?php
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tb_member_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            biz_no VARCHAR(50),
            address VARCHAR(255),
            homepage VARCHAR(255),
            email VARCHAR(100),
            established_date DATE,
            memo TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES tb_members(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS tb_member_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            field_name VARCHAR(50) NOT NULL,
            old_value TEXT,
            new_value TEXT,
            change_reason VARCHAR(255),
            changed_by VARCHAR(50),
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES tb_members(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS tb_member_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            category VARCHAR(50) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_size INT,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES tb_members(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Tables created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
