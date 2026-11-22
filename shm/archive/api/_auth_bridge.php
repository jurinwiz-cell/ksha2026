<?php
// api/_auth_bridge.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Perform Session Bridging from shm parent to archive
if (empty($_SESSION['user_id'])) {
    $parent_keys = ['ss_mb_id', 'userid', 'mb_id', 'member_id', 'seq', 'member_seq', 'idx', 'member_idx', 'shm_user', 'shm_id', 'login_id'];
    foreach ($parent_keys as $k) {
        if (!empty($_SESSION[$k])) {
            $_SESSION['user_id'] = $_SESSION[$k];
            $_SESSION['name'] = $_SESSION['name'] ?? $_SESSION['ss_mb_nick'] ?? $_SESSION['ss_mb_name'] ?? $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'SHM User';
            
            // Check admin role
            $is_admin = false;
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $is_admin = true;
            } elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
                $is_admin = true;
            } elseif (isset($_SESSION['level']) && intval($_SESSION['level']) >= 10) {
                $is_admin = true;
            } elseif (isset($_SESSION['ss_mb_level']) && intval($_SESSION['ss_mb_level']) >= 10) {
                $is_admin = true;
            }
            $_SESSION['role'] = $is_admin ? 'admin' : 'user';
            $_SESSION['email'] = $_SESSION['email'] ?? ($_SESSION['user_id'] . '@socialhousing.kr');
            break;
        }
    }
}

// 2. Helper to protect page access and redirect if needed
function requireSessionPage($isAdminOnly = false, $relativeToRoot = '') {
    if (empty($_SESSION['user_id'])) {
        header("Location: " . $relativeToRoot . "login.php");
        exit;
    }
    
    if ($isAdminOnly && ($_SESSION['role'] ?? 'user') !== 'admin') {
        header("Location: " . $relativeToRoot . "dashboard.php");
        exit;
    }
}