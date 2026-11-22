<?php
require_once __DIR__ . '/includes/db.php';

$rows = $pdo->query("SELECT id, member_no, company_name_raw FROM tb_social_housing_supply WHERE member_id IS NULL LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

$members = $pdo->query("SELECT member_no, id FROM tb_members LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($members);
