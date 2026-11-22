<?php
/**
 * login.php - 관리자 로그인 페이지
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// 이미 로그인 중이면 대시보드로
if (isset($_SESSION['admin_id'])) {
    header('Location: /shm/');
    exit;
}

require_once __DIR__ . '/includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM tb_admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: /shm/');
            exit;
        } else {
            $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
        }
    } else {
        $error = '아이디와 비밀번호를 모두 입력해주세요.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 | 한국사회주택협회 관리시스템</title>
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="/static/vendor/js/phosphor-icons.js"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['"Noto Sans KR"', 'sans-serif'] }, colors: { primary: '#1e3a8a', secondary: '#3b82f6' } } }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-primary to-secondary min-h-screen flex items-center justify-center font-sans">
    <div class="w-full max-w-md px-6">
        <!-- 로고 영역 -->
        <div class="text-center mb-8">
            <img src="/shm/assets/logo-new-big.jpg" alt="한국사회주택협회 로고" class="h-24 mx-auto mb-4 rounded-xl shadow-lg object-contain bg-white p-2">
            <h1 class="text-2xl font-bold text-white">한국사회주택협회</h1>
            <p class="text-blue-200 text-sm mt-1">회원·회비 통합 관리 시스템</p>
        </div>

        <!-- 로그인 카드 -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-bold text-gray-800 mb-2 text-center">로그인</h2>
            <p class="text-sm text-gray-500 text-center mb-6">회원사 담당자는 소셜 계정으로 로그인해주세요.</p>

            <!-- SNS 로그인 버튼 목록 -->
            <div class="flex flex-col gap-3 mb-6">
                <button onclick="location.href='sns_login.php?provider=kakao'" class="w-full bg-[#FEE500] text-[#000000] py-2.5 rounded-lg font-semibold text-sm hover:bg-[#F4DC00] transition-colors shadow-sm flex items-center justify-center gap-2">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/e/e3/KakaoTalk_logo.svg" alt="Kakao" class="w-5 h-5"> 카카오로 시작하기
                </button>
                <button onclick="location.href='sns_login.php?provider=naver'" class="w-full bg-[#03C75A] text-white py-2.5 rounded-lg font-semibold text-sm hover:bg-[#02b351] transition-colors shadow-sm flex items-center justify-center gap-2">
                    <span class="font-bold text-lg leading-none mb-0.5">N</span> 네이버로 시작하기
                </button>
                <button onclick="location.href='sns_login.php?provider=google'" class="w-full bg-white border border-gray-300 text-gray-700 py-2.5 rounded-lg font-semibold text-sm hover:bg-gray-50 transition-colors shadow-sm flex items-center justify-center gap-2">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="w-5 h-5"> Google로 시작하기
                </button>
                <button onclick="location.href='sns_login.php?provider=facebook'" class="w-full bg-[#1877F2] text-white py-2.5 rounded-lg font-semibold text-sm hover:bg-[#166fe5] transition-colors shadow-sm flex items-center justify-center gap-2">
                    <i class="ph-fill ph-facebook-logo text-xl"></i> Facebook으로 시작하기
                </button>
            </div>

            <div class="relative flex items-center py-2 mb-4">
                <div class="flex-grow border-t border-gray-200"></div>
                <span class="flex-shrink-0 mx-4 text-gray-400 text-xs">또는 관리자로 로그인</span>
                <div class="flex-grow border-t border-gray-200"></div>
            </div>

            <?php if ($error): ?>
                <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg mb-5">
                    <i class="ph ph-warning-circle text-lg flex-shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <div class="relative">
                        <i class="ph ph-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                        <input type="text" id="username" name="username" required
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="관리자 아이디"
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition">
                    </div>
                </div>
                <div class="mb-5">
                    <div class="relative">
                        <i class="ph ph-lock-simple absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
                        <input type="password" id="password" name="password" required
                               placeholder="관리자 비밀번호"
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition">
                    </div>
                </div>
                <button type="submit"
                        class="w-full bg-gray-800 text-white py-2.5 rounded-lg font-semibold text-sm hover:bg-gray-900 transition-colors shadow-sm flex items-center justify-center gap-2">
                    <i class="ph ph-sign-in text-lg"></i> 관리자 로그인
                </button>
            </form>
        </div>
        <p class="text-center text-blue-200 text-xs mt-6">© 2026 (사)한국사회주택협회</p>
    </div>
</body>
</html>

