<?php
session_start();

// ==========================================
// 1. 데이터베이스 연결 설정
// ==========================================
$db_host = 'localhost';
$db_name = 'public_housing_db';
$db_user = 'socialhousing'; // 운영 환경에 맞게 변경
$db_pass = 'socialhousing0815';     // 운영 환경에 맞게 변경

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB 연결 실패: " . $e->getMessage() . " (schema.sql을 먼저 실행해주세요)");
}

// ==========================================
// 2. AJAX 요청 처리 (데이터 업데이트 및 업로드)
// ==========================================
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// 관리자 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = $_POST['password'] ?? '';
    if ($password === 'admin') { // 데모용 비밀번호
        $_SESSION['is_admin'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '비밀번호가 일치하지 않습니다.']);
    }
    exit;
}

// 로그아웃 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// 필드 업데이트 처리 (인라인 에디트)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_field') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
        exit;
    }
    $key = $_POST['field_key'] ?? '';
    $value = $_POST['field_value'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO portal_content (field_key, field_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE field_value = ?");
    $stmt->execute([$key, $value, $value]);
    echo json_encode(['success' => true]);
    exit;
}

// PDF 업로드 및 파싱 시뮬레이션 처리 (1번째 탭 자동 생성)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_pdf') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
        exit;
    }
    
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        // 실제 운영 시 외부 라이브러리(Smalot/PdfParser 등)로 텍스트를 추출하는 로직이 들어갑니다.
        // 현재는 업로드 성공 시 임의의 내용으로 1탭 데이터를 덮어씌우는 시뮬레이션입니다.
        $filename = $_FILES['pdf_file']['name'];
        
        $simulatedParsedData = [
            'doc1Title' => "[자동추출] $filename 모집 공고",
            'doc1Scale' => "추출됨: 2,000호 내외",
            'doc1Deadline' => "추출됨: 2026.06.30",
            'doc1OverviewDesc' => "[PDF 추출 내용] 민간사업자 자유테마 제안 방식의 새로운 구조가 반영되었습니다.",
            'doc1Eligibility' => "[PDF 추출 내용] 자격 요건이 완화되어 모든 민간 사업자가 참여 가능합니다."
        ];

        foreach ($simulatedParsedData as $key => $val) {
            $stmt = $pdo->prepare("INSERT INTO portal_content (field_key, field_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE field_value = ?");
            $stmt->execute([$key, $val, $val]);
        }
        
        echo json_encode(['success' => true, 'message' => 'PDF가 성공적으로 업로드 및 분석되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '파일 업로드 실패.']);
    }
    exit;
}

// ==========================================
// 3. 데이터 불러오기
// ==========================================
$stmt = $pdo->query("SELECT field_key, field_value FROM portal_content");
$data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[$row['field_key']] = $row['field_value'];
}

// 기본값 설정 헬퍼
function getField($key, $default = '') {
    global $data;
    return isset($data[$key]) ? htmlspecialchars($data[$key]) : $default;
}

// 에디터 렌더링 헬퍼
function editable($key, $isMultiline = false) {
    global $isAdmin;
    $val = getField($key);
    if (!$isAdmin) {
        return nl2br($val);
    }
    $type = $isMultiline ? 'textarea' : 'input';
    $nl2brVal = nl2br($val);
    // 수정 권한이 있으면 노란색 배경의 수정 가능한 UI 렌더링
    return <<<HTML
    <span class="editable-wrapper" data-key="{$key}" data-type="{$type}">
        <span class="display-text bg-yellow-100 border border-yellow-300 border-dashed rounded px-1 cursor-pointer hover:bg-yellow-200" title="클릭하여 수정">{$nl2brVal} ✏️</span>
        <span class="edit-ui hidden inline-flex gap-1 items-center">
            <textarea class="border-2 border-yellow-400 bg-yellow-50 rounded px-2 py-1 text-gray-900 w-full min-w-[200px]" rows="3">{$val}</textarea>
            <button class="save-btn bg-emerald-500 text-white p-1 rounded shadow text-sm">저장</button>
            <button class="cancel-btn bg-red-500 text-white p-1 rounded shadow text-sm">취소</button>
        </span>
    </span>
HTML;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공공임대주택 통합 안내 포털 (PHP/MySQL)</title>
    <link rel="stylesheet" href="/static/vendor/css/tailwind.min.css">
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css" rel="stylesheet">
    <style>
        body { font-family: 'Pretendard', sans-serif; }
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-slate-100 text-gray-800 p-0 md:p-4">

    <!-- Admin Status Banner -->
    <?php if ($isAdmin): ?>
    <div class="bg-yellow-400 text-yellow-900 text-center py-2 font-bold shadow-md">
        현재 관리자 편집 모드입니다. 노란색 영역을 클릭하여 내용을 수정할 수 있습니다.
    </div>
    <?php endif; ?>

    <div class="w-full bg-white shadow-xl md:rounded-2xl overflow-hidden <?php echo $isAdmin ? 'mt-4' : ''; ?>">
        
        <!-- Header -->
        <div class="bg-indigo-800 text-white p-6 md:p-8 text-center relative">
            <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight mb-2">
                <?= editable('portalTitle') ?>
            </h1>
            <p class="text-indigo-200 text-lg">
                <?= editable('portalSubtitle') ?>
            </p>
        </div>

        <!-- Main Tabs -->
        <div class="flex flex-col sm:flex-row bg-indigo-50 border-b-2 border-indigo-200">
            <button onclick="switchMainTab(1, this)" class="main-tab-btn flex-1 text-center py-4 px-4 font-bold text-indigo-800 border-b-4 border-indigo-800 bg-white transition text-base md:text-lg">
                <?= editable('tab1Name') ?>
            </button>
            <button onclick="switchMainTab(2, this)" class="main-tab-btn flex-1 text-center py-4 px-4 font-bold text-gray-500 border-b-4 border-transparent hover:bg-indigo-100 hover:text-indigo-700 transition text-base md:text-lg">
                <?= editable('tab2Name') ?>
            </button>
            <button onclick="switchMainTab(3, this)" class="main-tab-btn flex-1 text-center py-4 px-4 font-bold text-gray-500 border-b-4 border-transparent hover:bg-indigo-100 hover:text-indigo-700 transition text-base md:text-lg">
                <?= editable('tab3Name') ?>
            </button>
        </div>

        <!-- ==========================================
             MAIN TAB 1 : 1번째 탭 (PDF 자동생성 타겟)
        =========================================== -->
        <div id="main-tab-1" class="tab-content main-content active">
            <div class="bg-blue-600 text-white p-6 md:p-8 text-center relative">
                <?php if($isAdmin): ?>
                <!-- 관리자용 PDF 업로드 폼 -->
                <div class="absolute top-4 left-6 bg-white/20 p-2 rounded-lg">
                    <form id="pdfForm" class="flex items-center gap-2 text-sm" onsubmit="uploadPDF(event)">
                        <span class="font-bold">📄 지침서 자동생성:</span>
                        <input type="file" name="pdf_file" accept=".pdf" class="file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                        <button type="submit" class="bg-blue-800 hover:bg-blue-900 text-white px-3 py-1 rounded">업로드 및 파싱</button>
                    </form>
                </div>
                <?php endif; ?>

                <p class="absolute top-4 right-6 text-sm text-blue-200"><?= editable('doc1Date') ?></p>
                <h2 class="text-2xl md:text-3xl font-bold mt-8 mb-2"><?= editable('doc1Title') ?></h2>
                <p class="text-lg text-blue-100"><?= editable('doc1Subtitle') ?></p>
                <div class="mt-4 inline-flex space-x-4 text-sm bg-blue-700/50 rounded-full px-5 py-2">
                    <span><strong class="text-white">공모규모:</strong> <?= editable('doc1Scale') ?></span>
                    <span>|</span>
                    <span><strong class="text-white">접수:</strong> <?= editable('doc1Deadline') ?></span>
                </div>
            </div>

            <!-- Sub Tabs -->
            <div class="flex overflow-x-auto border-b border-gray-200 bg-gray-50 text-sm md:text-base sticky top-0 z-10">
                <button onclick="switchSubTab(1, this, 'blue')" class="sub1-tab-btn flex-1 text-center py-4 px-4 font-semibold text-blue-600 border-b-2 border-blue-600 bg-gray-100 transition">1. 공모개요</button>
                <button onclick="switchSubTab(2, this, 'blue')" class="sub1-tab-btn flex-1 text-center py-4 px-4 font-medium text-gray-500 border-b-2 border-transparent hover:text-blue-600 transition">2. 신청자격</button>
            </div>

            <div class="p-6 md:p-10 min-h-[400px]">
                <div id="sub1-tab-1" class="tab-content sub1-content active">
                    <h3 class="text-xl font-bold mb-6 text-blue-700 border-l-4 border-blue-600 pl-3">공모개요</h3>
                    <ul class="space-y-4 text-gray-700 mb-8">
                        <li class="flex items-start"><span class="font-bold text-blue-600 mr-2">■</span><div class="flex-1"><?= editable('doc1OverviewDesc', true) ?></div></li>
                        <li class="flex items-start"><span class="font-bold text-blue-600 mr-2">■</span><div class="flex-1"><?= editable('doc1OverviewTheme', true) ?></div></li>
                        <li class="flex items-start"><span class="font-bold text-blue-600 mr-2">■</span><div class="flex-1"><?= editable('doc1OverviewScale', true) ?></div></li>
                        <li class="flex items-start"><span class="font-bold text-blue-600 mr-2">■</span><div class="flex-1"><?= editable('doc1OverviewArea', true) ?></div></li>
                    </ul>
                </div>
                <div id="sub1-tab-2" class="tab-content sub1-content">
                    <h3 class="text-xl font-bold mb-6 text-blue-700 border-l-4 border-blue-600 pl-3">신청자격</h3>
                    <div class="bg-yellow-50 p-4 rounded border border-yellow-200 text-gray-700">
                        <?= editable('doc1Eligibility', true) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             MAIN TAB 2 : 각 기관별 내용
        =========================================== -->
        <div id="main-tab-2" class="tab-content main-content">
            <div class="bg-cyan-700 text-white p-6 md:p-8 text-center relative">
                <h2 class="text-2xl md:text-3xl font-bold mb-3"><?= editable('doc2Title') ?></h2>
                <p class="text-lg text-cyan-100"><?= editable('doc2Subtitle') ?></p>
            </div>
            <div class="p-6 md:p-10 space-y-8">
                <!-- LH -->
                <div class="bg-cyan-50/50 rounded-lg p-6 border border-cyan-100">
                    <h3 class="text-xl font-bold mb-3 text-cyan-900 flex items-center"><span class="text-cyan-600 mr-2">■</span> LH 한국토지주택공사</h3>
                    <div class="pl-6 text-gray-700"><?= editable('doc2LH', true) ?></div>
                </div>
                <!-- SH -->
                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <h3 class="text-xl font-bold mb-3 text-gray-900 flex items-center"><span class="text-cyan-600 mr-2">■</span> SH 서울주택도시공사</h3>
                    <div class="pl-6 text-gray-700"><?= editable('doc2SH', true) ?></div>
                </div>
                <!-- GH -->
                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <h3 class="text-xl font-bold mb-3 text-gray-900 flex items-center"><span class="text-cyan-600 mr-2">■</span> GH 경기주택도시공사</h3>
                    <div class="pl-6 text-gray-700"><?= editable('doc2GH', true) ?></div>
                </div>
            </div>
        </div>

        <!-- ==========================================
             MAIN TAB 3 : 연도별 내용
        =========================================== -->
        <div id="main-tab-3" class="tab-content main-content">
            <div class="bg-emerald-700 text-white p-6 md:p-8 text-center relative">
                <h2 class="text-2xl md:text-3xl font-bold mb-3"><?= editable('doc3Title') ?></h2>
                <p class="text-lg text-emerald-100"><?= editable('doc3Subtitle') ?></p>
            </div>
            <div class="p-6 md:p-10 space-y-10">
                <section>
                    <h3 class="text-2xl font-bold mb-4 text-emerald-800 border-l-4 border-emerald-600 pl-3">2025년 현황</h3>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 text-gray-700 shadow-sm">
                        <?= editable('doc3Year2025', true) ?>
                    </div>
                </section>
                <section>
                    <h3 class="text-2xl font-bold mb-4 text-emerald-800 border-l-4 border-emerald-600 pl-3">2024년 현황</h3>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 text-gray-700 shadow-sm">
                        <?= editable('doc3Year2024', true) ?>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- ==========================================
         Admin Control Floating Button
    =========================================== -->
    <div class="fixed bottom-6 right-6 flex flex-col gap-3 z-50">
        <?php if ($isAdmin): ?>
            <button onclick="logoutAdmin()" class="bg-gray-800 text-white px-5 py-3 rounded-full shadow-lg font-bold hover:bg-gray-700">🔓 로그아웃</button>
        <?php else: ?>
            <button onclick="document.getElementById('loginModal').classList.remove('hidden')" class="bg-indigo-600 text-white w-14 h-14 rounded-full shadow-lg flex items-center justify-center hover:bg-indigo-700" title="관리자 로그인">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </button>
        <?php endif; ?>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-[100]">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold flex items-center gap-2">🔒 관리자 로그인</h3>
                <button onclick="document.getElementById('loginModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-800">✕</button>
            </div>
            <p class="text-sm text-gray-500 mb-4">비밀번호: <strong>admin</strong></p>
            <input type="password" id="adminPwd" class="w-full border border-gray-300 rounded px-4 py-2 mb-4" placeholder="비밀번호">
            <button onclick="loginAdmin()" class="w-full bg-indigo-600 text-white font-bold py-2 rounded hover:bg-indigo-700">로그인</button>
        </div>
    </div>

    <!-- JS Logic -->
    <script>
        // 탭 전환 로직
        function switchMainTab(tabIndex, element) {
            document.querySelectorAll('.main-content').forEach(el => el.classList.remove('active'));
            document.getElementById('main-tab-' + tabIndex).classList.add('active');
            
            document.querySelectorAll('.main-tab-btn').forEach(btn => {
                btn.className = 'main-tab-btn flex-1 text-center py-4 px-4 font-bold text-gray-500 border-b-4 border-transparent hover:bg-indigo-100 hover:text-indigo-700 transition text-base md:text-lg';
            });
            element.className = 'main-tab-btn flex-1 text-center py-4 px-4 font-bold text-indigo-800 border-b-4 border-indigo-800 bg-white transition text-base md:text-lg';
        }

        function switchSubTab(tabIndex, element, theme) {
            document.querySelectorAll('.sub1-content').forEach(el => el.classList.remove('active'));
            document.getElementById('sub1-tab-' + tabIndex).classList.add('active');
            
            document.querySelectorAll('.sub1-tab-btn').forEach(btn => {
                btn.className = `sub1-tab-btn flex-1 text-center py-4 px-4 font-medium text-gray-500 border-b-2 border-transparent hover:text-${theme}-600 transition`;
            });
            element.className = `sub1-tab-btn flex-1 text-center py-4 px-4 font-semibold text-${theme}-600 border-b-2 border-${theme}-600 bg-gray-100 transition`;
        }

        // 로그인 / 로그아웃 로직
        async function loginAdmin() {
            const pwd = document.getElementById('adminPwd').value;
            const res = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=login&password=${encodeURIComponent(pwd)}`
            });
            const data = await res.json();
            if(data.success) location.reload();
            else alert(data.message);
        }

        async function logoutAdmin() {
            await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=logout`
            });
            location.reload();
        }

        // 인라인 에디터 로직 (저장)
        document.querySelectorAll('.editable-wrapper').forEach(wrapper => {
            const displaySpan = wrapper.querySelector('.display-text');
            const editUi = wrapper.querySelector('.edit-ui');
            const textarea = editUi.querySelector('textarea');
            const saveBtn = editUi.querySelector('.save-btn');
            const cancelBtn = editUi.querySelector('.cancel-btn');
            const fieldKey = wrapper.getAttribute('data-key');

            displaySpan.addEventListener('click', () => {
                displaySpan.classList.add('hidden');
                editUi.classList.remove('hidden');
                textarea.focus();
            });

            cancelBtn.addEventListener('click', () => {
                editUi.classList.add('hidden');
                displaySpan.classList.remove('hidden');
            });

            saveBtn.addEventListener('click', async () => {
                const newValue = textarea.value;
                const formData = new URLSearchParams();
                formData.append('action', 'update_field');
                formData.append('field_key', fieldKey);
                formData.append('field_value', newValue);

                const res = await fetch('', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    displaySpan.innerHTML = newValue.replace(/\n/g, '<br>') + ' ✏️';
                    editUi.classList.add('hidden');
                    displaySpan.classList.remove('hidden');
                } else {
                    alert('저장 실패: ' + data.message);
                }
            });
        });

        // PDF 업로드 로직 (1번째 탭 자동 생성)
        async function uploadPDF(e) {
            e.preventDefault();
            const form = e.target;
            const fileInput = form.querySelector('input[type="file"]');
            
            if (fileInput.files.length === 0) return;

            const formData = new FormData();
            formData.append('action', 'upload_pdf');
            formData.append('pdf_file', fileInput.files[0]);

            // 로딩 표시
            const btn = form.querySelector('button');
            const originalText = btn.innerText;
            btn.innerText = '처리 중...';
            btn.disabled = true;

            const res = await fetch('', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                alert('PDF 처리 완료! 화면을 새로고침합니다.\n\n(참고: 실제 추출된 데이터 대신 시뮬레이션용 데이터가 DB에 덮어씌워졌습니다.)');
                location.reload();
            } else {
                alert('오류: ' + data.message);
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>

