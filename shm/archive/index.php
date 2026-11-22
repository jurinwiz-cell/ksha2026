<?php
// index.php
require_once 'api/_auth_bridge.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
