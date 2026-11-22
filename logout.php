<?php
/**
 * logout.php - 세션 종료 및 로그인 페이지 리다이렉트
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
header('Location: /shm/login.php');
exit;
