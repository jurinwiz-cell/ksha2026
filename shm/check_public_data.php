<?php
require_once __DIR__ . '/includes/db.php';

try {
    $stmt = $pdo->query("SELECT id, raw_data FROM tb_social_housing_supply WHERE raw_data IS NOT NULL LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw = json_decode($row['raw_data'], true);
        echo "ID: {$row['id']} | 공공 필드값: " . ($raw['공공'] ?? '누락됨') . "\n";
    }
} catch (Exception $e) {
    echo "오류: " . $e->getMessage();
}
