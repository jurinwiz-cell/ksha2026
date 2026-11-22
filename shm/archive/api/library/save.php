<?php
// api/library/save.php

require_once '../_authz.php';

$me = requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

$workspace_id = $data['workspace_id'] ?? '';
$item_id = $data['item_id'] ?? '';
$note = $data['note'] ?? '';

if (empty($workspace_id) || empty($item_id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Workspace ID and Item ID are required']);
    exit;
}

try {
    $stmt = db()->prepare("
        INSERT INTO library_entries (workspace_id, item_id, created_by, note)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE note = VALUES(note)
    ");
    $stmt->execute([
        $workspace_id,
        $item_id,
        $me['id'],
        $note
    ]);
    
    echo json_encode([
        'ok' => true
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to save to library: ' . $e->getMessage()
    ]);
}