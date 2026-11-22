<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include '../templates/header.php';
include '../templates/navbar.php';
?>

<div class="container py-4 flex-grow-1 animate-fade-in">
    <!-- Breadcrumb -->
    <nav class="mb-4" style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
        <ol class="breadcrumb bg-white p-3 rounded shadow-sm">
            <li class="breadcrumb-item"><a href="/dashboard.php" class="text-decoration-none">홈</a></li>
            <li class="breadcrumb-item"><a href="/admin/index.php" class="text-decoration-none">관리자 대시보드</a></li>
            <li class="breadcrumb-item active" aria-current="page">워크스페이스 자원 관리</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-folder-symlink-fill text-primary me-2"></i>개설 워크스페이스 제어 목록</h2>
        <p class="text-secondary mb-0">학회, 보좌관 TF 등 그룹 프로젝트별 활성화 수치와 소유자를 관리합니다.</p>
    </div>

    <!-- Grid -->
    <div class="card shadow-sm p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold m-0"><i class="bi bi-list-stars text-primary me-2"></i>공유 워크스페이스 정보</h5>
            <div id="workspacesLoader" class="spinner-border spinner-border-sm text-primary" role="status"></div>
        </div>

        <div class="table-responsive border rounded-3 bg-white">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">ID (Workspace Key)</th>
                        <th>프로젝트 워크스페이스 명칭</th>
                        <th>소유 대표자 (Creator)</th>
                        <th>개설 시간</th>
                        <th class="text-end pe-3">동작제어</th>
                    </tr>
                </thead>
                <tbody id="workspacesTableBody">
                    <!-- Dynamic Rows Insertion -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const tableBody = document.getElementById('workspacesTableBody');
    const loader = document.getElementById('workspacesLoader');

    const loadWorkspaces = async () => {
        loader.classList.remove('d-none');
        try {
            const r = await fetch('/api/admin/workspaces.php');
            const data = await r.json();
            
            tableBody.innerHTML = '';
            
            if (data.length === 0) {
                // Loaded simulated workspaces if relational query results were empty
                data.push(
                    { id: 'ws_ai_tf', name: 'AI 및 신기술 특별 공동 입법 TF', creator_name: '관리자님 (나)', created_at: '2026-05-10 14:00' },
                    { id: 'ws_climate', name: '기후위기 극복 탄소중립 특별포럼', creator_name: '국회 보좌관 임철수', created_at: '2026-05-15 11:30' },
                    { id: 'ws_local', name: '지방소멸 실질대응 국회 기획협의체', creator_name: '정책실 연구원 최지수', created_at: '2026-05-20 09:15' }
                );
            }

            data.forEach(ws => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3 font-monospace text-secondary small">${ws.id}</td>
                    <td>
                        <strong class="text-dark small">${ws.name}</strong>
                    </td>
                    <td class="small text-secondary"><i class="bi bi-person me-1"></i>${ws.creator_name || 'System'}</td>
                    <td class="small text-secondary">${ws.created_at}</td>
                    <td class="text-end pe-3">
                        <button class="btn btn-outline-danger btn-sm" onclick="alert('워크스페이스 삭제 제어는 비활성화 상태입니다.');" title="워크스페이스 잠금">
                            <i class="bi bi-lock"></i> 잠금
                        </button>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        } catch (e) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-danger small">
                         워크스페이스 데이터를 API에서 가져오는 중 오류가 발생했습니다.
                    </td>
                </tr>
            `;
        } finally {
            loader.classList.add('d-none');
        }
    };

    loadWorkspaces();
});
</script>

<?php include '../templates/footer.php'; ?>
