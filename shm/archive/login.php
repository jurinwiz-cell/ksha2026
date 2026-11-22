<?php
session_start();
if (!empty($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}
include 'templates/header.php';
include 'templates/navbar.php';
?>

<div class="container d-flex flex-grow-1 align-items-center justify-content-center py-5">
    <div class="card p-4 p-md-5 shadow-lg w-100" style="max-width: 440px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex bg-primary bg-opacity-10 p-3 rounded-circle text-primary mb-3">
                <i class="bi bi-shield-lock-fill fs-1"></i>
            </div>
            <h3 class="fw-bold text-dark m-0">로그인</h3>
            <p class="text-muted small mt-2">정책자료 아카이브 시스템</p>
        </div>
        
        <form id="loginForm">
            <div class="mb-3">
                <label for="email" class="form-label small text-secondary">이메일 주소</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" required placeholder="name@domain.com">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label small text-secondary">비밀번호</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" required placeholder="••••••••">
                </div>
            </div>
            
            <div id="errorMessage" class="alert alert-danger d-none mb-3 py-2 small"></div>
            
            <button type="submit" class="btn btn-primary w-100 fw-medium shadow-sm py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i>로그인
            </button>
            
            <div class="text-center mt-4">
                <span class="text-muted small">계정이 없으신가요? </span>
                <a href="./register.php" class="text-decoration-none small fw-medium text-primary">회원가입</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    document.getElementById('loginForm').addEventListener('submit', async e => {
        e.preventDefault();
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const errorDiv = document.getElementById('errorMessage');
        
        errorDiv.classList.add('d-none');
        
        try {
            const r = await fetch('./api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            const data = await r.json();
            if (data.ok) {
                window.location.href = './dashboard.php';
            } else {
                errorDiv.textContent = data.error || '이메일 또는 비밀번호가 올바르지 않습니다.';
                errorDiv.classList.remove('d-none');
            }
        } catch (err) {
            errorDiv.textContent = '네트워크 서버 통신 중 오류가 발생했습니다.';
            errorDiv.classList.remove('d-none');
        }
    });
});
</script>

<?php include 'templates/footer.php'; ?>
