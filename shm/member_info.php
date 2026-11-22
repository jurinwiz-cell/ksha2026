<?php
/**
 * member_info.php - 자사 정보 관리 (회원사 전용)
 */
require_once __DIR__ . '/includes/member_auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '자사 정보 관리';
$currentPage = 'my_info';

$memberId = $_SESSION['member_id'];
$success_msg = '';
$error_msg = '';

// 정보 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $ceo_name = trim($_POST['ceo_name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $biz_no = trim($_POST['biz_no'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $homepage = trim($_POST['homepage'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $established_date = $_POST['established_date'] ?: null;

    if (!$company_name) {
        $error_msg = '조직명(회사명)은 필수입니다.';
    } else {
        try {
            // [변경 로그 기록] 기존 데이터와 비교를 위해 현재 데이터 조회
            $oldStmt = $pdo->prepare("
                SELECT m.company_name, m.ceo_name, m.contact, d.biz_no, d.address, d.homepage, d.email, d.established_date
                FROM tb_members m 
                LEFT JOIN tb_member_details d ON m.id = d.member_id 
                WHERE m.id = ?
            ");
            $oldStmt->execute([$memberId]);
            $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);

            $pdo->beginTransaction();

            // tb_members 업데이트
            $stmt = $pdo->prepare("UPDATE tb_members SET company_name = ?, ceo_name = ?, contact = ? WHERE id = ?");
            $stmt->execute([$company_name, $ceo_name, $contact, $memberId]);

            // tb_member_details 업데이트
            $stmtDet = $pdo->prepare("
                INSERT INTO tb_member_details (member_id, biz_no, address, homepage, email, established_date)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                biz_no=VALUES(biz_no), address=VALUES(address), homepage=VALUES(homepage), email=VALUES(email), established_date=VALUES(established_date)
            ");
            $stmtDet->execute([$memberId, $biz_no, $address, $homepage, $email, $established_date]);

            // 비교할 필드 맵 (DB필드명 => 표시명)
            $trackFields = [
                'company_name' => '조직명',
                'ceo_name' => '대표자명',
                'contact' => '연락처',
                'biz_no' => '사업자번호',
                'address' => '주소',
                'homepage' => '홈페이지',
                'email' => '이메일',
                'established_date' => '설립일'
            ];

            $newDataMap = [
                'company_name' => $company_name,
                'ceo_name' => $ceo_name,
                'contact' => $contact,
                'biz_no' => $biz_no,
                'address' => $address,
                'homepage' => $homepage,
                'email' => $email,
                'established_date' => $established_date
            ];

            // 변경 사항 로그 기록
            $logStmt = $pdo->prepare("
                INSERT INTO tb_member_history (member_id, field_name, old_value, new_value, changed_by, change_reason)
                VALUES (?, ?, ?, ?, ?, '회원사 직접 수정')
            ");

            foreach ($trackFields as $fKey => $fLabel) {
                $oldV = (string)($oldData[$fKey] ?? '');
                $newV = (string)($newDataMap[$fKey] ?? '');
                if ($oldV !== $newV) {
                    $logStmt->execute([$memberId, $fLabel, $oldV, $newV, ($_SESSION['user_name'] ?? '담당자') . ' (회원사)']);
                }
            }

            $pdo->commit();
            $success_msg = '정보가 성공적으로 수정되었습니다.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = '저장 중 오류가 발생했습니다: ' . $e->getMessage();
        }
    }
}

// 회원 정보 조회
$stmt = $pdo->prepare("
    SELECT m.*, d.biz_no, d.address, d.email, d.homepage, d.established_date, d.memo 
    FROM tb_members m 
    LEFT JOIN tb_member_details d ON m.id = d.member_id 
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$company = $stmt->fetch();

if (!$company) {
    die("회원사 정보를 찾을 수 없습니다.");
}

// 최근 변경 이력 조회 (10건)
$historyStmt = $pdo->prepare("SELECT * FROM tb_member_history WHERE member_id = ? ORDER BY changed_at DESC LIMIT 10");
$historyStmt->execute([$memberId]);
$changeLogs = $historyStmt->fetchAll();

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/member_sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <h1 class="text-xl font-bold text-gray-800">자사 정보 관리</h1>
        <div class="text-sm text-gray-500">
            <span class="font-bold text-primary"><?= htmlspecialchars($company['company_name']) ?></span>
        </div>
    </header>

    <main class="flex-1 overflow-auto bg-gray-50 p-6 lg:p-8">
        <?php if ($success_msg): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
                <i class="ph-fill ph-check-circle text-lg"></i> <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
                <i class="ph-fill ph-warning-circle text-lg"></i> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-3xl mb-8">
            <h2 class="text-lg font-bold text-gray-800 mb-6 border-b pb-3">기본 및 상세 정보</h2>
            
            <form method="POST" action="">
                <!-- 읽기 전용 정보 (관리자 전용) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">회원번호</label>
                        <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($company['member_no'] ?? '-') ?></div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">회원구분</label>
                        <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($company['member_type']) ?></div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">상태</label>
                        <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($company['status']) ?></div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">가입일</label>
                        <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($company['join_date'] ?? '-') ?></div>
                    </div>
                </div>

                <!-- 수정 가능한 정보 -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="company_name" class="block text-sm font-bold text-gray-700 mb-2">조직명 (회사명) <span class="text-red-500">*</span></label>
                        <input type="text" id="company_name" name="company_name" required value="<?= htmlspecialchars($company['company_name']) ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="ceo_name" class="block text-sm font-bold text-gray-700 mb-2">대표자명</label>
                        <input type="text" id="ceo_name" name="ceo_name" value="<?= htmlspecialchars($company['ceo_name'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="biz_no" class="block text-sm font-bold text-gray-700 mb-2">사업자등록번호 / 고유번호</label>
                        <input type="text" id="biz_no" name="biz_no" value="<?= htmlspecialchars($company['biz_no'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="contact" class="block text-sm font-bold text-gray-700 mb-2">회사 대표 연락처</label>
                        <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($company['contact'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-bold text-gray-700 mb-2">대표 이메일</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($company['email'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="established_date" class="block text-sm font-bold text-gray-700 mb-2">설립일</label>
                        <input type="date" id="established_date" name="established_date" value="<?= htmlspecialchars($company['established_date'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-bold text-gray-700 mb-2">주소</label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($company['address'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label for="homepage" class="block text-sm font-bold text-gray-700 mb-2">홈페이지</label>
                        <input type="url" id="homepage" name="homepage" placeholder="https://" value="<?= htmlspecialchars($company['homepage'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-100 mt-6">
                    <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-blue-800 transition-colors shadow-sm flex items-center gap-2">
                        <i class="ph-bold ph-floppy-disk text-lg"></i> 정보 저장
                    </button>
                </div>
            </form>
        </div>

        <!-- 변경 이력 섹션 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-3xl">
            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ph ph-clock-counter-clockwise text-primary"></i> 최근 정보 변경 이력
            </h2>
            <div class="overflow-hidden border border-gray-100 rounded-lg">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-4 py-3">변경일시</th>
                            <th class="px-4 py-3">항목</th>
                            <th class="px-4 py-3">변경 내용</th>
                            <th class="px-4 py-3">수정자</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($changeLogs)): ?>
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">변경 이력이 없습니다.</td></tr>
                        <?php else: ?>
                            <?php foreach ($changeLogs as $log): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-4 py-3 text-xs text-gray-500"><?= date('Y-m-d H:i', strtotime($log['changed_at'])) ?></td>
                                    <td class="px-4 py-3 font-medium text-gray-700"><?= htmlspecialchars($log['field_name']) ?></td>
                                    <td class="px-4 py-3 text-xs">
                                        <div class="text-gray-400 line-through mb-1">기존: <?= htmlspecialchars($log['old_value'] ?: '(없음)') ?></div>
                                        <div class="text-blue-600 font-bold">변경: <?= htmlspecialchars($log['new_value'] ?: '(없음)') ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500"><?= htmlspecialchars($log['changed_by']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
