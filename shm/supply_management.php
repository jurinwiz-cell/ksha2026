<?php
/**
 * supply_management.php - 사회주택 공급현황 관리 (관리자 전용)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
$pageTitle = '공급현황 관리';
$currentPage = 'supply';
$success_msg = '';
$error_msg = '';
// ─── POST 요청 처리 ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_supply') {
        $id = (int) $_POST['id'];
        $bName = trim($_POST['building_name'] ?? '');
        $addr = trim($_POST['address'] ?? '');
        $hCount = (int) ($_POST['household_count'] ?? 0);
        $sType = trim($_POST['supply_type'] ?? '');
        $cDate = trim($_POST['completion_date'] ?? '');
        $cStatus = trim($_POST['completion_status'] ?? '');
        $sType2 = trim($_POST['supply_type2'] ?? '');
        $pEntity = trim($_POST['public_entity'] ?? '');
        $mId = $_POST['member_id'] ? (int) $_POST['member_id'] : null;
        $mNo = trim($_POST['member_no'] ?? '');
        $opCustom = trim($_POST['operator_name_custom'] ?? '');
        // 자동 매칭 시도
        if (!$mId && $mNo) {
            $stmt = $pdo->prepare("SELECT id FROM tb_members WHERE member_no = ?");
            $stmt->execute([$mNo]);
            $mId = $stmt->fetchColumn() ?: null;
        }
        if ($id) {
            // 기존 데이터 로드 (변경 감지용)
            $current = $pdo->prepare("SELECT * FROM tb_social_housing_supply WHERE id = ?");
            $current->execute([$id]);
            $currentData = $current->fetch();
            $change_reason = $_POST['change_reason'] ?? '관리자 수정';
            // 변경 감지 및 이력 기록 함수
            $logChange = function ($field, $old, $new) use ($pdo, $id, $change_reason) {
                if ($old != $new) {
                    $stmt = $pdo->prepare("INSERT INTO tb_social_housing_history (supply_id, field_name, old_value, new_value, change_reason) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $field, $old, $new, $change_reason]);
                }
            };
            // 1. 운영사 변경 체크
            $oldOp = $currentData['member_id'] ? $currentData['member_id'] : ($currentData['operator_name_custom'] ?: $currentData['company_name_raw']);
            $newOp = $mId ? $mId : $opCustom;
            if ($oldOp != $newOp) {
                $logChange('운영사', $oldOp, $newOp);
            }
            // 2. 기타 주요 필드 변경 체크
            $logChange('대분류', $currentData['supply_type'], $sType);
            $logChange('소분류', $currentData['supply_type2'], $sType2);
            $logChange('공공주체', $currentData['public_entity'], $pEntity);
            $stmt = $pdo->prepare("UPDATE tb_social_housing_supply SET building_name=?, address=?, household_count=?, supply_type=?, supply_type2=?, public_entity=?, completion_date=?, completion_status=?, member_id=?, member_no=?, operator_name_custom=? WHERE id=?");
            if ($stmt->execute([$bName, $addr, $hCount, $sType, $sType2, $pEntity, $cDate, $cStatus, $mId, $mNo, $opCustom, $id])) {
                $success_msg = "주택 정보가 수정되었습니다.";
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO tb_social_housing_supply (building_name, address, household_count, supply_type, supply_type2, public_entity, completion_date, completion_status, member_id, member_no, operator_name_custom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$bName, $addr, $hCount, $sType, $sType2, $pEntity, $cDate, $cStatus, $mId, $mNo, $opCustom])) {
                $success_msg = "새로운 주택 정보가 등록되었습니다.";
            }
        }
    } elseif ($action === 'delete_history') {
        $hid = (int) $_POST['history_id'];
        $stmt = $pdo->prepare("DELETE FROM tb_social_housing_history WHERE id = ?");
        $stmt->execute([$hid]);
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'update_history') {
        $hid = (int) $_POST['history_id'];
        $val = trim($_POST['new_value']);
        $reason = trim($_POST['change_reason']);
        $stmt = $pdo->prepare("UPDATE tb_social_housing_history SET new_value = ?, change_reason = ? WHERE id = ?");
        $stmt->execute([$val, $reason, $hid]);
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'delete_supply') {
        $id = (int) $_POST['id'];
        if ($id > 0) {
            $pdo->prepare("UPDATE tb_social_housing_supply SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            $success_msg = "주택 정보가 휴지통으로 이동되었습니다.";
        }
    } elseif ($action === 'batch_update_supply') {
        $ids = $_POST['ids'] ?? [];
        $newStatus = $_POST['batch_status'] ?? '';
        $newMemberId = $_POST['batch_member_id'] ? (int) $_POST['batch_member_id'] : null;
        if (!empty($ids)) {
            $updates = [];
            $params = [];
            if ($newStatus !== '') {
                $updates[] = "completion_status = ?";
                $params[] = $newStatus;
            }
            if ($newMemberId !== null || isset($_POST['batch_member_id'])) {
                $updates[] = "member_id = ?";
                $params[] = $newMemberId;
            }
            if (!empty($updates)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "UPDATE tb_social_housing_supply SET " . implode(', ', $updates) . " WHERE id IN ($placeholders)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute(array_merge($params, $ids))) {
                    $success_msg = count($ids) . "건의 정보가 일괄 수정되었습니다.";
                }
            }
        }
    } elseif ($action === 'batch_match_members') {
        // 회원번호 기준 일괄 자동 매칭
        $stmt = $pdo->query("SELECT id, member_no FROM tb_social_housing_supply WHERE member_no != ''");
        $toMatch = $stmt->fetchAll();
        $matchedCount = 0;
        $members = $pdo->query("SELECT member_no, id FROM tb_members")->fetchAll(PDO::FETCH_KEY_PAIR);
        $updateStmt = $pdo->prepare("UPDATE tb_social_housing_supply SET member_id = ? WHERE id = ?");
        foreach ($toMatch as $row) {
            $mNo = trim($row['member_no']);
            if (isset($members[$mNo])) {
                $updateStmt->execute([$members[$mNo], $row['id']]);
                $matchedCount++;
            }
        }
        $success_msg = "{$matchedCount}건의 회원사가 자동 매칭되었습니다.";
    }
}
// 필터 처리
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$memberStatus = $_GET['member_status'] ?? '';
$showAll = isset($_GET['show_all']) && $_GET['show_all'] === '1';
// 정렬 설정
$sort = $_GET['sort'] ?? 'completion_date';
$order = $_GET['order'] ?? 'DESC';
$allowedSort = ['no', 'building_name', 'location_si', 'supply_type', 'household_count', 'company_name_raw', 'address', 'completion_status'];
if (!in_array($sort, $allowedSort))
    $sort = 'completion_date';
$order = (strtoupper($order) === 'ASC') ? 'ASC' : 'DESC';
$mainStatuses = ['준공', '미준공', '종료', '미착공'];
$where = ["s.deleted_at IS NULL"];
$params = [];
if ($search) {
    $where[] = "(s.building_name LIKE ? OR s.company_name_raw LIKE ? OR s.address LIKE ? OR s.member_no LIKE ? OR s.no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($type) {
    $where[] = "s.supply_type = ?";
    $params[] = $type;
}
if ($memberStatus === 'member') {
    $where[] = "s.member_no LIKE '1-%'";
} elseif ($memberStatus === 'non_member') {
    $where[] = "s.member_no LIKE '5-%'";
}
if ($status) {
    if ($status === '미진행') {
        $where[] = "s.completion_status NOT IN ('" . implode("','", $mainStatuses) . "')";
    } else {
        $where[] = "s.completion_status = ?";
        $params[] = $status;
    }
} elseif (!$showAll) {
    // 기본적으로 '모두 표시'가 아닐 때는 주요 4개 상태만 표시
    $where[] = "s.completion_status IN ('" . implode("','", $mainStatuses) . "')";
}
$whereClause = implode(" AND ", $where);
// 정렬 필드 매핑
$orderBy = "s.$sort";
if ($sort === 'matched_company')
    $orderBy = "matched_company";
$sql = "SELECT s.*, m.company_name as matched_company 
        FROM tb_social_housing_supply s 
        LEFT JOIN tb_members m ON s.member_id = m.id 
        WHERE $whereClause 
        ORDER BY $orderBy $order";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$supplies = $stmt->fetchAll();
// 회원사 목록 (매칭용)
$memberList = $pdo->query("SELECT id, company_name, member_no FROM tb_members ORDER BY company_name")->fetchAll();
// 통계 및 필터 옵션 (검색/필터 결과 반영)
$statsSql = "SELECT COUNT(*) as total_count, SUM(household_count) as total_households 
             FROM tb_social_housing_supply s 
             WHERE $whereClause";
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch();
$types = $pdo->query("SELECT DISTINCT supply_type FROM tb_social_housing_supply WHERE supply_type != '' ORDER BY supply_type")->fetchAll(PDO::FETCH_COLUMN);
$statuses = $pdo->query("SELECT DISTINCT completion_status FROM tb_social_housing_supply WHERE completion_status != '' ORDER BY completion_status")->fetchAll(PDO::FETCH_COLUMN);
// 운영사 매칭 통계 (검색/필터 결과 반영)
$matchedSql = "SELECT COUNT(*) FROM tb_social_housing_supply s WHERE s.member_id IS NOT NULL AND $whereClause";
$matchedStmt = $pdo->prepare($matchedSql);
$matchedStmt->execute($params);
$matched = $matchedStmt->fetchColumn();
require_once __DIR__ . '/includes/html_head.php';
?>

<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
            <h1 class="text-xl font-bold text-gray-800">사회주택 공급현황 관리</h1>
            <div class="flex items-center gap-2">
                <button onclick="openBatchMatch()"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-50 transition flex items-center gap-2">
                    <i class="ph ph-link text-blue-600"></i> 일괄 매칭
                </button>
                <button onclick="openEditModal()"
                    class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition flex items-center gap-2">
                    <i class="ph ph-plus"></i> 주택 등록
                </button>
                <button onclick="exportExcel()"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-50 transition flex items-center gap-2">
                    <i class="ph ph-file-xls text-green-600"></i> 엑셀
                </button>
            </div>
        </header>
        <main class="flex-1 overflow-auto bg-gray-50 p-6 lg:p-8">

            <?php if ($success_msg): ?>
                <div
                    class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm">
                    <i class="ph-fill ph-check-circle text-lg"></i> <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>
            <!-- 요약 카드 -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <p class="text-xs text-gray-500 font-bold uppercase mb-1">전체 공급 주택</p>
                    <h3 class="text-2xl font-bold text-gray-800"><?= number_format($stats['total_count'] ?? 0) ?> <span
                            class="text-sm font-normal text-gray-400">개소</span></h3>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <p class="text-xs text-gray-500 font-bold uppercase mb-1">전체 공급 세대</p>
                    <h3 class="text-2xl font-bold text-primary"><?= number_format($stats['total_households'] ?? 0) ?>
                        <span class="text-sm font-normal text-gray-400">세대</span></h3>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <p class="text-xs text-gray-500 font-bold uppercase mb-1">운영사 매칭률</p>
                    <?php
                    $percent = ($stats['total_count'] ?? 0) > 0 ? round(($matched / $stats['total_count']) * 100, 1) : 0;
                    ?>
                    <h3 class="text-2xl font-bold text-green-600"><?= $percent ?>% <span
                            class="text-sm font-normal text-gray-400">(<?= $matched ?>/<?= $stats['total_count'] ?>)</span>
                    </h3>
                </div>
            </div>
            <!-- 필터 영역 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
                <form action="supply_management.php" method="GET" class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[240px]">
                        <label class="block text-xs font-bold text-gray-500 mb-1">건물명 / 운영사 / 주소 / 회원번호 검색</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="검색어 입력..."
                                class="w-full border border-gray-300 rounded-lg pl-9 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            <i class="ph ph-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="w-32">
                        <label class="block text-xs font-bold text-gray-500 mb-1">회원 구분</label>
                        <select name="member_status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            <option value="">전체</option>
                            <option value="member" <?= $memberStatus === 'member' ? 'selected' : '' ?>>회원사 (1-)</option>
                            <option value="non_member" <?= $memberStatus === 'non_member' ? 'selected' : '' ?>>비회원사 (5-)
                            </option>
                        </select>
                    </div>
                    <div class="w-40">
                        <label class="block text-xs font-bold text-gray-500 mb-1">공급 유형</label>
                        <select name="type"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            <option value="">전체 유형</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $type === $t ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-40">
                        <label class="block text-xs font-bold text-gray-500 mb-1">공급 현황</label>
                        <select name="status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                            <option value="">기본 상태 (4종)</option>
                            <option value="준공" <?= $status === '준공' ? 'selected' : '' ?>>준공</option>
                            <option value="미준공" <?= $status === '미준공' ? 'selected' : '' ?>>미준공</option>
                            <option value="종료" <?= $status === '종료' ? 'selected' : '' ?>>종료</option>
                            <option value="미착공" <?= $status === '미착공' ? 'selected' : '' ?>>미착공</option>
                            <option value="미진행" <?= $status === '미진행' ? 'selected' : '' ?>>미진행 (기타)</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3 self-center pb-2">
                        <label class="flex items-center gap-2 text-sm font-bold text-gray-600 cursor-pointer">
                            <input type="checkbox" name="show_all" value="1" <?= $showAll ? 'checked' : '' ?>
                                class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                            모두 표시
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                            class="bg-primary text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-blue-800 transition">필터
                            적용</button>
                        <a href="supply_management.php"
                            class="bg-gray-100 text-gray-600 px-5 py-2 rounded-lg text-sm font-bold hover:bg-gray-200 transition flex items-center justify-center">초기화</a>
                    </div>
                </form>
            </div>
            <!-- 일괄 작업 바 -->
            <div id="batchActionBar" class="mb-4 hidden">
                <div
                    class="bg-blue-50 border border-blue-200 rounded-xl px-6 py-3 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-bold text-blue-700"><span id="selectedCount">0</span>건 선택됨</span>
                        <div class="h-4 w-px bg-blue-200"></div>
                        <button onclick="openBatchEditModal()"
                            class="text-sm font-bold text-blue-600 hover:underline flex items-center gap-1">
                            <i class="ph ph-pencil-simple"></i> 일괄 수정
                        </button>
                    </div>
                    <button onclick="clearSelection()" class="text-gray-400 hover:text-gray-600"><i
                            class="ph ph-x"></i></button>
                </div>
            </div>
            <!-- 데이터 테이블 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="supplyTable" class="w-full text-sm text-left border-collapse table-fixed">
                        <thead class="text-xs text-gray-400 uppercase font-bold sticky top-0 z-30">
                            <tr>
                                <th class="w-10 px-2 py-4 text-center bg-gray-50 border-b border-gray-200 sticky top-0">
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"
                                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                                </th>
                                <th class="w-14 px-2 py-4 bg-gray-50 border-b border-gray-200 sticky top-0 text-center">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'no', 'order' => ($sort === 'no' && $order === 'ASC' ? 'DESC' : 'ASC')])) ?>"
                                        class="flex items-center justify-center gap-1 hover:text-gray-600">
                                        번호 <?= $sort === 'no' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                                    </a>
                                </th>
                                <th class="w-60 px-2 py-4 bg-gray-50 border-b border-gray-200 sticky top-0">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'building_name', 'order' => ($sort === 'building_name' && $order === 'ASC' ? 'DESC' : 'ASC')])) ?>"
                                        class="flex items-center gap-1 hover:text-gray-600">
                                        주택명 <?= $sort === 'building_name' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                                    </a>
                                </th>
                                <th class="w-16 px-2 py-4 bg-gray-50 border-b border-gray-200 sticky top-0 text-center">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'location_si', 'order' => ($sort === 'location_si' && $order === 'ASC' ? 'DESC' : 'ASC')])) ?>"
                                        class="flex items-center justify-center gap-1 hover:text-gray-600">
                                        광역 <?= $sort === 'location_si' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                                    </a>
                                </th>
                                <th class="w-24 px-2 py-4 bg-gray-50 border-b border-gray-200 sticky top-0 text-center">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'supply_type', 'order' => ($sort === 'supply_type' && $order === 'ASC' ? 'DESC' : 'ASC')])) ?>"
                                        class="flex items-center justify-center gap-1 hover:text-gray-600">
                                        분류 <?= $sort === 'supply_type' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                                    </a>
                                </th>
                                <th class="w-14 px-2 py-4 bg-gray-50 border-b border-gray-200 sticky top-0 text-center">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'household_count', 'order' => ($sort === 'household_count' && $order === 'ASC' ? 'DESC' : 'ASC')])) ?>"
                                        class="flex items-center justify-center gap-1 hover:text-gray-600">
                                        세대 <?= $sort === 'household_count' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                                    </a>
                                </th>
                                <th class="w-48 px-2 py-4 bg-gray-50 border-b border-gray-200 sticky top-0">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'matched_company', 'order' => ($sort === 'matched_company' && $order === 'ASC' ? 'DESC' : 'ASC')])) ?>"
                                        class="flex items-center gap-1 hover:text-gray-600">
                                        운영사 <?= $sort === 'matched_company' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                                    </a>
                                </th>
                                <th class="min-w-[200px] px-2 py-4 bg-gray-50 border-b border-gray-200 sticky top-0">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'address', 'order' => ($sort === 'address' && $order === 'ASC' ? 'DESC' : 'ASC')])) ?>"
                                        class="flex items-center gap-1 hover:text-gray-600">
                                        주소 <?= $sort === 'address' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                                    </a>
                                </th>
                                <th class="w-20 px-2 py-4 bg-gray-50 border-b border-gray-200 sticky top-0 text-center">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'completion_status', 'order' => ($sort === 'completion_status' && $order === 'ASC' ? 'DESC' : 'ASC')])) ?>"
                                        class="flex items-center justify-center gap-1 hover:text-gray-600">
                                        상태 <?= $sort === 'completion_status' ? ($order === 'ASC' ? '▲' : '▼') : '' ?>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($supplies)): ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-400">데이터가 없습니다.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($supplies as $s): ?>
                                    <tr class="hover:bg-gray-50/50 transition group" data-id="<?= $s['id'] ?>">
                                        <td class="px-2 py-2 text-center">
                                            <input type="checkbox" name="row_select" value="<?= $s['id'] ?>"
                                                onchange="updateBatchBar()"
                                                class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                                        </td>
                                        <td class="px-2 py-2 text-center text-[11px] text-gray-400 font-medium">
                                            <?= htmlspecialchars($s['no'] ?: $s['id']) ?></td>
                                        <td class="px-2 py-2">
                                            <div onclick='openEditModal(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                class="text-[11px] font-extrabold text-gray-900 cursor-pointer hover:text-primary hover:underline whitespace-nowrap overflow-hidden text-ellipsis transition-colors leading-none"
                                                title="<?= htmlspecialchars($s['building_name']) ?>">
                                                <?= htmlspecialchars($s['building_name']) ?>
                                            </div>
                                        </td>
                                        <td
                                            class="px-2 py-2 text-center text-[11px] text-gray-500 whitespace-nowrap leading-none">
                                            <?= htmlspecialchars($s['location_si']) ?></td>
                                        <td
                                            class="px-2 py-2 text-center text-[11px] text-gray-500 whitespace-normal leading-none">
                                            <?= htmlspecialchars($s['supply_type']) ?></td>
                                        <td class="px-2 py-2 text-center font-bold text-gray-700 text-[11px]">
                                            <?= number_format($s['household_count']) ?></td>
                                        <td class="px-2 py-2">
                                            <div
                                                class="text-[11px] font-bold text-gray-700 leading-none whitespace-nowrap overflow-hidden text-ellipsis">
                                                <?= htmlspecialchars($s['matched_company'] ?: ($s['operator_name_custom'] ?: $s['company_name_raw'])) ?>
                                            </div>
                                            <?php if (!$s['matched_company'] && !$s['operator_name_custom']): ?>
                                                <span
                                                    class="text-[9px] text-red-400 flex items-center gap-1 leading-none whitespace-nowrap">매칭필요</span>
                                            <?php else: ?>
                                                <span
                                                    class="text-[9px] text-blue-400 leading-none whitespace-nowrap"><?= htmlspecialchars($s['member_no'] ?: '비회원사') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-2 py-2 text-[10px] text-gray-400 whitespace-normal leading-none">
                                            <?= htmlspecialchars($s['address']) ?></td>
                                        <td class="px-2 py-2 text-center">
                                            <span
                                                class="px-2 py-0.5 text-[10px] font-bold rounded-full leading-none
                                            <?= $s['completion_status'] === '준공' ? 'bg-green-100 text-green-800' : ($s['completion_status'] === '미준공' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                                <?= htmlspecialchars($s['completion_status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <!-- 등록/수정 모달 -->
    <div id="editModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-800" id="modalTitle">주택 정보 수정</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i
                        class="ph ph-x text-2xl"></i></button>
            </div>
            <form action="supply_management.php" method="POST" class="p-6">
                <input type="hidden" name="action" value="update_supply">
                <input type="hidden" name="id" id="editId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">회원번호</label>
                        <input type="text" name="member_no" id="editMemberNo" placeholder="예: 1-001"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-gray-500 mb-1">회원사 매칭</label>
                        <select name="member_id" id="editMemberId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            <option value="">-- 자동 매칭 또는 미지정 --</option>
                            <?php foreach ($memberList as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['company_name']) ?>
                                    (<?= $m['member_no'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-500 mb-1">주택명</label>
                        <input type="text" name="building_name" id="editBuildingName" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-gray-500 mb-1">운영사명 직접입력 (비회원)</label>
                        <input type="text" name="operator_name_custom" id="editOperatorNameCustom"
                            placeholder="비회원사인 경우 직접 입력"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-[10px] text-gray-400 mt-[-8px]">운영사(원천): <span id="rawName"></span></p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-500 mb-1">변경 사유 (수정 시 필수)</label>
                        <input type="text" name="change_reason" placeholder="운영사 변경 사유를 입력하세요"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">대분류</label>
                        <input type="text" name="supply_type" id="editSupplyType"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                            list="typeList">
                        <datalist id="typeList">
                            <?php foreach ($types as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">소분류</label>
                        <input type="text" name="supply_type2" id="editSupplyType2"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">공공주체</label>
                        <input type="text" name="public_entity" id="editPublicEntity"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-500 mb-1">주소</label>
                        <input type="text" name="address" id="editAddress" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">세대수</label>
                        <input type="number" name="household_count" id="editHouseholdCount" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">준공년월</label>
                        <input type="text" name="completion_date" id="editCompletionDate" placeholder="예: 2024-01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-500 mb-1">공급 현황</label>
                        <select name="completion_status" id="editCompletionStatus"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            <option value="준공">준공</option>
                            <option value="미준공">미준공</option>
                            <option value="종료">종료</option>
                            <option value="미착공">미착공</option>
                            <option value="종결">종결</option>
                            <option value="취소">취소</option>
                        </select>
                    </div>
                </div>
                <!-- 운영 이력 (타임라인) -->
                <div id="historySection" class="mt-8 border-t border-gray-100 pt-6 hidden">
                    <h4
                        class="text-[11px] font-bold text-gray-400 uppercase mb-4 flex items-center gap-2 tracking-wider">
                        <i class="ph ph-clock-counter-clockwise text-blue-500"></i> 변경 이력
                    </h4>
                    <div id="historyTimeline" class="space-y-4 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                        <!-- AJAX로 데이터 로드됨 -->
                    </div>
                </div>
                <div class="flex gap-2 mt-8">
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50 transition">취소</button>
                    <button type="button" id="btnDelete" onclick="confirmDelete()"
                        class="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-bold hover:bg-red-100 transition flex items-center gap-2">
                        <i class="ph ph-trash"></i> 휴지통으로
                    </button>
                    <button type="submit"
                        class="flex-[2] px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-blue-800 transition">저장하기</button>
                </div>
            </form>
        </div>
    </div>
    <!-- 일괄 수정 모달 -->
    <div id="batchEditModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-800">선택 항목 일괄 수정</h3>
                <button onclick="closeBatchEditModal()" class="text-gray-400 hover:text-gray-600"><i
                        class="ph ph-x text-2xl"></i></button>
            </div>
            <form action="supply_management.php" method="POST" class="p-6">
                <input type="hidden" name="action" value="batch_update_supply">
                <div id="batchIdsContainer"></div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">공급 현황 변경</label>
                        <select name="batch_status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            <option value="">-- 변경하지 않음 --</option>
                            <option value="준공">준공</option>
                            <option value="미준공">미준공</option>
                            <option value="종료">종료</option>
                            <option value="미착공">미착공</option>
                            <option value="종결">종결</option>
                            <option value="취소">취소</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">회원사 일괄 매칭</label>
                        <select name="batch_member_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary">
                            <option value="">-- 변경하지 않음 --</option>
                            <?php foreach ($memberList as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['company_name']) ?>
                                    (<?= $m['member_no'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 mt-8">
                    <button type="button" onclick="closeBatchEditModal()"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50 transition">취소</button>
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-blue-800 transition">일괄
                        저장</button>
                </div>
            </form>
        </div>
    </div>
    <form id="batchMatchForm" action="supply_management.php" method="POST">
        <input type="hidden" name="action" value="batch_match_members">
    </form>
    <form id="deleteForm" action="supply_management.php" method="POST">
        <input type="hidden" name="action" value="delete_supply">
        <input type="hidden" name="id" id="deleteId">
    </form>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script>
        async function loadHistory(supplyId) {
            const res = await fetch(`get_supply_history.php?id=${supplyId}`);
            const data = await res.json();
            const container = document.getElementById('historyTimeline');
            container.innerHTML = '';

            if (data.length === 0) {
                container.innerHTML = '<p class="text-[11px] text-gray-400 py-4 text-center">기록된 이력이 없습니다.</p>';
                return;
            }
            data.forEach(h => {
                const div = document.createElement('div');
                div.className = 'relative pl-6 pb-4 border-l-2 border-gray-100 last:border-0';
                div.innerHTML = `
            <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-white border-2 border-blue-400"></div>
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[10px] font-bold text-blue-500 bg-blue-50 px-1.5 py-0.5 rounded">${h.field_name}</span>
                    <span class="text-[10px] text-gray-400 ml-2">${h.created_at}</span>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="editHistory(${h.id}, '${h.new_value}', '${h.change_reason}')" class="text-gray-400 hover:text-blue-500 transition"><i class="ph ph-pencil-simple text-xs"></i></button>
                    <button type="button" onclick="deleteHistory(${h.id})" class="text-gray-400 hover:text-red-500 transition"><i class="ph ph-trash text-xs"></i></button>
                </div>
            </div>
            <p class="text-[11px] text-gray-700 mt-1 font-medium">
                <span class="text-gray-400 line-through mr-1">${h.old_value || '(없음)'}</span> → 
                <span class="text-blue-600">${h.new_value || '(없음)'}</span>
            </p>
            <p class="text-[10px] text-gray-400 mt-0.5">사유: ${h.change_reason || '-'}</p>
        `;
                container.appendChild(div);
            });
        }
        async function editHistory(id, currentVal, currentReason) {
            const newVal = prompt("새로운 값을 입력하세요:", currentVal);
            if (newVal === null) return;
            const newReason = prompt("수정 사유를 입력하세요:", currentReason);
            if (newReason === null) return;
            const formData = new FormData();
            formData.append('action', 'update_history');
            formData.append('history_id', id);
            formData.append('new_value', newVal);
            formData.append('change_reason', newReason);
            const res = await fetch('supply_management.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) loadHistory(document.getElementById('editId').value);
        }
        async function deleteHistory(id) {
            if (!confirm('이 이력을 정말 삭제하시겠습니까?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_history');
            formData.append('history_id', id);
            const res = await fetch('supply_management.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) loadHistory(document.getElementById('editId').value);
        }
        function openEditModal(data = null) {
            const modal = document.getElementById('editModal');
            const title = document.getElementById('modalTitle');

            const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
            const setText = (id, txt) => { const el = document.getElementById(id); if (el) el.innerText = txt ?? ''; };
            const show = (id) => { const el = document.getElementById(id); if (el) el.classList.remove('hidden'); };
            const hide = (id) => { const el = document.getElementById(id); if (el) el.classList.add('hidden'); };
            if (data) {
                title.innerText = '주택 정보 상세/수정';
                setVal('editId', data.id);
                setVal('editBuildingName', data.building_name);
                setVal('editAddress', data.address);
                setVal('editHouseholdCount', data.household_count);
                setVal('editSupplyType', data.supply_type || '');
                setVal('editSupplyType2', data.supply_type2 || '');
                setVal('editPublicEntity', data.public_entity || '');
                setVal('editCompletionDate', data.completion_date || '');
                setVal('editCompletionStatus', data.completion_status || '준공');
                setVal('editMemberId', data.member_id || '');
                setVal('editMemberNo', data.member_no || '');
                setVal('editOperatorNameCustom', data.operator_name_custom || '');
                setText('rawName', data.company_name_raw || '-');
                show('btnDelete');
                show('historySection');
                loadHistory(data.id);
            } else {
                title.innerText = '새 주택 등록';
                setVal('editId', '');
                setVal('editBuildingName', '');
                setVal('editAddress', '');
                setVal('editHouseholdCount', '0');
                setVal('editSupplyType', '');
                setVal('editSupplyType2', '');
                setVal('editPublicEntity', '');
                setVal('editCompletionDate', '');
                setVal('editCompletionStatus', '준공');
                setVal('editMemberId', '');
                setVal('editMemberNo', '');
                setVal('editOperatorNameCustom', '');
                setText('rawName', '-');
                hide('btnDelete');
                hide('historySection');
            }

            modal.classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        function confirmDelete() {
            const id = document.getElementById('editId').value;
            if (!id) return;
            if (confirm('이 주택 정보를 휴지통으로 이동하시겠습니까?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        function openBatchMatch() {
            if (confirm('회원번호가 입력된 항목 중 매칭되지 않은 항목을 자동으로 매칭하시겠습니까?')) {
                document.getElementById('batchMatchForm').submit();
            }
        }
        function toggleSelectAll(el) {
            const checkboxes = document.getElementsByName('row_select');
            checkboxes.forEach(cb => cb.checked = el.checked);
            updateBatchBar();
        }
        function updateBatchBar() {
            const checkboxes = document.getElementsByName('row_select');
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            const bar = document.getElementById('batchActionBar');
            const countEl = document.getElementById('selectedCount');

            if (selected.length > 0) {
                bar.classList.remove('hidden');
                countEl.innerText = selected.length;
            } else {
                bar.classList.add('hidden');
            }
        }
        function clearSelection() {
            const checkboxes = document.getElementsByName('row_select');
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBatchBar();
        }
        function openBatchEditModal() {
            const checkboxes = document.getElementsByName('row_select');
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            const container = document.getElementById('batchIdsContainer');
            container.innerHTML = '';

            selected.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });

            document.getElementById('batchEditModal').classList.remove('hidden');
        }
        function closeBatchEditModal() {
            document.getElementById('batchEditModal').classList.add('hidden');
        }
        function exportExcel() {
            const table = document.getElementById('supplyTable');
            const wb = XLSX.utils.table_to_book(table, { sheet: "공급현황" });
            XLSX.writeFile(wb, "사회주택_공급현황_<?= date('Ymd') ?>.xlsx");
        }
    </script>
</body>

</html>