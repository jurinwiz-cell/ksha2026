<?php
/**
 * sync_supply_data.php - stock 테이블 데이터를 tb_social_housing_supply로 동기화
 */
$host = 'localhost';
$user = 'root';
$pass = '1@tkghlwnxor';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "데이터 동기화 시작...\n";

    // 1. 기존 테이블 백업 및 초기화
    $pdo->exec("USE socialhousing_db");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tb_social_housing_supply_bak LIKE tb_social_housing_supply");
    $pdo->exec("TRUNCATE TABLE tb_social_housing_supply_bak");
    $pdo->exec("INSERT INTO tb_social_housing_supply_bak SELECT * FROM tb_social_housing_supply");
    echo "기본 테이블 백업 완료 (tb_social_housing_supply_bak)\n";

    $pdo->exec("TRUNCATE TABLE tb_social_housing_supply");
    echo "기존 테이블 초기화 완료.\n";

    // 2. stock 테이블에서 데이터 가져오기
    $stmt = $pdo->query("SELECT * FROM socialhousing.stock");
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertStmt = $pdo->prepare("INSERT INTO tb_social_housing_supply 
        (member_no, company_name_raw, building_name, address, household_count, supply_type, completion_date, completion_status, operation_status, location_si, location_gu, raw_data) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $count = 0;
    foreach ($stocks as $row) {
        $insertStmt->execute([
            $row['member_no'],
            $row['provider'],
            $row['name'],
            $row['address'],
            (int)preg_replace('/[^0-9]/', '', $row['households']), // 숫자만 추출
            $row['type1'],
            $row['completion_date'],
            $row['is_completed'],
            $row['operation_status'],
            $row['region_type'],
            $row['district'],
            json_encode($row, JSON_UNESCAPED_UNICODE) // 원본 데이터 보관
        ]);
        $count++;
    }
    echo "총 {$count}개의 데이터를 성공적으로 이전했습니다.\n";

    // 3. 회원사 자동 매칭 (member_no 기준)
    echo "회원사 자동 매칭 중...\n";
    $pdo->exec("
        UPDATE tb_social_housing_supply s
        JOIN tb_members m ON s.member_no = m.member_no COLLATE utf8mb4_general_ci
        SET s.member_id = m.id
        WHERE s.member_no != '' AND s.member_no IS NOT NULL
    ");
    $matched = $pdo->query("SELECT COUNT(*) FROM tb_social_housing_supply WHERE member_id IS NOT NULL")->fetchColumn();
    echo "매칭 결과: 총 {$count}건 중 {$matched}건 매칭 완료.\n";

    echo "모든 작업이 성공적으로 완료되었습니다.\n";

} catch (Exception $e) {
    die("오류 발생: " . $e->getMessage() . "\n");
}
