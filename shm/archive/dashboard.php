<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'templates/header.php';
include 'templates/navbar.php';

// Safe fallback default workspaces for the UI workspace selector
$workspaces = [
    ['id' => 'ws_ai_tf', 'name' => 'AI 및 신기술 특별 공동 입법 TF'],
    ['id' => 'ws_climate', 'name' => '기후위기 극복 탄소중립 특별포럼'],
    ['id' => 'ws_local', 'name' => '지방소멸 실질대응 국회 기획협의체']
];
?>

<div class="container py-4 flex-grow-1">
    <!-- Hero Header -->
    <div class="p-4 p-md-5 mb-4 text-white rounded bg-dark shadow-sm position-relative overflow-hidden">
        <div class="col-md-8 px-0 position-relative" style="z-index: 2;">
            <span class="badge bg-primary px-3 py-2 mb-3 text-uppercase">국회 정책자료 검색 포털</span>
            <h1 class="display-5 fw-bold">국가 정밀 입법 및 정책 분석자료 탐색</h1>
            <p class="lead my-3 text-white-50">인공지능 특별조사처, 예산분석 등 국회 산하 기관에서 기획·발간된 공용 정책 연구물들을 실시간으로 필터링하고 공용 워크스페이스에 아카이빙 하세요.</p>
        </div>
        <div class="position-absolute end-0 top-0 bottom-0 d-none d-md-block opacity-25" style="width: 40%; background: linear-gradient(135deg, transparent, rgba(13,110,253,0.5)); transform: skewX(-15deg);"></div>
    </div>

    <div class="row g-4">
        <!-- Dashboard Sidebar / Controls -->
        <div class="col-lg-4">
            <div class="card p-4 shadow-sm mb-4">
                <h5 class="fw-bold mb-3 d-flex align-items-center">
                    <i class="bi bi-filter-square-fill text-primary me-2"></i>
                    자료 통합 검색
                </h5>
                <form id="searchForm">
                    <div class="mb-3">
                        <label for="q" class="form-label text-secondary small">검색어 (Title / Topic)</label>
                        <input type="text" class="form-control" id="q" name="q" placeholder="예: 인공지능, 기후위기, 지방소멸" value="">
                    </div>
                    
                    <div class="mb-3">
                        <label for="selected_workspace" class="form-label text-secondary small">저장 타겟 워크스페이스</label>
                        <select class="form-select" id="selected_workspace">
                            <?php foreach ($workspaces as $ws): ?>
                                <option value="<?= htmlspecialchars($ws['id']) ?>"><?= htmlspecialchars($ws['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-medium">
                        <i class="bi bi-search me-1"></i>검색하기
                    </button>
                </form>
            </div>

            <!-- Fast Categories Help -->
            <div class="card p-4 shadow-sm">
                <h6 class="fw-bold mb-3">빠른 원클릭 카테고리</h6>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline-secondary fast-tag" data-tag="AI">#인공지능</button>
                    <button class="btn btn-sm btn-outline-secondary fast-tag" data-tag="기후">#기후위기</button>
                    <button class="btn btn-sm btn-outline-secondary fast-tag" data-tag="지방">#지방소멸</button>
                    <button class="btn btn-sm btn-outline-secondary fast-tag" data-tag="가상">#가상자산</button>
                    <button class="btn btn-sm btn-outline-secondary fast-tag" data-tag="헬스케어">#바이오헬스</button>
                </div>
            </div>
        </div>

        <!-- Search Results Panel -->
        <div class="col-lg-8 animate-fade-in">
            <div class="card p-4 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold m-0 d-flex align-items-center">
                        <i class="bi bi-card-checklist text-primary me-2"></i>
                        자료 매칭 내역
                        <span id="resultsCount" class="badge bg-secondary ms-2 small">0</span>
                    </h5>
                    <div class="loader spinner-border spinner-border-sm text-primary d-none" role="status"></div>
                </div>

                <div id="results" class="d-flex flex-column gap-3">
                    <!-- Default State before Search -->
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-search-heart display-1 text-secondary opacity-50 mb-3"></i>
                        <p class="mb-0">국회 입법 정책자료를 검색해 주세요.</p>
                        <small class="text-muted">검색어를 입력하고 버튼을 클릭하거나 상단 추천 키워드를 불러와 보세요.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Save Modal with Action Note -->
<div class="modal fade" id="saveNoteModal" tabindex="-1" aria-labelledby="saveNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="saveNoteModalLabel">마이 라이브러리 저장</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 bg-light rounded mb-3">
                    <span id="modalCategory" class="badge bg-primary mb-1">카테고리</span>
                    <h6 id="modalTitle" class="fw-bold text-dark mb-1">자료 제목</h6>
                    <small id="modalDate" class="text-muted d-block"></small>
                </div>
                <div class="mb-3">
                    <label for="modalNote" class="form-label small fw-medium">개인 주석 및 정책 검토 의견 (선택사항)</label>
                    <textarea class="form-control" id="modalNote" rows="3" placeholder="예: 2Q 정책 토론회 세션 발제 자료로 활용 예정..."></textarea>
                </div>
                <div id="modalErrorMessage" class="alert alert-danger d-none py-2 small"></div>
                <div id="modalSuccessMessage" class="alert alert-success d-none py-2 small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="confirmSaveBtn">자료 저장하기</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    let activeItemToSave = null;
    const saveModal = new bootstrap.Modal(document.getElementById('saveNoteModal'));

    const searchForm = document.getElementById('searchForm');
    const resultsContainer = document.getElementById('results');
    const loader = document.querySelector('.loader');
    const countBadge = document.getElementById('resultsCount');

    // Trigger Search
    const performSearch = async (query = '') => {
        loader.classList.remove('d-none');
        resultsContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-secondary small">정밀 국회 레포트를 분석 및 매칭시키는 중...</p>
            </div>
        `;

        try {
            const r = await fetch(`./api/assembly/search.php?q=${encodeURIComponent(query)}`);
            const data = await r.json();
            
            resultsContainer.innerHTML = '';
            countBadge.textContent = data.length;

            if (data.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-exclamation-triangle display-3 text-warning mb-3"></i>
                        <p class="fw-bold">매칭되는 국회 분석 자료가 없습니다.</p>
                        <small>검색단어를 수정하거나 맞춤법을 확인해 주세요.</small>
                    </div>
                `;
                loader.classList.add('d-none');
                return;
            }

            data.forEach(item => {
                const card = document.createElement('div');
                card.className = 'card p-3 hover-elevate border bg-white';
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 small">${item.mtr_div}</span>
                                <small class="text-muted font-monospace"><i class="bi bi-clock me-1"></i>${item.wrt_dt}</small>
                            </div>
                            <h6 class="fw-bold text-dark lh-base mb-2">${item.mtr_ttl}</h6>
                        </div>
                        <a href="${item.link_url}" target="_blank" class="btn btn-outline-secondary btn-sm flex-shrink-0" title="원문 링크 이동">
                            <i class="bi bi-box-arrow-up-right"></i> 원문
                        </a>
                    </div>
                    <hr class="my-2 border-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">출처: 국회아카이브 (Assembly Cache)</span>
                        <button class="btn btn-primary btn-sm py-1 px-3 save-to-lib-btn animate-btn" data-id="${item.id}">
                            <i class="bi bi-plus-circle me-1"></i>워크스페이스 아카이브
                        </button>
                    </div>
                `;

                // Handle save button trigger modal
                card.querySelector('.save-to-lib-btn').addEventListener('click', () => {
                    activeItemToSave = item;
                    document.getElementById('modalCategory').textContent = item.mtr_div;
                    document.getElementById('modalTitle').textContent = item.mtr_ttl;
                    document.getElementById('modalDate').textContent = `작성일: ${item.wrt_dt}`;
                    document.getElementById('modalNote').value = '';
                    
                    document.getElementById('modalErrorMessage').classList.add('d-none');
                    document.getElementById('modalSuccessMessage').classList.add('d-none');
                    
                    saveModal.show();
                });

                resultsContainer.appendChild(card);
            });
        } catch (err) {
            resultsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-octagon me-1"></i>데이터 검색 중 알 수 없는 시스템 오류가 발생했습니다.
                </div>
            `;
        } finally {
            loader.classList.add('d-none');
        }
    };

    // Fast recommendation click
    document.querySelectorAll('.fast-tag').forEach(btn => {
        btn.addEventListener('click', () => {
            const tag = btn.getAttribute('data-tag');
            document.getElementById('q').value = tag;
            performSearch(tag);
        });
    });

    // Form submission
    searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        performSearch(document.getElementById('q').value);
    });

    // Handle Confirm Save inside Modal
    document.getElementById('confirmSaveBtn').addEventListener('click', async () => {
        if (!activeItemToSave) return;
        
        const workspace_id = document.getElementById('selected_workspace').value;
        const note = document.getElementById('modalNote').value;
        const errorDiv = document.getElementById('modalErrorMessage');
        const successDiv = document.getElementById('modalSuccessMessage');

        errorDiv.classList.add('d-none');
        successDiv.classList.add('d-none');

        try {
            const r = await fetch('./api/library/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    workspace_id: workspace_id,
                    item_id: activeItemToSave.id,
                    note: note
                })
            });
            const res = await r.json();
            if (res.ok) {
                successDiv.textContent = '선택하신 워크스페이스 라이브러리에 완전히 등록되었습니다.';
                successDiv.classList.remove('d-none');
                setTimeout(() => {
                    saveModal.hide();
                }, 1000);
            } else {
                errorDiv.textContent = res.error || '저장하는 중 오류가 발생했습니다.';
                errorDiv.classList.remove('d-none');
            }
        } catch (err) {
            errorDiv.textContent = '네트워크 서버 응답 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        }
    });

    // Initial default search
    performSearch('');
});
</script>

<?php include 'templates/footer.php'; ?>
