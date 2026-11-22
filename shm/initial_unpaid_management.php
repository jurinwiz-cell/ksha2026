<?php
/**
 * initial_unpaid_management.php - 초기이월 미납데이터 조회 및 수정
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle   = '초기이월데이터 관리';
$currentPage = 'initial_unpaid'; // sidebar.php에서 사용

// ─────────────────────────────────────────────
// AJAX: 초기이월데이터 업데이트
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['action'] === 'edit_initial_unpaid') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $y17      = (int)preg_replace('/[^0-9-]/', '', $_POST['year_2017'] ?? '0');
        $y18      = (int)preg_replace('/[^0-9-]/', '', $_POST['year_2018'] ?? '0');
        $y19      = (int)preg_replace('/[^0-9-]/', '', $_POST['year_2019'] ?? '0');
        $y20      = (int)preg_replace('/[^0-9-]/', '', $_POST['year_2020'] ?? '0');
        $y21      = (int)preg_replace('/[^0-9-]/', '', $_POST['year_2021'] ?? '0');
        $total    = (int)preg_replace('/[^0-9-]/', '', $_POST['total'] ?? '0');

        if (!$memberId) {
            echo json_encode(['success' => false, 'message' => '회원사 정보가 올바르지 않습니다.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO tb_initial_unpaid (member_id, year_2017, year_2018, year_2019, year_2020, year_2021, total)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                year_2017 = VALUES(year_2017),
                year_2018 = VALUES(year_2018),
                year_2019 = VALUES(year_2019),
                year_2020 = VALUES(year_2020),
                year_2021 = VALUES(year_2021),
                total = VALUES(total)
        ");
        $stmt->execute([$memberId, $y17, $y18, $y19, $y20, $y21, $total]);

        echo json_encode(['success' => true, 'message' => '초기이월 데이터가 성공적으로 수정되었습니다.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => '알 수 없는 요청입니다.']);
    exit;
}

// ─────────────────────────────────────────────
// 데이터 조회
// ─────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT 
        m.id as member_id, m.member_no, m.company_name, m.ceo_name, m.member_type, m.status, m.join_date, m.withdraw_date,
        COALESCE(iu.year_2017, 0) as year_2017,
        COALESCE(iu.year_2018, 0) as year_2018,
        COALESCE(iu.year_2019, 0) as year_2019,
        COALESCE(iu.year_2020, 0) as year_2020,
        COALESCE(iu.year_2021, 0) as year_2021,
        COALESCE(iu.total, 0) as total
    FROM tb_members m
    LEFT JOIN tb_initial_unpaid iu ON m.id = iu.member_id
    ORDER BY m.member_no ASC
");
$members = $stmt->fetchAll();

require_once __DIR__ . '/includes/html_head.php';
?>
<body class="bg-gray-100 text-gray-800 font-sans antialiased flex h-screen overflow-hidden">

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<!-- 메인 컨텐츠 -->
<div class="flex-1 flex flex-col overflow-hidden">

    <!-- 헤더 -->
    <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 lg:px-10 z-10 flex-shrink-0">
        <h1 class="text-xl font-bold text-gray-800">초기이월데이터 관리 (2017~2021)</h1>
        <div class="text-sm text-gray-500">
            <i class="ph ph-info mr-1"></i>2021년 이전의 장기 미납액을 관리합니다.
        </div>
    </header>

    <main class="flex-1 overflow-auto bg-gray-100 p-6 lg:p-8">
        <!-- 필터바 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-gray-500 mb-1">회원사 검색</label>
                <div class="relative">
                    <input type="text" id="filterSearch" onkeyup="applyFilters()" placeholder="번호 또는 명칭 입력"
                           class="w-full border border-gray-300 rounded-lg pl-9 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary">
                    <i class="ph ph-magnifying-glass absolute left-3 top-2.5 text-gray-400"></i>
                </div>
            </div>
            <div class="w-32">
                <label class="block text-xs font-bold text-gray-500 mb-1">운영 상태</label>
                <select id="filterStatus" onchange="applyFilters()" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                    <option value="">전체보기</option>
                    <option value="운영" selected>운영</option>
                    <option value="비운영">비운영</option>
                    <option value="탈퇴">탈퇴</option>
                </select>
            </div>
            <button onclick="resetFilters()" class="px-3 py-2 text-sm text-gray-500 hover:text-primary transition">
                <i class="ph ph-arrows-counter-clockwise"></i> 초기화
            </button>
        </div>

        <!-- 데이터 테이블 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-auto" style="max-height: calc(100vh - 280px);">
                <table id="dataTable" class="w-full text-sm text-left whitespace-nowrap border-collapse">
                    <thead class="sticky top-0 z-10 bg-gray-50 border-b border-gray-200 shadow-sm text-gray-700">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-center w-16">번호</th>
                            <th class="px-4 py-3 font-semibold">회원사명</th>
                            <th class="px-4 py-3 font-semibold">상태</th>
                            <th class="px-4 py-3 font-semibold text-right">2017년</th>
                            <th class="px-4 py-3 font-semibold text-right">2018년</th>
                            <th class="px-4 py-3 font-semibold text-right">2019년</th>
                            <th class="px-4 py-3 font-semibold text-right">2020년</th>
                            <th class="px-4 py-3 font-semibold text-right">2021년</th>
                            <th class="px-4 py-3 font-semibold text-right text-blue-700 bg-blue-50/50">합계</th>
                            <th class="px-4 py-3 font-semibold text-center w-24">관리</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="tableBody">
                        <?php foreach($members as $m): ?>
                            <tr class="data-row hover:bg-gray-50 transition-colors <?= $m['status'] !== '운영' ? 'opacity-60 bg-gray-50' : '' ?>"
                                data-name="<?= htmlspecialchars($m['company_name']) ?>"
                                data-no="<?= htmlspecialchars($m['member_no']) ?>"
                                data-status="<?= htmlspecialchars($m['status']) ?>">
                                <td class="px-4 py-3 text-center text-gray-500"><?= htmlspecialchars($m['member_no']) ?></td>
                                <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($m['company_name']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs <?= $m['status'] === '운영' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
                                        <?= htmlspecialchars($m['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right"><?= number_format($m['year_2017']) ?></td>
                                <td class="px-4 py-3 text-right"><?= number_format($m['year_2018']) ?></td>
                                <td class="px-4 py-3 text-right"><?= number_format($m['year_2019']) ?></td>
                                <td class="px-4 py-3 text-right"><?= number_format($m['year_2020']) ?></td>
                                <td class="px-4 py-3 text-right"><?= number_format($m['year_2021']) ?></td>
                                <td class="px-4 py-3 text-right font-bold text-blue-600 bg-blue-50/30"><?= number_format($m['total']) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" 
                                            onclick='openEditModal(<?= json_encode($m, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'
                                            class="bg-white border border-gray-300 text-gray-600 px-3 py-1.5 rounded hover:bg-blue-50 hover:text-blue-600 hover:border-blue-300 transition text-xs flex items-center justify-center gap-1 mx-auto">
                                        <i class="ph ph-pencil-simple"></i> 수정
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center text-gray-500">등록된 회원사가 없습니다.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </main>
</div>

<!-- 데이터 수정 모달 -->
<div id="editModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="editModalContent">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="ph-fill ph-pencil-line text-primary"></i> <span id="modalCompanyName"></span> 초기이월 수정
            </h3>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-700 transition">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>
        <form id="editForm" onsubmit="submitEditForm(event)">
            <input type="hidden" name="action" value="edit_initial_unpaid">
            <input type="hidden" name="member_id" id="editMemberId">
            <div class="p-6 space-y-4">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">2017년 미납금</label>
                        <input type="text" name="year_2017" id="edit2017" oninput="formatNumberInput(this); calcTotal()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary text-right" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">2018년 미납금</label>
                        <input type="text" name="year_2018" id="edit2018" oninput="formatNumberInput(this); calcTotal()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary text-right" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">2019년 미납금</label>
                        <input type="text" name="year_2019" id="edit2019" oninput="formatNumberInput(this); calcTotal()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary text-right" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">2020년 미납금</label>
                        <input type="text" name="year_2020" id="edit2020" oninput="formatNumberInput(this); calcTotal()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary text-right" required>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1">2021년 미납금</label>
                        <input type="text" name="year_2021" id="edit2021" oninput="formatNumberInput(this); calcTotal()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary text-right" required>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <label class="block text-sm font-bold text-gray-800 mb-1 flex justify-between">
                        합계 (Total)
                        <button type="button" onclick="calcTotal(true)" class="text-xs text-blue-500 hover:text-blue-700 font-normal underline">재계산</button>
                    </label>
                    <input type="text" name="total" id="editTotal" oninput="formatNumberInput(this)" class="w-full border border-blue-300 bg-blue-50 rounded-lg px-3 py-3 focus:ring-2 focus:ring-primary text-right font-bold text-blue-700 text-lg" required>
                </div>

            </div>
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">취소</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-blue-800 transition shadow-sm">
                    저장
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// 숫자 포맷
function formatNumber(num) {
    return Number(num).toLocaleString('ko-KR');
}

// 입력 중 포맷팅
function formatNumberInput(input) {
    let val = input.value.replace(/[^0-9-]/g, '');
    if (val !== '' && val !== '-') {
        input.value = Number(val).toLocaleString('ko-KR');
    }
}

// 합계 계산
function calcTotal(force = false) {
    const y17 = Number(document.getElementById('edit2017').value.replace(/[^0-9-]/g, '')) || 0;
    const y18 = Number(document.getElementById('edit2018').value.replace(/[^0-9-]/g, '')) || 0;
    const y19 = Number(document.getElementById('edit2019').value.replace(/[^0-9-]/g, '')) || 0;
    const y20 = Number(document.getElementById('edit2020').value.replace(/[^0-9-]/g, '')) || 0;
    const y21 = Number(document.getElementById('edit2021').value.replace(/[^0-9-]/g, '')) || 0;
    
    const total = y17 + y18 + y19 + y20 + y21;
    document.getElementById('editTotal').value = formatNumber(total);
}

// 모달 열기
function openEditModal(data) {
    document.getElementById('modalCompanyName').textContent = data.company_name;
    document.getElementById('editMemberId').value = data.member_id;
    document.getElementById('edit2017').value = formatNumber(data.year_2017);
    document.getElementById('edit2018').value = formatNumber(data.year_2018);
    document.getElementById('edit2019').value = formatNumber(data.year_2019);
    document.getElementById('edit2020').value = formatNumber(data.year_2020);
    document.getElementById('edit2021').value = formatNumber(data.year_2021);
    document.getElementById('editTotal').value = formatNumber(data.total);

    const modal = document.getElementById('editModal');
    const content = document.getElementById('editModalContent');
    modal.classList.remove('hidden');
    // animate in
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
}

// 모달 닫기
function closeEditModal() {
    const modal = document.getElementById('editModal');
    const content = document.getElementById('editModalContent');
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

// 데이터 전송
async function submitEditForm(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> 저장 중...';

    try {
        const formData = new FormData(form);
        const response = await fetch('initial_unpaid_management.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            alert('저장되었습니다.');
            window.location.reload();
        } else {
            alert('오류: ' + result.message);
            submitBtn.disabled = false;
            submitBtn.textContent = '저장';
        }
    } catch (err) {
        alert('서버 오류가 발생했습니다.');
        console.error(err);
        submitBtn.disabled = false;
        submitBtn.textContent = '저장';
    }
}

// 검색 및 필터링
function applyFilters() {
    const searchText = document.getElementById('filterSearch').value.toLowerCase();
    const statusVal = document.getElementById('filterStatus').value;
    
    document.querySelectorAll('.data-row').forEach(row => {
        const name = row.dataset.name.toLowerCase();
        const no = row.dataset.no.toLowerCase();
        const status = row.dataset.status;
        
        const matchSearch = (name.includes(searchText) || no.includes(searchText));
        const matchStatus = (statusVal === '' || status === statusVal);
        
        if (matchSearch && matchStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '운영';
    applyFilters();
}

// 초기 필터 적용
document.addEventListener('DOMContentLoaded', applyFilters);
</script>

</body>
</html>
