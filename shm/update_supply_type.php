<?php
/**
 * update_supply_type.php - raw_data에서 type2를 추출하여 supply_type 컬럼 업데이트
 */
$host = 'localhost';
$user = 'root';
$pass = '1@tkghlwnxor';
$dbname = 'socialhousing_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT id, raw_data FROM tb_social_housing_supply WHERE raw_data IS NOT NULL");
    $updateStmt = $pdo->prepare("UPDATE tb_social_housing_supply SET supply_type = ? WHERE id = ?");

    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw = json_decode($row['raw_data'], true);
        if (isset($raw['type2'])) {
            $updateStmt->execute([$raw['type2'], $row['id']]);
            $count++;
        }
    }
    echo "총 {$count}개의 분류(유형2) 정보가 업데이트되었습니다.\n";

} catch (Exception $e) {
    die("오류: " . $e->getMessage() . "\n");
}
