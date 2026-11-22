<?php

require_once '../_db.php';

$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$name = $data['name'] ?? '';

if (empty($email) || empty($password) || empty($name)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'All fields are required']);
    exit;
}

// Check if email already exists
$stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetchColumn() > 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email is already registered']);
    exit;
}

// First registered user gets to be admin, others are user
$countStmt = db()->query("SELECT COUNT(*) FROM users");
$role = ($countStmt->fetchColumn() == 0) ? 'admin' : 'user';

$hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$stmt = db()->prepare("
INSERT INTO users
(email, password_hash, name, role)
VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $email,
    $hash,
    $name,
    $role
]);

echo json_encode([
    'ok' => true
]);