<?php
/**
 * includes/sidebar.php - 프리미엄 아코디언 & 축소형 사이드바
 * $currentPage 변수를 설정해두면 활성 메뉴가 하이라이트됩니다.
 */
$currentPage = $currentPage ?? '';

// 현재 파일 기준 디폴트 currentPage 매핑 (누락 및 오동작 방지)
$currentFile = basename($_SERVER['PHP_SELF']);
if (empty($currentPage)) {
    if ($currentFile === 'sp_fee.php') {
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
    } elseif ($currentFile === 'member_management.php') {
        $currentPage = 'members';
    } elseif ($currentFile === 'dues_management.php') {
        $currentPage = 'dues';
    } elseif ($currentFile === 'member_report.php') {
        $currentPage = 'reports';
    } elseif ($currentFile === 'status_report.php') {
        $currentPage = 'status_report';
    } elseif ($currentFile === 'unpaid_report.php') {
        $currentPage = 'unpaid_report';
    } elseif ($currentFile === 'initial_unpaid_management.php') {
        $currentPage = 'initial_unpaid';
    } elseif ($currentFile === 'settings.php') {
        $currentPage = 'settings';
    } elseif ($currentFile === 'supply_dashboard.php') {
        $currentPage = 'supply_stats';
    } elseif ($currentFile === 'supply_management.php') {
        $currentPage = 'supply';
    } elseif ($currentFile === 'request_management.php') {
        $currentPage = 'auth_requests';
    } elseif ($currentFile === 'index.php' || $currentFile === '') {
        $currentPage = 'dashboard';
    }
}

// 대분류 및 소분류 그룹 정의
$menuGroups = [
    'members' => [
        'title' => '회원관리',
        'icon' => 'ph-users',
        'items' => [
            ['label' => '회원 관리', 'href' => '/shm/member_management.php', 'active' => 'members']
        ]
    ],
    'supply' => [
        'title' => '공급현황',
        'icon' => 'ph-house',
        'items' => [
            ['label' => '공급현황 통계', 'href' => '/shm/supply_dashboard.php', 'active' => 'supply_stats'],
            ['label' => '공급현황 관리', 'href' => '/shm/supply_management.php', 'active' => 'supply']
        ]
    ],
    'dues' => [
        'title' => '정기회비',
        'icon' => 'ph-coins',
        'items' => [
            ['label' => '정기회비 관리', 'href' => '/shm/dues_management.php', 'active' => 'dues'],
            ['label' => '회원사별 현황', 'href' => '/shm/member_report.php', 'active' => 'reports'],
            ['label' => '총괄납부현황', 'href' => '/shm/status_report.php', 'active' => 'status_report'],
            ['label' => '장기미납현황', 'href' => '/shm/unpaid_report.php', 'active' => 'unpaid_report'],
            ['label' => '초기이월 미납관리', 'href' => '/shm/initial_unpaid_management.php', 'active' => 'initial_unpaid']
        ]
    ],
    'special' => [
        'title' => '특별회비',
        'icon' => 'ph-hand-coins',
        'items' => [
            ['label' => '전체회원사', 'href' => '/shm/sp_fee.php?view=dashboard', 'active' => 'sp_fee_total'],
            ['label' => '회원사별', 'href' => '/shm/sp_fee.php?view=report_summary', 'active' => 'sp_fee'],
            ['label' => '미납현황', 'href' => '/shm/sp_fee.php?view=report_status', 'active' => 'sp_fee_unpaid'],
            ['label' => '기초데이터', 'href' => '/shm/sp_fee.php?view=companies', 'active' => 'sp_fee_base']
        ]
    ],
    'admin' => [
        'title' => '관리자 전용',
        'icon' => 'ph-shield-checkered',
        'items' => [
            ['label' => '시스템 관리', 'href' => '/shm/settings.php', 'active' => 'settings'],
            ['label' => '권한신청 관리', 'href' => '/shm/request_management.php', 'active' => 'auth_requests']
        ]
    ]
];

// 현재 활성화된 그룹 검색
$activeGroup = '';
foreach ($menuGroups as $groupKey => $group) {
    foreach ($group['items'] as $item) {
        if ($currentPage === $item['active']) {
            $activeGroup = $groupKey;
            break;
        }
    }
}
?>

<aside id="sidebar" class="w-64 bg-gradient-to-b from-[#1e3a8a] to-[#0f172a] text-white flex flex-col hidden md:flex flex-shrink-0 transition-all duration-300 relative border-r border-blue-900/30">
    <!-- 깜빡임 방지 즉시 실행 스크립트 -->
    <script>
        (function() {
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                var sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.add('collapsed');
                    sidebar.style.width = '4rem';
                }
            }
        })();
    </script>

    <!-- 사이드바 토글 버튼 (데스크톱 전용 경계면 플로팅 버튼) -->
    <button id="sidebar-toggle" 
            class="absolute -right-3 top-5 w-6 h-6 bg-blue-800 text-white rounded-full flex items-center justify-center border border-blue-700 hover:bg-blue-700 focus:outline-none transition-all shadow-md z-50 hover:scale-110">
        <i id="toggle-icon" class="ph ph-caret-left text-xs"></i>
    </button>

    <!-- 로고 영역 -->
    <div class="h-16 flex items-center justify-start border-b border-blue-900/60 px-6 gap-3 sidebar-header transition-all duration-300">
        <i class="ph-fill ph-house-line text-2xl text-blue-400 flex-shrink-0"></i>
        <span class="text-base font-bold tracking-wider logo-text whitespace-nowrap bg-gradient-to-r from-white to-blue-200 bg-clip-text text-transparent">한국사회주택협회</span>
    </div>

    <!-- 메뉴 리스트 -->
    <nav class="flex-1 px-4 py-6 space-y-4 overflow-y-auto overflow-x-hidden">
        <!-- 대시보드 (단독형) -->
        <?php $isDashboardActive = ($currentPage === 'dashboard'); ?>
        <div>
            <a href="/shm/" 
               data-tooltip="대시보드"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition-all text-sm font-medium tooltip-collapsed
                      <?= $isDashboardActive ? 'bg-blue-800 text-white font-bold shadow-md shadow-blue-950/50' : 'text-gray-300 hover:bg-blue-800/40 hover:text-white' ?> menu-item">
                <i class="ph ph-squares-four text-lg"></i>
                <span class="menu-text">대시보드</span>
            </a>
        </div>

        <!-- 아코디언 메뉴 그룹 -->
        <div class="space-y-3">
            <?php foreach ($menuGroups as $groupKey => $group): ?>
                <?php 
                $isGroupActive = ($activeGroup === $groupKey); 
                ?>
                <div class="menu-group-container">
                    <!-- 대분류 토글러 -->
                    <button onclick="toggleAccordion('<?= $groupKey ?>')" 
                            data-tooltip="<?= $group['title'] ?>"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-gray-300 hover:bg-blue-800/40 hover:text-white transition-all text-sm font-medium menu-item tooltip-collapsed">
                        <div class="flex items-center gap-3">
                            <i class="ph <?= $group['icon'] ?> text-lg"></i>
                            <span class="menu-text"><?= $group['title'] ?></span>
                        </div>
                        <i class="ph ph-caret-down text-xs caret-icon transition-transform duration-200 <?= $isGroupActive ? 'rotate-180' : '' ?>" id="caret-<?= $groupKey ?>"></i>
                    </button>
                    <!-- 소분류 리스트 (경계선 장식 추가) -->
                    <div id="sub-<?= $groupKey ?>" 
                         class="sub-menu-list mt-1 space-y-1 border-l border-blue-800/40 ml-5 pl-4 overflow-hidden transition-all duration-300"
                         style="max-height: <?= $isGroupActive ? '200px' : '0px' ?>;">
                        <?php foreach ($group['items'] as $item): ?>
                            <?php $isActive = ($currentPage === $item['active']); ?>
                            <a href="<?= $item['href'] ?>" 
                               class="block px-3 py-1.5 rounded-md text-xs transition-colors <?= $isActive ? 'text-white font-semibold bg-blue-800/60 shadow-inner' : 'text-gray-400 hover:text-white hover:bg-blue-800/20' ?>">
                                <?= $item['label'] ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- 사용자 정보 영역 -->
    <div class="p-4 border-t border-blue-900/60 bg-blue-950/20 user-section flex items-center gap-3 transition-all duration-300">
        <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-sm font-bold shadow-md shadow-blue-950/50 flex-shrink-0">
            <?= mb_substr($_SESSION['admin_name'] ?? '관', 0, 1) ?>
        </div>
        <div class="user-info transition-all duration-300 overflow-hidden">
            <p class="text-sm font-semibold text-gray-200 leading-none mb-1 whitespace-nowrap"><?= htmlspecialchars($_SESSION['admin_name'] ?? '관리자') ?></p>
            <a href="/shm/logout.php" class="text-xs text-gray-400 hover:text-red-400 transition-colors">로그아웃</a>
        </div>
    </div>
</aside>

<style>
    /* 프리미엄 사이드바 로컬 스타일 */
    #sidebar.collapsed {
        width: 4rem !important; /* w-16 */
    }
    #sidebar.collapsed .logo-text,
    #sidebar.collapsed .menu-text,
    #sidebar.collapsed .caret-icon,
    #sidebar.collapsed .user-info,
    #sidebar.collapsed .sub-menu-list,
    #sidebar.collapsed .sidebar-title-label {
        display: none !important;
    }
    #sidebar.collapsed .sidebar-header {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }
    #sidebar.collapsed .menu-item {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
        width: 2.5rem;
        height: 2.5rem;
        margin-left: auto;
        margin-right: auto;
    }
    #sidebar.collapsed .menu-item i {
        font-size: 1.25rem;
        margin: 0;
    }
    #sidebar.collapsed .user-section {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }
    #sidebar.collapsed .menu-group-container {
        border-bottom: none;
        padding-bottom: 0.25rem;
    }
    
    /* 툴팁 기본 스타일 */
    .tooltip-collapsed {
        position: relative;
    }
    #sidebar.collapsed .tooltip-collapsed::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 3.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: #0f172a;
        color: #fff;
        padding: 0.35rem 0.65rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s, transform 0.2s;
        z-index: 100;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.3), 0 4px 6px -4px rgb(0 0 0 / 0.3);
        border: 1px solid rgba(255,255,255,0.08);
    }
    #sidebar.collapsed .tooltip-collapsed:hover::after {
        opacity: 1;
        transform: translateY(-50%) translateX(3px);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const toggleIcon = document.getElementById('toggle-icon');

    // 초기 상태 아이콘 방향 설정
    if (sidebar.classList.contains('collapsed')) {
        if (toggleIcon) toggleIcon.classList.replace('ph-caret-left', 'ph-caret-right');
    }

    // 1. 사이드바 접기/펼치기 토글 함수
    function toggleSidebar() {
        const isCollapsed = sidebar.classList.contains('collapsed');
        if (isCollapsed) {
            sidebar.classList.remove('collapsed');
            sidebar.style.width = '16rem'; // w-64
            if (toggleIcon) toggleIcon.classList.replace('ph-caret-right', 'ph-caret-left');
            localStorage.setItem('sidebar-collapsed', 'false');
            
            // 사이드바가 확장될 때 활성화된 아코디언이 있다면 복원해서 열기
            restoreAccordions();
        } else {
            sidebar.classList.add('collapsed');
            sidebar.style.width = '4rem'; // w-16
            if (toggleIcon) toggleIcon.classList.replace('ph-caret-left', 'ph-caret-right');
            localStorage.setItem('sidebar-collapsed', 'true');
            
            // 사이드바가 접힐 때는 모든 서브메뉴 아코디언을 임시로 닫기
            closeAllAccordions();
        }
    }

    // 2. 아코디언 토글 함수
    window.toggleAccordion = function(groupKey) {
        // 만약 사이드바가 접힌 상태라면, 아코디언 클릭 시 사이드바를 먼저 활짝 펼친다.
        if (sidebar.classList.contains('collapsed')) {
            toggleSidebar();
            // 사이드바가 부드럽게 펼쳐진 후(transition duration=300) 아코디언이 열리도록 함
            setTimeout(() => openAccordion(groupKey), 200);
            return;
        }

        const subMenu = document.getElementById('sub-' + groupKey);
        const caret = document.getElementById('caret-' + groupKey);
        const isOpen = subMenu.style.maxHeight !== '0px' && subMenu.style.maxHeight !== '';

        if (isOpen) {
            subMenu.style.maxHeight = '0px';
            if (caret) caret.classList.remove('rotate-180');
            localStorage.setItem('accordion-open-' + groupKey, 'false');
        } else {
            subMenu.style.maxHeight = subMenu.scrollHeight + 'px';
            if (caret) caret.classList.add('rotate-180');
            localStorage.setItem('accordion-open-' + groupKey, 'true');
        }
    };

    function openAccordion(groupKey) {
        const subMenu = document.getElementById('sub-' + groupKey);
        const caret = document.getElementById('caret-' + groupKey);
        if (subMenu) {
            subMenu.style.maxHeight = subMenu.scrollHeight + 'px';
        }
        if (caret) {
            caret.classList.add('rotate-180');
        }
        localStorage.setItem('accordion-open-' + groupKey, 'true');
    }

    function closeAllAccordions() {
        const subMenus = document.querySelectorAll('.sub-menu-list');
        const carets = document.querySelectorAll('.caret-icon');
        subMenus.forEach(menu => menu.style.maxHeight = '0px');
        carets.forEach(caret => caret.classList.remove('rotate-180'));
    }

    function restoreAccordions() {
        const groupKeys = ['members', 'supply', 'dues', 'special', 'admin'];
        groupKeys.forEach(groupKey => {
            const isOpen = localStorage.getItem('accordion-open-' + groupKey);
            if (isOpen === 'true') {
                openAccordion(groupKey);
            } else if (isOpen === 'false') {
                const subMenu = document.getElementById('sub-' + groupKey);
                const caret = document.getElementById('caret-' + groupKey);
                if (subMenu) subMenu.style.maxHeight = '0px';
                if (caret) caret.classList.remove('rotate-180');
            } else {
                // 저장된 설정이 없을 때는 초기 PHP 활성화 그룹만 유지
                const subMenu = document.getElementById('sub-' + groupKey);
                if (subMenu && subMenu.style.maxHeight !== '0px') {
                    subMenu.style.maxHeight = subMenu.scrollHeight + 'px';
                }
            }
        });
    }

    // 초기 이벤트 바인딩
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    
    // 로드 시점에 사이드바가 확장되어 있으면 아코디언 상태 복구
    if (!sidebar.classList.contains('collapsed')) {
        restoreAccordions();
    } else {
        closeAllAccordions();
    }
});
</script>
