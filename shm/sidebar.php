<?php
/**
 * includes/sidebar.php - 공통 사이드바
 * $currentPage 변수를 설정해두면 활성 메뉴가 하이라이트됩니다.
 * 예: $currentPage = 'dashboard'; | 'members' | 'dues' | 'settings'
 */
$currentPage = $currentPage ?? '';
$nav = [
    'dashboard'     => ['href' => '/shm/', 'icon' => 'ph-squares-four', 'label' => '대시보드'],
    'members'       => ['href' => '/shm/member_management.php', 'icon' => 'ph-users', 'label' => '회원 관리'],
    'dues'          => ['href' => '/shm/dues_management.php', 'icon' => 'ph-coins', 'label' => '회비 관리'],
    'supply_stats'  => ['href' => '/shm/supply_dashboard.php', 'icon' => 'ph-chart-pie', 'label' => '공급현황 통계'],
    'supply'        => ['href' => '/shm/supply_management.php', 'icon' => 'ph-house-line', 'label' => '공급현황 관리'],
    'reports'       => ['href' => '/shm/member_report.php', 'icon' => 'ph-chart-bar', 'label' => '회원사별 현황'],
    'status_report' => ['href' => '/shm/status_report.php', 'icon' => 'ph-file-text', 'label' => '총괄 납부현황'],
    'unpaid_report' => ['href' => '/shm/unpaid_report.php', 'icon' => 'ph-warning-circle', 'label' => '장기 미납 현황'],
    'auth_requests' => ['href' => '/shm/request_management.php', 'icon' => 'ph-shield-check', 'label' => '권한 신청 관리'],
    'archive'       => ['href' => '/shm/archive/dashboard.php', 'icon' => 'ph-file-text', 'label' => '아카이브'],
    'settings'      => ['href' => '/shm/settings.php', 'icon' => 'ph-gear', 'label' => '시스템 관리'],
];
?>
<aside class="w-64 bg-primary text-white flex flex-col hidden md:flex flex-shrink-0">
    <div class="h-16 flex items-center justify-center border-b border-blue-800 px-4">
        <img src="/shm/assets/logo-new-big.jpg" alt="한국사회주택협회 로고" class="h-10 object-contain bg-white px-2 py-1 rounded">
    </div>
    <nav class="flex-1 px-4 py-6 space-y-1">
        <?php foreach ($nav as $key => $item): ?>
            <?php $isActive = ($currentPage === $key); ?>
            <a href="<?= $item['href'] ?>"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors
                      <?= $isActive ? 'bg-blue-800 text-white font-medium' : 'text-gray-300 hover:bg-blue-800 hover:text-white' ?>">
                <i class="ph <?= $item['icon'] ?> text-xl"></i>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-blue-800">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-sm font-bold">
                <?= mb_substr($_SESSION['admin_name'] ?? '관', 0, 1) ?>
            </div>
            <div>
                <p class="text-sm font-medium"><?= htmlspecialchars($_SESSION['admin_name'] ?? '관리자') ?></p>
                <a href="/shm/logout.php" class="text-xs text-gray-400 hover:text-red-300 transition-colors">로그아웃</a>
            </div>
        </div>
    </div>
</aside>
