<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'templates/header.php';
include 'templates/navbar.php';

$workspaces = [
    ['id' => '', 'name' => '한눈에 보기 (전체 워크스페이스)'],
    ['id' => 'ws_ai_tf', 'name' => 'AI 및 신기술 특별 공동 입법 TF'],
    ['id' => 'ws_climate', 'name' => '기후위기 극복 탄소중립 특별포럼'],
    ['id' => 'ws_local', 'name' => '지방소멸 실질대응 국회 기획협의체']
];
?>

<div class="container py-4 flex-grow-1 animate-fade-in">
    <!-- Header Summary -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-8">
            <h1 class="fw-bold text-dark m-0"><i class="bi bi-bookmark-star text-warning me-2"></i>마이 라이브러리</h1>
            <p class="text-secondary mb-0">워크스페이스별로 스크랩한 국합 분석자료, 실시간 메모와 요약 보고서를 관리합니다.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <select class="form-select shadow-sm" id="workspace_filter">
                <?php foreach ($workspaces as $ws): ?>
                    <option value="<?= htmlspecialchars($ws['id']) ?>"><?= htmlspecialchars($ws['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Live Library List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold m-0 d-flex align-items-center">
                        <i class="bi bi-folder2-open text-primary me-2"></i>
                        아카이브 아티클 정보 목록
                        <span id="libraryCount" class="badge bg-primary ms-2">0</span>
                    </h5>
                    <div id="loadingIndicator" class="spinner-border spinner-border-sm text-primary" role="status"></div>
                </div>

                <!-- Grid Items Container -->
                <div id="libraryItems" class="row g-3">
                    <!-- Default State when empty -->
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-archive display-1 text-secondary opacity-25"></i>
                        <p class="mt-3 lead">저장된 정책 자료가 없습니다.</p>
                        <a href="/dashboard.php" class="btn btn-primary btn-sm mt-1">자료 탐색 및 추가에 이동</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Note Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editNoteModalLabel">주석 및 노션 검토 메모 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="edit_note_text" class="form-label small text-secondary">검토 주석 내용</label>
                    <textarea class="form-control" id="edit_note_text" rows="4" placeholder="메모할 주석을 기재해 주세요..."></textarea>
                </div>
                <div id="editErrorMessage" class="alert alert-danger d-none py-2 small"></div>
                <div id="editSuccessMessage" class="alert alert-success d-none py-2 small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="confirmEditBtn">적용하기</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    let activeEntryForEdit = null;
    const editModal = new bootstrap.Modal(document.getElementById('editNoteModal'));
    
    const filterSelect = document.getElementById('workspace_filter');
    const container = document.getElementById('libraryItems');
    const loader = document.getElementById('loadingIndicator');
    const countBadge = document.getElementById('libraryCount');

    // Load library materials
    const fetchLibrary = async () => {
        loader.classList.remove('d-none');
        const wsId = filterSelect.value;
        const url = `/api/library/list.php?workspace_id=${encodeURIComponent(wsId)}`;

        try {
            const r = await fetch(url);
            const data = await r.json();
            
            container.innerHTML = '';
            countBadge.textContent = data.length;

            if (data.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-inboxes display-3 text-secondary opacity-50 mb-3"></i>
                        <h5 class="fw-bold text-secondary">본 워크스페이스에 아직 아카이빙된 자료가 없습니다.</h5>
                        <p class="small mb-3">검색 포털에서 찾아 자료 목록에 추가해 보세요.</p>
                        <a href="/dashboard.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus"></i> 아카이빙 추가하기</a>
                    </div>
                `;
                return;
            }

            data.forEach(item => {
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4 d-flex align-items-stretch';
                
                const isFav = parseInt(item.is_favorite) === 1;
                const favClass = isFav ? 'bi-star-fill text-warning' : 'bi-star text-secondary';
                
                col.innerHTML = `
                    <div class="card w-100 hover-elevate border p-3 bg-white d-flex flex-column justify-content-between">
                        <div>
                            <!-- Top Info Row -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-secondary bg-opacity-15 text-dark small text-truncate" style="max-width: 140px;">
                                    ${getWorkspaceLabel(item.workspace_id)}
                                </span>
                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-link p-0 fav-toggle-btn fs-5 text-decoration-none" title="즐겨찾기 토글" data-id="${item.id}" data-fav="${isFav}">
                                        <i class="bi ${favClass}"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Document Meta -->
                            <small class="text-primary font-monospace small mb-1 d-block">${item.mtr_div}</small>
                            <h6 class="fw-bold text-dark lh-base mb-3 text-line-clamp-2" style="height: 2.7rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                ${item.mtr_ttl}
                            </h6>

                            <!-- User Personal Note Block -->
                            <div class="p-2 bg-light rounded mb-3" style="min-height: 5rem;">
                                <div class="d-flex justify-content-between align-items-center mb-1 border-bottom pb-1 border-secondary border-opacity-10">
                                    <span class="small fw-semibold text-secondary"><i class="bi bi-pencil-square me-1"></i>나의 검토 주석</span>
                                    <button class="btn btn-link btn-xs p-0 text-primary small text-decoration-none edit-note-btnBtn" style="font-size: 0.75rem;">수정</button>
                                </div>
                                <p class="text-secondary small mb-0 note-text" style="font-style: italic;">
                                    ${item.note ? item.note : '추가한 주석 내용이 없습니다.'}
                                </p>
                            </div>
                        </div>

                        <!-- Bottom Controls -->
                        <div>
                            <hr class="my-2 border-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small font-monospace"><i class="bi bi-calendar3 me-1"></i>${item.wrt_dt}</span>
                                <div class="d-flex gap-2">
                                    <a href="${item.link_url}" target="_blank" class="btn btn-outline-info btn-xs py-1 px-2 small">
                                        <i class="bi bi-link-45deg"></i> 원문
                                    </a>
                                    <button class="btn btn-outline-danger btn-xs py-1 px-2 small delete-entry-btnBtn" data-id="${item.id}">
                                        <i class="bi bi-trash"></i> 삭제
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Workspace friendly labels
                function getWorkspaceLabel(id) {
                    if (id === 'ws_ai_tf') return 'AI 입법 TF';
                    if (id === 'ws_climate') return '기후위기 포럼';
                    if (id === 'ws_local') return '지방소멸 기획체';
                    return '일반';
                }

                // Handle favorites trigger
                col.querySelector('.fav-toggle-btn').addEventListener('click', async () => {
                    const newFavValue = !isFav;
                    try {
                        const r = await fetch('/api/library/favorite.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: item.id, is_favorite: newFavValue })
                        });
                        const res = await r.json();
                        if (res.ok) fetchLibrary();
                    } catch (e) {
                        alert("통신 오류가 발생했습니다.");
                    }
                });

                // Handle edit note trigger
                col.querySelector('.edit-note-btnBtn').addEventListener('click', () => {
                    activeEntryForEdit = item;
                    document.getElementById('edit_note_text').value = item.note || '';
                    document.getElementById('editErrorMessage').classList.add('d-none');
                    document.getElementById('editSuccessMessage').classList.add('d-none');
                    editModal.show();
                });

                // Handle delete entry trigger
                col.querySelector('.delete-entry-btnBtn').addEventListener('click', async () => {
                    if (!confirm("해당 보관 정책자료를 라이브러리에서 완전히 삭제하시겠습니까?")) return;
                    try {
                        const r = await fetch('/api/library/delete.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: item.id })
                        });
                        const res = await r.json();
                        if (res.ok) fetchLibrary();
                    } catch (e) {
                        alert("통신 오류가 발생했습니다.");
                    }
                });

                container.appendChild(col);
            });
        } catch (err) {
            container.innerHTML = `
                <div class="alert alert-danger w-100">
                    <i class="bi bi-exclamation-octagon me-1"></i>보관함 자료를 불러오지 못했습니다. DB 서버를 확인하세요.
                </div>
            `;
        } finally {
            loader.classList.add('d-none');
        }
    };

    // Filter change trigger
    filterSelect.addEventListener('change', fetchLibrary);

    // Confirm edit note
    document.getElementById('confirmEditBtn').addEventListener('click', async () => {
        if (!activeEntryForEdit) return;
        const newNote = document.getElementById('edit_note_text').value;
        const errorDiv = document.getElementById('editErrorMessage');
        const successDiv = document.getElementById('editSuccessMessage');

        errorDiv.classList.add('d-none');
        successDiv.classList.add('d-none');

        try {
            const r = await fetch('/api/library/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: activeEntryForEdit.id, note: newNote })
            });
            const res = await r.json();
            if (res.ok) {
                successDiv.textContent = '아카이브 검토 메모가 정상적으로 수정되었습니다.';
                successDiv.classList.remove('d-none');
                setTimeout(() => {
                    editModal.hide();
                    fetchLibrary();
                }, 1000);
            } else {
                errorDiv.textContent = res.error || '메모 수정에 실패했습니다.';
                errorDiv.classList.remove('d-none');
            }
        } catch (e) {
            errorDiv.textContent = '네트워크 서버 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        }
    });

    // Initial Fetch
    fetchLibrary();
});
</script>

<?php include 'templates/footer.php'; ?>
