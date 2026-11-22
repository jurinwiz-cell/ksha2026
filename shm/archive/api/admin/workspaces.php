<?php
// api/admin/workspaces.php

require_once '../_authz.php';

try {
    $me = requireAdmin();
    $stmt = db()->query("
        SELECT w.id, w.name, w.created_at, u.name as creator_name
        FROM workspaces w
        LEFT JOIN users u ON w.created_by = u.id
        ORDER BY w.created_at DESC
    ");
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database failure: ' . $e->getMessage()]);
}
