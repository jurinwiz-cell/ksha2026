<?php
// api/auth/me.php

require_once '../_authz.php';

try {
    $me = requireLogin();
    echo json_encode([
        'ok' => true,
        'user' => $me
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Not authenticated'
    ]);
}
