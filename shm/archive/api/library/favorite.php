<?php
// api/library/favorite.php

require_once '../_authz.php';

requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? '';
$is_favorite = isset($data['is_favorite']) ? (bool)$data['is_favorite'] : false;

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Entry ID is required']);
    exit;
}

try {
    $stmt = db()->prepare("
        UPDATE library_entries
        SET is_favorite = ?
        WHERE id = ?
    ");
    $stmt->execute([$is_favorite ? 1 : 0, $id]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to modify favorite status: ' . $e->getMessage()]);
}
