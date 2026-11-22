<?php
/**
 * member_index.php - 회원사 전용 대시보드
 */
require_once __DIR__ . '/includes/member_auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '회원사 대시보드';
$currentPage = 'dashboard';

$memberId = $_SESSION['member_id'];

// 1. 자사 기본 정보 조회
$stmt = $pdo->prepare("
    SELECT m.*, d.biz_no, d.address, d.email 
    FROM tb_members m 
    LEFT JOIN tb_member_details d ON m.id = d.member_id 
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$company = $stmt->fetch();

if (!$company) {
    die("회원사 정보를 찾을 수 없습니다.");
}

// 2. 현재 적용 중인 월회비 기준액
$feeStmt = $pdo->prepare("SELECT monthly_fee FROM tb_member_fee_history WHERE member_id = ? ORDER BY start_date DESC LIMIT 1");
$feeStmt->execute([$memberId]);
$currentFee = (int)$feeStmt->fetchColumn();

// 3. 미납/납부 현황 (최근 12개월 기준 간략 집계)
$paymentStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(amount) as total_amount
    FROM tb_dues_payments 
    WHERE member_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
");
$paymentStmt->execute([$memberId]);
$paymentStats = $paymentStmt->fetch();

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/member_sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <h1 class="text-xl font-bold text-gray-800">대시보드</h1>
        <div class="text-sm text-gray-500">
            <span class="font-bold text-primary"><?= htmlspecialchars($company['company_name']) ?></span> 환영합니다.
        </div>
    </header>

    <main class="flex-1 overflow-auto bg-gray-50 p-6 lg:p-8">
        <!-- 환영 메시지 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">안녕하세요, <?= htmlspecialchars($_SESSION['user_name']) ?>님! 👋</h2>
            <p class="text-gray-500">한국사회주택협회 회원사 전용 포털입니다. 귀사의 정보를 관리하고 회비 내역을 확인하실 수 있습니다.</p>
        </div>

        <!-- 요약 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                    <i class="ph-fill ph-buildings"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">소속 회원사</p>
                    <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($company['company_name']) ?></h3>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-2xl">
                    <i class="ph-fill ph-money"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">현재 월회비 기준액</p>
                    <h3 class="text-lg font-bold text-gray-800"><?= number_format($currentFee) ?>원</h3>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-4 relative overflow-hidden">
                <div class="absolute -right-4 -bottom-4 opacity-5">
                    <i class="ph-fill ph-house-line text-8xl"></i>
                </div>
                <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center text-2xl relative z-10">
                    <i class="ph-fill ph-house-line"></i>
                </div>
                <div class="relative z-10">
                    <p class="text-sm text-gray-500 font-medium">주택 공급현황</p>
                    <h3 class="text-lg font-bold text-gray-800">업데이트 준비중</h3>
                </div>
            </div>
        </div>

        <!-- 빠른 메뉴 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">빠른 이동</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="member_info.php" class="block p-4 border border-gray-200 rounded-lg hover:border-primary hover:shadow-md transition text-center group">
                    <i class="ph ph-identification-card text-3xl text-gray-400 group-hover:text-primary mb-2 transition"></i>
                    <p class="font-bold text-gray-700 group-hover:text-primary transition">자사 정보 수정</p>
                </a>
                <a href="member_dues.php" class="block p-4 border border-gray-200 rounded-lg hover:border-primary hover:shadow-md transition text-center group">
                    <i class="ph ph-receipt text-3xl text-gray-400 group-hover:text-primary mb-2 transition"></i>
                    <p class="font-bold text-gray-700 group-hover:text-primary transition">회비 내역 조회</p>
                </a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
