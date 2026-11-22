<?php
/**
 * pending_auth.php - 소속 회원사 권한 승인 대기 화면
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.*, m.company_name 
    FROM tb_user_company_requests r
    JOIN tb_members m ON r.member_id = m.id
    WHERE r.user_id = ? ORDER BY r.requested_at DESC LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: request_auth.php');
    exit;
}

if ($request['status'] === 'approved') {
    $_SESSION['member_id'] = $request['member_id'];
    header('Location: member_index.php');
    exit;
} elseif ($request['status'] === 'rejected') {
    header('Location: request_auth.php?rejected=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>승인 대기 중 | 한국사회주택협회</title>
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
    <div class="w-full max-w-md px-6">
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-50 rounded-full mb-6">
                <i class="ph-fill ph-hourglass-high text-4xl text-secondary animate-pulse"></i>
            </div>
            
            <h2 class="text-2xl font-bold text-gray-800 mb-2">관리자 승인 대기 중</h2>
            
            <div class="bg-gray-50 rounded-lg p-4 my-6 text-left border border-gray-100">
                <p class="text-sm text-gray-600 mb-1"><span class="font-semibold w-20 inline-block text-gray-800">신청 회원사:</span> <?= htmlspecialchars($request['company_name']) ?></p>
                <p class="text-sm text-gray-600"><span class="font-semibold w-20 inline-block text-gray-800">신청 일시:</span> <?= date('Y-m-d H:i', strtotime($request['requested_at'])) ?></p>
            </div>
            
            <p class="text-sm text-gray-500 mb-6 leading-relaxed">
                현재 관리자가 제출해주신 담당자 정보를 확인하고 있습니다.<br>
                승인이 완료되면 시스템을 정상적으로 이용하실 수 있습니다.
            </p>

            <div class="flex flex-col gap-3">
                <button onclick="location.reload()" class="w-full bg-primary text-white py-3 rounded-lg font-bold text-sm hover:bg-blue-900 transition-colors shadow-sm flex items-center justify-center gap-2">
                    <i class="ph-bold ph-arrows-clockwise text-lg"></i> 새로고침 (상태 확인)
                </button>
                <a href="logout.php" class="text-sm text-gray-400 hover:text-gray-600 underline mt-2">로그아웃</a>
            </div>
        </div>
    </div>
</body>
</html>

