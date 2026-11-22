<?php

declare(strict_types=1);

use Richrix\Modules\DbAdminer\Services\SessionService;
use Richrix\Modules\DbAdminer\Services\InstallerService;

$baseDir = __DIR__ . '/../../';
require $baseDir . 'common/autoload.php';
Context::init();

if (!SessionService::validateTokenSession() && !SessionService::validateAccessSession()) {
    http_response_code(403);
    exit();
}

if (SessionService::clearTokenSession()) {
    $dbInfo   = Context::getDBInfo();
    $masterDb = [];
    if (is_object($dbInfo) && isset($dbInfo->master_db) && is_array($dbInfo->master_db)) {
        $masterDb = $dbInfo->master_db;
    }
    if (empty($masterDb)) {
        http_response_code(500);
        exit();
    }

    $driver   = 'server';
    $server   = (string) ($masterDb['db_hostname'] ?? 'localhost');
    $port     = (string) ($masterDb['db_port'] ?? '');
    $username = (string) ($masterDb['db_userid'] ?? '');
    $password = (string) ($masterDb['db_password'] ?? '');
    $database = (string) ($masterDb['db_database'] ?? '');
    if ($port !== '') {
        if (filter_var($server, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $server = '[' . $server . ']:' . $port;
        } elseif (!str_contains($server, ':')) {
            $server .= ':' . $port;
        }
    }

    $_POST['auth'] = [
        'driver' => $driver,
        'server' => $server,
        'username' => $username,
        'password' => $password,
        'db' => $database,
        'permanent' => 0,
    ];
    SessionService::createAccessSession();
}

include InstallerService::ADMINER_TARGET_PATH;
exit;
