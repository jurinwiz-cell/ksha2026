<?php

require_once '../_authz.php';

requireLogin();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$id = $data['id'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Entry ID is required']);
    exit;
}

try {
    $stmt = db()->prepare("
        DELETE
        FROM library_entries
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}