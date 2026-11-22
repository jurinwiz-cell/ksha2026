<?php
if (!defined('__XE__')) exit();
/**
 * @file   counter_plus.admin.view.php
 * @author 불패의초인 (BSplus) <admin@bsplus.net>
 */
class counter_plusAdminView extends counter_plus
{
    function init()
    {
        $logged = Context::get('logged_info');
        if (!$logged || $logged->is_admin !== 'Y') {
            return new BaseObject(-1, '관리자 권한이 필요합니다.');
        }
    }

    // ================================================================
    // 통계 페이지 (탭1: Rhymix / 탭2: GA4)
    // ================================================================
    function dispCounter_plusAdminStats()
    {
        $oModel = getModel('counter_plus');

        $tab = Context::get('tab');
        if ($tab !== 'ga4') $tab = 'rhymix';

        // 탭1
        $valid_periods = ['today' => true, 'week' => true, 'month' => true, 'all' => true];
        $period        = Context::get('period');
        if (!$period || !isset($valid_periods[$period])) $period = 'week';
        $module_srl    = (int)(Context::get('module_srl') ?: 0);

        $top_docs      = $oModel->getTopDocuments($period, $module_srl, 20);
        $board_list    = $oModel->getBoardList();
        $period_labels = ['today' => '오늘', 'week' => '최근 7일', 'month' => '최근 30일', 'all' => '전체'];

        $rx_summary = $rx_hourly = $rx_boards = $rx_members = null;
        if ($tab === 'rhymix') {
            $rx_summary = $oModel->getRhymixSummary();
            $rx_hourly  = $oModel->getRhymixHourlyStats();
            $rx_boards  = $oModel->getRhymixBoardStats();
            $rx_members = $oModel->getRhymixMemberStats();
        }

        // 탭2
        $valid_days = ['7' => true, '30' => true];
        $ga4_days   = Context::get('ga4_days');
        if (!$ga4_days || !isset($valid_days[$ga4_days])) $ga4_days = '7';

        $ga4_result    = null;
        $ga4_dashboard = null;
        $ga4_realtime  = null;
        if ($tab === 'ga4') {
            $ga4_result    = $oModel->getGA4TopDocuments((int)$ga4_days, 20);
            $ga4_dashboard = $oModel->getGA4Dashboard((int)$ga4_days);
            $ga4_realtime  = $oModel->getGA4Realtime();
        }

        Context::set('tab',           $tab);
        Context::set('period',        $period);
        Context::set('module_srl',    $module_srl);
        Context::set('top_docs',      $top_docs);
        Context::set('board_list',    $board_list);
        Context::set('period_labels', $period_labels);
        Context::set('ga4_days',      $ga4_days);
        Context::set('ga4_result',    $ga4_result);
        Context::set('ga4_dashboard', $ga4_dashboard);
        Context::set('ga4_realtime',  $ga4_realtime);
        Context::set('rx_summary',    $rx_summary);
        Context::set('rx_hourly',     $rx_hourly);
        Context::set('rx_boards',     $rx_boards);
        Context::set('rx_members',    $rx_members);

        $this->setTemplatePath($this->module_path . 'tpl');
        $this->setTemplateFile('admin_stats');
    }

    // ================================================================
    // GA4 설정 페이지
    // ================================================================
    function dispCounter_plusAdminConfig()
    {
        $oModel = getModel('counter_plus');
        $config = $oModel->getGA4Config();

        $is_connected = !empty($config->ga4_refresh_token);

        // OAuth 인증 URL (클라이언트 ID/Secret이 저장된 경우)
        $auth_url = '';
        if (!empty($config->ga4_client_id) && !empty($config->ga4_client_secret)) {
            require_once RX_BASEDIR . 'modules/counter_plus/lib/GA4Client.php';
            $redirect_uri = $this->_getRedirectUri();
            $auth_url     = GA4Client::buildAuthUrl($config->ga4_client_id, $redirect_uri);
        }

        Context::set('ga4_property_id',  $config->ga4_property_id ?? '');
        Context::set('ga4_client_id',    $config->ga4_client_id ?? '');
        Context::set('ga4_client_secret',$config->ga4_client_secret ?? '');
        Context::set('is_connected',     $is_connected);
        Context::set('ga4_connected_at', $config->ga4_connected_at ?? '');
        Context::set('auth_url',         $auth_url);
        Context::set('redirect_uri',     $this->_getRedirectUri());
        Context::set('oauth_success',    Context::get('oauth_success'));
        Context::set('oauth_error',      Context::get('oauth_error'));

        $this->setTemplatePath($this->module_path . 'tpl');
        $this->setTemplateFile('admin_config');
    }

    // ================================================================
    // OAuth2 콜백 (Google → 여기로 리다이렉트)
    // ================================================================
    function dispCounter_plusAdminOAuthCallback()
    {
        $code  = Context::get('code');
        $error = Context::get('error');

        $config_url = getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCounter_plusAdminConfig');

        if ($error || !$code) {
            header('Location: ' . $config_url . '&oauth_error=' . urlencode($error ?: 'cancelled'));
            exit;
        }

        $config = \ModuleModel::getInstance()->getModuleConfig('counter_plus') ?: new stdClass;

        if (empty($config->ga4_client_id) || empty($config->ga4_client_secret)) {
            header('Location: ' . $config_url . '&oauth_error=no_client');
            exit;
        }

        require_once RX_BASEDIR . 'modules/counter_plus/lib/GA4Client.php';
        $redirect_uri = $this->_getRedirectUri();
        $tokens = GA4Client::exchangeCode($code, $config->ga4_client_id, $config->ga4_client_secret, $redirect_uri);

        if (!$tokens || empty($tokens['access_token'])) {
            header('Location: ' . $config_url . '&oauth_error=token_exchange');
            exit;
        }

        // 토큰 저장
        $config->ga4_access_token     = $tokens['access_token'];
        $config->ga4_token_expires_at = time() + (int)($tokens['expires_in'] ?? 3600) - 60;
        if (!empty($tokens['refresh_token'])) {
            $config->ga4_refresh_token = $tokens['refresh_token'];
        }
        $config->ga4_connected_at = date('Y-m-d H:i:s');

        getController('module')->insertModuleConfig('counter_plus', $config);

        header('Location: ' . $config_url . '&oauth_success=1');
        exit;
    }

    // ================================================================
    private function _getRedirectUri()
    {
        return getNotEncodedFullUrl('', 'module', 'admin', 'act', 'dispCounter_plusAdminOAuthCallback');
    }
}
