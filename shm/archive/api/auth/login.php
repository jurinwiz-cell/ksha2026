<?php

require_once '../_db.php';

session_start();

$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

$stmt = db()->prepare("
SELECT *
FROM users
WHERE email = ?
");

$stmt->execute([$email]);

$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['name'] = $user['name'];
$_SESSION['role'] = $user['role'];

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role']
    ]
]);