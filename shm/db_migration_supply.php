<?php
/**
 * db_migration_supply.php - 사회주택 공급현황 데이터 이관
 */
require_once __DIR__ . '/includes/db.php';

// 1. 테이블 생성
$sqlCreate = "
CREATE TABLE IF NOT EXISTS tb_social_housing_supply (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NULL,
    member_no VARCHAR(50),
    company_name_raw VARCHAR(100),
    building_name VARCHAR(100),
    address VARCHAR(255),
    household_count INT DEFAULT 0,
    supply_type VARCHAR(50),
    completion_date VARCHAR(50),
    operation_status VARCHAR(50),
    special_fee_target VARCHAR(20),
    location_si VARCHAR(50),
    location_gu VARCHAR(50),
    raw_data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (member_id),
    INDEX (member_no),
    FOREIGN KEY (member_id) REFERENCES tb_members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sqlCreate);
    echo "Table tb_social_housing_supply created or already exists.\n";
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}

// 2. 데이터 이관 (다른 데이터베이스에서 가져오기)
try {
    // 레거시 DB 연결 (socialhousing)
    $legacyPdo = new PDO("mysql:host=localhost;dbname=socialhousing;charset=utf8mb4", 'root', '1@tkghlwnxor');
    $legacyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $legacyPdo->query("SELECT * FROM stock");
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total records found in legacy stock: " . count($stocks) . "\n";

    // 회원사 매칭을 위해 현재 회원사 리스트 가져오기
    $members = $pdo->query("SELECT id, member_no FROM tb_members")->fetchAll(PDO::FETCH_KEY_PAIR);

    $insertStmt = $pdo->prepare("
        INSERT INTO tb_social_housing_supply 
        (member_id, member_no, company_name_raw, building_name, address, household_count, supply_type, completion_date, operation_status, special_fee_target, location_si, location_gu, raw_data)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $count = 0;
    foreach ($stocks as $s) {
        $mNo = trim($s['단체번호'] ?? '');
        $mId = null;
        
        // 회원사 번호로 매칭 시도
        if ($mNo && isset($members[$mNo])) {
            foreach($members as $no => $id) {
                if ($no == $mNo) {
                    $mId = $id;
                    break;
                }
            }
        }
        
        // 세대수 정수화
        $hCount = (int)preg_replace('/[^0-9]/', '', $s['세대'] ?? '0');
        
        $insertStmt->execute([
            $mId,
            $mNo,
            $s['단체명'],
            $s['건물명'],
            $s['소재지'],
            $hCount,
            $s['유형'],
            $s['준공년월'],
            $s['운영상태'],
            $s['특별회비'],
            $s['광역'],
            $s['자치구'],
            json_encode($s, JSON_UNESCAPED_UNICODE)
        ]);
        $count++;
    }

    echo "Successfully migrated $count records.\n";

} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
