<?php
/**
 * includes/member_auth.php - 회원사 전용(소셜 로그인 사용자) 인증 체크
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 소셜 로그인 후 승인 완료된 사용자만 접근 가능
if (!isset($_SESSION['user_id']) || !isset($_SESSION['member_id']) || $_SESSION['user_role'] !== 'company_user') {
    // 권한이 없으면 일반 로그인 페이지로 리다이렉트
    header('Location: /shm/login.php');
    exit;
}
