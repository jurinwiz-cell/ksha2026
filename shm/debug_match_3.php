<?php
require_once __DIR__ . '/includes/db.php';

$supply_nos = $pdo->query("SELECT DISTINCT member_no FROM tb_social_housing_supply WHERE member_id IS NULL AND member_no != ''")->fetchAll(PDO::FETCH_COLUMN);
$member_nos = $pdo->query("SELECT DISTINCT member_no FROM tb_members")->fetchAll(PDO::FETCH_COLUMN);

$intersect = array_intersect($supply_nos, $member_nos);

echo "Unmatched member_nos in supply: " . count($supply_nos) . "\n";
echo "Total member_nos in members: " . count($member_nos) . "\n";
echo "Intersection count: " . count($intersect) . "\n";
if (count($intersect) > 0) {
    echo "Common Nos: " . implode(', ', $intersect) . "\n";
}
