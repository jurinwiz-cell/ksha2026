<?php
/**
 * init_operator_history.php - 기존 공급 데이터를 기준으로 첫 번째 운영 이력 생성
 */
$host = 'localhost';
$user = 'root';
$pass = '1@tkghlwnxor';
$dbname = 'socialhousing_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 기존 데이터 조회
    $stmt = $pdo->query("SELECT id, member_id, member_no, company_name_raw FROM tb_social_housing_supply");
    $insertStmt = $pdo->prepare("INSERT INTO tb_social_housing_operator_history (supply_id, member_id, member_no, company_name_raw, start_date, change_reason) VALUES (?, ?, ?, ?, CURDATE(), '시스템 초기 등록')");

    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['member_id'] || $row['company_name_raw']) {
            $insertStmt->execute([$row['id'], $row['member_id'], $row['member_no'], $row['company_name_raw']]);
            $count++;
        }
    }
    echo "총 {$count}개의 초기 운영 이력이 생성되었습니다.\n";

} catch (Exception $e) {
    die("오류: " . $e->getMessage() . "\n");
}
