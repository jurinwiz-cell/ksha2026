<?php
if (!defined('__XE__')) exit();
/**
 * @file   counter_plus.admin.controller.php
 * @author 불패의초인 (BSplus) <admin@bsplus.net>
 */
class counter_plusAdminController extends counter_plus
{
    function init() {}

    // ================================================================
    // 설정 저장 (Property ID + Client ID + Client Secret)
    // ================================================================
    function procCounter_plusAdminConfig()
    {
        $logged = Context::get('logged_info');
        if (!$logged || $logged->is_admin !== 'Y') {
            return new BaseObject(-1, '관리자 권한이 필요합니다.');
        }

        $property_id   = trim(Context::get('ga4_property_id') ?: '');
        $client_id     = trim(Context::get('ga4_client_id') ?: '');
        $client_secret = trim(Context::get('ga4_client_secret') ?: '');

        $config = \ModuleModel::getInstance()->getModuleConfig('counter_plus') ?: new stdClass;

        // 덮어쓰기 전에 이전 값을 먼저 읽어야 비교 가능
        $prev_client_id = $config->ga4_client_id ?? '';
        $config->ga4_property_id   = $property_id;
        $config->ga4_client_id     = $client_id;
        $config->ga4_client_secret = $client_secret;

        // 클라이언트 ID가 바뀌면 기존 토큰 초기화
        if ($prev_client_id && $prev_client_id !== $client_id) {
            unset($config->ga4_access_token, $config->ga4_refresh_token,
                  $config->ga4_token_expires_at, $config->ga4_connected_at);
        }

        getController('module')->insertModuleConfig('counter_plus', $config);

        $redirect = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCounter_plusAdminConfig');
        $this->setRedirectUrl($redirect);
        return new BaseObject(0, '설정이 저장되었습니다.');
    }

    // ================================================================
    // 구글 연동 해제
    // ================================================================
    function procCounter_plusAdminOAuthRevoke()
    {
        $logged = Context::get('logged_info');
        if (!$logged || $logged->is_admin !== 'Y') {
            return new BaseObject(-1, '관리자 권한이 필요합니다.');
        }

        $config = \ModuleModel::getInstance()->getModuleConfig('counter_plus') ?: new stdClass;

        // 구글에 토큰 revoke 요청 (선택적, 실패해도 무방)
        if (!empty($config->ga4_access_token)) {
            @file_get_contents('https://oauth2.googleapis.com/revoke?token=' . urlencode($config->ga4_access_token));
        }

        unset($config->ga4_access_token, $config->ga4_refresh_token,
              $config->ga4_token_expires_at, $config->ga4_connected_at);

        getController('module')->insertModuleConfig('counter_plus', $config);

        $redirect = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCounter_plusAdminConfig');
        $this->setRedirectUrl($redirect);
        return new BaseObject(0, '구글 연동이 해제되었습니다.');
    }
}
