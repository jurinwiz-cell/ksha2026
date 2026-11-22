<?php
if (!defined('__XE__')) exit();

class GA4Client
{
    const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    const REPORT_URL   = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport';
    const BATCH_URL    = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:batchRunReports';
    const REALTIME_URL = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:runRealtimeReport';
    const SCOPE        = 'https://www.googleapis.com/auth/analytics.readonly';

    private $access_token;
    private $property_id;
    private $last_error = '';

    public function __construct($access_token, $property_id)
    {
        $this->access_token = $access_token;
        $this->property_id  = preg_replace('/[^0-9]/', '', (string)$property_id);
    }

    public function getLastError() { return $this->last_error; }

    public function isConfigured()
    {
        return !empty($this->access_token) && !empty($this->property_id);
    }

    // ----------------------------------------------------------------
    // OAuth2 정적 메서드
    // ----------------------------------------------------------------
    public static function buildAuthUrl($client_id, $redirect_uri)
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    public static function exchangeCode($code, $client_id, $client_secret, $redirect_uri)
    {
        $resp = self::_curlPost(self::TOKEN_URL, http_build_query([
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        if (!$resp) return null;
        $data = json_decode($resp, true);
        return empty($data['access_token']) ? null : $data;
    }

    public static function refreshAccessToken($refresh_token, $client_id, $client_secret)
    {
        $resp = self::_curlPost(self::TOKEN_URL, http_build_query([
            'refresh_token' => $refresh_token,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'refresh_token',
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        if (!$resp) return null;
        $data = json_decode($resp, true);
        return empty($data['access_token']) ? null : $data;
    }

    // ----------------------------------------------------------------
    // 인기 페이지 (기존 호환 유지)
    // ----------------------------------------------------------------
    public function runReport($days = 7, $limit = 100)
    {
        return $this->_apiPost(sprintf(self::REPORT_URL, $this->property_id), [
            'dimensions' => [['name' => 'pagePath']],
            'metrics'    => [
                ['name' => 'screenPageViews'],
                ['name' => 'userEngagementDuration'],
            ],
            'dateRanges' => [['startDate' => $days . 'daysAgo', 'endDate' => 'today']],
            'orderBys'   => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit'      => $limit,
        ]);
    }

    // ----------------------------------------------------------------
    // 실시간 활성 사용자 수 — ['count' => int, 'error' => string|null]
    // ----------------------------------------------------------------
    public function runRealtimeReport()
    {
        $data = $this->_apiPost(sprintf(self::REALTIME_URL, $this->property_id), [
            'metrics' => [['name' => 'activeUsers']],
        ]);
        if ($data === null) {
            return ['count' => 0, 'error' => $this->last_error ?: 'API 오류'];
        }
        $count = empty($data['rows']) ? 0 : (int)($data['rows'][0]['metricValues'][0]['value'] ?? 0);
        return ['count' => $count, 'error' => null];
    }

    // ----------------------------------------------------------------
    // 실시간 접속자 + 현재 페이지 목록
    // ----------------------------------------------------------------
    public function runRealtimePageReport($limit = 10)
    {
        $data = $this->_apiPost(sprintf(self::REALTIME_URL, $this->property_id), [
            'dimensions' => [['name' => 'unifiedScreenName']],
            'metrics'    => [['name' => 'activeUsers']],
            'orderBys'   => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
            'limit'      => $limit,
        ]);

        if (!$data) {
            return ['active_users' => 0, 'pages' => [], 'error' => $this->last_error ?: 'API 오류'];
        }

        $pages = [];
        $total = 0;
        foreach ($data['rows'] ?? [] as $row) {
            $users   = (int)($row['metricValues'][0]['value'] ?? 0);
            $total  += $users;
            $raw_title = $row['dimensionValues'][0]['value'] ?? '';
            $pages[] = [
                'title' => ($raw_title !== '' && $raw_title !== '(not set)') ? $raw_title : '(알 수 없음)',
                'users' => $users,
            ];
        }

        return ['active_users' => $total, 'pages' => $pages, 'error' => null];
    }

    // ----------------------------------------------------------------
    // 배치 리포트 (국가/기기/소스/신규재방문 — HTTP 1회)
    // $requests: runReport body 배열의 배열
    // returns: 각 리포트 결과 배열 (실패한 항목은 null)
    // ----------------------------------------------------------------
    public function batchRunReports(array $requests)
    {
        $url  = sprintf(self::BATCH_URL, $this->property_id);
        $data = $this->_apiPost($url, ['requests' => $requests]);

        if (!$data || empty($data['reports'])) {
            return array_fill(0, count($requests), null);
        }

        $results = [];
        foreach ($data['reports'] as $report) {
            $results[] = empty($report['error']) ? $report : null;
        }
        // 부족한 경우 null로 채움
        while (count($results) < count($requests)) {
            $results[] = null;
        }
        return $results;
    }

    // ----------------------------------------------------------------
    // 내부: 인증 헤더 포함 API POST
    // ----------------------------------------------------------------
    private function _apiPost($url, array $body)
    {
        $resp = self::_curlPost($url, json_encode($body), [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json',
        ]);

        if (!$resp) {
            $this->last_error = 'cURL 요청 실패';
            return null;
        }

        $data = json_decode($resp, true);
        if (!empty($data['error'])) {
            $code = $data['error']['code'] ?? 0;
            $msg  = $data['error']['message'] ?? 'Unknown error';
            $this->last_error = "[{$code}] {$msg}";
            return null;
        }
        return $data;
    }

    // ----------------------------------------------------------------
    // cURL 공통 (static, SSL CA 자동 처리)
    // ----------------------------------------------------------------
    private static function _curlPost($url, $body, array $headers)
    {
        $ch   = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        $ca = self::_findCaBundle();
        if ($ca) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opts);
        $resp     = curl_exec($ch);
        $curl_err = ($resp === false) ? curl_error($ch) : '';
        curl_close($ch);
        if ($curl_err) {
            error_log('counter_plus GA4 cURL error: ' . $curl_err . ' [' . $url . ']');
        }
        return $resp ?: null;
    }

    private static function _findCaBundle()
    {
        // PHP openssl 기본 경로 (Linux 배포판 대부분 여기서 해결됨)
        if (function_exists('openssl_get_cert_locations')) {
            $loc = openssl_get_cert_locations();
            if (!empty($loc['default_cert_file']) && file_exists($loc['default_cert_file'])) {
                return $loc['default_cert_file'];
            }
        }
        // Linux 주요 경로
        foreach ([
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/ca-bundle.pem',
            '/usr/local/share/certs/ca-root-nss.crt',
        ] as $path) {
            if (file_exists($path)) return $path;
        }
        // XAMPP on Windows (드라이브 문자 자동 탐색)
        foreach (['C', 'D', 'E', 'F', 'G'] as $d) {
            $path = $d . ':/xampp/apache/bin/curl-ca-bundle.crt';
            if (file_exists($path)) return $path;
        }
        return null;
    }
}
