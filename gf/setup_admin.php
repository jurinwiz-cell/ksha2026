<?php
$pdo = new PDO('mysql:host=localhost;dbname=socialhousing_db;charset=utf8mb4', 'root', '1@tkghlwnxor');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$hash = password_hash('1234', PASSWORD_DEFAULT);

$check = (int)$pdo->query("SELECT COUNT(*) FROM tb_admins WHERE username='admin'")->fetchColumn();
if ($check == 0) {
    $pdo->prepare("INSERT INTO tb_admins (username, password, name) VALUES (?, ?, ?)")
        ->execute(['admin', $hash, '최고관리자']);
    echo "계정 신규 생성\n";
} else {
    $pdo->prepare("UPDATE tb_admins SET password=?, name=? WHERE username='admin'")
        ->execute([$hash, '최고관리자']);
    echo "계정 비밀번호 갱신\n";
}

$admin = $pdo->prepare("SELECT * FROM tb_admins WHERE username='admin'");
$admin->execute();
$row = $admin->fetch(PDO::FETCH_ASSOC);
$ok = password_verify('1234', $row['password']);
echo "비밀번호 검증: " . ($ok ? "성공" : "실패") . "\n";
echo "이름: " . $row['name'] . "\n";
echo "완료.\n";
