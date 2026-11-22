<?php
session_start();
if (!empty($_SESSION['user_id'])) {
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
                <i class="bi bi-person-plus-fill fs-1"></i>
            </div>
            <h3 class="fw-bold text-dark m-0">회원가입</h3>
            <p class="text-muted small mt-2">새 계정을 만들어 시스템을 시작하세요.</p>
        </div>
        
        <form id="registerForm">
            <div class="mb-3">
                <label for="name" class="form-label small text-secondary">이름</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="name" required placeholder="홍길동">
                </div>
            </div>
            
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
            <div id="successMessage" class="alert alert-success d-none mb-3 py-2 small"></div>
            
            <button type="submit" class="btn btn-primary w-100 fw-medium shadow-sm py-2">
                <i class="bi bi-person-check me-1"></i>회원 등록
            </button>
            
            <div class="text-center mt-4">
                <span class="text-muted small">이미 계정이 있으신가요? </span>
                <a href="./login.php" class="text-decoration-none small fw-medium text-primary">로그인</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    document.getElementById('registerForm').addEventListener('submit', async e => {
        e.preventDefault();
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const errorDiv = document.getElementById('errorMessage');
        const successDiv = document.getElementById('successMessage');
        
        errorDiv.classList.add('d-none');
        successDiv.classList.add('d-none');
        
        try {
            const r = await fetch('/api/auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email, password })
            });
            const data = await r.json();
            if (data.ok) {
                successDiv.textContent = '회원가입이 완료되었습니다! 잠시 후 로그인 화면으로 이동합니다.';
                successDiv.classList.remove('d-none');
                setTimeout(() => {
                    window.location.href = '/login.php';
                }, 1500);
            } else {
                errorDiv.textContent = data.error || '회원가입 진행 중 오류가 발생했습니다.';
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
