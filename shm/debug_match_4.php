<?php
require_once __DIR__ . '/includes/db.php';

$all_supply_nos = $pdo->query("SELECT DISTINCT member_no FROM tb_social_housing_supply WHERE member_no != ''")->fetchAll(PDO::FETCH_COLUMN);
$all_member_nos = $pdo->query("SELECT DISTINCT member_no FROM tb_members WHERE member_no != ''")->fetchAll(PDO::FETCH_COLUMN);

$intersect = array_intersect($all_supply_nos, $all_member_nos);

echo "Total distinct member_nos in supply: " . count($all_supply_nos) . "\n";
echo "Total distinct member_nos in members: " . count($all_member_nos) . "\n";
echo "Total Intersection: " . count($intersect) . "\n";
if (count($intersect) > 0) {
    echo "Common: " . implode(', ', array_slice($intersect, 0, 10)) . "\n";
}
