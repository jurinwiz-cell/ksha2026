<?php
require_once __DIR__ . '/includes/db.php';

$rows = $pdo->query("SELECT DISTINCT member_no, company_name_raw FROM tb_social_housing_supply WHERE member_id IS NULL AND member_no != ''")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "Supply: [{$row['member_no']}] {$row['company_name_raw']}\n";
    $m = $pdo->prepare("SELECT id, company_name FROM tb_members WHERE member_no = ?");
    $m->execute([$row['member_no']]);
    $matched = $m->fetch(PDO::FETCH_ASSOC);
    if ($matched) {
        echo "  -> Found in Members: [{$matched['id']}] {$matched['company_name']}\n";
    } else {
        // Try searching by name just to see
        $m2 = $pdo->prepare("SELECT id, member_no, company_name FROM tb_members WHERE company_name LIKE ?");
        $m2->execute(['%' . $row['company_name_raw'] . '%']);
        $matched2 = $m2->fetch(PDO::FETCH_ASSOC);
        if ($matched2) {
            echo "  -> (Name match only): [{$matched2['member_no']}] {$matched2['company_name']}\n";
        }
    }
}
