<?php
/**
 * settings.php - 시스템 관리
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle   = '시스템 관리';
$currentPage = 'settings';

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10">
            <h1 class="text-2xl font-bold text-gray-800">시스템 관리</h1>
        </header>

        <main class="flex-1 overflow-auto bg-gray-100 p-6 lg:p-10">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 max-w-4xl">
                <h2 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <i class="ph ph-gear-six text-primary"></i> 서비스 설정
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">환경 정보</h3>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                            <p class="text-xs text-gray-400 mb-1">시스템 버전</p>
                            <p class="text-sm font-mono font-bold text-gray-700">v1.2.0 (History Support Enabled)</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                            <p class="text-xs text-gray-400 mb-1">PHP 버전</p>
                            <p class="text-sm font-mono font-bold text-gray-700"><?= PHP_VERSION ?></p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">데이터 개요</h3>
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                            <p class="text-xs text-blue-400 mb-1">현재 활성 회원사</p>
                            <p class="text-xl font-bold text-secondary">
                                <?php
                                $cnt = $pdo->query("SELECT COUNT(*) FROM tb_members WHERE status = '운영'")->fetchColumn();
                                echo number_format($cnt);
                                ?> 건
                            </p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 border border-green-100">
                            <p class="text-xs text-green-400 mb-1">데이터베이스 상태</p>
                            <p class="text-sm font-bold text-green-700">Connected</p>
                        </div>
                    </div>
                </div>

                <div class="mt-10 pt-6 border-t border-gray-100">
                    <h3 class="text-sm font-bold text-gray-500 mb-4">관리자 계정</h3>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-primary rounded-full flex items-center justify-center text-white text-xl font-bold">A</div>
                        <div>
                            <p class="text-sm font-bold text-gray-800">Administrator</p>
                            <p class="text-xs text-gray-400">admin@shm.or.kr</p>
                        </div>
                        <button class="ml-auto bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-md text-xs font-bold transition">비밀번호 변경</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
