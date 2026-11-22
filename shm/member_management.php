<?php
/**
 * member_management.php - 회원 관리
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle   = '회원 관리';
$currentPage = 'members';

$members = [];
$db_connected = false;
$error_message = '';

// [추가] 구글 시트 연동 처리 (AJAX 수신부)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_gsheet') {
    header('Content-Type: application/json; charset=utf-8');

    $sheetUrl = trim($_POST['sheet_url'] ?? '');
    if (!$sheetUrl) {
        echo json_encode(['success' => false, 'message' => 'URL을 입력해주세요.']);
        exit;
    }

    if (!preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $sheetUrl, $m)) {
        echo json_encode(['success' => false, 'message' => '올바른 구글 스프레드시트 URL이 아닙니다.']);
        exit;
    }
    $sheetId = $m[1];
    $gid     = 0;
    if (preg_match('/[#&?]gid=(\d+)/', $sheetUrl, $gm)) {
        $gid = (int)$gm[1];
    }
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";

    $ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => true, 'user_agent' => 'Mozilla/5.0']]);
    $raw = @file_get_contents($csvUrl, false, $ctx);

    if ($raw === false) {
        echo json_encode(['success' => false, 'message' => '구글 시트를 불러올 수 없습니다. 링크 공유 설정을 확인해주세요.']);
        exit;
    }

    $rows = array_map('str_getcsv', explode("\n", trim($raw)));
    if (count($rows) < 2) {
        echo json_encode(['success' => false, 'message' => '시트에 데이터가 없습니다.']);
        exit;
    }

    $headers = array_map(fn($h) => trim(preg_replace('/[\x00-\x1F\xEF\xBB\xBF]+/u', '', $h)), $rows[0]);
    $colMap = [];
    $colAlias = [
        'member_no'    => ['회원번호', '번호', 'no'],
        'company_name' => ['조직명', '회원사명', '회사명'],
        'ceo_name'     => ['대표자명', '대표자', '대표명'],
        'contact'      => ['회사연락처', '연락처', '전화번호'],
        'member_type'  => ['정회원', '회원구분', '구분'],
        'status'       => ['탈퇴', '상태', '운영'],
        'join_date'    => ['가입일', '등록일'],
        'biz_no'       => ['등록번호', '사업자번호'],
        'homepage'     => ['회사 홈페이지', '홈페이지'],
        'email'        => ['대표메일', '이메일'],
        'address'      => ['주소(새주소)', '주소'],
        'established'  => ['설립연도', '설립일'],
        'memo_fields'  => ['비고', '페이스북', '대표자연락처', '담당연락처', '주요 활동', '주택공급현황', '상근직원 수', '기관성격', '광역', '지자체', '담당자메일', '시행', '설계', '시공', '운영']
    ];

    foreach ($colAlias as $field => $aliases) {
        if ($field === 'memo_fields') {
            foreach ($aliases as $alias) {
                $idx = array_search(mb_strtolower($alias), array_map('mb_strtolower', $headers));
                if ($idx !== false) $colMap['memo_fields'][$alias] = $idx;
            }
        } else {
            foreach ($aliases as $alias) {
                $idx = array_search(mb_strtolower($alias), array_map('mb_strtolower', $headers));
                if ($idx !== false) { $colMap[$field] = $idx; break; }
            }
        }
    }

    if (!isset($colMap['member_no']) || !isset($colMap['company_name'])) {
        echo json_encode(['success' => false, 'message' => "필수 컬럼(회원번호, 조직명)을 찾을 수 없습니다."]);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmtM = $pdo->prepare("
            INSERT INTO tb_members (member_no, company_name, ceo_name, contact, member_type, status, join_date, monthly_fee)
            VALUES (:no, :name, :ceo, :contact, :type, :status, :join, 30000)
            ON DUPLICATE KEY UPDATE
            company_name=VALUES(company_name), ceo_name=VALUES(ceo_name), contact=VALUES(contact), 
            member_type=VALUES(member_type), status=VALUES(status), join_date=VALUES(join_date)
        ");

        $stmtD = $pdo->prepare("
            INSERT INTO tb_member_details (member_id, biz_no, address, homepage, email, established_date, memo)
            VALUES (:mid, :biz, :addr, :home, :email, :est, :memo)
            ON DUPLICATE KEY UPDATE
            biz_no=VALUES(biz_no), address=VALUES(address), homepage=VALUES(homepage), email=VALUES(email), established_date=VALUES(established_date), memo=VALUES(memo)
        ");

        $successCount = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < 2) continue;

            $mNo = trim($row[$colMap['member_no']] ?? '');
            $mName = trim($row[$colMap['company_name']] ?? '');
            if (!$mNo || !$mName) continue;

            // 날짜 가공 함수 (엑셀 숫자 형식 대응)
            $normalizeDate = function($d) {
                if (!$d) return null;
                $d = trim($d);
                
                // 만약 숫자로만 되어 있다면 (엑셀/구글시트 시리얼 날짜 형식 대응)
                if (is_numeric($d) && (int)$d > 30000) {
                    $unixTimestamp = ($d - 25569) * 86400;
                    return date('Y-m-d', $unixTimestamp);
                }

                $d = str_replace(['.', ' ', '/'], '-', $d);
                $d = preg_replace('/-+/', '-', $d);
                try {
                    $dt = new DateTime($d);
                    return $dt->format('Y-m-d');
                } catch (Exception $e) {
                    if (preg_match('/^\d{4}$/', $d)) return $d . "-01-01";
                    return null;
                }
            };

            $joinDate = isset($colMap['join_date']) ? $normalizeDate($row[$colMap['join_date']] ?? '') : date('Y-m-d');
            $withdrawDate = null;
            $rawStatus = trim($row[$colMap['status']] ?? '');
            if ($rawStatus) {
                $status = '탈퇴';
                $withdrawDate = $normalizeDate($rawStatus);
                if ($withdrawDate && substr($withdrawDate, 5) === '01-01' && strlen($rawStatus) === 4) {
                    $withdrawDate = substr($withdrawDate, 0, 4) . "-12-31"; // 연도만 입력된 탈퇴일은 말일 처리
                }
            }
            
            $type = '준회원';
            $rawType = trim($row[$colMap['member_type']] ?? '');
            if ($rawType == '1') {
                $type = '정회원';
            } else if ($rawType == '2') {
                $type = '준회원';
            } else if ($rawType == '0') {
                $type = '준회원';
                $status = '탈퇴';
            } else if ($rawType === '정회원') {
                $type = '정회원';
            }

            // 기본 정보 저장
            $stmtM = $pdo->prepare("
                INSERT INTO tb_members (member_no, company_name, ceo_name, contact, member_type, status, join_date, withdraw_date, monthly_fee)
                VALUES (:no, :name, :ceo, :contact, :type, :status, :join, :withdraw, 30000)
                ON DUPLICATE KEY UPDATE
                company_name=VALUES(company_name), ceo_name=VALUES(ceo_name), contact=VALUES(contact), 
                member_type=VALUES(member_type), status=VALUES(status), join_date=VALUES(join_date), withdraw_date=VALUES(withdraw_date)
            ");
            
            $stmtM->execute([
                ':no' => $mNo,
                ':name' => $mName,
                ':ceo' => isset($colMap['ceo_name']) ? trim($row[$colMap['ceo_name']] ?? '') : '',
                ':contact' => isset($colMap['contact']) ? trim($row[$colMap['contact']] ?? '') : '',
                ':type' => $type,
                ':status' => $status,
                ':join' => $joinDate ?: date('Y-m-d'),
                ':withdraw' => $withdrawDate
            ]);
            
            $memberId = $pdo->query("SELECT id FROM tb_members WHERE member_no = " . $pdo->quote($mNo))->fetchColumn();

            // 메모 필드 통합
            $memoParts = [];
            if (isset($colMap['memo_fields'])) {
                foreach ($colMap['memo_fields'] as $label => $idx) {
                    $val = trim($row[$idx] ?? '');
                    if ($val) $memoParts[] = "[$label] $val";
                }
            }
            $memo = implode("\n", $memoParts);

            // 설립연도 가공
            $est = isset($colMap['established']) ? $normalizeDate($row[$colMap['established']] ?? '') : null;

            // 상세 정보 저장
            $stmtD->execute([
                ':mid' => $memberId,
                ':biz' => isset($colMap['biz_no']) ? trim($row[$colMap['biz_no']] ?? '') : '',
                ':addr' => isset($colMap['address']) ? trim($row[$colMap['address']] ?? '') : '',
                ':home' => isset($colMap['homepage']) ? trim($row[$colMap['homepage']] ?? '') : '',
                ':email' => isset($colMap['email']) ? trim($row[$colMap['email']] ?? '') : '',
                ':est' => $est ?: null,
                ':memo' => $memo
            ]);

            $successCount++;
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "{$successCount}건의 데이터가 연동/업데이트되었습니다."]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => "DB 오류: " . $e->getMessage()]);
    }
    exit;
}

// [추가] 구글 시트 연동: 회비 변동 이력 일괄 가져오기
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_fee_history') {
    header('Content-Type: application/json; charset=utf-8');

    $sheetUrl = trim($_POST['sheet_url'] ?? '');
    if (!$sheetUrl || !preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $sheetUrl, $m)) {
        echo json_encode(['success' => false, 'message' => '올바른 구글 시트 URL이 아닙니다.']);
        exit;
    }
    $sheetId = $m[1];
    $gid     = 0;
    if (preg_match('/[#&?]gid=(\d+)/', $sheetUrl, $gm)) $gid = (int)$gm[1];
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";

    $raw = @file_get_contents($csvUrl);
    if ($raw === false) {
        echo json_encode(['success' => false, 'message' => '시트를 불러올 수 없습니다.']);
        exit;
    }

    $rows = array_map('str_getcsv', explode("\n", trim($raw)));
    if (count($rows) < 2) {
        echo json_encode(['success' => false, 'message' => '데이터가 없습니다.']);
        exit;
    }

    $headers = array_map(fn($h) => trim(preg_replace('/[\x00-\x1F\xEF\xBB\xBF]+/u', '', $h)), $rows[0]);
    $colMap = [];
    $colAlias = [
        'member_no'    => ['회원번호', '번호', 'no'],
        'company_name' => ['조직명', '회원사명', '회사명', 'name'],
        'start_date'   => ['적용시작일', '시작일', '날짜', 'date', 'start_date'],
        'monthly_fee'  => ['월회비', '회비', '금액', 'fee'],
        'memo'         => ['비고', '사유', '메모', 'memo']
    ];

    foreach ($colAlias as $field => $aliases) {
        foreach ($aliases as $alias) {
            $idx = array_search(mb_strtolower($alias), array_map('mb_strtolower', $headers));
            if ($idx !== false) { $colMap[$field] = $idx; break; }
        }
    }

    if ((!isset($colMap['member_no']) && !isset($colMap['company_name'])) || !isset($colMap['monthly_fee']) || !isset($colMap['start_date'])) {
        echo json_encode(['success' => false, 'message' => '필수 컬럼(회원번호/조직명, 시작일, 월회비)을 찾을 수 없습니다.']);
        exit;
    }

    try {
        // 회원 매칭용 맵 생성
        $membersRaw = $pdo->query("SELECT id, member_no, company_name FROM tb_members")->fetchAll();
        $memberMapNo   = []; $memberMapName = [];
        foreach ($membersRaw as $m) {
            $memberMapNo[trim($m['member_no'])] = $m['id'];
            $memberMapName[trim($m['company_name'])] = $m['id'];
        }

        $pdo->beginTransaction();
        $stmtHistory = $pdo->prepare("INSERT INTO tb_member_fee_history (member_id, start_date, monthly_fee, memo) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE monthly_fee = VALUES(monthly_fee), memo = VALUES(memo)");
        
        $successCount = 0; $failCount = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $mNo   = isset($colMap['member_no']) ? trim($row[$colMap['member_no']] ?? '') : null;
            $mName = isset($colMap['company_name']) ? trim($row[$colMap['company_name']] ?? '') : null;
            $sDate = trim($row[$colMap['start_date']] ?? '');
            $fee   = (int)preg_replace('/[^0-9]/', '', $row[$colMap['monthly_fee']] ?? '0');
            $memo  = isset($colMap['memo']) ? trim($row[$colMap['memo']] ?? '') : '';

            $mId = $memberMapNo[$mNo] ?? $memberMapName[$mName] ?? null;
            if (!$mId || !$sDate || $fee <= 0) { $failCount++; continue; }

            // 날짜 형식 보정 (YYYY.MM.DD -> YYYY-MM-DD)
            $sDate = str_replace('.', '-', $sDate);

            $stmtHistory->execute([$mId, $sDate, $fee, $memo]);
            $successCount++;
        }

        // 전체 회원의 현재 회비 최신화
        $pdo->exec("
            UPDATE tb_members m 
            SET monthly_fee = (
                SELECT monthly_fee FROM tb_member_fee_history 
                WHERE member_id = m.id AND start_date <= CURDATE() 
                ORDER BY start_date DESC LIMIT 1
            )
            WHERE id IN (SELECT DISTINCT member_id FROM tb_member_fee_history)
        ");

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "{$successCount}건 처리 완료 (미매칭/오류: {$failCount}건)"]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


// [추가] 회비 이력 조회 API
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_fee_history') {
    header('Content-Type: application/json; charset=utf-8');
    $memberId = (int)($_GET['member_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM tb_member_fee_history WHERE member_id = ? ORDER BY start_date DESC");
        $stmt->execute([$memberId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// [추가] 회비 이력 추가 API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_fee_history') {
    header('Content-Type: application/json; charset=utf-8');
    $memberId = (int)($_POST['member_id'] ?? 0);
    $fee      = (int)($_POST['monthly_fee'] ?? 0);
    $sDate    = $_POST['start_date'] ?? '';
    $memo     = $_POST['memo'] ?? '';

    if (!$memberId || !$fee || !$sDate) {
        echo json_encode(['success' => false, 'message' => '필수 항목이 누락되었습니다.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        // 1. 이력 추가
        $stmt = $pdo->prepare("INSERT INTO tb_member_fee_history (member_id, start_date, monthly_fee, memo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$memberId, $sDate, $fee, $memo]);

        // 2. 만약 추가한 날짜가 오늘보다 과거이거나 오늘이면, 회원 테이블의 현재 회비도 업데이트
        $pdo->prepare("
            UPDATE tb_members m 
            SET monthly_fee = (SELECT monthly_fee FROM tb_member_fee_history WHERE member_id = m.id AND start_date <= CURDATE() ORDER BY start_date DESC LIMIT 1)
            WHERE id = ?
        ")->execute([$memberId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => '회비 변동 이력이 추가되었습니다.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// [추가] 회원 상세 및 히스토리, 파일 조회 API
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_member_full_info') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_GET['id'] ?? 0);
    try {
        // 기본 정보
        $m = $pdo->prepare("SELECT * FROM tb_members WHERE id = ?");
        $m->execute([$id]);
        $basic = $m->fetch(PDO::FETCH_ASSOC);

        // 상세 정보
        $d = $pdo->prepare("SELECT biz_no, established_date, address, homepage, email, memo, unpaid_before_2022 FROM tb_member_details WHERE member_id = ?");
        $d->execute([$id]);
        $details = $d->fetch(PDO::FETCH_ASSOC) ?: [
            'biz_no' => '', 'address' => '', 'homepage' => '', 'email' => '', 'established_date' => '', 'memo' => '', 'unpaid_before_2022' => 0
        ];

        // 변경 이력
        $h = $pdo->prepare("SELECT * FROM tb_member_history WHERE member_id = ? ORDER BY changed_at DESC");
        $h->execute([$id]);
        $history = $h->fetchAll(PDO::FETCH_ASSOC);

        // 첨부 파일
        $f = $pdo->prepare("SELECT * FROM tb_member_files WHERE member_id = ? ORDER BY uploaded_at DESC");
        $f->execute([$id]);
        $files = $f->fetchAll(PDO::FETCH_ASSOC);

        // 회비 변동 이력 (tb_member_fee_history)
        $fh = $pdo->prepare("SELECT * FROM tb_member_fee_history WHERE member_id = ? ORDER BY start_date DESC");
        $fh->execute([$id]);
        $feeHistory = $fh->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'   => true,
            'basic'     => $basic,
            'details'   => $details,
            'history'   => $history,
            'files'     => $files,
            'feeHistory' => $feeHistory
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// [추가] 파일 업로드 API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_member_file') {
    header('Content-Type: application/json; charset=utf-8');
    $member_id = (int)($_POST['member_id'] ?? 0);
    $category  = $_POST['category'] ?? 'etc';
    
    if (!$member_id || !isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        exit;
    }

    $file = $_FILES['file'];
    $uploadDir = __DIR__ . '/uploads/members/' . $member_id . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = $category . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tb_member_files (member_id, category, file_path, original_name, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$member_id, $category, 'uploads/members/' . $member_id . '/' . $newName, $file['name'], $file['size']]);
            echo json_encode(['success' => true, 'message' => '파일이 업로드되었습니다.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '파일 업로드 실패']);
    }
    exit;
}

// [추가] 파일 삭제 API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    header('Content-Type: application/json; charset=utf-8');
    $file_id = (int)($_POST['file_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM tb_member_files WHERE id = ?");
        $stmt->execute([$file_id]);
        $f = $stmt->fetch();
        if ($f) {
            $fullPath = __DIR__ . '/' . $f['file_path'];
            if (file_exists($fullPath)) unlink($fullPath);
            $pdo->prepare("DELETE FROM tb_member_files WHERE id = ?")->execute([$file_id]);
            echo json_encode(['success' => true, 'message' => '파일이 삭제되었습니다.']);
        } else {
            echo json_encode(['success' => false, 'message' => '파일을 찾을 수 없습니다.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// [수정] 회원 정보 저장 API (상세 및 히스토리 포함)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_member') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_POST['member_id'] ?? 0);
    
    // 기본 필드
    $basicData = [
        'member_no'    => trim($_POST['member_no'] ?? ''),
        'company_name' => trim($_POST['company_name'] ?? ''),
        'ceo_name'     => trim($_POST['ceo_name'] ?? ''),
        'contact'      => trim($_POST['contact'] ?? ''),
        'member_type'  => $_POST['member_type'] ?? '정회원',
        'status'       => $_POST['status'] ?? '운영',
        'monthly_fee'  => (int)($_POST['monthly_fee'] ?? 30000),
        'join_date'    => $_POST['join_date'] ?? date('Y-m-d'),
        'withdraw_date'=> $_POST['withdraw_date'] ?: null
    ];

    // 상세 필드
    $detailData = [
        'biz_no'           => trim($_POST['biz_no'] ?? ''),
        'address'          => trim($_POST['address'] ?? ''),
        'homepage'         => trim($_POST['homepage'] ?? ''),
        'email'            => trim($_POST['email'] ?? ''),
        'established_date' => $_POST['established_date'] ?: null,
        'memo'             => trim($_POST['memo'] ?? ''),
        'unpaid_before_2022' => (int)($_POST['unpaid_before_2022'] ?? 0)
    ];

    $changeReason = trim($_POST['change_reason'] ?? '');

    try {
        $pdo->beginTransaction();

        if ($id > 0) {
            // 1. 기존 데이터와 비교하여 히스토리 기록
            $oldStmt = $pdo->prepare("SELECT * FROM tb_members WHERE id = ?");
            $oldStmt->execute([$id]);
            $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

            $fieldsToTrack = ['member_no', 'company_name', 'ceo_name', 'contact', 'member_type', 'status', 'monthly_fee', 'withdraw_date'];
            foreach ($fieldsToTrack as $f) {
                if ((string)($old[$f] ?? '') !== (string)($basicData[$f] ?? '')) {
                    $hst = $pdo->prepare("INSERT INTO tb_member_history (member_id, field_name, old_value, new_value, change_reason, changed_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $hst->execute([$id, $f, $old[$f], $basicData[$f], $changeReason, $_SESSION['admin_name'] ?? 'Admin']);
                }
            }

            // 2. 기본 정보 업데이트
            $stmt = $pdo->prepare("UPDATE tb_members SET member_no=?, company_name=?, ceo_name=?, contact=?, member_type=?, status=?, monthly_fee=?, join_date=?, withdraw_date=? WHERE id=?");
            $stmt->execute([$basicData['member_no'], $basicData['company_name'], $basicData['ceo_name'], $basicData['contact'], $basicData['member_type'], $basicData['status'], $basicData['monthly_fee'], $basicData['join_date'], $basicData['withdraw_date'], $id]);

            // 3. 상세 정보 업데이트/삽입
            $stmtDet = $pdo->prepare("
                INSERT INTO tb_member_details (member_id, biz_no, address, homepage, email, established_date, memo, unpaid_before_2022)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                biz_no=VALUES(biz_no), address=VALUES(address), homepage=VALUES(homepage), email=VALUES(email), established_date=VALUES(established_date), memo=VALUES(memo), unpaid_before_2022=VALUES(unpaid_before_2022)
            ");
            $stmtDet->execute([$id, $detailData['biz_no'], $detailData['address'], $detailData['homepage'], $detailData['email'], $detailData['established_date'], $detailData['memo'], $detailData['unpaid_before_2022']]);
            
            echo json_encode(['success' => true, 'message' => '회원 정보가 수정되었습니다.']);
        } else {
            // 신규 등록
            $stmt = $pdo->prepare("INSERT INTO tb_members (member_no, company_name, ceo_name, contact, member_type, status, monthly_fee, join_date, withdraw_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$basicData['member_no'], $basicData['company_name'], $basicData['ceo_name'], $basicData['contact'], $basicData['member_type'], $basicData['status'], $basicData['monthly_fee'], $basicData['join_date'], $basicData['withdraw_date']]);
            $newId = $pdo->lastInsertId();

            $stmtDet = $pdo->prepare("INSERT INTO tb_member_details (member_id, biz_no, address, homepage, email, established_date, memo, unpaid_before_2022) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtDet->execute([$newId, $detailData['biz_no'], $detailData['address'], $detailData['homepage'], $detailData['email'], $detailData['established_date'], $detailData['memo'], $detailData['unpaid_before_2022']]);

            echo json_encode(['success' => true, 'message' => '신규 회원이 등록되었습니다.']);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
    }
    exit;
}

// [추가] 단일 회원 삭제 API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_member') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_POST['id'] ?? 0);
    try {
        $pdo->beginTransaction();
        
        // 연관 데이터 삭제 (제약 조건 오류 방지)
        $pdo->prepare("DELETE FROM tb_member_fee_history WHERE member_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM tb_dues_payments WHERE member_id=?")->execute([$id]);
        
        // 회원 삭제
        $pdo->prepare("DELETE FROM tb_members WHERE id=?")->execute([$id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => '삭제되었습니다.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
    }
    exit;
}

// [추가] 다중 회원 삭제 API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_members') {
    header('Content-Type: application/json; charset=utf-8');
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => '선택된 회원이 없습니다.']);
        exit;
    }
    try {
        $pdo->beginTransaction();
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        
        // 연관 데이터 일괄 삭제
        $pdo->prepare("DELETE FROM tb_member_fee_history WHERE member_id IN ($inQuery)")->execute($ids);
        $pdo->prepare("DELETE FROM tb_dues_payments WHERE member_id IN ($inQuery)")->execute($ids);
        
        // 회원 일괄 삭제
        $pdo->prepare("DELETE FROM tb_members WHERE id IN ($inQuery)")->execute($ids);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => '일괄 삭제되었습니다.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
    }
    exit;
}

// [추가] 다중 회원 정보 수정 API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_edit_members') {
    header('Content-Type: application/json; charset=utf-8');
    $ids = $_POST['ids'] ?? [];
    $member_type = $_POST['member_type'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => '선택된 회원이 없습니다.']);
        exit;
    }
    try {
        $updates = [];
        $params = [];
        if ($member_type !== '') {
            $updates[] = 'member_type=?';
            $params[] = $member_type;
        }
        if ($status !== '') {
            $updates[] = 'status=?';
            $params[] = $status;
        }
        
        if (!empty($updates)) {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($params, $ids);
            $query = "UPDATE tb_members SET " . implode(', ', $updates) . " WHERE id IN ($inQuery)";
            $pdo->prepare($query)->execute($params);
            echo json_encode(['success' => true, 'message' => '일괄 수정되었습니다.']);
        } else {
            echo json_encode(['success' => false, 'message' => '수정할 항목이 없습니다.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'DB 오류: ' . $e->getMessage()]);
    }
    exit;
}

try {
    $db_connected = true;
    $stmt = $pdo->query("
        SELECT m.*, d.biz_no, d.address, d.email, d.homepage, d.established_date, d.memo 
        FROM tb_members m 
        LEFT JOIN tb_member_details d ON m.id = d.member_id 
        ORDER BY m.member_no ASC
    ");
    $members_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($members_db as $row) {
        $members[] = [
            'id'        => $row['id'],
            'no'        => $row['member_no'],
            'name'      => $row['company_name'],
            'ceo'       => $row['ceo_name'] ?? '-',
            'phone'     => $row['contact'] ?? '-',
            'type'      => $row['member_type'],
            'status'    => $row['status'],
            'fee'       => (int)$row['monthly_fee'],
            'join_date'     => $row['join_date'],
            'withdraw_date' => $row['withdraw_date'],
            'biz_no'    => $row['biz_no'] ?? '-',
            'address'   => $row['address'] ?? '-',
            'email'     => $row['email'] ?? '-',
            'homepage'  => $row['homepage'] ?? '-',
            'established_date' => $row['established_date'] ?? '-'
        ];
    }
} catch (PDOException $e) {
    $db_connected = false;
    $error_message = 'DB 연결 실패: ' . $e->getMessage();
}
?>
<?php require_once __DIR__ . '/includes/html_head.php'; ?>

<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- 메인 컨텐츠 영역 -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- 상단 헤더 -->
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-800">회원 관리</h1>
                <?php if (!$db_connected): ?>
                    <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-bold border border-red-200">
                        <i class="ph ph-warning-circle"></i> DB 연결 안됨
                    </span>
                <?php else: ?>
                    <span
                        class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold border border-green-200">
                        <i class="ph ph-check-circle"></i> DB 연동 완료
                    </span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openModal()" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition flex items-center gap-2 shadow-sm whitespace-nowrap">
                    <i class="ph ph-user-plus text-lg"></i> 신규 등록
                </button>
                <button onclick="document.getElementById('gSheetImportPanel').classList.toggle('hidden')" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-700 transition flex items-center gap-2 whitespace-nowrap">
                    <i class="ph ph-file-xls"></i> 구글시트 연동
                </button>
                <a href="/shm/logout.php"
                    class="bg-gray-100 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200 transition-colors ml-2">로그아웃</a>
            </div>
        </header>

        <!-- 본문 -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 lg:p-10 relative">

            <?php if (!$db_connected && $error_message): ?>
                <!-- DB 연결 에러 메시지 알림바 -->
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-md shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="ph-fill ph-warning-circle text-red-500 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700 font-medium">데이터베이스 연결에 실패했습니다. (상단 PHP의 $user, $pass 값을 확인해주세요)
                            </p>
                            <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars($error_message) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 구글 시트 연동 패널 -->
            <div id="gSheetImportPanel" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6 no-print hidden">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-3">
                    <h2 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="4" fill="#0F9D58"/><path d="M7 6h10v2H7V6zm0 4h10v2H7v-2zm0 4h7v2H7v-2z" fill="white"/></svg>
                        구글 시트 연동 일괄 데이터 처리
                    </h2>
                    <div class="flex bg-gray-100 p-1 rounded-lg">
                        <button type="button" onclick="setImportType('member')" id="btnTypeMember" 
                                class="px-3 py-1 text-xs font-bold rounded-md transition-all bg-white shadow-sm text-primary">회원 정보</button>
                        <button type="button" onclick="setImportType('fee_history')" id="btnTypeFee" 
                                class="px-3 py-1 text-xs font-bold rounded-md transition-all text-gray-500 hover:text-gray-700">회비 이력</button>
                    </div>
                </div>
                
                <div class="flex flex-col md:flex-row gap-3">
                    <input type="url" id="sheetUrlInput" placeholder="구글 스프레드시트 URL을 입력하세요"
                           class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary">
                    <div class="flex gap-2">
                        <button onclick="importGSheetBySelectedType()"
                                class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition flex items-center gap-2 whitespace-nowrap">
                            <i class="ph ph-cloud-arrow-down text-base"></i> <span id="importBtnText">데이터 가져오기</span>
                        </button>
                    </div>
                </div>
                <!-- 안내 메시지 동적 변경 -->
                <p id="importGuide" class="text-xs text-gray-400 mt-2">
                    <i class="ph ph-info text-blue-400"></i>
                    헤더 필수값: <code class="bg-gray-100 px-1 rounded">회원번호</code>, <code class="bg-gray-100 px-1 rounded">조직명</code>
                </p>
                <div id="importResult" class="mt-3 hidden"></div>
            </div>

            <!-- 상단 컨트롤 (검색 및 필터) -->
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
                <!-- 일괄 수정/삭제 버튼 영역 -->
                <div class="flex items-center gap-2">
                    <button onclick="openBulkEditModal()" class="bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm hover:bg-gray-300 transition shadow-sm flex items-center gap-1">
                        <i class="ph ph-pencil-simple"></i> 일괄 수정
                    </button>
                    <button onclick="bulkDeleteMembers()" class="bg-red-50 text-red-600 border border-red-200 px-3 py-2 rounded-lg text-sm hover:bg-red-100 transition shadow-sm flex items-center gap-1">
                        <i class="ph ph-trash"></i> 일괄 삭제
                    </button>
                </div>

                <!-- 검색/필터 영역 -->
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <!-- 필드 설정 드롭다운 -->
                    <div class="relative group">
                        <button class="bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm hover:bg-gray-50 transition shadow-sm flex items-center gap-1">
                            <i class="ph ph-columns"></i> 표시 필드 설정
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-30 p-3">
                            <div class="text-xs font-bold text-gray-400 mb-2">기본 정보</div>
                            <div id="colTogglesBasic" class="space-y-1"></div>
                            <div class="text-xs font-bold text-gray-400 mt-3 mb-2">상세 정보</div>
                            <div id="colTogglesDetail" class="space-y-1"></div>
                        </div>
                    </div>
                    <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer bg-white border border-gray-300 rounded-lg px-3 py-2">
                        <input type="checkbox" id="showWithdrawn" onchange="filterData()" class="rounded text-primary focus:ring-primary">
                        탈퇴 회원 포함
                    </label>
                    <select id="filterType" onchange="filterData()"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-secondary">
                        <option value="all">전체 구분</option>
                        <option value="정회원">정회원</option>
                        <option value="준회원">준회원</option>
                        <option value="특별회원">특별회원</option>
                    </select>
                    <select id="filterStatus" onchange="filterData()"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-secondary">
                        <option value="all">전체 상태</option>
                        <option value="운영">운영</option>
                        <option value="비운영">비운영</option>
                        <option value="탈퇴">탈퇴</option>
                    </select>
                    <div class="relative flex-1 md:w-64">
                        <input type="text" id="searchInput" onkeyup="filterData()" placeholder="회원번호, 조직명 검색"
                            class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-secondary">
                        <i class="ph ph-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-lg"></i>
                    </div>
                </div>
            </div>

            <!-- 회원 목록 테이블 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto max-h-[calc(100vh-320px)]">
                    <table class="w-full text-sm text-left text-gray-600 whitespace-nowrap border-collapse">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200 sticky top-0 z-20 shadow-sm">
                            <tr id="tableHeaderRow">
                                <!-- JS로 렌더링 됨 -->
                            </tr>
                        </thead>
                        <tbody id="memberTableBody" class="divide-y divide-gray-200">
                            <!-- JS로 렌더링 됨 -->
                        </tbody>
                    </table>
                </div>

                <!-- 페이지네이션 -->
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                총 <span id="totalCount" class="font-medium text-primary">0</span>개 회원사 표시
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 회원 정보 등록/수정 모달 (Modal) -->
    <div id="memberModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300 no-print">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl mx-4 overflow-hidden transform scale-95 transition-transform duration-300 flex flex-col max-h-[90vh]" id="modalContent">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gray-50 flex-shrink-0">
                <h3 class="text-xl font-bold text-gray-800" id="modalTitle">신규 회원사 등록</h3>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="ph ph-x text-2xl"></i>
                </button>
            </div>
            
            <!-- 탭 메뉴 -->
            <div class="flex border-b bg-white flex-shrink-0">
                <button type="button" onclick="switchTab('basic')" id="tab-basic" class="tab-btn px-6 py-3 text-sm font-bold border-b-2 border-primary text-primary">기본 정보</button>
                <button type="button" onclick="switchTab('detail')" id="tab-detail" class="tab-btn px-6 py-3 text-sm font-bold text-gray-400 border-b-2 border-transparent hover:text-gray-600">상세 정보</button>
                <button type="button" onclick="switchTab('history')" id="tab-history" class="tab-btn px-6 py-3 text-sm font-bold text-gray-400 border-b-2 border-transparent hover:text-gray-600">변경 이력</button>
                <button type="button" onclick="switchTab('files')" id="tab-files" class="tab-btn px-6 py-3 text-sm font-bold text-gray-400 border-b-2 border-transparent hover:text-gray-600">첨부 자료</button>
            </div>

            <form id="memberForm" onsubmit="saveData(event)" class="flex-1 overflow-hidden flex flex-col">
                <input type="hidden" id="member_id" name="member_id">
                
                <div class="p-6 overflow-y-auto flex-1">
                    <!-- 1. 기본 정보 탭 -->
                    <div id="pane-basic" class="tab-pane space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">회원번호</label>
                                <input type="text" id="member_no" name="member_no" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">조직명 (회원사명)</label>
                                <input type="text" id="company_name" name="company_name" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">대표자명</label>
                                <input type="text" id="ceo_name" name="ceo_name" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                                <input type="text" id="contact" name="contact" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">회원 구분</label>
                                <select id="member_type" name="member_type" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                                    <option value="정회원">정회원</option>
                                    <option value="준회원">준회원</option>
                                    <option value="특별회원">특별회원</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">운영 상태</label>
                                <select id="status" name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                                    <option value="운영">운영</option>
                                    <option value="비운영">비운영</option>
                                    <option value="탈퇴">탈퇴</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">책정 월회비 (원)</label>
                                <input type="number" id="monthly_fee" name="monthly_fee" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">가입일</label>
                                <input type="date" id="join_date" name="join_date" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">탈퇴일</label>
                                <input type="date" id="withdraw_date" name="withdraw_date" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            </div>
                        </div>
                        
                        <!-- 변경 사유 (수정 시 노출) -->
                        <div id="changeReasonBox" class="hidden mt-6 bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                            <label class="block text-xs font-bold text-yellow-700 mb-1">정보 변경 사유</label>
                            <input type="text" name="change_reason" placeholder="조직명 변경 등 변경 사유를 입력하세요 (히스토리에 기록됨)" class="w-full border-yellow-300 rounded-md px-3 py-2 text-sm focus:ring-yellow-500 bg-white outline-none">
                        </div>
                    </div>

                    <!-- 2. 상세 정보 탭 -->
                    <div id="pane-detail" class="tab-pane hidden space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">사업자등록번호</label>
                                <input type="text" id="biz_no" name="biz_no" placeholder="000-00-00000" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">설립일</label>
                                <input type="date" id="established_date" name="established_date" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                                <input type="text" id="address" name="address" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">홈페이지</label>
                                <input type="url" id="homepage" name="homepage" placeholder="https://" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">대표 이메일</label>
                                <input type="email" id="email" name="email" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">대표 이메일</label>
                                <input type="email" id="email" name="email" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-blue-700 mb-1 font-bold">2021년 이전 미납총액 (원)</label>
                                <input type="number" id="unpaid_before_2022" name="unpaid_before_2022" placeholder="0" class="w-full border border-blue-200 bg-blue-50 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">메모</label>
                                <textarea id="memo" name="memo" rows="4" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 3. 변경 이력 탭 -->
                    <div id="pane-history" class="tab-pane hidden space-y-6">
                        <div class="mb-8">
                            <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                                <i class="ph ph-money text-primary"></i> 책정 회비 변동 이력
                            </h4>
                            <div class="bg-gray-50 rounded-xl overflow-hidden border border-gray-100 mb-4">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-100 text-gray-600 uppercase">
                                        <tr>
                                            <th class="px-4 py-2">적용시작일</th>
                                            <th class="px-4 py-2">월회비</th>
                                            <th class="px-4 py-2">사유</th>
                                        </tr>
                                    </thead>
                                    <tbody id="feeHistoryList">
                                        <!-- JS 렌더링 -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                                <i class="ph ph-clock-counter-clockwise text-primary"></i> 정보 변경 이력 (Audit Log)
                            </h4>
                            <div class="bg-gray-50 rounded-xl overflow-hidden border border-gray-100 overflow-x-auto">
                                <table class="w-full text-left text-xs whitespace-nowrap">
                                    <thead class="bg-gray-100 text-gray-600 uppercase">
                                        <tr>
                                            <th class="px-4 py-2">변경일시</th>
                                            <th class="px-4 py-2">항목</th>
                                            <th class="px-4 py-2">이전 값</th>
                                            <th class="px-4 py-2">변경 값</th>
                                            <th class="px-4 py-2">사유/관리자</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyList">
                                        <!-- JS 렌더링 -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 4. 첨부 파일 탭 -->
                    <div id="pane-files" class="tab-pane hidden space-y-6">
                        <!-- 파일 업로드 폼 -->
                        <div id="uploadBox" class="bg-gray-50 p-4 rounded-lg border border-dashed border-gray-300">
                            <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-1"><i class="ph ph-upload"></i> 신규 파일 업로드</h4>
                            <div class="flex gap-2">
                                <select id="fileCategory" class="border border-gray-300 rounded-md px-3 py-2 text-sm bg-white outline-none">
                                    <option value="biz_license">사업자등록증</option>
                                    <option value="intro">조직소개서</option>
                                    <option value="etc">기타 증빙</option>
                                </select>
                                <input type="file" id="fileInput" class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm bg-white">
                                <button type="button" onclick="uploadFile()" class="bg-secondary text-white px-4 py-2 rounded-md text-sm font-bold hover:bg-gray-800 transition">업로드</button>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-2">※ 신규 등록 시에는 회원사 정보를 먼저 저장한 후 파일을 업로드할 수 있습니다.</p>
                        </div>

                        <!-- 파일 리스트 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="fileList">
                            <!-- JS로 렌더링 -->
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 flex-shrink-0">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">취소</button>
                    <button type="submit" class="px-6 py-2 bg-primary rounded-md text-sm font-bold text-white hover:bg-blue-800 transition-colors">저장하기</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // -----------------------------------------
        // 1. PHP 백엔드에서 전달받은 MySQL 데이터 매핑
        // -----------------------------------------
        let membersData = <?= json_encode($members, JSON_UNESCAPED_UNICODE) ?>;
        let currentSort = { col: 'no', dir: 'asc' };

        const tbody = document.getElementById('memberTableBody');

        // 초기 실행
        document.addEventListener('DOMContentLoaded', () => {
            filterData();
        });

        // 정렬 함수
        function sortBy(col) {
            if (currentSort.col === col) {
                currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.col = col;
                currentSort.dir = 'asc';
            }
            
            // 아이콘 업데이트
            document.querySelectorAll('thead i').forEach(i => {
                i.className = 'ph ph-caret-up-down opacity-30';
            });
            const icon = document.getElementById('sort-' + col);
            if (icon) {
                icon.className = currentSort.dir === 'asc' ? 'ph ph-caret-up opacity-100 text-primary' : 'ph ph-caret-down opacity-100 text-primary';
            }

            filterData();
        }

        // 데이터 필터링 및 렌더링
        function filterData() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const type = document.getElementById('filterType').value;
            const status = document.getElementById('filterStatus').value;
            const showWithdrawn = document.getElementById('showWithdrawn').checked;

            // 1. 필터링
            let filtered = membersData.filter(m => {
                const matchesSearch = m.no.toLowerCase().includes(query) || m.name.toLowerCase().includes(query);
                const matchesType = type === 'all' || m.type === type;
                const matchesStatus = status === 'all' || m.status === status;
                
                // 탈퇴 회원 배제 로직
                const isWithdrawn = m.status === '탈퇴';
                const matchesWithdrawn = showWithdrawn || !isWithdrawn;

                return matchesSearch && matchesType && matchesStatus && matchesWithdrawn;
            });

            // 2. 정렬
            filtered.sort((a, b) => {
                let valA = a[currentSort.col];
                let valB = b[currentSort.col];

                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();

                if (valA < valB) return currentSort.dir === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.dir === 'asc' ? 1 : -1;
                return 0;
            });

            // 3. 렌더링
            renderTable(filtered);
        }

        const allColumns = {
            'no': { label: '회원번호', type: 'basic', align: 'left', sortable: true },
            'name': { label: '조직명', type: 'basic', align: 'left', sortable: true },
            'ceo': { label: '대표자', type: 'basic', align: 'left', sortable: true },
            'phone': { label: '연락처', type: 'basic', align: 'left', sortable: false },
            'type': { label: '구분', type: 'basic', align: 'center', sortable: true },
            'status': { label: '상태', type: 'basic', align: 'center', sortable: true },
            'fee': { label: '월회비', type: 'basic', align: 'right', sortable: true },
            'join_date': { label: '가입일', type: 'detail', align: 'center', sortable: true },
            'withdraw_date': { label: '탈퇴일', type: 'detail', align: 'center', sortable: true },
            'biz_no': { label: '사업자번호', type: 'detail', align: 'left', sortable: false },
            'established_date': { label: '설립일', type: 'detail', align: 'center', sortable: true },
            'email': { label: '이메일', type: 'detail', align: 'left', sortable: false },
            'address': { label: '주소', type: 'detail', align: 'left', sortable: false },
            'homepage': { label: '홈페이지', type: 'detail', align: 'left', sortable: false }
        };

        let visibleColumns = JSON.parse(localStorage.getItem('memberVisibleCols')) || ['no', 'name', 'ceo', 'phone', 'type', 'status', 'fee'];

        function initColumnToggles() {
            const basicDiv = document.getElementById('colTogglesBasic');
            const detailDiv = document.getElementById('colTogglesDetail');
            basicDiv.innerHTML = ''; detailDiv.innerHTML = '';

            for (const [key, info] of Object.entries(allColumns)) {
                const label = document.createElement('label');
                label.className = "flex items-center gap-2 text-sm text-gray-700 cursor-pointer p-1 hover:bg-gray-50 rounded";
                label.innerHTML = `<input type="checkbox" value="${key}" class="col-toggle rounded text-primary focus:ring-primary" ${visibleColumns.includes(key) ? 'checked' : ''}> ${info.label}`;
                label.querySelector('input').addEventListener('change', (e) => {
                    if (e.target.checked) {
                        visibleColumns.push(key);
                    } else {
                        visibleColumns = visibleColumns.filter(c => c !== key);
                    }
                    localStorage.setItem('memberVisibleCols', JSON.stringify(visibleColumns));
                    renderHeaders();
                    filterData();
                });

                if (info.type === 'basic') basicDiv.appendChild(label);
                else detailDiv.appendChild(label);
            }
            renderHeaders();
        }

        function renderHeaders() {
            const tr = document.getElementById('tableHeaderRow');
            let html = `<th scope="col" class="px-6 py-4 font-semibold w-12 text-center bg-gray-50"><input type="checkbox" id="checkAll" onchange="toggleAllCheckboxes(this)" class="rounded text-primary focus:ring-primary"></th>`;
            
            visibleColumns.forEach(key => {
                const info = allColumns[key];
                const sortIcon = info.sortable ? `<i id="sort-${key}" class="ph ph-caret-up-down opacity-30"></i>` : '';
                const cursor = info.sortable ? 'cursor-pointer hover:bg-gray-100 transition-colors' : '';
                const align = info.align === 'center' ? 'text-center' : (info.align === 'right' ? 'text-right' : 'text-left');
                const onclick = info.sortable ? `onclick="sortBy('${key}')"` : '';
                html += `<th scope="col" class="px-6 py-4 font-semibold ${align} ${cursor} bg-gray-50" ${onclick}>${info.label} ${sortIcon}</th>`;
            });
            html += `<th scope="col" class="px-6 py-4 font-semibold text-center bg-gray-50">관리</th>`;
            tr.innerHTML = html;
        }

        document.addEventListener('DOMContentLoaded', () => {
            initColumnToggles();
            filterData();
        });

        function renderTable(data) {
            tbody.innerHTML = '';
            document.getElementById('totalCount').innerText = data.length;

            if (data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${visibleColumns.length + 2}" class="px-6 py-10 text-center text-gray-500">조건에 맞는 데이터가 없습니다.</td></tr>`;
                return;
            }

            data.forEach(m => {
                const mJson = encodeURIComponent(JSON.stringify(m));
                const tr = document.createElement('tr');
                tr.className = "hover:bg-blue-50/50 transition-colors group " + (m.status === '탈퇴' ? 'opacity-60 bg-gray-50/50' : '');
                
                let tdHtml = `<td class="px-6 py-4 text-center"><input type="checkbox" class="member-checkbox rounded text-primary focus:ring-primary" value="${m.id}"></td>`;
                
                visibleColumns.forEach(key => {
                    const info = allColumns[key];
                    let val = m[key];
                    const align = info.align === 'center' ? 'text-center' : (info.align === 'right' ? 'text-right' : 'text-left');
                    
                    if (key === 'no') {
                        tdHtml += `<td class="px-6 py-4 text-gray-500 font-mono text-[11px] ${align}">${val}</td>`;
                    } else if (key === 'name') {
                        tdHtml += `<td class="px-6 py-4 font-bold text-gray-800 group-hover:text-primary transition-colors cursor-pointer ${align}" onclick="openEditModal('${mJson}')">${val}</td>`;
                    } else if (key === 'type') {
                        const typeClass = val === '정회원' ? 'text-primary font-medium' : 'text-gray-500';
                        tdHtml += `<td class="px-6 py-4 ${align}"><span class="text-xs ${typeClass}">${val}</span></td>`;
                    } else if (key === 'status') {
                        const statusClass = val === '운영' ? 'bg-green-100 text-green-700' : (val === '탈퇴' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500');
                        tdHtml += `<td class="px-6 py-4 ${align}"><span class="px-2 py-1 rounded-full text-[10px] font-bold ${statusClass}">${val}</span></td>`;
                    } else if (key === 'fee') {
                        tdHtml += `<td class="px-6 py-4 font-semibold text-gray-700 ${align}">${val.toLocaleString()}원</td>`;
                    } else {
                        tdHtml += `<td class="px-6 py-4 text-gray-500 ${align}">${val || '-'}</td>`;
                    }
                });

                tdHtml += `
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                             <a href="member_report.php?member_id=${m.id}" class="text-gray-400 hover:text-secondary transition-colors" title="리포트">
                                <i class="ph ph-chart-bar text-lg"></i>
                            </a>
                            <button onclick="openEditModal('${mJson}')" class="text-gray-400 hover:text-primary transition-colors"><i class="ph ph-pencil-simple text-lg"></i></button>
                            <button onclick="deleteMember(${m.id})" class="text-gray-400 hover:text-red-500 transition-colors"><i class="ph ph-trash text-lg"></i></button>
                        </div>
                    </td>
                `;
                tr.innerHTML = tdHtml;
                tbody.appendChild(tr);
            });
            
            // 정렬 아이콘 복원
            const icon = document.getElementById('sort-' + currentSort.col);
            if (icon) icon.className = currentSort.dir === 'asc' ? 'ph ph-caret-up opacity-100 text-primary' : 'ph ph-caret-down opacity-100 text-primary';
        }

        // 체크박스 전체 선택/해제
        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('.member-checkbox');
            checkboxes.forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = source.checked;
                }
            });
        }

        // -----------------------------------------
        // 2. 모달(Modal) 제어 및 데이터 바인딩
        // -----------------------------------------
        const modal = document.getElementById('memberModal');
        const modalContent = document.getElementById('modalContent');
        const form = document.getElementById('memberForm');

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('text-primary', 'border-primary');
                b.classList.add('text-gray-400', 'border-transparent');
            });
            document.getElementById('tab-' + tab).classList.add('text-primary', 'border-primary');
            document.getElementById('tab-' + tab).classList.remove('text-gray-400', 'border-transparent');

            document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
            document.getElementById('pane-' + tab).classList.remove('hidden');
        }

        function openModal() {
            form.reset();
            document.getElementById('member_id').value = '';
            document.getElementById('modalTitle').innerText = '신규 회원사 등록';
            document.getElementById('changeReasonBox').classList.add('hidden');
            document.getElementById('uploadBox').classList.add('opacity-50', 'pointer-events-none');
            
            // 초기 탭 설정
            switchTab('basic');
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        }

        function closeModal() {
            modal.classList.add('opacity-0');
            modalContent.classList.add('scale-95');
            modalContent.classList.remove('scale-100');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        async function openEditModal(memberJson) {
            const mBasic = JSON.parse(decodeURIComponent(memberJson));
            const id = mBasic.id;

            // 상세 데이터 및 히스토리 서버에서 조회
            try {
                const res = await fetch(`member_management.php?action=get_member_full_info&id=${id}`);
                const result = await res.json();
                if (!result.success) { alert(result.message); return; }

                const m = result.basic;
                const d = result.details;

                // 1. 기본 정보 채우기
                document.getElementById('member_id').value = m.id;
                document.getElementById('member_no').value = m.member_no;
                document.getElementById('company_name').value = m.company_name;
                document.getElementById('ceo_name').value = m.ceo_name || '';
                document.getElementById('contact').value = m.contact || '';
                document.getElementById('member_type').value = m.member_type;
                document.getElementById('status').value = m.status;
                document.getElementById('monthly_fee').value = m.monthly_fee;
                if (m.join_date) document.getElementById('join_date').value = m.join_date;
                if (m.withdraw_date) document.getElementById('withdraw_date').value = m.withdraw_date;

                // 2. 상세 정보 채우기
                document.getElementById('biz_no').value = d.biz_no || '';
                document.getElementById('established_date').value = d.established_date || '';
                document.getElementById('address').value = d.address || '';
                document.getElementById('homepage').value = d.homepage || '';
                document.getElementById('email').value = d.email || '';
                document.getElementById('memo').value = d.memo || '';
                document.getElementById('unpaid_before_2022').value = d.unpaid_before_2022 || 0;

                // 3. 히스토리 렌더링
                renderHistory(result.history, result.feeHistory);

                // 4. 파일 리스트 렌더링
                renderFiles(result.files);

                document.getElementById('modalTitle').innerText = '회원사 정보 수정';
                document.getElementById('changeReasonBox').classList.remove('hidden');
                document.getElementById('uploadBox').classList.remove('opacity-50', 'pointer-events-none');

                switchTab('basic');
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.remove('opacity-0');
                    modalContent.classList.remove('scale-95');
                    modalContent.classList.add('scale-100');
                }, 10);
            } catch (e) {
                console.error(e);
            }
        }

        function renderHistory(history, feeHistory) {
            const hList = document.getElementById('historyList');
            hList.innerHTML = '';
            if (history.length === 0) {
                hList.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">변경 이력이 없습니다.</td></tr>';
            } else {
                history.forEach(h => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="px-4 py-2 text-xs text-gray-500">${h.changed_at}</td>
                        <td class="px-4 py-2 font-bold text-primary">${h.field_name}</td>
                        <td class="px-4 py-2 text-xs text-gray-400 line-through">${h.old_value || '-'}</td>
                        <td class="px-4 py-2 text-xs text-green-600 font-bold">${h.new_value || '-'}</td>
                        <td class="px-4 py-2 text-xs text-gray-500">${h.change_reason || '-'} <span class="text-[10px] text-gray-300">by ${h.changed_by}</span></td>
                    `;
                    hList.appendChild(tr);
                });
            }

            const fhList = document.getElementById('feeHistoryList');
            fhList.innerHTML = '';
            if (feeHistory.length === 0) {
                fhList.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">등록된 회비 변동 이력이 없습니다.</td></tr>';
            } else {
                feeHistory.forEach(fh => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="px-4 py-2 font-mono">${fh.start_date}</td>
                        <td class="px-4 py-2 font-bold text-blue-600">${parseInt(fh.monthly_fee).toLocaleString()}원</td>
                        <td class="px-4 py-2 text-gray-500">${fh.memo || '-'}</td>
                    `;
                    fhList.appendChild(tr);
                });
            }
        }

        function renderFiles(files) {
            const fList = document.getElementById('fileList');
            fList.innerHTML = '';
            if (files.length === 0) {
                fList.innerHTML = '<div class="col-span-2 text-center py-10 text-gray-400">첨부된 자료가 없습니다.</div>';
                return;
            }
            const catNames = { biz_license: '사업자등록증', intro: '조직소개서', etc: '기타' };
            files.forEach(f => {
                const div = document.createElement('div');
                div.className = "bg-white border border-gray-200 rounded-lg p-3 flex justify-between items-center shadow-sm hover:border-primary transition";
                div.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">
                            <i class="ph ph-file-text text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-800 line-clamp-1">${f.original_name}</p>
                            <p class="text-[10px] text-gray-400">${catNames[f.category] || f.category} · ${(f.file_size / 1024).toFixed(1)}KB</p>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <a href="${f.file_path}" target="_blank" class="p-1.5 text-blue-500 hover:bg-blue-50 rounded transition"><i class="ph ph-download-simple text-lg"></i></a>
                        <button type="button" onclick="deleteFile(${f.id})" class="p-1.5 text-red-400 hover:bg-red-50 rounded transition"><i class="ph ph-trash text-lg"></i></button>
                    </div>
                `;
                fList.appendChild(div);
            });
        }

        async function uploadFile() {
            const id = document.getElementById('member_id').value;
            const cat = document.getElementById('fileCategory').value;
            const file = document.getElementById('fileInput').files[0];
            if (!file) { alert('파일을 선택해주세요.'); return; }

            const fd = new FormData();
            fd.append('action', 'upload_member_file');
            fd.append('member_id', id);
            fd.append('category', cat);
            fd.append('file', file);

            const res = await fetch('member_management.php', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) {
                alert(result.message);
                // 리로드 대신 리스트만 갱신 (간략화를 위해 openEditModal 다시 호출)
                const resFull = await fetch(`member_management.php?action=get_member_full_info&id=${id}`);
                const resultFull = await resFull.json();
                renderFiles(resultFull.files);
                document.getElementById('fileInput').value = '';
            } else {
                alert(result.message);
            }
        }

        async function deleteFile(fileId) {
            if (!confirm('파일을 삭제하시겠습니까?')) return;
            const id = document.getElementById('member_id').value;
            const fd = new FormData();
            fd.append('action', 'delete_file');
            fd.append('file_id', fileId);

            const res = await fetch('member_management.php', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) {
                const resFull = await fetch(`member_management.php?action=get_member_full_info&id=${id}`);
                const resultFull = await resFull.json();
                renderFiles(resultFull.files);
            }
        }

        // -----------------------------------------
        // 3. 데이터 저장 및 삭제 처리
        // -----------------------------------------
        async function saveData(e) {
            e.preventDefault();
            const form = document.getElementById('memberForm');
            const fd = new FormData(form);
            fd.append('action', 'save_member');

            const res = await fetch('member_management.php', { method: 'POST', body: fd });
            const result = await res.json();
            alert(result.message);
            if (result.success) {
                location.reload();
            }
        }

        async function deleteMember(id) {
            if (!confirm('정말 삭제하시겠습니까? 관련 납부 내역도 모두 삭제됩니다.')) return;
            const fd = new FormData();
            fd.append('action', 'delete_member');
            fd.append('id', id);

            const res = await fetch('member_management.php', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) {
                location.reload();
            } else {
                alert(result.message);
            }
        }

        function getCheckedMemberIds() {
            return Array.from(document.querySelectorAll('.member-checkbox:checked')).map(cb => cb.value);
        }

        async function bulkDeleteMembers() {
            const ids = getCheckedMemberIds();
            if (ids.length === 0) { alert('삭제할 회원을 선택해주세요.'); return; }
            if (!confirm(`선택한 ${ids.length}명의 회원과 모든 납부 내역을 삭제하시겠습니까?`)) return;

            const fd = new FormData();
            fd.append('action', 'bulk_delete_members');
            ids.forEach(id => fd.append('ids[]', id));

            const res = await fetch('member_management.php', { method: 'POST', body: fd });
            const result = await res.json();
            alert(result.message);
            if (result.success) location.reload();
        }

        function openBulkEditModal() {
            const ids = getCheckedMemberIds();
            if (ids.length === 0) { alert('수정할 회원을 선택해주세요.'); return; }
            document.getElementById('bulkMemberIds').value = ids.join(',');
            const modal = document.getElementById('bulkEditModal');
            const content = document.getElementById('bulkModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        function closeBulkEditModal() {
            const modal = document.getElementById('bulkEditModal');
            const content = document.getElementById('bulkModalContent');
            modal.classList.add('opacity-0');
            content.classList.add('scale-95');
            content.classList.remove('scale-100');
            setTimeout(() => { modal.classList.add('hidden'); }, 300);
        }

        async function submitBulkEdit(e) {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            fd.append('action', 'bulk_edit_members');
            const ids = document.getElementById('bulkMemberIds').value.split(',');
            ids.forEach(id => fd.append('ids[]', id));

            const res = await fetch('member_management.php', { method: 'POST', body: fd });
            const result = await res.json();
            alert(result.message);
            if (result.success) location.reload();
        }

        // 구글 시트 연동 타입 설정
        let currentImportType = 'member';
        function setImportType(type) {
            currentImportType = type;
            const btnMember = document.getElementById('btnTypeMember');
            const btnFee = document.getElementById('btnTypeFee');
            const guide = document.getElementById('importGuide');
            const btnText = document.getElementById('importBtnText');

            if (type === 'member') {
                btnMember.className = 'px-3 py-1 text-xs font-bold rounded-md transition-all bg-white shadow-sm text-primary';
                btnFee.className = 'px-3 py-1 text-xs font-bold rounded-md transition-all text-gray-500 hover:text-gray-700';
                guide.innerHTML = '<i class="ph ph-info text-blue-400"></i> 헤더 필수값: <code class="bg-gray-100 px-1 rounded">회원번호</code>, <code class="bg-gray-100 px-1 rounded">조직명</code>';
                btnText.innerText = '데이터 가져오기';
            } else {
                btnFee.className = 'px-3 py-1 text-xs font-bold rounded-md transition-all bg-white shadow-sm text-primary';
                btnMember.className = 'px-3 py-1 text-xs font-bold rounded-md transition-all text-gray-500 hover:text-gray-700';
                guide.innerHTML = '<i class="ph ph-info text-blue-400"></i> 헤더 필수값: <code class="bg-gray-100 px-1 rounded">회원번호(또는 조직명)</code>, <code class="bg-gray-100 px-1 rounded">시작일</code>, <code class="bg-gray-100 px-1 rounded">월회비</code>';
                btnText.innerText = '이력 가져오기';
            }
        }

        async function importGSheetBySelectedType() {
            const url = document.getElementById('sheetUrlInput').value.trim();
            if (!url) { alert('시트 URL을 입력해주세요.'); return; }
            
            const resultEl = document.getElementById('importResult');
            resultEl.className = 'mt-3 bg-blue-50 border border-blue-200 text-blue-700 text-sm px-4 py-3 rounded-lg';
            resultEl.innerHTML = '<i class="ph ph-spinner animate-spin mr-1"></i> 데이터 처리 중...';
            resultEl.classList.remove('hidden');

            const fd = new FormData();
            fd.append('action', currentImportType === 'member' ? 'import_gsheet' : 'import_fee_history');
            fd.append('sheet_url', url);

            try {
                const res = await fetch('member_management.php', { method: 'POST', body: fd });
                const result = await res.json();
                if (result.success) {
                    resultEl.className = 'mt-3 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg whitespace-pre-line';
                    resultEl.innerHTML = '<i class="ph ph-check-circle mr-1"></i>' + result.message;
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultEl.className = 'mt-3 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg whitespace-pre-line';
                    resultEl.innerHTML = '<i class="ph ph-warning-circle mr-1"></i>' + result.message;
                }
            } catch (e) {
                resultEl.innerHTML = '에러 발생: ' + e.message;
            }
        }
    </script>

    <!-- 일괄 수정 모달 (Modal) -->
    <div id="bulkEditModal"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300 no-print">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 overflow-hidden transform scale-95 transition-transform duration-300"
            id="bulkModalContent">
            <form onsubmit="submitBulkEdit(event)">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <i class="ph ph-pencil-simple text-xl text-primary"></i> 회원사 일괄 수정
                    </h3>
                    <button type="button" onclick="closeBulkEditModal()"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="ph ph-x text-2xl"></i>
                    </button>
                </div>
                <div class="px-6 py-6 grid gap-4">
                    <input type="hidden" id="bulkMemberIds" name="ids" value="">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">회원 구분 변경</label>
                        <select name="member_type"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">변경 안 함</option>
                            <option value="정회원">정회원</option>
                            <option value="준회원">준회원</option>
                            <option value="특별회원">특별회원</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">운영 상태 변경</label>
                        <select name="status"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">변경 안 함</option>
                            <option value="운영">운영</option>
                            <option value="비운영">비운영</option>
                            <option value="탈퇴">탈퇴</option>
                        </select>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
                    <button type="button" onclick="closeBulkEditModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">취소</button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary rounded-md text-sm font-medium text-white hover:bg-blue-800 transition-colors">적용하기</button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>