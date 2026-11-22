<?php
// templates/navbar.php
$user_id = $_SESSION['user_id'] ?? null;
$name = $_SESSION['name'] ?? '';
$role = $_SESSION['role'] ?? 'user';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center animate-brand" href="/dashboard.php">
            <i class="bi bi-archive-fill me-2 text-primary"></i>
            <span>정책자료 아카이브</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (!empty($user_id)): ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard.php">
                            <i class="bi bi-search me-1"></i>자료검색
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/library.php">
                            <i class="bi bi-bookmark-star me-1"></i>마이 라이브러리
                        </a>
                    </li>
                    <?php if ($role === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-warning" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-shield-lock me-1"></i>시스템 관리
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="/admin/index.php"><i class="bi bi-graph-up me-1"></i>현황 통계</a></li>
                                <li><a class="dropdown-item" href="/admin/users.php"><i class="bi bi-people me-1"></i>사용자 통계</a></li>
                                <li><a class="dropdown-item" href="/admin/workspaces.php"><i class="bi bi-folder2-open me-1"></i>워크스페이스 제어</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="/profile.php" class="text-white text-decoration-none hover-light">
                        <i class="bi bi-person-circle text-info me-1"></i>
                        <strong><?= htmlspecialchars($name) ?></strong>
                        <span class="badge bg-secondary ms-1 small text-capitalize"><?= htmlspecialchars($role) ?></span>
                    </a>
                    <a href="/logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>로그아웃
                    </a>
                </div>
            <?php else: ?>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="/login.php">로그인</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white btn-sm px-3 ms-2" href="/register.php">회원가입</a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
