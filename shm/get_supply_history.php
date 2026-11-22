<?php
require_once __DIR__ . '/includes/db.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode([]);
    exit;
}

try {
    // 통합 이력 테이블에서 데이터 조회
    $stmt = $pdo->prepare("
        SELECT 
            id,
            field_name,
            old_value,
            new_value,
            change_reason,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at
        FROM tb_social_housing_history
        WHERE supply_id = ?
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute([$id]);
    $history = $stmt->fetchAll();

    echo json_encode($history);

} catch (PDOException $e) {
    error_log("History Fetch Error: " . $e->getMessage());
    echo json_encode(['error' => '데이터를 불러오지 못했습니다.']);
}
