<?php

declare(strict_types=1);

namespace Richrix\Modules\DbAdminer\Services;

use Context;

final class SessionService
{
    public const TOKEN_SESSION_KEY = 'richrix_db_adminer_token_session_key';
    public const ACCESS_SESSION_KEY = 'richrix_db_adminer_access_session_key';
    public const TOKEN_TTL = 10;
    public const ACCESS_TTL = 3600;

    /**
     * 토큰 세션을 생성합니다.
     * 이 세션은 Adminer 페이지로 리디렉션된 후 아주 짧은 시간 동안만 유효합니다.
     *
     * @return void
     */
    public static function createTokenSession(): void
    {
        $_SESSION[self::TOKEN_SESSION_KEY] = time();
    }

    /**
     * 접속 세션을 생성합니다.
     * 이 세션은 Adminer 페이지에 접속한 후 일정 시간 동안 유효합니다.
     *
     * @return void
     */
    public static function createAccessSession(): void
    {
        $_SESSION[self::ACCESS_SESSION_KEY] = time();
    }

    /**
     * 토큰 세션을 삭제합니다.
     *
     * @return bool
     */
    public static function clearTokenSession(): bool
    {
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return false;
        }
        unset($_SESSION[self::TOKEN_SESSION_KEY]);
        return true;
    }

    /**
     * 토큰 세션이 유효한지 검증합니다.
     *
     * @return bool
     */
    public static function validateTokenSession(): bool
    {
        if (!self::validateAdmin()) {
            return false;
        }
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return false;
        }
        return time() - $_SESSION[self::TOKEN_SESSION_KEY] <= self::TOKEN_TTL;
    }

    /**
     * 접속 세션이 유효한지 검증합니다.
     *
     * @return bool
     */
    public static function validateAccessSession(): bool
    {
        if (!self::validateAdmin()) {
            return false;
        }
        if (!isset($_SESSION[self::ACCESS_SESSION_KEY])) {
            return false;
        }
        return time() - $_SESSION[self::ACCESS_SESSION_KEY] <= self::ACCESS_TTL;
    }

    /**
     * 현재 로그인한 사용자가 관리자 권한을 가지고 있는지 검증합니다.
     *
     * @return bool
     */
    private static function validateAdmin(): bool
    {
        $loggedInfo = Context::get('logged_info');
        return is_object($loggedInfo) && $loggedInfo->is_admin === 'Y';
    }
}
