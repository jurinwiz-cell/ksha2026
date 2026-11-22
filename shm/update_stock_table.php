<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$db_host = 'localhost';
$db_name = 'socialhousing';
$db_user = 'root';
$db_pass = '1@tkghlwnxor';

$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// 1. Backup existing stock table if it exists
$db->query("DROP TABLE IF EXISTS stock_bak");

// Check if the stock table exists
$check = $db->query("SHOW TABLES LIKE 'stock'");
if ($check && $check->num_rows > 0) {
    // Rename only when the table really exists
    $db->query("RENAME TABLE stock TO stock_bak");
}

// 2. 새로운 stock 테이블 생성
$create_sql = "CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no VARCHAR(50),
    name VARCHAR(255),
    provider VARCHAR(255),
    region_type VARCHAR(100),
    supply_method VARCHAR(100),
    ownership VARCHAR(100),
    public_entity VARCHAR(100),
    new_construction VARCHAR(50),
    member_no VARCHAR(50),
    membership_status VARCHAR(50),
    building_type VARCHAR(100),
    district VARCHAR(50),
    households VARCHAR(50),
    social_economy VARCHAR(100),
    type2 VARCHAR(100),
    type1 VARCHAR(100),
    location VARCHAR(255),
    address VARCHAR(255),
    completion_date VARCHAR(50),
    operation_status VARCHAR(100),
    audit_year VARCHAR(50),
    is_completed VARCHAR(50),
    remarks TEXT,
    community VARCHAR(255),
    special_fee VARCHAR(100),
    country VARCHAR(50),
    category VARCHAR(100),
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$db->query($create_sql)) {
    die("테이블 생성 실패: " . $db->error);
}

// 3. CSV 데이터 가져오기
$file = __DIR__ . '/stock_data.csv';
if (($handle = fopen($file, "r")) !== FALSE) {
    // 첫 번째 행(헤더) 건너뛰기
    fgetcsv($handle);
    
    $inserted = 0;
    $batch_size = 100;
    $total_processed = 0;
    
    $stmt = $db->prepare("INSERT INTO stock (no, name, provider, region_type, supply_method, ownership, public_entity, new_construction, member_no, membership_status, building_type, district, households, social_economy, type2, type1, location, address, completion_date, operation_status, audit_year, is_completed, remarks, community, special_fee, country, category, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $db->begin_transaction();
    
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (empty($data[2])) continue; 
        
        $params = [
            $data[2],        // no (문자열 그대로 유지)
            $data[3] ?? '',  // name
            $data[4] ?? '',  // provider
            $data[5] ?? '',  // region_type
            $data[6] ?? '',  // supply_method
            $data[7] ?? '',  // ownership
            $data[8] ?? '',  // public_entity
            $data[9] ?? '',  // new_construction
            $data[10] ?? '', // member_no
            $data[11] ?? '', // membership_status
            $data[12] ?? '', // building_type
            $data[13] ?? '', // district
            $data[14] ?? '', // households
            $data[15] ?? '', // social_economy
            $data[16] ?? '', // type2
            $data[17] ?? '', // type1
            $data[18] ?? '', // location
            $data[19] ?? '', // address
            $data[20] ?? '', // completion_date
            $data[21] ?? '', // operation_status
            $data[22] ?? '', // audit_year
            $data[23] ?? '', // is_completed
            $data[24] ?? '', // remarks
            $data[25] ?? '', // community
            $data[26] ?? '', // special_fee
            $data[27] ?? '', // country
            $data[28] ?? '', // category
            (float)($data[32] ?? 0), 
            (float)($data[33] ?? 0)  
        ];
        
        $types = str_repeat('s', count($params));
        $refParams = [];
        foreach ($params as $key => $value) {
            $refParams[$key] = &$params[$key];
        }
        
        call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $refParams));
        if ($stmt->execute()) {
            $inserted++;
        } else {
            echo "행 " . ($total_processed + 1) . " 처리 중 오류 발생: " . $stmt->error . "\n";
        }
        
        $total_processed++;
        
        // 100행마다 커밋
        if ($total_processed % $batch_size == 0) {
            $db->commit();
            echo "{$total_processed}개 행 처리 완료 (현재까지 {$inserted}개 삽입 성공...)\n";
            $db->begin_transaction();
        }
    }
    
    $db->commit(); // 최종 커밋
    fclose($handle);
    echo "\n최종 결과: 총 {$total_processed}개 데이터 중 {$inserted}개 삽입 완료.\n";
}

echo "Migration completed successfully.";
$db->close();
?>
