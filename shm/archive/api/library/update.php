<?php
// api/library/update.php

require_once '../_authz.php';

requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? '';
$note = $data['note'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Entry ID is required']);
    exit;
}

try {
    $stmt = db()->prepare("
        UPDATE library_entries
        SET note = ?
        WHERE id = ?
    ");
    $stmt->execute([$note, $id]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to update note: ' . $e->getMessage()]);
}
