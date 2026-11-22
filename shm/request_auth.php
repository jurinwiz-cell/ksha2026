<?php
/**
 * request_auth.php - 소속 회원사 권한 신청 화면
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// 회원사 목록 가져오기 (가나다 순)
$stmt = $pdo->query("SELECT id, company_name FROM tb_members WHERE status = '운영' ORDER BY company_name ASC");
$members = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'] ?? '';
    $memo = trim($_POST['memo'] ?? '');

    if ($member_id && $memo) {
        try {
            $insertStmt = $pdo->prepare("INSERT INTO tb_user_company_requests (user_id, member_id, memo, status) VALUES (?, ?, ?, 'pending')");
            $insertStmt->execute([$_SESSION['user_id'], $member_id, $memo]);
            
            header('Location: pending_auth.php');
            exit;
        } catch (Exception $e) {
            $error = '신청 처리 중 오류가 발생했습니다.';
        }
    } else {
        $error = '회원사를 선택하고 소속 증빙 메모를 입력해주세요.';
    }
}

$rejected = isset($_GET['rejected']) && $_GET['rejected'] == '1';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>권한 신청 | 한국사회주택협회</title>
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="/static/vendor/js/phosphor-icons.js"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['"Noto Sans KR"', 'sans-serif'] }, colors: { primary: '#1e3a8a', secondary: '#3b82f6' } } }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center font-sans">
    <div class="w-full max-w-lg px-6">
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <div class="text-center mb-6">
                <i class="ph-fill ph-shield-check text-5xl text-primary mb-3"></i>
                <h2 class="text-2xl font-bold text-gray-800">소속 회원사 권한 신청</h2>
                <p class="text-sm text-gray-500 mt-2">환영합니다, <?= htmlspecialchars($_SESSION['user_name']) ?>님!<br>회원사 전용 메뉴를 이용하시려면 소속 회원사를 선택해 주세요.</p>
            </div>

            <?php if ($rejected): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg mb-5 flex gap-2">
                    <i class="ph-fill ph-warning-circle text-lg"></i>
                    <span>이전 신청이 반려되었습니다. 정보를 정확히 확인 후 다시 신청해 주세요.</span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg mb-5">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-5">
                    <label for="member_id" class="block text-sm font-bold text-gray-700 mb-2">소속 회원사 선택 <span class="text-red-500">*</span></label>
                    <select id="member_id" name="member_id" required class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-gray-50">
                        <option value="">회원사를 검색/선택하세요</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label for="memo" class="block text-sm font-bold text-gray-700 mb-2">신청자 정보 (부서/직급/연락처 등) <span class="text-red-500">*</span></label>
                    <p class="text-xs text-gray-500 mb-2">관리자가 담당자 신원을 확인할 수 있도록 소속 정보 및 연락처를 남겨주세요.</p>
                    <textarea id="memo" name="memo" required rows="3" placeholder="예: 관리팀 홍길동 대리입니다. 연락처: 010-1234-5678" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-gray-50"></textarea>
                </div>

                <button type="submit" class="w-full bg-primary text-white py-3.5 rounded-lg font-bold text-sm hover:bg-blue-900 transition-colors shadow-md flex items-center justify-center gap-2">
                    <i class="ph-fill ph-paper-plane-tilt text-lg"></i> 권한 신청하기
                </button>
            </form>
            <div class="mt-4 text-center">
                <a href="logout.php" class="text-sm text-gray-400 hover:text-gray-600 underline">로그아웃</a>
            </div>
        </div>
    </div>
</body>
</html>

