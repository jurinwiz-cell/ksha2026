<?php
// api/_authz.php

require_once __DIR__ . '/_auth_bridge.php';
require_once __DIR__ . '/_db.php';

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'Unauthorized'
        ]);
        exit;
    }
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['email'],
        'name' => $_SESSION['name'],
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

function requireAdmin() {
    $me = requireLogin();
    if ($me['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'Forbidden: Admins only'
        ]);
        exit;
    }
    return $me;
}