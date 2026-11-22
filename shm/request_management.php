<?php
/**
 * request_management.php - 관리자용 회원사 권한 신청 관리 화면
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = '권한 신청 관리';
$currentPage = 'auth_requests';

// 승인/반려 처리 로직
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $reqId = (int)$_POST['request_id'];
    $action = $_POST['action'];

    if (in_array($action, ['approved', 'rejected'])) {
        $stmt = $pdo->prepare("UPDATE tb_user_company_requests SET status = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$action, $reqId]);
        
        // PRG 패턴 방지
        header('Location: request_management.php');
        exit;
    }
}

// 신청 목록 가져오기
$stmt = $pdo->query("
    SELECT r.id, r.status, r.memo, r.requested_at, r.approved_at,
           u.name AS user_name, u.email, u.sns_provider,
           m.company_name
    FROM tb_user_company_requests r
    JOIN tb_users u ON r.user_id = u.id
    JOIN tb_members m ON r.member_id = m.id
    ORDER BY r.requested_at DESC
");
$requests = $stmt->fetchAll();

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
    <!-- 헤더 -->
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i class="ph ph-shield-check text-primary"></i> 회원사 권한 신청 관리
        </h1>
    </header>

    <!-- 본문 -->
    <main class="flex-1 overflow-auto p-6 lg:p-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 font-bold">신청일시</th>
                            <th class="px-6 py-4 font-bold">신청 회원사</th>
                            <th class="px-6 py-4 font-bold">신청자 정보(SNS)</th>
                            <th class="px-6 py-4 font-bold">메모 (신원 확인용)</th>
                            <th class="px-6 py-4 font-bold">상태</th>
                            <th class="px-6 py-4 font-bold text-center">관리</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(empty($requests)): ?>
                            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">신청 내역이 없습니다.</td></tr>
                        <?php else: ?>
                            <?php foreach ($requests as $r): ?>
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-4 text-gray-500 whitespace-nowrap"><?= date('Y-m-d H:i', strtotime($r['requested_at'])) ?></td>
                                    <td class="px-6 py-4 font-bold text-gray-800 whitespace-nowrap"><?= htmlspecialchars($r['company_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($r['user_name']) ?></div>
                                        <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($r['email']) ?> (<?= htmlspecialchars(ucfirst($r['sns_provider'])) ?>)</div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($r['memo']) ?>">
                                        <?= nl2br(htmlspecialchars($r['memo'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <span class="px-2.5 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">대기중</span>
                                        <?php elseif ($r['status'] === 'approved'): ?>
                                            <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">승인됨</span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">반려됨</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap">
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <form method="POST" class="inline-flex gap-2">
                                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                                <button type="submit" name="action" value="approved" class="px-3 py-1.5 bg-blue-600 text-white rounded text-xs font-semibold hover:bg-blue-700 transition shadow-sm">승인</button>
                                                <button type="submit" name="action" value="rejected" class="px-3 py-1.5 bg-white border border-red-200 text-red-600 rounded text-xs font-semibold hover:bg-red-50 transition shadow-sm" onclick="return confirm('이 권한 신청을 반려하시겠습니까?');">반려</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400"><?= date('y.m.d', strtotime($r['approved_at'])) ?> 처리됨</span>
                                        <?php endif; ?>
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
</body>
</html>
