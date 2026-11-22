<?php
/**
 * includes/db.php - DB 연결 공통 모듈
 */
$host   = 'localhost';
$dbname = 'socialhousing_db';
$user   = 'root';
$pass   = '1@tkghlwnxor';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['error' => 'DB 연결 실패: ' . $e->getMessage()]));
}
