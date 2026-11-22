<?php
/**
 * includes/auth.php - 세션 인증 체크 공통 모듈
 * 모든 보호 페이지 최상단에 require 합니다.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header('Location: /shm/login.php');
    exit;
}
