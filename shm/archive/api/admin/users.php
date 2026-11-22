<?php
// api/admin/users.php

require_once '../_authz.php';

try {
    $me = requireAdmin();
    $stmt = db()->query("
        SELECT id, email, name, role, created_at
        FROM users
        ORDER BY created_at DESC
    ");
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database failure: ' . $e->getMessage()]);
}
