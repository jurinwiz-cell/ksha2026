<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'templates/header.php';
include 'templates/navbar.php';

$name = $_SESSION['name'] ?? '사용자';
$email = $_SESSION['email'] ?? '이메일 없음';
$role = $_SESSION['role'] ?? 'user';
?>

<div class="container py-5 flex-grow-1 animate-fade-in">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card p-4 p-md-5 shadow-sm bg-white">
                <div class="text-center mb-4 border-bottom pb-4">
                    <div class="position-relative d-inline-block">
                        <div class="avatar bg-primary text-white d-flex align-items-center justify-content-center rounded-circle mx-auto mb-3 shadow" style="width: 90px; height: 90px; font-size: 2.2rem;">
                            <?= mb_substr($name, 0, 1) ?>
                        </div>
                        <span class="position-absolute bottom-0 end-0 badge bg-warning text-dark p-2 border border-white rounded-circle" title="공식 가입자">
                            <i class="bi bi-patch-check-fill"></i>
                        </span>
                    </div>
                    <h4 class="fw-bold text-dark m-0"><?= htmlspecialchars($name) ?></h4>
                    <p class="text-secondary small mt-1 mb-0"><?= htmlspecialchars($email) ?></p>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold mb-3 text-secondary"><i class="bi bi-info-circle me-1"></i>계정 정보 상세</h6>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">계정 유형 (Role)</span>
                            <span class="badge bg-secondary p-2 text-capitalize"><?= htmlspecialchars($role) ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted small">기본 언어</span>
                            <span class="fw-medium text-dark small">한국어 (Korean)</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                            <span class="text-muted small">시스템 접속 허가</span>
                            <span class="text-success fw-medium small"><i class="bi bi-check-circle-fill me-1"></i>정상 인가전용 승인됨</span>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold mb-3 text-secondary"><i class="bi bi-folder-check me-1"></i>소속 워크스페이스 참여 정보</h6>
                    <div class="p-3 bg-light rounded d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-dark fw-medium">1. AI 및 신기술 특별 공동 입법 TF</span>
                            <span class="badge bg-info text-dark px-2 py-1" style="font-size: 0.7rem;">실무 에디터</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                            <span class="small text-dark fw-medium">2. 기후위기 극복 탄소중립 특별포럼</span>
                            <span class="badge bg-info text-dark px-2 py-1" style="font-size: 0.7rem;">실무 에디터</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                            <span class="small text-dark fw-medium">3. 지방소멸 실질대응 국회 기획협의체</span>
                            <span class="badge bg-dark text-light px-2 py-1" style="font-size: 0.7rem;">소유주 Owner</span>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <a href="/dashboard.php" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-house-door-fill me-1"></i>홈 대시보드로 가기
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
