<?php
/**
 * db_migration_fee_history.php - 회비 이력 테이블 생성 및 초기 데이터 이관
 */
$host   = 'localhost';
$dbname = 'socialhousing_db';
$user   = 'root';
$pass   = '1@tkghlwnxor';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. 이력 테이블 생성
    $sql = "
    CREATE TABLE IF NOT EXISTS tb_member_fee_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        start_date DATE NOT NULL COMMENT '회비 적용 시작일',
        monthly_fee INT NOT NULL DEFAULT 0,
        memo VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_member_date (member_id, start_date),
        CONSTRAINT fk_fee_member FOREIGN KEY (member_id) REFERENCES tb_members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql);
    echo "1. tb_member_fee_history 테이블 생성 완료\n";

    // 2. 기존 데이터 이관 (이력이 없는 회원사만)
    $stmt = $pdo->query("
        SELECT id, monthly_fee, join_date 
        FROM tb_members m
        WHERE NOT EXISTS (SELECT 1 FROM tb_member_fee_history h WHERE h.member_id = m.id)
    ");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($members) {
        $ins = $pdo->prepare("INSERT INTO tb_member_fee_history (member_id, start_date, monthly_fee, memo) VALUES (?, ?, ?, ?)");
        foreach ($members as $m) {
            // 적용 시작일은 가입일로 하되, 가입일이 없으면 아주 옛날 날짜로 설정
            $startDate = !empty($m['join_date']) ? $m['join_date'] : '2000-01-01';
            $ins->execute([$m['id'], $startDate, $m['monthly_fee'], '초기 시스템 이관 데이터']);
        }
        echo "2. 총 " . count($members) . "건의 초기 회비 데이터 이관 완료\n";
    } else {
        echo "2. 이관할 대상 데이터가 없습니다.\n";
    }

    echo "모든 작업 성공.\n";

} catch (PDOException $e) {
    die("오류 발생: " . $e->getMessage());
}
