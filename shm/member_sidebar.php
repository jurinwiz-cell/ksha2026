<?php
/**
 * includes/member_sidebar.php - 회원사 전용 사이드바
 */
$currentPage = $currentPage ?? '';
$nav = [
    'dashboard'       => ['href' => '/shm/member_index.php', 'icon' => 'ph-squares-four', 'label' => '대시보드'],
    'my_info'         => ['href' => '/shm/member_info.php', 'icon' => 'ph-buildings', 'label' => '자사 정보 관리'],
    'dues_history'    => ['href' => '/shm/member_dues.php', 'icon' => 'ph-coins', 'label' => '회비 납부 내역'],
    // 향후 확장 기능을 위한 메뉴 (기획 단계)
    'housing_supply'  => ['href' => '/shm/member_supply.php', 'icon' => 'ph-house-line', 'label' => '주택 공급현황'],
    'special_dues'    => ['href' => '#', 'icon' => 'ph-receipt', 'label' => '특별회비 내역 (준비중)'],
];
?>
<aside class="w-64 bg-slate-800 text-white flex flex-col hidden md:flex flex-shrink-0">
    <div class="h-16 flex items-center justify-center border-b border-slate-700 px-4 gap-2">
        <img src="/shm/assets/logo-new-big.jpg" alt="로고" class="h-8 object-contain bg-white px-1.5 py-0.5 rounded">
        <span class="text-sm font-bold tracking-wider text-slate-200">회원사 전용 포털</span>
    </div>
    <nav class="flex-1 px-4 py-6 space-y-1">
        <?php foreach ($nav as $key => $item): ?>
            <?php 
                $isActive = ($currentPage === $key); 
                $isDisabled = strpos($item['label'], '(준비중)') !== false;
            ?>
            <a href="<?= $isDisabled ? 'javascript:alert(\'업데이트 준비 중인 기능입니다.\')' : $item['href'] ?>"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors
                      <?= $isActive ? 'bg-slate-700 text-white font-medium' : ($isDisabled ? 'text-slate-500 cursor-not-allowed' : 'text-slate-300 hover:bg-slate-700 hover:text-white') ?>">
                <i class="ph <?= $item['icon'] ?> text-xl"></i>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-slate-700">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-600 flex items-center justify-center text-sm font-bold text-slate-200">
                <?= mb_substr($_SESSION['user_name'] ?? '유', 0, 1) ?>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-100"><?= htmlspecialchars($_SESSION['user_name'] ?? '사용자') ?></p>
                <a href="/shm/logout.php" class="text-xs text-slate-400 hover:text-red-300 transition-colors">로그아웃</a>
            </div>
        </div>
    </div>
</aside>
