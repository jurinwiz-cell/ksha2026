<?php

declare(strict_types=1);

namespace Richrix\Modules\DbAdminer\Services;

use ModuleModel;

final class ConfigService
{
    public const PASSWORD_SESSION_KEY = 'richrix_db_adminer_password_session_key';

    public static function getConfig(): object
    {
        return ModuleModel::getModuleConfig('db_adminer') ?? new \stdClass();
    }

    public static function hasPassword(): bool
    {
        $config = self::getConfig();
        return isset($config->accessPassword) && (string) $config->accessPassword !== '';
    }

    public static function verifyPassword(string $password): bool
    {
        $config = self::getConfig();
        if (!isset($config->accessPassword) || (string) $config->accessPassword === '') {
            return false;
        }
        return password_verify($password, (string) $config->accessPassword);
    }

    public static function getSessionTtl(): int
    {
        $config = self::getConfig();
        if (!isset($config->sessionTtl)) {
            return 0;
        }
        $ttl = (int) $config->sessionTtl;
        return $ttl < 0 ? 0 : $ttl;
    }

    public static function isPasswordSessionValid(): bool
    {
        if (!isset($_SESSION[self::PASSWORD_SESSION_KEY])) {
            return false;
        }
        $sessionTime = (int) $_SESSION[self::PASSWORD_SESSION_KEY];
        $now = time();
        if ($sessionTime > $now) {
            return false;
        }
        $ttl = self::getSessionTtl();
        if ($ttl === 0) {
            return true;
        }
        return ($now - $sessionTime) <= $ttl;
    }

    public static function createPasswordSession(): void
    {
        $_SESSION[self::PASSWORD_SESSION_KEY] = time();
    }

    public static function clearPasswordSession(): void
    {
        unset($_SESSION[self::PASSWORD_SESSION_KEY]);
    }
}
