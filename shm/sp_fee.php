<?php
// 에러 출력을 켜서 500 에러의 구체적인 원인을 확인합니다.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 치명적 에러(Fatal Error - ex: db.php 내부의 require 실패)를 화면에 강제 출력하는 핸들러
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<div style='padding:20px; background:#fee; color:#c00; font-family:sans-serif; border:1px solid #c00;'>";
        echo "<strong>치명적 오류(Fatal Error) 발생 (500 에러 원인):</strong><br>";
        echo "메시지: " . htmlspecialchars($error['message']) . "<br>";
        echo "파일: " . htmlspecialchars($error['file']) . " (줄: " . $error['line'] . ")";
        echo "</div>";
    }
});

try {
    // ==========================================
// 1. 데이터베이스 연결 설정
// ==========================================
// /var/www/html/z/ 가 루트이므로, 하드코딩하여 서버 환경 변수 누락 문제를 방지합니다.
    $db_path = '/var/www/html/z/shm/includes/db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    } else {
        throw new Exception("DB 연결 파일을 찾을 수 없습니다: " . $db_path);
    }

    // 주택 정보 수정 (POST) 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_housing') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $upQuery = "UPDATE sp_fee SET 
                company_no = :company_no,
                company_name = :company_name,
                housing_no = :housing_no,
                building_name = :building_name,
                units = :units,
                project_type = :project_type,
                operation_type = :operation_type,
                region = :region,
                base_date = :base_date,
                payment_date = :payment_date,
                status_category = :status_category,
                total_fee = :total_fee,
                paid_amount = :paid_amount
                WHERE id = :id";

            $upStmt = $pdo->prepare($upQuery);
            $upStmt->execute([
                ':company_no' => $_POST['company_no'],
                ':company_name' => $_POST['company_name'],
                ':housing_no' => $_POST['housing_no'],
                ':building_name' => $_POST['building_name'],
                ':units' => (int) $_POST['units'],
                ':project_type' => $_POST['project_type'],
                ':operation_type' => $_POST['operation_type'],
                ':region' => $_POST['region'],
                ':base_date' => !empty($_POST['base_date']) ? $_POST['base_date'] : null,
                ':payment_date' => !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
                ':status_category' => $_POST['status_category'],
                ':total_fee' => (int) $_POST['total_fee'],
                ':paid_amount' => (int) $_POST['paid_amount'],
                ':id' => $id
            ]);
        } else {
            // Calculate next ID manually since 'id' field is not AUTO_INCREMENT in DB
            $maxIdStmt = $pdo->query("SELECT MAX(id) FROM sp_fee");
            $maxId = (int) $maxIdStmt->fetchColumn();
            $newId = ($maxId > 0) ? $maxId + 1 : 1;

            // INSERT 신규 주택 등록
            $insQuery = "INSERT INTO sp_fee (
                id,
                company_no,
                company_name,
                housing_no,
                building_name,
                units,
                project_type,
                operation_type,
                region,
                base_date,
                payment_date,
                status_category,
                total_fee,
                paid_amount
            ) VALUES (
                :id,
                :company_no,
                :company_name,
                :housing_no,
                :building_name,
                :units,
                :project_type,
                :operation_type,
                :region,
                :base_date,
                :payment_date,
                :status_category,
                :total_fee,
                :paid_amount
            )";

            $insStmt = $pdo->prepare($insQuery);
            $insStmt->execute([
                ':id' => $newId,
                ':company_no' => $_POST['company_no'],
                ':company_name' => $_POST['company_name'],
                ':housing_no' => $_POST['housing_no'],
                ':building_name' => $_POST['building_name'],
                ':units' => (int) $_POST['units'],
                ':project_type' => $_POST['project_type'],
                ':operation_type' => $_POST['operation_type'],
                ':region' => $_POST['region'],
                ':base_date' => !empty($_POST['base_date']) ? $_POST['base_date'] : null,
                ':payment_date' => !empty($_POST['payment_date']) ? $_POST['payment_date'] : null,
                ':status_category' => $_POST['status_category'],
                ':total_fee' => (int) $_POST['total_fee'],
                ':paid_amount' => (int) $_POST['paid_amount']
            ]);
        }

        $redirectUrl = "?view=" . urlencode($_POST['redirect_view']) . "&company=" . urlencode($_POST['redirect_company']);
        header("Location: " . $redirectUrl);
        exit;
    }

    // 주택 정보 일괄 수정 (POST) 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_update_housing') {
        $ids_str = isset($_POST['batch_ids']) ? $_POST['batch_ids'] : '';
        $ids = array_filter(array_map('intval', explode(',', $ids_str)));

        if (!empty($ids)) {
            $setClauses = [];
            $queryParams = [];

            if (isset($_POST['batch_status_category']) && $_POST['batch_status_category'] !== '') {
                $setClauses[] = "status_category = :status_category";
                $queryParams[':status_category'] = $_POST['batch_status_category'];
            }
            if (isset($_POST['batch_payment_date']) && $_POST['batch_payment_date'] !== '') {
                $setClauses[] = "payment_date = :payment_date";
                $queryParams[':payment_date'] = $_POST['batch_payment_date'];
            }
            if (isset($_POST['batch_paid_amount']) && $_POST['batch_paid_amount'] !== '') {
                $setClauses[] = "paid_amount = :paid_amount";
                $queryParams[':paid_amount'] = (int) $_POST['batch_paid_amount'];
            }
            if (isset($_POST['batch_total_fee']) && $_POST['batch_total_fee'] !== '') {
                $setClauses[] = "total_fee = :total_fee";
                $queryParams[':total_fee'] = (int) $_POST['batch_total_fee'];
            }
            if (isset($_POST['batch_units']) && $_POST['batch_units'] !== '') {
                $setClauses[] = "units = :units";
                $queryParams[':units'] = (int) $_POST['batch_units'];
            }
            if (isset($_POST['batch_project_type']) && $_POST['batch_project_type'] !== '') {
                $setClauses[] = "project_type = :project_type";
                $queryParams[':project_type'] = $_POST['batch_project_type'];
            }
            if (isset($_POST['batch_operation_type']) && $_POST['batch_operation_type'] !== '') {
                $setClauses[] = "operation_type = :operation_type";
                $queryParams[':operation_type'] = $_POST['batch_operation_type'];
            }
            if (isset($_POST['batch_region']) && $_POST['batch_region'] !== '') {
                $setClauses[] = "region = :region";
                $queryParams[':region'] = $_POST['batch_region'];
            }
            if (isset($_POST['batch_base_date']) && $_POST['batch_base_date'] !== '') {
                $setClauses[] = "base_date = :base_date";
                $queryParams[':base_date'] = $_POST['batch_base_date'];
            }

            if (!empty($setClauses)) {
                $placeholders = [];
                foreach ($ids as $index => $id) {
                    $placeholder = ":id_" . $index;
                    $placeholders[] = $placeholder;
                    $queryParams[$placeholder] = $id;
                }

                $setSql = implode(', ', $setClauses);
                $idsSql = implode(', ', $placeholders);

                $batchQuery = "UPDATE sp_fee SET {$setSql} WHERE id IN ({$idsSql})";
                $batchStmt = $pdo->prepare($batchQuery);
                $batchStmt->execute($queryParams);
            }
        }

        $redirectUrl = "?view=" . urlencode($_POST['redirect_view']) . "&company=" . urlencode($_POST['redirect_company']);
        header("Location: " . $redirectUrl);
        exit;
    }

    // *참고: db.php 내에서 생성된 데이터베이스 객체 변수명이 $pdo 가 아닐 경우(예: $conn 등), 
    // 하단의 쿼리 실행 부분에서 $pdo 변수명을 알맞게 수정해주셔야 합니다.

    // ==========================================
// 2. 검색 및 필터링 파라미터 수신 (GET 방식)
// ==========================================
    $view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
    $allowedViews = ['dashboard', 'companies', 'report_summary', 'report_status'];
    if (!in_array($view, $allowedViews)) {
        $view = 'dashboard';
    }

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $selectedCompany = isset($_GET['company']) ? $_GET['company'] : '모든 회원사';

    // ==========================================
// 3. 데이터 쿼리 실행
// ==========================================

    // 드롭다운을 위한 고유 회원사 목록 추출 (회원번호 및 회원사명)
    if (!isset($pdo)) {
        throw new Exception("데이터베이스 연결 객체(\$pdo)가 정의되지 않았습니다. db.php에서 사용하는 변수명(예: \$conn)을 확인해주세요.");
    }

    $stmt = $pdo->query("SELECT DISTINCT company_no, company_name FROM sp_fee WHERE company_no IS NOT NULL AND company_name IS NOT NULL ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 상단 KPI 지표 계산 쿼리
    $kpiSql = "SELECT 
            COUNT(CASE WHEN status_category NOT IN ('후원금', '이사회 조정') THEN 1 END) as totalProjects,
            SUM(CASE WHEN status_category NOT IN ('후원금', '이사회 조정') THEN total_fee ELSE 0 END) as totalFeeAmt,
            SUM(CASE WHEN status_category = '납부완료' THEN paid_amount ELSE 0 END) as paidFeeAmt,
            SUM(CASE WHEN status_category = '후원금' THEN total_fee ELSE 0 END) as donationAmt,
            SUM(CASE WHEN status_category = '상계' THEN total_fee ELSE 0 END) as offsetAmt
        FROM sp_fee";
    $kpiResult = $pdo->query($kpiSql)->fetch();

    $totalProjects = isset($kpiResult['totalProjects']) ? $kpiResult['totalProjects'] : 0;
    $totalFeeAmt = isset($kpiResult['totalFeeAmt']) ? $kpiResult['totalFeeAmt'] : 0;
    $paidFeeAmt = isset($kpiResult['paidFeeAmt']) ? $kpiResult['paidFeeAmt'] : 0;
    $donationAmt = isset($kpiResult['donationAmt']) ? $kpiResult['donationAmt'] : 0;
    $offsetAmt = isset($kpiResult['offsetAmt']) ? $kpiResult['offsetAmt'] : 0;
    $totalRevenue = $paidFeeAmt + $donationAmt + $offsetAmt;

    // 메인 테이블 필터링 데이터 쿼리
    $query = "SELECT * FROM sp_fee WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (housing_no LIKE :search_hno OR building_name LIKE :search_bname OR id LIKE :search_id)";
        $params[':search_hno'] = "%$search%";
        $params[':search_bname'] = "%$search%";
        $params[':search_id'] = "%$search%";
    }

    if ($selectedCompany !== '모든 회원사') {
        $query .= " AND company_no = :company";
        $params[':company'] = $selectedCompany;
    }

    // 정렬 로직 (현황 보고서는 후원금, 납부완료, 청구완료, 이사회 조정, 신규 청구, 추후청구 순으로 정렬)
    if ($view === 'report_summary') {
        $query .= " ORDER BY CASE 
            WHEN status_category = '후원금' THEN 0 
            WHEN status_category = '납부완료' THEN 1 
            WHEN status_category = '신규 청구' THEN 4
            WHEN status_category = '청구완료' THEN 2 
            WHEN status_category = '이사회 조정' THEN 3 
            WHEN status_category = '추후청구' THEN 5 
            ELSE 6 
        END ASC, COALESCE(payment_date, base_date) ASC, id ASC";
    } else {
        $query .= " ORDER BY id ASC";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $filteredData = $stmt->fetchAll();

    // 회원사별 뷰를 위한 집계 쿼리
    if ($view === 'companies') {
        $compQuery = "SELECT 
                company_no,
                company_name, 
                COUNT(*) as project_count, 
                SUM(units) as total_units,
                SUM(total_fee) as sum_total_fee,
                SUM(paid_amount) as sum_paid_amount
            FROM sp_fee 
            GROUP BY company_no, company_name 
            ORDER BY company_name ASC";
        $compStmt = $pdo->query($compQuery);
        $companyStats = $compStmt->fetchAll();
    }

    // ==========================================
    // 공급현황 데이터 조회 및 비교 분석 (report_summary 뷰, 특정 회원사 선택 시)
    // ==========================================
    $supplyData = [];
    $supplyIndexByNo = [];
    $supplyIndexByName = [];
    $supplyOnlyRows = [];    // 공급현황에만 있고 sp_fee에 없는 항목
    $mismatchMap = [];       // sp_fee 행 id => ['units' => ..., 'name' => ...] 불일치 정보

    if ($view === 'report_summary' && $selectedCompany !== '모든 회원사') {
        // 해당 회원사의 공급현황 조회 (member_no = company_no 기준)
        $supplyStmt = $pdo->prepare("
            SELECT id, no, building_name, household_count, supply_type, supply_type2,
                   location_si, location_gu, operation_status, special_fee_target
            FROM tb_social_housing_supply
            WHERE member_no = :company_no
              AND deleted_at IS NULL
            ORDER BY CAST(no AS UNSIGNED) ASC
        ");
        $supplyStmt->execute([':company_no' => $selectedCompany]);
        $supplyData = $supplyStmt->fetchAll(PDO::FETCH_ASSOC);

        // 인덱싱 (no 기준, building_name 기준)
        foreach ($supplyData as $sRow) {
            if (!empty($sRow['no'])) {
                $supplyIndexByNo[(string) $sRow['no']] = $sRow;
            }
            if (!empty($sRow['building_name'])) {
                $supplyIndexByName[trim($sRow['building_name'])] = $sRow;
            }
        }

        // sp_fee 각 행과 공급현황 매칭 → 불일치 탐지
        $matchedSupplyNos = [];
        foreach ($filteredData as $spRow) {
            $spId = $spRow['id'];
            $spHousingNo = (string) ($spRow['housing_no'] ?? '');
            $spBuildingName = trim($spRow['building_name'] ?? '');
            $spUnits = (int) ($spRow['units'] ?? 0);

            $matched = null;
            if (!empty($spHousingNo) && isset($supplyIndexByNo[$spHousingNo])) {
                $matched = $supplyIndexByNo[$spHousingNo];
            } elseif (!empty($spBuildingName) && isset($supplyIndexByName[$spBuildingName])) {
                $matched = $supplyIndexByName[$spBuildingName];
            }

            if ($matched !== null) {
                $matchedSupplyNos[] = (string) $matched['no'];
                $diff = [];
                // 세대수 불일치 체크
                if ((int) $matched['household_count'] !== $spUnits && (int) $matched['household_count'] > 0) {
                    $diff['units_supply'] = (int) $matched['household_count'];
                }
                // 주택명 불일치 체크
                if (!empty($matched['building_name']) && trim($matched['building_name']) !== $spBuildingName) {
                    $diff['name_supply'] = $matched['building_name'];
                }
                if (!empty($diff)) {
                    $mismatchMap[$spId] = $diff;
                }
            } else {
                // sp_fee에만 있고 공급현황에 없음 — 별도 표시
                $mismatchMap[$spId] = ['supply_missing' => true];
            }
        }

        // 공급현황에만 있고 sp_fee에 없는 항목 추출
        foreach ($supplyData as $sRow) {
            if (!in_array((string) $sRow['no'], $matchedSupplyNos)) {
                $supplyOnlyRows[] = $sRow;
            }
        }
    }



} catch (Exception $e) {
    die("<div style='padding:20px; background:#fee; color:#c00; font-family:sans-serif; border:1px solid #c00;'><strong>오류 발생 (500 에러 원인):</strong><br>" . $e->getMessage() . "</div>");
}

// 미납 회원사 (누적잔액 > 0) 계산 및 추출 로직
$unpaidCompanies = [];
try {
    // 회원사 이메일 정보 가져오기
    $emailMap = [];
    try {
        $emailStmt = $pdo->query("
            SELECT m.member_no, d.email 
            FROM tb_members m
            LEFT JOIN tb_member_details d ON m.id = d.member_id
            WHERE m.member_no IS NOT NULL
        ");
        while ($eRow = $emailStmt->fetch(PDO::FETCH_ASSOC)) {
            $emailMap[$eRow['member_no']] = $eRow['email'];
        }
    } catch (Exception $ex) {
        // Fail-safe
    }

    $allStmt = $pdo->query("SELECT * FROM sp_fee ORDER BY company_no, CASE 
        WHEN status_category = '후원금' THEN 0 
        WHEN status_category = '납부완료' THEN 1 
        WHEN status_category = '신규 청구' THEN 2
        WHEN status_category = '청구완료' THEN 3 
        WHEN status_category = '이사회 조정' THEN 4 
        WHEN status_category = '추후청구' THEN 5 
        ELSE 6 
    END ASC, COALESCE(payment_date, base_date) ASC, id ASC");
    $allRows = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    $companyBalances = [];
    foreach ($allRows as $row) {
        $cNo = $row['company_no'];
        if (!$cNo)
            continue;

        $cName = $row['company_name'];

        if (!isset($companyBalances[$cNo])) {
            $companyBalances[$cNo] = [
                'company_no' => $cNo,
                'company_name' => $cName,
                'email' => $emailMap[$cNo] ?? '',
                'units_total' => 0,
                'billed_total' => 0,
                'paid_total' => 0,
                'offset_total' => 0,
                'donation_total' => 0,
                'running_balance' => 0,
                'project_count' => 0
            ];
        }

        $status = isset($row['status_category']) ? $row['status_category'] : '-';
        $totalFee = isset($row['total_fee']) ? (int) $row['total_fee'] : 0;
        $paidAmount = isset($row['paid_amount']) ? (int) $row['paid_amount'] : 0;
        $offsetAmount = 0;
        $donationAmount = 0;

        $opType = isset($row['operation_type']) ? $row['operation_type'] : '';
        $region = isset($row['region']) ? $row['region'] : '';
        $units = isset($row['units']) ? (int) $row['units'] : 0;

        if ($status === '후원금') {
            $donationAmount = $totalFee > 0 ? $totalFee : $paidAmount;
            $billedAmount = 0;
            $paidAmount = 0;
        } elseif ($status === '이사회 조정' || (isset($row['housing_no']) && (int) $row['housing_no'] >= 50000) || (isset($row['id']) && (int) $row['id'] >= 50000)) {
            $billedAmount = $totalFee;
            $paidAmount = 0;
        } else {
            if ($status === '납부완료' && $totalFee > 0 && $totalFee == $paidAmount) {
                $billedAmount = $paidAmount;
            } else {
                $basePrice = 40000;
                $opMultiplier = (strpos($opType, '시행') !== false) ? 1 : 0.5;
                $isCapital = (in_array($region, ['서울', '경기', '인천']) || strpos($region, '수도권') !== false);
                $regMultiplier = $isCapital ? 1 : 0.5;
                $billedAmount = $units * $basePrice * $opMultiplier * $regMultiplier;
            }

            if ($status === '상계') {
                $offsetAmount = $totalFee > 0 ? $totalFee : $billedAmount;
                $paidAmount = 0;
            }
        }

        if ($status !== '추후청구' && $status !== '종결') {
            $companyBalances[$cNo]['running_balance'] += $billedAmount;
        }
        $companyBalances[$cNo]['running_balance'] -= $paidAmount;
        $companyBalances[$cNo]['running_balance'] -= $donationAmount;

        $companyBalances[$cNo]['units_total'] += $units;
        $companyBalances[$cNo]['billed_total'] += $billedAmount;
        $companyBalances[$cNo]['paid_total'] += $paidAmount;
        $companyBalances[$cNo]['offset_total'] += $offsetAmount;
        $companyBalances[$cNo]['donation_total'] += $donationAmount;
        $companyBalances[$cNo]['project_count']++;
    }

    $totalUnpaidAmount = 0;
    $totalUnpaidCompaniesCount = 0;
    $allCompaniesBalances = [];

    foreach ($companyBalances as $cNo => $data) {
        $allCompaniesBalances[] = $data;
        if ($data['running_balance'] > 0) {
            $unpaidCompanies[] = $data;
            $totalUnpaidAmount += $data['running_balance'];
            $totalUnpaidCompaniesCount++;
        }
    }

    if (!empty($search)) {
        $unpaidCompanies = array_filter($unpaidCompanies, function ($item) use ($search) {
            return (strpos($item['company_name'], $search) !== false) || (strpos($item['company_no'], $search) !== false);
        });
        $allCompaniesBalances = array_filter($allCompaniesBalances, function ($item) use ($search) {
            return (strpos($item['company_name'], $search) !== false) || (strpos($item['company_no'], $search) !== false);
        });
    }
} catch (Exception $ex) {
    // Fail-safe
}

// 테이블 데이터 서머리 계산 (불러온 필터링 데이터 기준)
$totalUnits = 0;
$totalFilteredFee = 0;
$totalFilteredPaid = 0;

foreach ($filteredData as $row) {
    $totalUnits += (int) (isset($row['units']) ? $row['units'] : 0);
    $totalFilteredFee += (int) (isset($row['total_fee']) ? $row['total_fee'] : 0);
    $totalFilteredPaid += (int) (isset($row['paid_amount']) ? $row['paid_amount'] : 0);
}

// ==========================================
// 4. 뷰를 위한 헬퍼 함수 및 설정
// ==========================================
function formatCurrency($num)
{
    return number_format($num);
}

function getStatusColor($status)
{
    switch ($status) {
        case '납부완료':
            return 'bg-green-50 text-green-700 border-green-200';
        case '신규 청구':
            return 'bg-cyan-50 text-cyan-700 border-cyan-200';
        case '청구완료':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case '추후청구':
            return 'bg-yellow-50 text-yellow-700 border-yellow-200';
        case '상계':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case '후원금':
            return 'bg-pink-50 text-pink-700 border-pink-200';
        case '종결':
            return 'bg-gray-50 text-gray-700 border-gray-200';
        case '이사회 조정':
            return 'bg-orange-50 text-orange-700 border-orange-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// KPI 카드 데이터 배열
$kpiCards = [
    ['icon' => 'building', 'label' => '전체 사업 수', 'value' => formatCurrency($totalProjects) . '개', 'color' => 'text-teal-600', 'bg' => 'bg-teal-50', 'link' => null],
    ['icon' => 'trending-up', 'label' => '회비 총액', 'value' => '₩' . formatCurrency($totalFeeAmt), 'color' => 'text-slate-600', 'bg' => 'bg-slate-100', 'link' => null],
    ['icon' => 'credit-card', 'label' => '총 수납액', 'value' => '₩' . formatCurrency($totalRevenue), 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50', 'link' => null],
    ['icon' => 'alert-triangle', 'label' => '⚠️ 미납 회원사 수', 'value' => $totalUnpaidCompaniesCount . '개사', 'color' => 'text-rose-600', 'bg' => 'bg-rose-50', 'link' => '?view=report_status'],
    ['icon' => 'bell', 'label' => '🚨 총 미납 잔액', 'value' => '₩' . formatCurrency($totalUnpaidAmount), 'color' => 'text-red-700', 'bg' => 'bg-red-50', 'link' => '?view=report_status'],
];

// 사이드바 메뉴 배열
$menus = [
    ['id' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => '전체 회원사'],
    ['id' => 'report_summary', 'icon' => 'file-text', 'label' => '회원사별 현황'],
    ['id' => 'report_status', 'icon' => 'pie-chart', 'label' => '미납회원사별 현황'],
    ['id' => 'companies', 'icon' => 'database', 'label' => '[기초데이터]'],
];
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KSHA 특별회비 관리</title>
    <!-- Tailwind CSS (CDN) -->
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <!-- Phosphor Icons (사이드바용) -->
    <script src="/static/vendor/js/phosphor-icons.js"></script>
    <!-- Lucide Icons (CDN) -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-slate-50 font-sans text-slate-800 h-screen overflow-hidden flex">

    <?php
    $view = $_GET['view'] ?? 'dashboard';
    if ($view === 'report_summary') {
        $currentPage = 'sp_fee';
    } elseif ($view === 'report_status') {
        $currentPage = 'sp_fee_unpaid';
    } elseif ($view === 'companies') {
        $currentPage = 'sp_fee_base';
    } else {
        $currentPage = 'sp_fee_total';
    }
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden">

        <!-- Header -->
        <header class="h-20 flex items-center justify-between px-8 bg-white border-b border-slate-200 shrink-0">
            <div>
                <?php
                $title = '전체 회원사 목록';
                foreach ($menus as $m) {
                    if ($m['id'] === $view)
                        $title = $m['label'];
                }
                ?>
                <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($title) ?></h1>
                <div class="flex items-center gap-2 mt-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Data Connected: MySQL
                        socialhousing_db</span>
                </div>
            </div>
            <button
                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors border border-slate-200">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                <span>로그아웃</span>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <?php if ($view === 'dashboard'): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-6 mb-8">
                    <?php foreach ($kpiCards as $card):
                        $hasLink = !empty($card['link']);
                        $cardTag = $hasLink ? 'a' : 'div';
                        $hrefAttr = $hasLink ? 'href="' . htmlspecialchars($card['link']) . '"' : '';
                        $hoverClasses = $hasLink ? 'hover:scale-[1.03] hover:border-rose-200 cursor-pointer shadow-sm hover:shadow-md' : 'hover:shadow-md';
                        ?>
                        <<?= $cardTag ?>         <?= $hrefAttr ?>
                            class="bg-white rounded-xl border border-slate-100 p-5 flex flex-col justify-between group
                            transition-all duration-200 <?= $hoverClasses ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div
                                    class="w-10 h-10 rounded-lg flex items-center justify-center <?= $card['bg'] ?> <?= $card['color'] ?>">
                                    <i data-lucide="<?= $card['icon'] ?>" class="w-5 h-5"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-500 mb-1"><?= $card['label'] ?></p>
                                <h3 class="text-2xl font-bold text-slate-900 truncate"><?= $card['value'] ?></h3>
                            </div>
                        </<?= $cardTag ?>>
                    <?php endforeach; ?>
                </div>

                <!-- Filter & Action Bar (Form) -->
                <form method="GET" action="" id="filterForm"
                    class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex flex-1 gap-4 w-full md:w-auto">
                        <div class="relative w-full md:w-96">
                            <i data-lucide="search"
                                class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 w-[18px] h-[18px]"></i>
                            <input type="text" name="search" placeholder="건물명 또는 회원사번호 검색... (Enter 입력)"
                                value="<?= htmlspecialchars($search) ?>"
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] focus:bg-white transition-all text-sm" />
                        </div>
                        <div class="relative w-full md:w-64">
                            <i data-lucide="filter"
                                class="absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 w-[18px] h-[18px]"></i>
                            <select name="company" id="filter_company"
                                onchange="document.getElementById('filterForm').submit();"
                                class="w-full pl-10 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] focus:bg-white appearance-none text-sm cursor-pointer">
                                <option value="모든 회원사">모든 회원사</option>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= htmlspecialchars($c['company_no']) ?>" <?= $selectedCompany === (string) $c['company_no'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <button type="button" onclick="openEditModal({}, true)"
                            class="flex-1 md:flex-none flex items-center justify-center gap-2 px-5 py-2.5 bg-[#0f766e] hover:bg-[#0d6962] text-white rounded-lg font-medium text-sm transition-colors shadow-sm cursor-pointer">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span>신규 사업 등록</span>
                        </button>
                    </div>
                </form>

                <!-- Data Table (전체 회원사 목록) -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- Table Summary Header -->
                    <div class="bg-[#0f766e] text-white px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                        <div class="text-sm font-medium opacity-90 flex items-center gap-1.5">
                            <i data-lucide="building" class="w-4 h-4"></i> 전체 회원사 특별회비 현황 목록 (후원금, 이사회 조정 완벽 반영)
                        </div>
                        <div class="text-xs text-teal-100">
                            총 <span class="font-bold text-base text-yellow-300"><?= count($allCompaniesBalances) ?></span>개의
                            회원사 데이터가 등록되어 있습니다.
                        </div>
                    </div>

                    <div class="overflow-x-auto max-h-[600px] overflow-y-auto relative">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead class="sticky top-0 bg-slate-50 z-10 shadow-[inset_0_-1px_0_rgba(0,0,0,0.1)]">
                                <tr
                                    class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    <th class="px-6 py-4">회원사 정보</th>
                                    <th class="px-6 py-4 text-center">관할 사업수</th>
                                    <th class="px-6 py-4 text-center">총 세대수</th>
                                    <th class="px-6 py-4 text-right">청구액 합계</th>
                                    <th class="px-6 py-4 text-right">수납액 합계</th>
                                    <th class="px-6 py-4 text-right">상계액 합계</th>
                                    <th class="px-6 py-4 text-right">후원금 합계</th>
                                    <th class="px-6 py-4 text-right">🔔 실시간 잔액</th>
                                    <th class="px-6 py-4 text-center">동작</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (count($allCompaniesBalances) > 0): ?>
                                    <?php foreach ($allCompaniesBalances as $c):
                                        $balColor = ($c['running_balance'] > 0)
                                            ? 'text-red-600 bg-red-50/20 font-extrabold'
                                            : (($c['running_balance'] < 0)
                                                ? 'text-pink-600 bg-pink-50/20 font-bold'
                                                : 'text-slate-600 font-medium');
                                        ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors group">
                                            <td class="px-6 py-4">
                                                <a href="?view=report_summary&company=<?= urlencode($c['company_no']) ?>"
                                                    class="block font-bold text-slate-900 group-hover:text-[#0f766e] transition-colors">
                                                    <?= htmlspecialchars($c['company_name'] ? $c['company_name'] : '미지정') ?>
                                                </a>
                                                <div class="text-xs text-slate-500 mt-1">회원번호:
                                                    <?= htmlspecialchars($c['company_no']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center font-medium text-slate-700">
                                                <?= number_format($c['project_count']) ?>개
                                            </td>
                                            <td class="px-6 py-4 text-center font-medium text-slate-700">
                                                <?= number_format($c['units_total']) ?>세대
                                            </td>
                                            <td class="px-6 py-4 text-right font-semibold text-slate-900">
                                                ₩<?= formatCurrency($c['billed_total']) ?></td>
                                            <td class="px-6 py-4 text-right text-emerald-600 font-medium">
                                                ₩<?= formatCurrency($c['paid_total']) ?></td>
                                            <td class="px-6 py-4 text-right text-orange-500 font-medium">
                                                ₩<?= formatCurrency($c['offset_total']) ?></td>
                                            <td class="px-6 py-4 text-right text-pink-500 font-medium">
                                                ₩<?= formatCurrency($c['donation_total']) ?></td>
                                            <td class="px-6 py-4 text-right <?= $balColor ?>">
                                                ₩<?= formatCurrency($c['running_balance']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <a href="?view=report_summary&company=<?= urlencode($c['company_no']) ?>"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 text-teal-700 hover:bg-teal-100 rounded-lg text-xs font-semibold transition-colors border border-teal-100 shadow-sm cursor-pointer">
                                                    보고서 보기 <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-6 py-12 text-center text-slate-500 font-medium">
                                            등록된 회원사 정보가 존재하지 않습니다.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($view === 'companies'): ?>
                <!-- 회원사별 UI -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto max-h-[600px] overflow-y-auto relative">
                        <table class="w-full text-left border-collapse text-[12px] whitespace-nowrap">
                            <thead class="sticky top-0 bg-slate-50 z-10 shadow-[inset_0_-1px_0_rgba(0,0,0,0.1)]">
                                <tr
                                    class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    <th class="px-6 py-4">회원사명</th>
                                    <th class="px-6 py-4 text-center">관할 사업수</th>
                                    <th class="px-6 py-4 text-center">총 세대수</th>
                                    <th class="px-6 py-4 text-right">총 회비</th>
                                    <th class="px-6 py-4 text-right">수납액</th>
                                    <th class="px-6 py-4 text-right">미수납액</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!empty($companyStats)): ?>
                                    <?php foreach ($companyStats as $row):
                                        $compTotal = isset($row['sum_total_fee']) ? $row['sum_total_fee'] : 0;
                                        $compPaid = isset($row['sum_paid_amount']) ? $row['sum_paid_amount'] : 0;
                                        $compUnpaid = $compTotal - $compPaid;
                                        ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-6 py-4 font-bold">
                                                <a href="?view=report_summary&company=<?= urlencode($row['company_no']) ?>"
                                                    class="text-[#0f766e] hover:underline">
                                                    <?= htmlspecialchars($row['company_name'] ? $row['company_name'] : '미지정') ?>
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 text-center font-medium text-slate-700">
                                                <?= number_format($row['project_count']) ?>개
                                            </td>
                                            <td class="px-6 py-4 text-center font-medium text-slate-700">
                                                <?= number_format($row['total_units']) ?>세대
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-slate-900">
                                                ₩<?= formatCurrency($compTotal) ?></td>
                                            <td class="px-6 py-4 text-right text-emerald-600 font-medium">
                                                ₩<?= formatCurrency($compPaid) ?></td>
                                            <td class="px-6 py-4 text-right text-orange-600 font-medium">
                                                ₩<?= formatCurrency($compUnpaid) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">데이터가 없습니다.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($view === 'report_summary'): ?>
                <!-- 회원사별 현황 (총괄 보고서) -->
                <div class="mb-4 flex items-center justify-between">
                    <?php
                    $noticeBilledTotal = 0;
                    $noticePaidTotal = 0;
                    $noticeOffsetTotal = 0;
                    $noticeDonationTotal = 0;
                    $noticeRunningBalance = 0;
                    $hasNewBilling = false;

                    foreach ($filteredData as $row) {
                        $status = isset($row['status_category']) ? $row['status_category'] : '-';
                        if ($status === '신규 청구') {
                            $hasNewBilling = true;
                        }
                        $totalFee = isset($row['total_fee']) ? (int) $row['total_fee'] : 0;
                        $paidAmount = isset($row['paid_amount']) ? (int) $row['paid_amount'] : 0;
                        $offsetAmount = 0;
                        $donationAmount = 0;

                        $opType = isset($row['operation_type']) ? $row['operation_type'] : '';
                        $region = isset($row['region']) ? $row['region'] : '';
                        $units = isset($row['units']) ? (int) $row['units'] : 0;

                        if ($status === '후원금') {
                            $donationAmount = $totalFee > 0 ? $totalFee : $paidAmount;
                            $billedAmount = 0;
                            $paidAmount = 0;
                        } elseif ($status === '이사회 조정' || (isset($row['housing_no']) && (int) $row['housing_no'] >= 50000) || (isset($row['id']) && (int) $row['id'] >= 50000)) {
                            $billedAmount = $totalFee;
                            $paidAmount = 0;
                        } else {
                            if ($status === '납부완료' && $totalFee > 0 && $totalFee == $paidAmount) {
                                $billedAmount = $paidAmount;
                            } else {
                                $basePrice = 40000;
                                $opMultiplier = (strpos($opType, '시행') !== false) ? 1 : 0.5;
                                $isCapital = (in_array($region, ['서울', '경기', '인천']) || strpos($region, '수도권') !== false);
                                $regMultiplier = $isCapital ? 1 : 0.5;
                                $billedAmount = $units * $basePrice * $opMultiplier * $regMultiplier;
                            }

                            if ($status === '상계') {
                                $offsetAmount = $totalFee > 0 ? $totalFee : $billedAmount;
                                $paidAmount = 0;
                            }
                        }

                        if ($status !== '추후청구' && $status !== '종결') {
                            $noticeRunningBalance += $billedAmount;
                        }
                        $noticeRunningBalance -= $paidAmount;
                        $noticeRunningBalance -= $donationAmount;

                        $noticeBilledTotal += $billedAmount;
                        $noticePaidTotal += $paidAmount;
                        $noticeOffsetTotal += $offsetAmount;
                        $noticeDonationTotal += $donationAmount;
                    }

                    $selectedCompanyName = $selectedCompany;
                    if ($selectedCompany !== '모든 회원사') {
                        foreach ($companies as $c) {
                            if ((string) $c['company_no'] === $selectedCompany) {
                                $selectedCompanyName = $c['company_name'];
                                break;
                            }
                        }
                    }
                    ?>
                    <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <?php if ($selectedCompany !== '모든 회원사'): ?>
                            <button type="button" onclick="openNoticeModal()"
                                class="hover:text-teal-700 transition-colors group flex items-center gap-2 cursor-pointer text-left focus:outline-none">
                                <span><?= htmlspecialchars($selectedCompanyName) ?> 현황 상세</span>
                                <span
                                    class="text-xs bg-teal-50 text-teal-600 font-medium px-2.5 py-1 rounded-full group-hover:bg-teal-100 transition-colors flex items-center gap-1 border border-teal-100">
                                    <i data-lucide="mail" class="w-3 h-3"></i> 안내문 출력
                                </span>
                            </button>
                        <?php else: ?>
                            <span>전체 현황 상세</span>
                        <?php endif; ?>
                    </h2>
                    <div class="flex items-center gap-4">
                        <button type="button" onclick="openEditModal({}, true)"
                            class="flex items-center justify-center gap-2 px-4 py-2 bg-[#0f766e] hover:bg-[#0d6962] text-white rounded-lg font-medium text-sm transition-colors shadow-sm cursor-pointer">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span>신규 사업 등록</span>
                        </button>
                        <a href="?view=dashboard" class="text-sm text-[#0f766e] hover:underline">← 전체 회원사 목록으로</a>
                    </div>
                </div>

                <?php if ($selectedCompany !== '모든 회원사'): ?>
                    <!-- ====================================================
                     📊 회원사별 상단 프리미엄 대시보드
                     ==================================================== -->

                    <!-- 1. 요약 카드 그리드 -->
                    <?php
                    $supplyTotalProjects = count($supplyData);
                    $supplyTotalUnits = array_sum(array_column($supplyData, 'household_count'));
                    $spFeeProjects = count($filteredData);
                    $spFeeUnits = 0;
                    foreach ($filteredData as $r) {
                        $spFeeUnits += (int) ($r['units'] ?? 0);
                    }
                    $mismatchCount = count(array_filter($mismatchMap, fn($v) => !isset($v['supply_missing']) && (isset($v['units_supply']) || isset($v['name_supply']))));
                    $supplyOnlyCount = count($supplyOnlyRows);
                    $spOnlyCount = count(array_filter($mismatchMap, fn($v) => isset($v['supply_missing'])));
                    $totalIssues = $mismatchCount + $supplyOnlyCount + $spOnlyCount;
                    $integrityOk = ($totalIssues === 0);
                    ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
                        <!-- 공급현황 카드 -->
                        <div
                            class="bg-gradient-to-br from-blue-600 to-blue-800 text-white rounded-2xl p-5 shadow-lg shadow-blue-900/20">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                                    <i data-lucide="building-2" class="w-5 h-5"></i>
                                </div>
                                <span class="text-xs font-semibold bg-white/20 px-2 py-1 rounded-full">공급현황 DB</span>
                            </div>
                            <div class="text-3xl font-extrabold tracking-tight mb-0.5">
                                <?= number_format($supplyTotalProjects) ?>건
                            </div>
                            <div class="text-sm text-blue-100 font-medium"><?= number_format($supplyTotalUnits) ?>세대 공급</div>
                            <div class="mt-3 pt-3 border-t border-white/20 text-xs text-blue-200">tb_social_housing_supply 기준
                            </div>
                        </div>

                        <!-- 특별회비 현황 카드 -->
                        <div
                            class="bg-gradient-to-br from-emerald-600 to-teal-800 text-white rounded-2xl p-5 shadow-lg shadow-teal-900/20">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                                    <i data-lucide="coins" class="w-5 h-5"></i>
                                </div>
                                <span class="text-xs font-semibold bg-white/20 px-2 py-1 rounded-full">특별회비</span>
                            </div>
                            <div class="text-3xl font-extrabold tracking-tight mb-0.5"><?= number_format($spFeeProjects) ?>건
                            </div>
                            <div class="text-sm text-emerald-100 font-medium"><?= number_format($spFeeUnits) ?>세대 등록</div>
                            <div class="mt-3 pt-3 border-t border-white/20 text-xs text-emerald-200 flex justify-between">
                                <span>청구 ₩<?= formatCurrency($noticeBilledTotal) ?></span>
                                <span>납부 ₩<?= formatCurrency($noticePaidTotal) ?></span>
                            </div>
                        </div>

                        <!-- 미납 잔액 카드 -->
                        <div
                            class="<?= $noticeRunningBalance > 0 ? 'bg-gradient-to-br from-rose-600 to-rose-800' : 'bg-gradient-to-br from-slate-600 to-slate-800' ?> text-white rounded-2xl p-5 shadow-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                                    <i data-lucide="<?= $noticeRunningBalance > 0 ? 'alert-circle' : 'check-circle' ?>"
                                        class="w-5 h-5"></i>
                                </div>
                                <span class="text-xs font-semibold bg-white/20 px-2 py-1 rounded-full">잔액</span>
                            </div>
                            <div class="text-2xl font-extrabold tracking-tight mb-0.5">
                                ₩<?= formatCurrency(abs($noticeRunningBalance)) ?></div>
                            <div class="text-sm text-white/80 font-medium">
                                <?= $noticeRunningBalance > 0 ? '🔔 미납 잔액' : '✅ 완납 (초과납부)' ?>
                            </div>
                            <div class="mt-3 pt-3 border-t border-white/20 text-xs text-white/60">후원금
                                ₩<?= formatCurrency($noticeDonationTotal) ?> 포함</div>
                        </div>

                        <!-- 데이터 정합성 카드 -->
                        <div
                            class="<?= $integrityOk ? 'bg-gradient-to-br from-violet-600 to-purple-800' : 'bg-gradient-to-br from-amber-500 to-orange-700' ?> text-white rounded-2xl p-5 shadow-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                                    <i data-lucide="<?= $integrityOk ? 'shield-check' : 'shield-alert' ?>" class="w-5 h-5"></i>
                                </div>
                                <span class="text-xs font-semibold bg-white/20 px-2 py-1 rounded-full">정합성</span>
                            </div>
                            <?php if ($integrityOk): ?>
                                <div class="text-2xl font-extrabold tracking-tight mb-0.5">✅ 완벽 일치</div>
                                <div class="text-sm text-white/80">공급현황 ↔ 특별회비 일치</div>
                            <?php else: ?>
                                <div class="text-2xl font-extrabold tracking-tight mb-0.5"><?= $totalIssues ?>건 불일치</div>
                                <div class="text-sm text-white/80">
                                    <?php if ($supplyOnlyCount > 0): ?><span
                                            class="inline-block bg-white/20 rounded px-1.5 py-0.5 text-xs mr-1">미등록
                                            <?= $supplyOnlyCount ?>건</span><?php endif; ?>
                                    <?php if ($mismatchCount > 0): ?><span
                                            class="inline-block bg-white/20 rounded px-1.5 py-0.5 text-xs mr-1">정보차이
                                            <?= $mismatchCount ?>건</span><?php endif; ?>
                                    <?php if ($spOnlyCount > 0): ?><span
                                            class="inline-block bg-white/20 rounded px-1.5 py-0.5 text-xs">공급누락
                                            <?= $spOnlyCount ?>건</span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3 pt-3 border-t border-white/20 text-xs text-white/60">공급
                                <?= $supplyTotalProjects ?>건 vs 회비 <?= $spFeeProjects ?>건
                            </div>
                        </div>
                    </div>

                    <!-- 2. 특별회비 산정 흐름도 -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 mb-5">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="calculator" class="w-5 h-5 text-teal-600"></i>
                            <h3 class="font-bold text-slate-800 text-sm">특별회비 산정 흐름도</h3>
                            <span class="text-xs text-slate-400 ml-auto">기준: 총회 의결 산정 방식</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Step 1 -->
                            <div class="flex-1 min-w-[120px] bg-blue-50 border border-blue-200 rounded-xl p-3 text-center">
                                <div class="text-xs font-bold text-blue-500 uppercase tracking-wider mb-1">기준 단가</div>
                                <div class="text-xl font-extrabold text-blue-700">₩40,000</div>
                                <div class="text-[11px] text-blue-400 mt-1">세대당</div>
                            </div>
                            <div class="text-slate-300 text-2xl font-light">×</div>
                            <!-- Step 2 -->
                            <div
                                class="flex-1 min-w-[140px] bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-center">
                                <div class="text-xs font-bold text-emerald-500 uppercase tracking-wider mb-1">운영유형 비율</div>
                                <div class="flex justify-center gap-3 mt-1">
                                    <div>
                                        <div class="text-lg font-extrabold text-emerald-700">100%</div>
                                        <div class="text-[10px] text-emerald-500">시행+운영</div>
                                    </div>
                                    <div class="text-emerald-300">|</div>
                                    <div>
                                        <div class="text-lg font-extrabold text-emerald-600">50%</div>
                                        <div class="text-[10px] text-emerald-400">운영전담</div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-slate-300 text-2xl font-light">×</div>
                            <!-- Step 3 -->
                            <div class="flex-1 min-w-[140px] bg-violet-50 border border-violet-200 rounded-xl p-3 text-center">
                                <div class="text-xs font-bold text-violet-500 uppercase tracking-wider mb-1">지역 비율</div>
                                <div class="flex justify-center gap-3 mt-1">
                                    <div>
                                        <div class="text-lg font-extrabold text-violet-700">100%</div>
                                        <div class="text-[10px] text-violet-500">수도권</div>
                                    </div>
                                    <div class="text-violet-300">|</div>
                                    <div>
                                        <div class="text-lg font-extrabold text-violet-600">50%</div>
                                        <div class="text-[10px] text-violet-400">비수도권</div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-slate-300 text-2xl font-light">×</div>
                            <!-- Step 4 -->
                            <div class="flex-1 min-w-[100px] bg-orange-50 border border-orange-200 rounded-xl p-3 text-center">
                                <div class="text-xs font-bold text-orange-500 uppercase tracking-wider mb-1">공급 세대수</div>
                                <div class="text-xl font-extrabold text-orange-700">N세대</div>
                                <div class="text-[11px] text-orange-400 mt-1">sp_fee.units</div>
                            </div>
                            <div class="text-slate-300 text-2xl font-light">=</div>
                            <!-- Result -->
                            <div
                                class="flex-1 min-w-[140px] bg-gradient-to-br from-teal-600 to-teal-800 text-white rounded-xl p-3 text-center shadow-md shadow-teal-900/20">
                                <div class="text-xs font-bold text-teal-200 uppercase tracking-wider mb-1">산정 청구금액</div>
                                <div class="text-xl font-extrabold">₩ 산출</div>
                                <div class="text-[11px] text-teal-300 mt-1">상태별 조정 반영</div>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                            <span
                                class="bg-green-50 text-green-700 border border-green-200 px-2 py-1 rounded-full font-medium">납부완료
                                → total_fee 직접 사용</span>
                            <span
                                class="bg-orange-50 text-orange-700 border border-orange-200 px-2 py-1 rounded-full font-medium">상계
                                → 청구액 전액 차감</span>
                            <span class="bg-pink-50 text-pink-700 border border-pink-200 px-2 py-1 rounded-full font-medium">후원금
                                → 잔액에서 차감</span>
                            <span
                                class="bg-slate-50 text-slate-700 border border-slate-200 px-2 py-1 rounded-full font-medium">추후청구/종결
                                → 잔액 반영 제외</span>
                            <span
                                class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-1 rounded-full font-medium">이사회
                                조정 → total_fee 직접 반영</span>
                        </div>
                    </div>

                    <!-- 3. 공급현황 vs 특별회비 비교 테이블 (불일치 및 누락 있을 때만) -->
                    <?php if (!empty($supplyOnlyRows) || !empty(array_filter($mismatchMap, fn($v) => isset($v['units_supply']) || isset($v['name_supply'])))): ?>
                        <div class="bg-amber-50 border border-amber-200 rounded-2xl shadow-sm mb-5 overflow-hidden">
                            <div class="bg-amber-500 text-white px-5 py-3 flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                <span class="font-bold text-sm">공급현황 ↔ 특별회비 데이터 불일치 현황</span>
                                <span class="ml-auto text-xs bg-white/20 px-2 py-0.5 rounded-full"><?= $totalIssues ?>건 확인 필요</span>
                            </div>
                            <div class="p-4 space-y-3">
                                <?php if (!empty($supplyOnlyRows)): ?>
                                    <div>
                                        <div class="text-xs font-bold text-amber-700 mb-2 flex items-center gap-1">
                                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                                            공급현황에만 있고 특별회비 미등록 (<?= count($supplyOnlyRows) ?>건) — 즉시 등록 가능
                                        </div>
                                        <div class="space-y-2">
                                            <?php foreach ($supplyOnlyRows as $sr):
                                                $si = $sr['location_si'] ?? '';
                                                $guessUnits = (int) ($sr['household_count'] ?? 0);
                                                $guessType = $sr['supply_type'] ?? '';
                                                $guessOp = $sr['supply_type2'] ?? '';
                                                $guessOpMult = (strpos($guessOp, '시행') !== false) ? 1 : 0.5;
                                                $capitalSi = ['서울특별시', '서울', '경기도', '경기', '인천광역시', '인천'];
                                                $guessIsCapital = in_array($si, $capitalSi);
                                                $guessRegMult = $guessIsCapital ? 1 : 0.5;
                                                $guessFee = $guessUnits * 40000 * $guessOpMult * $guessRegMult;
                                                $guessRegion = preg_replace('/(특별시|광역시|특별자치시|도)$/', '', $si);
                                                $preData = json_encode([
                                                    'company_no' => $selectedCompany,
                                                    'company_name' => $selectedCompanyName,
                                                    'housing_no' => (int) ($sr['no'] ?? ''),
                                                    'building_name' => $sr['building_name'] ?? '',
                                                    'units' => $guessUnits,
                                                    'region' => $guessRegion,
                                                    'project_type' => $guessType,
                                                    'operation_type' => $guessOp,
                                                    'total_fee' => 0,
                                                    'paid_amount' => 0,
                                                    'status_category' => '추후청구',
                                                    'base_date' => '',
                                                    'payment_date' => '',
                                                ], JSON_UNESCAPED_UNICODE);
                                                ?>
                                                <div
                                                    class="bg-white border border-amber-200 rounded-xl p-3 flex flex-wrap items-center gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-semibold text-slate-800 text-sm truncate">
                                                            <?= htmlspecialchars($sr['building_name'] ?? '-') ?>
                                                        </div>
                                                        <div class="text-xs text-slate-500 flex flex-wrap gap-2 mt-0.5">
                                                            <span>주택번호: <?= htmlspecialchars($sr['no'] ?? '-') ?></span>
                                                            <span>세대수: <b
                                                                    class="text-slate-700"><?= number_format($guessUnits) ?>세대</b></span>
                                                            <span><?= htmlspecialchars($si) ?>
                                                                <?= htmlspecialchars($sr['location_gu'] ?? '') ?></span>
                                                            <?php if ($guessType): ?><span
                                                                    class="bg-slate-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($guessType) ?></span><?php endif; ?>
                                                            <?php if ($guessOp): ?><span
                                                                    class="bg-slate-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($guessOp) ?></span><?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-xs text-slate-500">예상 청구금액</div>
                                                        <div class="font-bold text-amber-700 text-sm">₩<?= formatCurrency($guessFee) ?>
                                                        </div>
                                                        <div class="text-[10px] text-slate-400">(<?= $guessUnits ?>세대 × 40,000 ×
                                                            <?= ($guessOpMult * 100) ?>% × <?= ($guessRegMult * 100) ?>%)
                                                        </div>
                                                    </div>
                                                    <button type="button"
                                                        onclick='openEditModal(<?= htmlspecialchars($preData, ENT_QUOTES, "UTF-8") ?>, true)'
                                                        class="flex items-center gap-1.5 px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition-colors shadow-sm whitespace-nowrap cursor-pointer">
                                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> 특별회비 자동 등록
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $diffRows = array_filter($mismatchMap, fn($v) => isset($v['units_supply']) || isset($v['name_supply']));
                                if (!empty($diffRows)):
                                    ?>
                                    <div>
                                        <div class="text-xs font-bold text-rose-700 mb-2 flex items-center gap-1 mt-2">
                                            <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                                            특별회비 ↔ 공급현황 정보 불일치 (<?= count($diffRows) ?>건)
                                        </div>
                                        <div class="space-y-2">
                                            <?php foreach ($filteredData as $spRow):
                                                $spId = $spRow['id'];
                                                if (!isset($mismatchMap[$spId]))
                                                    continue;
                                                $mInfo = $mismatchMap[$spId];
                                                if (!isset($mInfo['units_supply']) && !isset($mInfo['name_supply']))
                                                    continue;
                                                ?>
                                                <div
                                                    class="bg-white border border-rose-200 rounded-xl p-3 flex flex-wrap items-center gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-semibold text-slate-800 text-sm">
                                                            <?= htmlspecialchars($spRow['building_name'] ?? '-') ?>
                                                            <span class="text-xs text-slate-400 ml-1">주택번호:
                                                                <?= htmlspecialchars($spRow['housing_no'] ?? '-') ?></span>
                                                        </div>
                                                        <div class="text-xs mt-1 space-y-0.5">
                                                            <?php if (isset($mInfo['units_supply'])): ?>
                                                                <div class="flex items-center gap-2">
                                                                    <span class="text-slate-500">세대수:</span>
                                                                    <span class="font-bold text-rose-600"><?= (int) ($spRow['units'] ?? 0) ?>세대
                                                                        (특별회비)</span>
                                                                    <span class="text-slate-400">vs</span>
                                                                    <span class="font-bold text-blue-600"><?= $mInfo['units_supply'] ?>세대
                                                                        (공급현황)</span>
                                                                    <span
                                                                        class="bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded text-[10px] font-bold">⚠
                                                                        불일치</span>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (isset($mInfo['name_supply'])): ?>
                                                                <div class="flex items-center gap-2">
                                                                    <span class="text-slate-500">주택명:</span>
                                                                    <span
                                                                        class="text-rose-600 font-medium">"<?= htmlspecialchars($spRow['building_name'] ?? '') ?>"</span>
                                                                    <span class="text-slate-400">vs</span>
                                                                    <span
                                                                        class="text-blue-600 font-medium">"<?= htmlspecialchars($mInfo['name_supply']) ?>"</span>
                                                                    <span
                                                                        class="bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded text-[10px] font-bold">명칭
                                                                        차이</span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <button type="button"
                                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($spRow, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)"
                                                        class="flex items-center gap-1.5 px-3 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-xs font-bold transition-colors shadow-sm whitespace-nowrap cursor-pointer">
                                                        <i data-lucide="edit" class="w-3.5 h-3.5"></i> 특별회비 수정
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; // end if mismatch ?>
                <?php endif; // end if selectedCompany ?>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto max-h-[600px] overflow-y-auto relative">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead class="sticky top-0 bg-slate-50 z-10 shadow-[inset_0_-1px_0_rgba(0,0,0,0.1)]">
                                <tr
                                    class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                    <th class="px-4 py-3 text-center w-12"><input type="checkbox" id="selectAllCheckbox"
                                            class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer">
                                    </th>
                                    <th class="px-4 py-3">주택 정보</th>
                                    <th class="px-4 py-3 text-center">세대수</th>
                                    <th class="px-4 py-3">유형/운영</th>
                                    <th class="px-4 py-3 text-center">광역</th>
                                    <th class="px-4 py-3 text-center">청구일자</th>
                                    <th class="px-4 py-3 text-right">청구금액</th>
                                    <th class="px-4 py-3 text-center">상태</th>
                                    <th class="px-4 py-3 text-center">납부일자</th>
                                    <th class="px-4 py-3 text-right">납부금액</th>
                                    <th class="px-4 py-3 text-right">상계</th>
                                    <th class="px-4 py-3 text-right">후원금액</th>
                                    <th class="px-4 py-3 text-right font-bold">누적 잔액</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                <?php if (count($filteredData) > 0): ?>
                                    <?php
                                    $running_balance = 0; // 누적 잔액(미수금) 초기화
                                    foreach ($filteredData as $row):
                                        $status = isset($row['status_category']) ? $row['status_category'] : '-';
                                        $totalFee = isset($row['total_fee']) ? (int) $row['total_fee'] : 0;
                                        $paidAmount = isset($row['paid_amount']) ? (int) $row['paid_amount'] : 0;
                                        $offsetAmount = isset($row['offset_amount']) ? (int) $row['offset_amount'] : 0;
                                        $donationAmount = isset($row['donation_amount']) ? (int) $row['donation_amount'] : 0;

                                        // 화면 출력을 위한 공통 변수 (조건문 외부에서 초기화)
                                        $opType = isset($row['operation_type']) ? $row['operation_type'] : '';
                                        $region = isset($row['region']) ? $row['region'] : '';
                                        $units = isset($row['units']) ? (int) $row['units'] : 0;

                                        // 1. 후원금, 이사회 조정 등 특수 거래 행 처리
                                        if ($status === '후원금') {
                                            $donationAmount = $totalFee > 0 ? $totalFee : $paidAmount;
                                            $billedAmount = 0;
                                            $paidAmount = 0; // 중복 차감 방지
                                            $offsetAmount = 0;
                                        } elseif ($status === '이사회 조정' || (isset($row['housing_no']) && (int) $row['housing_no'] >= 50000) || (isset($row['id']) && (int) $row['id'] >= 50000)) {
                                            // 이사회 조정 감면 거래 내역입니다.
                                            // 입력된 청구금액(total_fee)을 그대로 청구금액으로 반영합니다 (마이너스 입력 시 감면 처리됨).
                                            $billedAmount = $totalFee;
                                            $offsetAmount = 0; // 청구금액 직접 마이너스로 정산하므로 상계는 0
                                            $paidAmount = 0; // 중복 차감 방지
                                            $status = '이사회 조정'; // 화면 표시 상태 강제 통일
                                        } else {
                                            // 2. 일반 및 상계 거래 행 처리 (납부완료 예외 처리)
                                            if ($status === '납부완료' && $totalFee > 0 && $totalFee == $paidAmount) {
                                                $billedAmount = $paidAmount;
                                            } else {
                                                // 3. 신규/추후 청구 및 상계에 대한 수식 계산 (단가 4만, 시행+운영=1배, 운영=0.5배, 수도권=1배, 비수도권=0.5배)
                                                $basePrice = 40000;
                                                $opMultiplier = (strpos($opType, '시행') !== false) ? 1 : 0.5;
                                                $isCapital = (in_array($region, ['서울', '경기', '인천']) || strpos($region, '수도권') !== false);
                                                $regMultiplier = $isCapital ? 1 : 0.5;
                                                $billedAmount = $units * $basePrice * $opMultiplier * $regMultiplier;
                                            }

                                            // 상계 처리인 경우 상계 금액을 설정하여 청구금액과 100% 상쇄되도록 함
                                            if ($status === '상계') {
                                                $offsetAmount = $totalFee > 0 ? $totalFee : $billedAmount;
                                                $paidAmount = 0; // 중복 차감 방지
                                            } else {
                                                $offsetAmount = 0;
                                            }
                                        }

                                        // 4. 누적 잔액(Running Balance) 계산 (+청구액 -납부액 -상계 -후원금)
                                        if ($status !== '추후청구' && $status !== '종결') {
                                            $running_balance += $billedAmount;
                                        }
                                        $running_balance -= $paidAmount;
                                        // $running_balance -= $offsetAmount;
                                        $running_balance -= $donationAmount;

                                        // 5. 날짜 필드 매핑
                                        $billingDate = isset($row['base_date']) ? $row['base_date'] : '-';
                                        $paymentDate = isset($row['payment_date']) ? $row['payment_date'] : '-';

                                        // 잔액이 남아있으면(>0) 빨간색, 초과납부/완납(<=0)이면 기본색
                                        $balanceClass = ($running_balance > 0) ? 'text-red-600' : 'text-slate-900';
                                        ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-4 py-3 text-center w-12"><input type="checkbox"
                                                    class="row-checkbox rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer"
                                                    value="<?= htmlspecialchars($row['id']) ?>"></td>
                                            <td class="px-4 py-3 font-medium text-slate-900">
                                                <a href="javascript:void(0)"
                                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)"
                                                    class="text-[#0f766e] hover:underline cursor-pointer block">
                                                    <?= htmlspecialchars(isset($row['housing_no']) ? $row['housing_no'] : '-') ?>
                                                    <div class="text-xs text-slate-500 font-normal">
                                                        <?= htmlspecialchars(isset($row['building_name']) ? $row['building_name'] : '') ?>
                                                    </div>
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-center"><?= $units ?></td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <?= htmlspecialchars(isset($row['project_type']) ? $row['project_type'] : '') ?><br>
                                                <span class="text-xs text-slate-400"><?= htmlspecialchars($opType) ?></span>
                                            </td>
                                            <td class="px-4 py-3 text-center"><?= htmlspecialchars($region) ?></td>
                                            <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars($billingDate) ?>
                                            </td>
                                            <td class="px-4 py-3 text-right font-medium">
                                                <a href="javascript:void(0)"
                                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)"
                                                    class="text-slate-800 hover:text-[#0f766e] hover:underline cursor-pointer block font-semibold">
                                                    <?= $billedAmount < 0 ? '-' : '' ?>₩<?= formatCurrency(abs($billedAmount)) ?>
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span
                                                    class="inline-flex px-2 py-1 rounded text-xs font-medium border <?= getStatusColor($status) ?>">
                                                    <?= htmlspecialchars($status) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center text-slate-500"><?= htmlspecialchars($paymentDate) ?>
                                            </td>
                                            <td class="px-4 py-3 text-right text-emerald-600 font-medium">
                                                ₩<?= formatCurrency($paidAmount) ?></td>
                                            <td class="px-4 py-3 text-right text-orange-500 font-medium">
                                                ₩<?= formatCurrency($offsetAmount) ?></td>
                                            <td class="px-4 py-3 text-right text-pink-500 font-medium">
                                                ₩<?= formatCurrency($donationAmount) ?></td>
                                            <td class="px-4 py-3 text-right font-bold <?= $balanceClass ?>">
                                                ₩<?= formatCurrency($running_balance) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="13" class="px-6 py-12 text-center text-slate-500">해당 회원사의 주택 데이터가 없습니다.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($view === 'report_status'): ?>
                <!-- 미납회원사별 현황 및 추후청구 주택 명단 -->
                <div class="space-y-8">
                    <!-- 1. 미납 회원사 현황 (누적잔액 > ₩0) -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <!-- Table Summary Header -->
                        <div class="bg-[#0f766e] text-white px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                            <div class="text-sm font-medium opacity-90 flex items-center gap-1.5">
                                <i data-lucide="alert-circle" class="w-4 h-4"></i> 미납 회원사 현황 (누적잔액 > ₩0)
                            </div>
                            <div class="text-xs text-teal-100">
                                총 <span class="font-bold text-base text-yellow-300"><?= count($unpaidCompanies) ?></span>개의
                                회원사가 현재 특별회비 미납 상태입니다.
                            </div>
                        </div>

                        <div class="overflow-x-auto max-h-[400px] overflow-y-auto relative">
                            <table class="w-full text-left border-collapse whitespace-nowrap">
                                <thead class="sticky top-0 bg-slate-50 z-10 shadow-[inset_0_-1px_0_rgba(0,0,0,0.1)]">
                                    <tr
                                        class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        <th class="px-6 py-4">회원사 정보</th>
                                        <th class="px-6 py-4 text-center">관할 사업수</th>
                                        <th class="px-6 py-4 text-center">총 세대수</th>
                                        <th class="px-6 py-4 text-right">청구액 합계</th>
                                        <th class="px-6 py-4 text-right">수납액 합계</th>
                                        <th class="px-6 py-4 text-right">상계액 합계</th>
                                        <th class="px-6 py-4 text-right">후원금 합계</th>
                                        <th class="px-6 py-4 text-right text-red-600">🔔 미납 잔액</th>
                                        <th class="px-6 py-4 text-center">동작</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm">
                                    <?php if (count($unpaidCompanies) > 0): ?>
                                        <?php foreach ($unpaidCompanies as $c): ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                                <td class="px-6 py-4">
                                                    <a href="?view=report_summary&company=<?= urlencode($c['company_no']) ?>"
                                                        class="block font-bold text-slate-900 group-hover:text-[#0f766e] transition-colors">
                                                        <?= htmlspecialchars($c['company_name'] ? $c['company_name'] : '미지정') ?>
                                                    </a>
                                                    <div class="text-xs text-slate-500 mt-1">회원번호:
                                                        <?= htmlspecialchars($c['company_no']) ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center font-medium text-slate-700">
                                                    <?= number_format($c['project_count']) ?>개
                                                </td>
                                                <td class="px-6 py-4 text-center font-medium text-slate-700">
                                                    <?= number_format($c['units_total']) ?>세대
                                                </td>
                                                <td class="px-6 py-4 text-right font-semibold text-slate-900">
                                                    ₩<?= formatCurrency($c['billed_total']) ?></td>
                                                <td class="px-6 py-4 text-right text-emerald-600 font-medium">
                                                    ₩<?= formatCurrency($c['paid_total']) ?></td>
                                                <td class="px-6 py-4 text-right text-orange-500 font-medium">
                                                    ₩<?= formatCurrency($c['offset_total']) ?></td>
                                                <td class="px-6 py-4 text-right text-pink-500 font-medium">
                                                    ₩<?= formatCurrency($c['donation_total']) ?></td>
                                                <td class="px-6 py-4 text-right font-extrabold text-red-600 bg-red-50/10">
                                                    ₩<?= formatCurrency($c['running_balance']) ?></td>
                                                <td class="px-6 py-4 text-center">
                                                    <a href="?view=report_summary&company=<?= urlencode($c['company_no']) ?>"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 text-teal-700 hover:bg-teal-100 rounded-lg text-xs font-semibold transition-colors border border-teal-100 shadow-sm cursor-pointer">
                                                        보고서 보기 <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                                    </a>
                                                    <button type="button" onclick="sendMail('<?= htmlspecialchars($c['company_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES) ?>', <?= (int)$c['running_balance'] ?>)"
                                                         class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg text-xs font-semibold transition-colors border border-emerald-100 shadow-sm cursor-pointer ml-1.5"
                                                         title="안내문 메일 발송">
                                                         안내문 메일 발송 <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="px-6 py-12 text-center text-slate-500 font-medium">
                                                미납 잔액이 있는 회원사가 없습니다. (모든 회원사 완납 완료!) 🎉
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 2. 추후청구 주택 명단 -->
                    <?php
                    // Fetch all rows with '추후청구' status
                    $deferredProjects = [];
                    $deferredUnitsSubtotal = 0;
                    $deferredFeeSubtotal = 0;
                    try {
                        $defStmt = $pdo->query("SELECT * FROM sp_fee WHERE status_category = '추후청구' ORDER BY company_no, id ASC");
                        $deferredProjects = $defStmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($deferredProjects as &$row) {
                            $opType = isset($row['operation_type']) ? $row['operation_type'] : '';
                            $region = isset($row['region']) ? $row['region'] : '';
                            $units = isset($row['units']) ? (int) $row['units'] : 0;

                            $basePrice = 40000;
                            $opMultiplier = (strpos($opType, '시행') !== false) ? 1 : 0.5;
                            $isCapital = (in_array($region, ['서울', '경기', '인천']) || strpos($region, '수도권') !== false);
                            $regMultiplier = $isCapital ? 1 : 0.5;
                            $expectedFee = $units * $basePrice * $opMultiplier * $regMultiplier;

                            $row['expected_fee'] = $expectedFee;
                            $deferredUnitsSubtotal += $units;
                            $deferredFeeSubtotal += $expectedFee;
                        }
                    } catch (Exception $e) {
                        // Fail-safe
                    }
                    ?>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <!-- Table Summary Header -->
                        <div class="bg-amber-600 text-white px-6 py-4 flex flex-wrap items-center justify-between gap-4">
                            <div class="text-sm font-medium opacity-90 flex items-center gap-1.5">
                                <i data-lucide="clock" class="w-4 h-4"></i> 추후청구 주택 명단 (회원사 정보 및 부분합 포함)
                            </div>
                            <div class="text-xs text-amber-100">
                                현재 총 <span
                                    class="font-bold text-base text-yellow-300"><?= count($deferredProjects) ?></span>개의 주택이
                                추후청구 대기 상태입니다.
                            </div>
                        </div>

                        <div class="overflow-x-auto max-h-[400px] overflow-y-auto relative">
                            <table class="w-full text-left border-collapse whitespace-nowrap text-sm">
                                <thead class="sticky top-0 bg-slate-50 z-10 shadow-[inset_0_-1px_0_rgba(0,0,0,0.1)]">
                                    <tr
                                        class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        <th class="px-6 py-4">회원사명 (회원번호)</th>
                                        <th class="px-6 py-4">주택 정보</th>
                                        <th class="px-6 py-4 text-center">세대수</th>
                                        <th class="px-6 py-4 text-center">사업유형</th>
                                        <th class="px-6 py-4 text-center">운영유형</th>
                                        <th class="px-6 py-4 text-center">광역</th>
                                        <th class="px-6 py-4 text-center">청구예정일</th>
                                        <th class="px-6 py-4 text-right">예상 청구금액</th>
                                        <th class="px-6 py-4 text-center">동작</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php if (count($deferredProjects) > 0): ?>
                                        <?php foreach ($deferredProjects as $row):
                                            $opType = isset($row['operation_type']) ? $row['operation_type'] : '';
                                            ?>
                                            <tr class="hover:bg-slate-50/50 transition-colors">
                                                <td class="px-6 py-4 font-bold text-slate-900">
                                                    <?= htmlspecialchars($row['company_name'] ? $row['company_name'] : '미지정') ?>
                                                    <div class="text-xs text-slate-500 font-normal mt-0.5">회원번호:
                                                        <?= htmlspecialchars($row['company_no']) ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <a href="javascript:void(0)"
                                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)"
                                                        class="text-[#0f766e] hover:underline font-semibold block cursor-pointer">
                                                        <?= htmlspecialchars(isset($row['housing_no']) ? $row['housing_no'] : '-') ?>
                                                    </a>
                                                    <div class="text-xs text-slate-500 mt-0.5">
                                                        <?= htmlspecialchars(isset($row['building_name']) ? $row['building_name'] : '') ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center font-medium text-slate-800">
                                                    <?= number_format($row['units']) ?>세대
                                                </td>
                                                <td class="px-6 py-4 text-center text-slate-600">
                                                    <?= htmlspecialchars(isset($row['project_type']) ? $row['project_type'] : '') ?>
                                                </td>
                                                <td class="px-6 py-4 text-center text-slate-600 text-xs">
                                                    <?= htmlspecialchars($opType) ?>
                                                </td>
                                                <td class="px-6 py-4 text-center text-slate-600">
                                                    <?= htmlspecialchars(isset($row['region']) ? $row['region'] : '') ?>
                                                </td>
                                                <td class="px-6 py-4 text-center text-slate-500">
                                                    <?= htmlspecialchars(isset($row['base_date']) ? $row['base_date'] : '-') ?>
                                                </td>
                                                <td class="px-6 py-4 text-right font-bold text-amber-700">
                                                    ₩<?= formatCurrency($row['expected_fee']) ?></td>
                                                <td class="px-6 py-4 text-center">
                                                    <button type="button"
                                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200 rounded-lg text-xs font-semibold transition-colors shadow-sm cursor-pointer">
                                                        수정 <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <!-- 부분합 (Subtotal) Row -->
                                        <tr class="bg-amber-50/50 font-bold border-t border-slate-300">
                                            <td colspan="2" class="px-6 py-4 text-slate-900 text-left">
                                                <span
                                                    class="inline-flex items-center justify-center bg-amber-100 text-amber-800 text-xs px-2 py-0.5 rounded mr-1.5">부분합</span>
                                                추후청구 주택 전체 합계
                                            </td>
                                            <td class="px-6 py-4 text-center text-slate-900">
                                                <?= number_format($deferredUnitsSubtotal) ?>세대
                                            </td>
                                            <td colspan="4" class="px-6 py-4"></td>
                                            <td class="px-6 py-4 text-right text-amber-800 text-lg">
                                                ₩<?= formatCurrency($deferredFeeSubtotal) ?></td>
                                            <td></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="px-6 py-12 text-center text-slate-500 font-medium">
                                                추후청구 대기 주택이 없습니다.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- 준비 중 UI -->
                <div
                    class="flex flex-col items-center justify-center h-64 bg-white rounded-xl border border-slate-200 shadow-sm border-dashed mt-4">
                    <i data-lucide="wrench" class="w-12 h-12 text-slate-300 mb-4"></i>
                    <h2 class="text-xl font-bold text-slate-700 mb-2">화면 준비 중</h2>
                    <p class="text-slate-500">이 메뉴는 현재 개발 중입니다.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div
            class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between shrink-0">
                <h3 class="text-lg font-bold text-slate-800" id="editModalTitle">주택 정보 수정</h3>
                <button type="button" onclick="closeEditModal()"
                    class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Form -->
            <form method="POST" action="" class="p-6 overflow-y-auto space-y-4 flex-1">
                <input type="hidden" name="action" value="update_housing">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($view) ?>">
                <input type="hidden" name="redirect_company" value="<?= htmlspecialchars($selectedCompany) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- 회원사번호 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">회원사번호</label>
                        <input type="text" name="company_no" id="edit_company_no" list="company_no_list"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                        <datalist id="company_no_list">
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= htmlspecialchars($c['company_no']) ?>">
                                    <?= htmlspecialchars($c['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <!-- 회원사명 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">회원사명</label>
                        <input type="text" name="company_name" id="edit_company_name" list="company_name_list"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                        <datalist id="company_name_list">
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= htmlspecialchars($c['company_name']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <!-- 주택번호 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">주택번호</label>
                        <input type="text" name="housing_no" id="edit_housing_no"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 주택명 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">주택명</label>
                        <input type="text" name="building_name" id="edit_building_name"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 세대수 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">세대수</label>
                        <input type="number" name="units" id="edit_units"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 광역 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">광역</label>
                        <input type="text" name="region" id="edit_region"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 사업유형 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">사업유형</label>
                        <input type="text" name="project_type" id="edit_project_type"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 운영유형 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">운영유형</label>
                        <input type="text" name="operation_type" id="edit_operation_type"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 청구일자 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">청구일자</label>
                        <input type="date" name="base_date" id="edit_base_date"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 청구금액 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">청구금액 (기존)</label>
                        <input type="number" name="total_fee" id="edit_total_fee"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 상태 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">상태</label>
                        <select name="status_category" id="edit_status_category"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm cursor-pointer">
                            <option value="납부완료">납부완료</option>
                            <option value="신규 청구">신규 청구</option>
                            <option value="청구완료">청구완료</option>
                            <option value="추후청구">추후청구</option>
                            <option value="상계">상계</option>
                            <option value="후원금">후원금</option>
                            <option value="종결">종결</option>
                            <option value="이사회 조정">이사회 조정</option>
                        </select>
                    </div>
                    <!-- 납부일자 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">납부일자</label>
                        <input type="date" name="payment_date" id="edit_payment_date"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 납부금액 -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">납부금액</label>
                        <input type="number" name="paid_amount" id="edit_paid_amount"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0">
                    <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-medium transition-colors">취소</button>
                    <button type="submit"
                        class="px-4 py-2 bg-[#0f766e] hover:bg-[#0d6962] text-white rounded-lg text-sm font-medium transition-colors shadow-sm">저장</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Batch Action Floating Panel -->
    <div id="batchActionPanel"
        class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-slate-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-6 border border-slate-800 z-40 hidden transition-all duration-300 translate-y-12 opacity-0">
        <div class="flex items-center gap-2">
            <span
                class="inline-flex items-center justify-center bg-teal-500 text-slate-950 font-bold rounded-full w-6 h-6 text-xs"
                id="selectedCountBadge">0</span>
            <span class="text-sm font-semibold text-slate-300">개의 주택이 선택되었습니다.</span>
        </div>
        <div class="h-4 w-px bg-slate-700"></div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="openBatchEditModal()"
                class="px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white rounded-lg text-sm font-semibold transition-colors flex items-center gap-1.5 shadow-sm cursor-pointer">
                <i data-lucide="edit-3" class="w-4 h-4"></i> 일괄 편집
            </button>
            <button type="button" onclick="clearSelection()"
                class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-lg text-sm font-medium transition-colors cursor-pointer">
                선택 해제
            </button>
        </div>
    </div>

    <!-- Batch Edit Modal -->
    <div id="batchEditModal"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div
            class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between shrink-0">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="edit-3" class="w-5 h-5 text-teal-600"></i> 선택 항목 일괄 편집 (<span
                        id="batchEditCount">0</span>개 주택)
                </h3>
                <button type="button" onclick="closeBatchEditModal()"
                    class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Warning Banner -->
            <div class="bg-amber-50 border-b border-amber-100 px-6 py-3 text-xs text-amber-800 flex items-start gap-2">
                <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <div>
                    수정하려는 항목만 입력하거나 선택하십시오. 빈 칸으로 두는 항목은 기존 데이터가 그대로 유지됩니다.
                </div>
            </div>

            <!-- Form -->
            <form method="POST" action="" class="p-6 overflow-y-auto space-y-4 flex-1">
                <input type="hidden" name="action" value="batch_update_housing">
                <input type="hidden" name="batch_ids" id="batch_ids_input">
                <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($view) ?>">
                <input type="hidden" name="redirect_company" value="<?= htmlspecialchars($selectedCompany) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- 상태 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">상태 (변경할 경우에만
                            선택)</label>
                        <select name="batch_status_category"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm cursor-pointer">
                            <option value="">-- 변경 안 함 --</option>
                            <option value="납부완료">납부완료</option>
                            <option value="신규 청구">신규 청구</option>
                            <option value="청구완료">청구완료</option>
                            <option value="추후청구">추후청구</option>
                            <option value="상계">상계</option>
                            <option value="후원금">후원금</option>
                            <option value="종결">종결</option>
                            <option value="이사회 조정">이사회 조정</option>
                        </select>
                    </div>
                    <!-- 광역 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">광역 (변경할 경우에만
                            입력)</label>
                        <input type="text" name="batch_region" placeholder="예: 서울, 경기, 인천, 강원 등"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 사업유형 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">사업유형 (변경할 경우에만
                            입력)</label>
                        <input type="text" name="batch_project_type" placeholder="예: 매입임대, 건설임대 등"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 운영유형 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">운영유형 (변경할 경우에만
                            입력)</label>
                        <input type="text" name="batch_operation_type" placeholder="예: 시행+운영, 운영 등"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 청구일자 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">청구일자 (변경할 경우에만
                            입력)</label>
                        <input type="date" name="batch_base_date"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 납부일자 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">납부일자 (변경할 경우에만
                            입력)</label>
                        <input type="date" name="batch_payment_date"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 세대수 -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">세대수 (변경할 경우에만
                            입력)</label>
                        <input type="number" name="batch_units" placeholder="예: 10"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 청구금액 (이사회 조정용) -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">청구금액 (변경할 경우에만
                            입력)</label>
                        <input type="number" name="batch_total_fee" placeholder="예: 500000"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                    <!-- 납부금액 -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">납부금액 (변경할 경우에만
                            입력)</label>
                        <input type="number" name="batch_paid_amount" placeholder="예: 240000"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0f766e] text-sm">
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0">
                    <button type="button" onclick="closeBatchEditModal()"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-medium transition-colors">취소</button>
                    <button type="submit"
                        class="px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">일괄
                        저장</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Init Lucide Icons -->
    <script>
        lucide.createIcons();

        function sendMail(companyName, email, fee) {
            const subject = encodeURIComponent(`[안내] ${companyName} 굀하, 특별회비 납부 안내의 건`);
            const body = encodeURIComponent(`${companyName} 굀하\n\n무굉한 발코과 번영을 기원합니다.\n미납된 특별회비 내앭(미납 잔액: ${fee.toLocaleString()}원)을 환인해 주시고, 아래 계좌로 납부해 주시면 개사하겠싵니다.\n\n* 계좌정보:\n  - 은행몕: 우리은행\n  - 계좌번호: 1005-502-844907\n  - 예금주: 사단법인 한국사회주텍협회\n\n※ 특별회비는 후원금으로 인정되어 기부금영수증이 발행뀩니다. 홈텍스읐서 환인해보시기 바랍니다.\n\n감사합니다.`);
            
            if (!email) {
                alert("등록된 이메일 주소가 없습니다. 기본 메일 ��라읽언트가 열리면 슘신자를 직접 입력해주셸요.");
            }
            
            window.location.href = `mailto:${email || ''}?subject=${subject}&body=${body}`;
        }

        // Autocomplete data for company name & no
        const companiesData = <?php echo json_encode($companies ? $companies : [], JSON_UNESCAPED_UNICODE); ?>;
        const companyNameToNo = {};
        const companyNoToName = {};

        companiesData.forEach(c => {
            if (c.company_name && c.company_no) {
                const name = c.company_name.trim();
                const no = c.company_no.trim();
                companyNameToNo[name] = no;
                companyNoToName[no] = name;
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const editCompName = document.getElementById('edit_company_name');
            const editCompNo = document.getElementById('edit_company_no');

            if (editCompName && editCompNo) {
                editCompName.addEventListener('input', function () {
                    const val = this.value.trim();
                    if (companyNameToNo[val]) {
                        editCompNo.value = companyNameToNo[val];
                    }
                });

                editCompNo.addEventListener('input', function () {
                    const val = this.value.trim();
                    if (companyNoToName[val]) {
                        editCompName.value = companyNoToName[val];
                    }
                });
            }
        });

        function openEditModal(data, isNew = false) {
            const titleEl = document.getElementById('editModalTitle');
            if (titleEl) {
                titleEl.innerText = isNew ? '신규 사업 등록' : '주택 정보 수정';
            }
            document.getElementById('edit_id').value = isNew ? '' : (data.id || '');

            if (isNew) {
                const filterComp = document.getElementById('filter_company');
                if (filterComp && filterComp.value !== '모든 회원사') {
                    document.getElementById('edit_company_no').value = filterComp.value;
                    const selectedText = filterComp.options[filterComp.selectedIndex].text;
                    document.getElementById('edit_company_name').value = selectedText.trim();
                } else {
                    document.getElementById('edit_company_no').value = '';
                    document.getElementById('edit_company_name').value = '';
                }
            } else {
                document.getElementById('edit_company_no').value = data.company_no || '';
                document.getElementById('edit_company_name').value = data.company_name || '';
            }

            document.getElementById('edit_housing_no').value = isNew ? '' : (data.housing_no || '');
            document.getElementById('edit_building_name').value = isNew ? '' : (data.building_name || '');
            document.getElementById('edit_units').value = isNew ? 0 : (data.units || 0);
            document.getElementById('edit_region').value = isNew ? '' : (data.region || '');
            document.getElementById('edit_project_type').value = isNew ? '' : (data.project_type || '');
            document.getElementById('edit_operation_type').value = isNew ? '' : (data.operation_type || '');
            document.getElementById('edit_base_date').value = isNew ? '' : (data.base_date || '');
            document.getElementById('edit_total_fee').value = isNew ? 0 : (data.total_fee || 0);
            document.getElementById('edit_status_category').value = isNew ? '추후청구' : (data.status_category || '추후청구');
            document.getElementById('edit_payment_date').value = isNew ? '' : (data.payment_date || '');
            document.getElementById('edit_paid_amount').value = isNew ? 0 : (data.paid_amount || 0);

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Checkbox Batch Selection Logic
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('selectAllCheckbox');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const batchPanel = document.getElementById('batchActionPanel');
            const selectedCountBadge = document.getElementById('selectedCountBadge');
            const batchIdsInput = document.getElementById('batch_ids_input');
            const batchEditCount = document.getElementById('batchEditCount');

            if (!selectAll) return;

            function updateBatchPanel() {
                const checkedRows = document.querySelectorAll('.row-checkbox:checked');
                const count = checkedRows.length;

                if (count > 0) {
                    selectedCountBadge.innerText = count;
                    batchPanel.classList.remove('hidden');
                    setTimeout(() => {
                        batchPanel.classList.remove('opacity-0', 'translate-y-12');
                    }, 10);
                } else {
                    batchPanel.classList.add('opacity-0', 'translate-y-12');
                    setTimeout(() => {
                        batchPanel.classList.add('hidden');
                    }, 300);
                }

                selectAll.checked = (count === rowCheckboxes.length);
                selectAll.indeterminate = (count > 0 && count < rowCheckboxes.length);
            }

            selectAll.addEventListener('change', function () {
                rowCheckboxes.forEach(cb => {
                    cb.checked = selectAll.checked;
                });
                updateBatchPanel();
            });

            rowCheckboxes.forEach(cb => {
                cb.addEventListener('change', function () {
                    updateBatchPanel();
                });
            });

            window.clearSelection = function () {
                rowCheckboxes.forEach(cb => {
                    cb.checked = false;
                });
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
                updateBatchPanel();
            };

            window.openBatchEditModal = function () {
                const checkedRows = document.querySelectorAll('.row-checkbox:checked');
                const ids = Array.from(checkedRows).map(cb => cb.value);

                batchIdsInput.value = ids.join(',');
                batchEditCount.innerText = ids.length;

                document.getElementById('batchEditModal').classList.remove('hidden');
            };

            window.closeBatchEditModal = function () {
                document.getElementById('batchEditModal').classList.add('hidden');
            };
        });
    </script>

    <!-- Notice Modal -->
    <div id="noticeModal"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div
            class="bg-white rounded-2xl shadow-xl border border-slate-100 max-w-2xl w-full flex flex-col max-h-[90vh] transition-all transform scale-100">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
                <h3 class="font-bold text-slate-900 text-lg flex items-center gap-2">
                    <i data-lucide="mail" class="w-5 h-5 text-teal-600"></i> 특별회비 납부 안내문
                </h3>
                <button type="button" onclick="closeNoticeModal()"
                    class="text-slate-400 hover:text-slate-600 rounded-lg p-1 hover:bg-slate-50 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Notice Document Content -->
            <div class="p-8 overflow-y-auto flex-1 space-y-6" id="noticePrintArea">
                <!-- Document Frame -->
                <div
                    class="border border-slate-200 p-8 rounded-xl bg-slate-50/30 font-serif space-y-8 text-slate-800 relative overflow-hidden shadow-sm">
                    <!-- Document Title -->
                    <div class="text-center">
                        <h1
                            class="text-3xl font-extrabold tracking-widest text-slate-900 border-b-2 border-double border-slate-400 pb-3 inline-block px-12">
                            <?= $hasNewBilling ? '특별회비 청구 안내문' : '특별회비 납부 안내문' ?>
                        </h1>
                    </div>

                    <!-- Recipient & Date -->
                    <div class="flex justify-between items-start text-sm">
                        <div>
                            <div class="font-bold text-base mb-1">수신:
                                <?= htmlspecialchars(isset($selectedCompanyName) ? $selectedCompanyName : '') ?> 대표자 귀하
                            </div>
                            <div class="text-slate-500">회원번호:
                                <?= htmlspecialchars(isset($selectedCompany) ? $selectedCompany : '') ?>
                            </div>
                        </div>
                        <div class="text-right text-slate-600">
                            발송일: <?= date('Y년 m월 d일') ?>
                        </div>
                    </div>

                    <!-- Greetings -->
                    <div class="text-sm leading-relaxed space-y-3 font-sans">
                        <p>귀사의 무궁한 발전과 번영을 기원합니다.</p>
                        <p>한국사회주택협회 정관 및 총회 의결 사항에 의거하여, 회원사 간 상생과 협회의 원활한 운영 및 공동 사업 추진을 위한
                            <strong><?= $hasNewBilling ? '특별회비 청구 현황 및 납부 안내' : '특별회비 납부 현황 및 납부 안내' ?></strong>를 드립니다.
                        </p>
                    </div>

                    <!-- Details Table -->
                    <div class="border-t border-b border-slate-300 py-4 font-sans">
                        <div class="grid grid-cols-2 gap-y-3 text-sm px-2">
                            <div class="text-slate-500">① 기 청구금액 합계:</div>
                            <div class="text-right font-semibold text-slate-950">
                                ₩<?= formatCurrency(isset($noticeBilledTotal) ? $noticeBilledTotal : 0) ?></div>

                            <div class="text-slate-500">② 기 납부금액 합계:</div>
                            <div class="text-right font-semibold text-emerald-600">
                                ₩<?= formatCurrency(isset($noticePaidTotal) ? $noticePaidTotal : 0) ?></div>

                            <div class="text-slate-500">③ 후원금 기여 금액:</div>
                            <div class="text-right font-semibold text-pink-500">
                                ₩<?= formatCurrency(isset($noticeDonationTotal) ? $noticeDonationTotal : 0) ?></div>

                            <div class="text-slate-500">④ 상계 처리 금액:</div>
                            <div class="text-right font-semibold text-orange-500">
                                ₩<?= formatCurrency(isset($noticeOffsetTotal) ? $noticeOffsetTotal : 0) ?></div>

                            <div class="col-span-2 border-t border-dashed border-slate-200 my-2"></div>

                            <div class="text-base font-bold text-slate-900 flex items-center gap-1">
                                🔔 최종 납부하실 특별회비 금액:
                            </div>
                            <div
                                class="text-right text-lg font-extrabold <?= (isset($noticeRunningBalance) && $noticeRunningBalance > 0) ? 'text-red-600' : 'text-slate-900' ?>">
                                <?php if (isset($noticeRunningBalance) && $noticeRunningBalance > 0): ?>
                                    ₩<?= formatCurrency($noticeRunningBalance) ?>
                                <?php else: ?>
                                    ₩0 (완납 / 초과납부:
                                    -₩<?= formatCurrency(abs(isset($noticeRunningBalance) ? $noticeRunningBalance : 0)) ?>)
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div
                        class="text-xs leading-relaxed space-y-2 text-slate-600 bg-slate-50 p-4 rounded-lg border border-slate-100 font-sans">
                        <p class="font-bold text-slate-800">📌 [납부 계좌 정보]</p>
                        <p>· 은행명: <strong>우리은행</strong></p>
                        <p>· 계좌번호: <strong>1005-502-844907</strong></p>
                        <p>· 예금주: <strong>사단법인 한국사회주택협회</strong></p>
                        <p class="text-[11px] text-teal-600 mt-1">※ 납부 시 송금인 명의를 회원사명 또는 회원번호로 기재해 주시기 바랍니다.</p>
                        <p class="text-[11px] text-slate-500 mt-1">※ 특별회비는 후원금으로 인정되어 기부금영수증이 발행됩니다. 홈텍스에서 확인해보시기 바랍니다.
                        </p>
                        <p class="text-[11px] text-slate-500 font-semibold mt-1">▶ 문의전화: 02-6264-5323
                            green@socialhousing.kr</p>
                    </div>

                    <!-- Closing -->
                    <div class="text-center pt-4 space-y-4">
                        <p class="text-sm font-semibold tracking-wider">협회의 발전을 위해 항상 협조해 주심에 깊은 감사를 드립니다.</p>
                        <div class="text-lg font-bold pt-4 tracking-widest text-slate-900">
                            사단법인 한국사회주택협회 이사장 <span
                                class="text-slate-400 font-normal text-sm font-serif select-none">(직인생략)</span>
                        </div>
                    </div>
                </div>

                <!-- Page 2: Attachment (별지 - 특별회비 청구 및 납부 상세 내역서) -->
                <div
                    class="mt-8 border border-slate-200 p-8 rounded-xl bg-slate-50/30 font-sans space-y-6 text-slate-800 relative overflow-hidden shadow-sm print-break">
                    <!-- page-break for print -->
                    <div style="page-break-before: always;" class="hidden print:block"></div>

                    <!-- Title -->
                    <div class="text-center pb-4 border-b-2 border-slate-300">
                        <h2 class="text-2xl font-bold tracking-wider text-slate-900">[별지] 특별회비 청구 및 납부 상세 내역서</h2>
                    </div>

                    <!-- Company Info Summary -->
                    <div class="flex justify-between items-center text-sm pt-2">
                        <div>
                            <span class="font-bold text-slate-700">회원사명:</span> <span
                                class="text-slate-900"><?= htmlspecialchars(isset($selectedCompanyName) ? $selectedCompanyName : '') ?></span>
                            <span class="ml-4 font-bold text-slate-700">회원번호:</span> <span
                                class="text-slate-900"><?= htmlspecialchars(isset($selectedCompany) ? $selectedCompany : '') ?></span>
                        </div>
                        <div class="text-slate-500 text-xs">기준일: <?= date('Y년 m월 d일') ?></div>
                    </div>

                    <!-- Itemized Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs whitespace-nowrap table-layout-fixed">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 border-b border-slate-300 font-semibold">
                                    <th class="px-2 py-1.5 border border-slate-200 w-[25%]">주택명<br><span
                                            class="text-[10px] text-slate-500 font-normal">세대수</span></th>
                                    <th class="px-2 py-1.5 border border-slate-200 text-center w-[15%]">유형<br><span
                                            class="text-[10px] text-slate-500 font-normal">운영</span></th>
                                    <th class="px-2 py-1.5 border border-slate-200 text-center w-[15%]">상태<br><span
                                            class="text-[10px] text-slate-500 font-normal">(청구일)</span></th>
                                    <th class="px-2 py-1.5 border border-slate-200 text-right w-[15%]">청구액<br><span
                                            class="text-[10px] text-slate-500 font-normal">청구일자</span></th>
                                    <th class="px-2 py-1.5 border border-slate-200 text-right w-[15%]">납부액<br><span
                                            class="text-[10px] text-slate-500 font-normal">납부일자</span></th>
                                    <th class="px-2 py-1.5 border border-slate-200 text-right w-[15%]">누계</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php
                                $subBilled = 0;
                                $subPaid = 0;
                                $subOffset = 0;
                                $subDonation = 0;
                                $runningBalance = 0;

                                if (isset($filteredData) && is_array($filteredData)) {
                                    foreach ($filteredData as $row) {
                                        $status = isset($row['status_category']) ? $row['status_category'] : '-';
                                        $totalFee = isset($row['total_fee']) ? (int) $row['total_fee'] : 0;
                                        $paidAmount = isset($row['paid_amount']) ? (int) $row['paid_amount'] : 0;
                                        $offsetAmount = 0;
                                        $donationAmount = 0;

                                        $opType = isset($row['operation_type']) ? $row['operation_type'] : '';
                                        $region = isset($row['region']) ? $row['region'] : '';
                                        $units = isset($row['units']) ? (int) $row['units'] : 0;

                                        if ($status === '후원금') {
                                            $donationAmount = $totalFee > 0 ? $totalFee : $paidAmount;
                                            $billedAmount = 0;
                                            $paidAmount = 0;
                                        } elseif ($status === '이사회 조정' || (isset($row['housing_no']) && (int) $row['housing_no'] >= 50000) || (isset($row['id']) && (int) $row['id'] >= 50000)) {
                                            $billedAmount = $totalFee;
                                            $paidAmount = 0;
                                        } else {
                                            if ($status === '납부완료' && $totalFee > 0 && $totalFee == $paidAmount) {
                                                $billedAmount = $paidAmount;
                                            } else {
                                                $basePrice = 40000;
                                                $opMultiplier = (strpos($opType, '시행') !== false) ? 1 : 0.5;
                                                $isCapital = (in_array($region, ['서울', '경기', '인천']) || strpos($region, '수도권') !== false);
                                                $regMultiplier = $isCapital ? 1 : 0.5;
                                                $billedAmount = $units * $basePrice * $opMultiplier * $regMultiplier;
                                            }

                                            if ($status === '상계') {
                                                $offsetAmount = $totalFee > 0 ? $totalFee : $billedAmount;
                                                $paidAmount = 0;
                                            }
                                        }

                                        if ($status !== '추후청구' && $status !== '종결') {
                                            $runningBalance += $billedAmount;
                                        }
                                        $runningBalance -= $paidAmount;
                                        $runningBalance -= $donationAmount;

                                        $subBilled += $billedAmount;
                                        $subPaid += $paidAmount;
                                        $subOffset += $offsetAmount;
                                        $subDonation += $donationAmount;

                                        // Abbreviate Type/Operation
                                        $shortType = str_replace(['사회주택', '공동체주택', '토지임대부'], ['사회', '공동체', '토지'], $row['project_type'] ?: '');
                                        $shortOp = str_replace(['시행+운영', '운영전담'], ['시행/운', '운영'], $row['operation_type'] ?: '');

                                        // Format dates tightly (YY-MM-DD)
                                        $shortBaseDate = ($row['base_date'] && strlen($row['base_date']) >= 10) ? substr($row['base_date'], 2) : ($row['base_date'] ?: '-');
                                        $shortPaymentDate = ($row['payment_date'] && strlen($row['payment_date']) >= 10) ? substr($row['payment_date'], 2) : ($row['payment_date'] ?: '-');
                                        ?>
                                        <tr class="hover:bg-slate-50 text-[11px]">
                                            <td class="px-2 py-1.5 border border-slate-200 text-slate-800">
                                                <div class="max-w-[140px] truncate font-medium"
                                                    title="<?= htmlspecialchars(isset($row['building_name']) ? $row['building_name'] : '') ?>">
                                                    <?= htmlspecialchars(isset($row['building_name']) ? $row['building_name'] : '-') ?>
                                                </div>
                                                <div class="text-[10px] text-slate-500 mt-0.5"><?= $units ?>세대</div>
                                            </td>
                                            <td class="px-2 py-1.5 border border-slate-200 text-center text-slate-600">
                                                <div class="truncate" title="<?= htmlspecialchars($shortType) ?>">
                                                    <?= htmlspecialchars($shortType ?: '-') ?>
                                                </div>
                                                <div class="text-[10px] text-slate-400 mt-0.5 truncate"
                                                    title="<?= htmlspecialchars($shortOp) ?>">
                                                    <?= htmlspecialchars($shortOp ?: '-') ?>
                                                </div>
                                            </td>
                                            <td class="px-2 py-1.5 border border-slate-200 text-center">
                                                <span
                                                    class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold border <?= getStatusColor($status) ?>">
                                                    <?= htmlspecialchars($status) ?>
                                                </span>
                                            </td>
                                            <td class="px-2 py-1.5 border border-slate-200 text-right">
                                                <div class="font-medium text-slate-900">
                                                    <?= $billedAmount > 0 ? formatCurrency($billedAmount) : '-' ?>
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                    <?= htmlspecialchars($shortBaseDate) ?>
                                                </div>
                                            </td>
                                            <td class="px-2 py-1.5 border border-slate-200 text-right">
                                                <div class="font-medium text-emerald-600">
                                                    <?= $paidAmount > 0 ? formatCurrency($paidAmount) : '-' ?>
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                    <?= htmlspecialchars($shortPaymentDate) ?>
                                                </div>
                                            </td>
                                            <td
                                                class="px-2 py-1.5 border border-slate-200 text-right font-bold text-slate-900 bg-slate-50/50">
                                                <?= formatCurrency($runningBalance) ?>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>

                                <!-- Totals Row -->
                                <tr
                                    class="bg-slate-50 font-bold border-t-2 border-slate-300 text-slate-950 text-[11px]">
                                    <td colspan="3"
                                        class="px-2 py-1.5 border border-slate-200 text-center text-slate-900">합계</td>
                                    <td class="px-2 py-1.5 border border-slate-200 text-right">
                                        <div class="text-slate-900">
                                            <?= $subBilled > 0 ? formatCurrency($subBilled) : '-' ?>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1.5 border border-slate-200 text-right">
                                        <div class="text-emerald-600">
                                            <?= $subPaid > 0 ? formatCurrency($subPaid) : '-' ?>
                                        </div>
                                    </td>
                                    <td
                                        class="px-2 py-1.5 border border-slate-200 text-right text-slate-900 bg-slate-100">
                                        <?= formatCurrency($runningBalance) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Signature or Note -->
                    <div class="text-xs text-slate-500 pt-4 leading-relaxed font-sans space-y-1">
                        <p>※ 위 청구 및 납부 상세 내역서는 사단법인 한국사회주택협회 특별회비 전산 시스템에서 실시간으로 출력된 공식 증빙 서류입니다.</p>
                        <p>※ 상계 및 후원금 처리 등 회무 관련 조정 사항이 모두 완벽히 반영되어 있습니다.</p>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div
                class="px-6 py-4 border-t border-slate-100 flex items-center justify-between shrink-0 bg-slate-50 rounded-b-2xl">
                <button type="button" onclick="copyNoticeText()"
                    class="px-4 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="copy" class="w-4 h-4 text-slate-500"></i> 안내문 텍스트 복사
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printNotice()"
                        class="px-4 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm font-medium transition-colors flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="printer" class="w-4 h-4 text-slate-500"></i> 인쇄 / PDF 저장
                    </button>
                    <button type="button" onclick="closeNoticeModal()"
                        class="px-4 py-2 bg-[#0f766e] hover:bg-[#0d6962] text-white rounded-lg text-sm font-medium transition-colors shadow-sm">확인</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Init Lucide Icons & Notice Scripts -->
    <script>
        lucide.createIcons();

        function sendMail(companyName, email, fee) {
            const subject = encodeURIComponent(`[안내] ${companyName} 굀하, 특별회비 납부 안내의 건`);
            const body = encodeURIComponent(`${companyName} 굀하\n\n무굉한 발코과 번영을 기원합니다.\n미납된 특별회비 내앭(미납 잔액: ${fee.toLocaleString()}원)을 환인해 주시고, 아래 계좌로 납부해 주시면 개사하겠싵니다.\n\n* 계좌정보:\n  - 은행몕: 우리은행\n  - 계좌번호: 1005-502-844907\n  - 예금주: 사단법인 한국사회주텍협회\n\n※ 특별회비는 후원금으로 인정되어 기부금영수증이 발행뀩니다. 홈텍스읐서 환인해보시기 바랍니다.\n\n감사합니다.`);
            
            if (!email) {
                alert("등록된 이메일 주소가 없습니다. 기본 메일 ��라읽언트가 열리면 슘신자를 직접 입력해주셸요.");
            }
            
            window.location.href = `mailto:${email || ''}?subject=${subject}&body=${body}`;
        }

        function openNoticeModal() {
            document.getElementById('noticeModal').classList.remove('hidden');
        }

        function closeNoticeModal() {
            document.getElementById('noticeModal').classList.add('hidden');
        }

        function copyNoticeText() {
            const companyName = <?= json_encode(isset($selectedCompanyName) ? $selectedCompanyName : '') ?>;
            const companyNo = <?= json_encode(isset($selectedCompany) ? $selectedCompany : '') ?>;
            const billed = "₩" + Number(<?= isset($noticeBilledTotal) ? $noticeBilledTotal : 0 ?>).toLocaleString();
            const paid = "₩" + Number(<?= isset($noticePaidTotal) ? $noticePaidTotal : 0 ?>).toLocaleString();
            const offset = "₩" + Number(<?= isset($noticeOffsetTotal) ? $noticeOffsetTotal : 0 ?>).toLocaleString();
            const donation = "₩" + Number(<?= isset($noticeDonationTotal) ? $noticeDonationTotal : 0 ?>).toLocaleString();

            const rawBalance = Number(<?= isset($noticeRunningBalance) ? $noticeRunningBalance : 0 ?>);
            const balance = rawBalance > 0 ? "₩" + rawBalance.toLocaleString() : "₩0 (완납 / 초과납부: -₩" + Math.abs(rawBalance).toLocaleString() + ")";

            const dateStr = <?= json_encode(date('Y년 m월 d일')) ?>;
            const hasNewBilling = <?= json_encode($hasNewBilling) ?>;
            const docTitle = hasNewBilling ? "특별회비 청구 안내문" : "특별회비 납부 안내문";
            const greetingLabel = hasNewBilling ? "특별회비 청구 현황 및 납부 안내" : "특별회비 납부 현황 및 납부 안내";
            const balanceLabel = "최종 납부하실 특별회비 금액:";

            const text = `[${docTitle}]

수신: ${companyName} 대표자 귀하
회원번호: ${companyNo}
발송일: ${dateStr}

귀사의 무궁한 발전과 번영을 기원합니다.
한국사회주택협회 정관 및 총회 의결 사항에 의거하여, 회원사 간 상생과 협회의 원활한 운영 및 공동 사업 추진을 위한 ${greetingLabel}를 드립니다.

■ 특별회비 정산 내역
1. 기 청구금액 합계: ${billed}
2. 기 납부금액 합계: ${paid}
3. 후원금 기여 금액: ${donation}
4. 상계 처리 금액: ${offset}
----------------------------------
🔔 ${balanceLabel} ${balance}

■ 납부 계좌 정보
· 은행명: 우리은행
· 계좌번호: 1005-502-844907
· 예금주: 사단법인 한국사회주택협회
※ 납부 시 송금인 명의를 회원사명 또는 회원번호로 기재해 주시기 바랍니다.
※ 특별회비는 후원금으로 인정되어 기부금영수증이 발행됩니다. 홈텍스에서 확인해보시기 바랍니다.
▶ 문의전화: 02-6264-5323 green@socialhousing.kr

협회의 발전을 위해 항상 협조해 주심에 깊은 감사를 드립니다.

사단법인 한국사회주택협회 이사장`;

            navigator.clipboard.writeText(text).then(() => {
                alert('안내문 텍스트가 클립보드에 성공적으로 복사되었습니다. 이메일이나 메신저에 붙여넣기(Ctrl+V)하여 사용하세요!');
            }).catch(err => {
                alert('텍스트 복사에 실패했습니다. 직접 선택하여 복사해 주세요.');
            });
        }

        function printNotice() {
            const printContent = document.getElementById('noticePrintArea').innerHTML;
            const printWindow = window.open('', '', 'height=800,width=800');
            printWindow.document.write('<html><head><title>특별회비 납부 안내문 및 상세 내역서</title>');
            printWindow.document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
            printWindow.document.write('<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@400;700;900&display=swap" rel="stylesheet">');
            printWindow.document.write('<style>body { font-family: "Noto Serif KR", Georgia, serif; padding: 40px; background-color: #fff; } table { table-layout: fixed; width: 100%; border-collapse: collapse; margin-top: 15px; font-family: sans-serif; font-size: 11px; } th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; overflow: hidden; text-overflow: ellipsis; word-break: break-all; } th { background-color: #f1f5f9; font-weight: bold; } .text-right { text-align: right; } .text-center { text-align: center; } .font-semibold { font-weight: 600; } .font-bold { font-weight: bold; } @media print { body { padding: 0; } .no-print { display: none; } .print-break { page-break-before: always; } }</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();

            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            }, 800);
        }

        // Table Sorting Logic
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('table').forEach(table => {
                const headers = table.querySelectorAll('thead th');
                headers.forEach((th, index) => {
                    const headerText = th.innerText.trim();
                    if (headerText === '동작' || headerText === '') return;

                    th.classList.add('cursor-pointer', 'select-none', 'hover:bg-slate-100/80', 'transition-colors', 'relative', 'pr-6');

                    const indicator = document.createElement('span');
                    indicator.className = 'absolute right-2 top-1/2 -translate-y-1/2 text-slate-300 sort-indicator text-[10px] font-sans';
                    indicator.innerHTML = '↕';
                    th.appendChild(indicator);

                    th.addEventListener('click', () => {
                        const tbody = table.querySelector('tbody');
                        if (!tbody) return;

                        const rows = Array.from(tbody.querySelectorAll('tr'));
                        // Filter out empty rows or single no-data messages
                        if (rows.length === 1 && rows[0].querySelector('td[colspan]')) return;

                        // Identify subtotal (부분합/합계) rows to exclude from sorting and keep at the bottom
                        const regularRows = [];
                        const footerRows = [];
                        rows.forEach(row => {
                            if (row.innerText.includes('부분합') || row.innerText.includes('합계') || row.classList.contains('bg-amber-50/50')) {
                                footerRows.push(row);
                            } else {
                                regularRows.push(row);
                            }
                        });

                        const currentDir = th.getAttribute('data-sort-dir') || 'none';
                        const nextDir = currentDir === 'asc' ? 'desc' : 'asc';

                        // Reset other headers
                        headers.forEach(h => {
                            if (h !== th) {
                                h.removeAttribute('data-sort-dir');
                                const ind = h.querySelector('.sort-indicator');
                                if (ind) ind.innerHTML = '↕';
                            }
                        });

                        th.setAttribute('data-sort-dir', nextDir);
                        const ind = th.querySelector('.sort-indicator');
                        if (ind) ind.innerHTML = nextDir === 'asc' ? '▲' : '▼';

                        regularRows.sort((rowA, rowB) => {
                            let cellA = rowA.children[index]?.innerText.trim() || '';
                            let cellB = rowB.children[index]?.innerText.trim() || '';

                            let valA = parseSortValue(cellA);
                            let valB = parseSortValue(cellB);

                            if (typeof valA === 'number' && typeof valB === 'number') {
                                return nextDir === 'asc' ? valA - valB : valB - valA;
                            }
                            return nextDir === 'asc'
                                ? valA.toString().localeCompare(valB.toString(), 'ko', { numeric: true })
                                : valB.toString().localeCompare(valA.toString(), 'ko', { numeric: true });
                        });

                        // Re-append rows
                        tbody.innerHTML = '';
                        regularRows.forEach(row => tbody.appendChild(row));
                        footerRows.forEach(row => tbody.appendChild(row));
                    });
                });
            });

            function parseSortValue(text) {
                let cleaned = text.replace(/[₩\s,개세대]/g, '');
                if (cleaned === '-') return 0;
                if (!isNaN(cleaned) && cleaned !== '') {
                    return parseFloat(cleaned);
                }
                return text;
            }
        });
    </script>
</body>

</html>
