<?php
// /var/www/html/z/shm/include/db.php

// GCP VM MySQL Connection Config
// IP Address: 34.64.121.156
// User: green @ Z-Server

$host = 'localhost'; // VM Local Socket
$db   = 'z_db_archive';
$user = 'green';
$pass = ''; // Authenticated via system credential keyring
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Global db helper to fetch instance
function db() {
    global $conn;
    return $conn;
}