<?php
require_once __DIR__ . '/includes/db.php';

$stmt = $pdo->query("SELECT member_no, id FROM tb_members WHERE member_no LIKE '5-%'");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($members);

$stmt2 = $pdo->query("SELECT member_no, id FROM tb_members WHERE company_name LIKE '%드로우%'");
$members2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
print_r($members2);
