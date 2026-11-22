<?php
if (!defined('__XE__')) exit();
/**
 * @file   counter_plus.model.php
 * @author 불패의초인 (BSplus) <admin@bsplus.net>
 * @brief  기간별 인기 게시물 조회 + GA4 연동
 */
class counter_plusModel extends counter_plus
{
    function init() {}

    // ================================================================
    // Rhymix 자체 조회수
    // ================================================================

    function getTopDocuments($period = 'week', $module_srl = 0, $limit = 20)
    {
        $date_from = $this->_getDateFrom($period);

        $conditions = array("WHERE d.status = 'PUBLIC'");
        $params     = array();

        if ($date_from !== null) {
            $conditions[] = "AND r.regdate >= ?";
            $params[] = $date_from;
        }
        if ($module_srl > 0) {
            $conditions[] = "AND d.module_srl = ?";
            $params[] = (int)$module_srl;
        }

        $where     = implode(' ', $conditions);
        $limit_int = max(1, (int)$limit);

        $sql = "
            SELECT
                r.document_srl,
                COUNT(*)         AS total_views,
                d.title,
                d.nick_name,
                d.module_srl,
                d.readed_count,
                m.browser_title  AS board_name,
                m.mid
            FROM `document_readed_log` AS r
            JOIN `documents` AS d ON r.document_srl = d.document_srl
            JOIN `modules`   AS m ON d.module_srl   = m.module_srl
            {$where}
            GROUP BY r.document_srl
            ORDER BY COUNT(*) DESC
            LIMIT {$limit_int}
        ";

        $oDB = DB::getInstance();
        try { $stmt = $oDB->query($sql, $params); }
        catch (Exception $e) { return array(); }
        if (!$stmt) return array();

        $rows     = $stmt->fetchAll(PDO::FETCH_OBJ);
        $base_url = RX_BASEURL;
        foreach ($rows as $row) {
            $row->url        = $base_url . ($row->mid ? $row->mid . '?document_srl=' . (int)$row->document_srl : '?document_srl=' . (int)$row->document_srl);
            $row->board_name = $row->board_name ?: ($row->mid ?: '-');
        }
        return $rows;
    }

    function getBoardList()
    {
        $sql = "
            SELECT DISTINCT m.module_srl, m.browser_title, m.mid
            FROM `document_readed_log` AS r
            JOIN `documents` AS d ON r.document_srl = d.document_srl
            JOIN `modules`   AS m ON d.module_srl   = m.module_srl
            ORDER BY m.browser_title ASC
        ";
        $oDB = DB::getInstance();
        try { $stmt = $oDB->query($sql); }
        catch (Exception $e) { return array(); }
        return $stmt ? $stmt->fetchAll(PDO::FETCH_OBJ) : array();
    }

    // ================================================================
    // GA4 Data API (OAuth2)
    // ================================================================

    function getGA4TopDocuments($days = 7, $limit = 20)
    {
        $config = $this->_getConfig();

        if (empty($config->ga4_property_id) || empty($config->ga4_refresh_token)) {
            return ['error' => 'not_configured', 'error_detail' => '', 'rows' => []];
        }

        $access_token = $this->_getValidAccessToken($config);
        if (!$access_token) {
            return ['error' => 'token_error', 'error_detail' => '액세스 토큰을 가져올 수 없습니다. 재연동이 필요할 수 있습니다.', 'rows' => []];
        }

        require_once RX_BASEDIR . 'modules/counter_plus/lib/GA4Client.php';
        $client = new GA4Client($access_token, $config->ga4_property_id);

        $report = $client->runReport((int)$days, 100);
        if (!$report) {
            return ['error' => 'api_error', 'error_detail' => $client->getLastError(), 'rows' => []];
        }
        if (empty($report['rows'])) {
            return ['error' => null, 'error_detail' => '', 'rows' => []];
        }

        // pagePath → views + engagement 집계
        $path_views = [];
        $path_eng   = [];
        foreach ($report['rows'] as $row) {
            $path  = $row['dimensionValues'][0]['value'] ?? '';
            $views = (int)($row['metricValues'][0]['value'] ?? 0);
            $eng   = (float)($row['metricValues'][1]['value'] ?? 0);
            if (!$path || $path === '(other)') continue;
            $path_views[$path] = ($path_views[$path] ?? 0) + $views;
            $path_eng[$path]   = ($path_eng[$path] ?? 0) + $eng;
        }

        // document_srl 추출 (쿼리스트링 + 경로 방식 모두)
        $srl_views = [];
        $srl_eng   = [];
        $unmatched = [];
        foreach ($path_views as $path => $views) {
            $srl = $this->_extractDocumentSrl($path);
            if ($srl) {
                $srl_views[$srl] = ($srl_views[$srl] ?? 0) + $views;
                $srl_eng[$srl]   = ($srl_eng[$srl] ?? 0) + ($path_eng[$path] ?? 0);
            } else {
                $unmatched[] = ['path' => $path, 'views' => $views, 'eng' => $path_eng[$path] ?? 0];
            }
        }
        arsort($srl_views);
        usort($unmatched, function($a, $b) { return $b['views'] - $a['views']; });

        $result   = [];
        $base_url = RX_BASEURL;

        if (!empty($srl_views)) {
            $placeholders = implode(',', array_fill(0, count($srl_views), '?'));
            $srls         = array_keys($srl_views);
            $sql = "
                SELECT d.document_srl, d.title, d.nick_name,
                       m.browser_title AS board_name, m.mid
                FROM `documents` AS d
                JOIN `modules`   AS m ON d.module_srl = m.module_srl
                WHERE d.document_srl IN ({$placeholders}) AND d.status = 'PUBLIC'
            ";
            $oDB = DB::getInstance();
            try { $stmt = $oDB->query($sql, $srls); } catch (Exception $e) { $stmt = null; }

            if ($stmt) {
                $docs = [];
                foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $doc) {
                    $docs[$doc->document_srl] = $doc;
                }
                foreach ($srl_views as $srl => $views) {
                    if (!isset($docs[$srl])) continue;
                    $doc             = $docs[$srl];
                    $eng_sec         = (int)round(($srl_eng[$srl] ?? 0) / ($views ?: 1));
                    $row             = new stdClass;
                    $row->title      = $doc->title;
                    $row->nick_name  = $doc->nick_name;
                    $row->board_name = $doc->board_name ?: ($doc->mid ?: '-');
                    $row->views      = $views;
                    $row->avg_time   = ($eng_sec >= 60)
                                            ? floor($eng_sec / 60) . '분 ' . ($eng_sec % 60) . '초'
                                            : $eng_sec . '초';
                    $row->matched    = true;
                    $row->url        = $base_url . ($doc->mid ? $doc->mid . '?document_srl=' . $srl : '?document_srl=' . $srl);
                    $result[]        = $row;
                    if (count($result) >= $limit) break;
                }
            }
        }

        // 미매칭 경로 분류: mid → xe_modules 배치 조회
        $mid_map = $this->_buildMidModuleMap($unmatched);

        foreach ($unmatched as $item) {
            if (count($result) >= $limit) break;
            $classified      = $this->_classifyPath($item['path'], $mid_map);
            $eng_sec         = (int)round(($item['eng'] ?? 0) / (($item['views'] ?: 1)));
            $row             = new stdClass;
            $row->title      = $classified['title'];
            $row->nick_name  = '-';
            $row->board_name = $classified['board_name'];
            $row->views      = $item['views'];
            $row->avg_time   = ($eng_sec >= 60)
                                    ? floor($eng_sec / 60) . '분 ' . ($eng_sec % 60) . '초'
                                    : $eng_sec . '초';
            $row->matched    = false;
            $row->url        = null;
            $result[]        = $row;
        }

        return ['error' => null, 'error_detail' => '', 'rows' => $result];
    }

    // ================================================================
    // GA4 대시보드 (실시간 + 국가/기기/소스/신규-재방문)
    // ================================================================
    function getGA4Dashboard($days = 7)
    {
        $config = $this->_getConfig();
        if (empty($config->ga4_property_id) || empty($config->ga4_refresh_token)) {
            return null;
        }

        $access_token = $this->_getValidAccessToken($config);
        if (!$access_token) return null;

        require_once RX_BASEDIR . 'modules/counter_plus/lib/GA4Client.php';
        $client     = new GA4Client($access_token, $config->ga4_property_id);
        $date_range = [['startDate' => $days . 'daysAgo', 'endDate' => 'today']];

        // 실시간 활성 사용자 (에러 구분 포함)
        $rt           = $client->runRealtimeReport();
        $active_users = $rt['count'];
        $active_users_error = $rt['error'];

        // 배치 A: 국가/기기/소스/신규재방문/총지표 (5개, API 한도)
        $rA = $client->batchRunReports([
            // 0: 국가 TOP 10
            [
                'dimensions' => [['name' => 'country'], ['name' => 'countryId']],
                'metrics'    => [['name' => 'sessions']],
                'dateRanges' => $date_range,
                'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit'      => 10,
            ],
            // 1: 기기별
            [
                'dimensions' => [['name' => 'deviceCategory']],
                'metrics'    => [['name' => 'sessions']],
                'dateRanges' => $date_range,
                'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            ],
            // 2: 유입 경로 TOP 5
            [
                'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
                'metrics'    => [['name' => 'sessions']],
                'dateRanges' => $date_range,
                'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit'      => 5,
            ],
            // 3: 신규 vs 재방문
            [
                'dimensions' => [['name' => 'newVsReturning']],
                'metrics'    => [['name' => 'sessions']],
                'dateRanges' => $date_range,
            ],
            // 4: 총 지표 요약 (차원 없음)
            [
                'metrics' => [
                    ['name' => 'totalUsers'],
                    ['name' => 'sessions'],
                    ['name' => 'screenPageViews'],
                    ['name' => 'averageSessionDuration'],
                    ['name' => 'bounceRate'],
                ],
                'dateRanges' => $date_range,
            ],
        ]);

        // 배치 B: 일별 추이/소스매체 (2개)
        $rB = $client->batchRunReports([
            // 0: 일별 방문자 추이
            [
                'dimensions' => [['name' => 'date']],
                'metrics'    => [['name' => 'sessions'], ['name' => 'totalUsers']],
                'dateRanges' => $date_range,
                'orderBys'   => [['dimension' => ['dimensionName' => 'date'], 'desc' => false]],
                'limit'      => 90,
            ],
            // 1: 소스/매체 상세 TOP 10
            [
                'dimensions' => [['name' => 'sessionSourceMedium']],
                'metrics'    => [['name' => 'sessions']],
                'dateRanges' => $date_range,
                'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit'      => 10,
            ],
        ]);

        return [
            'active_users'       => $active_users,
            'active_users_error' => $active_users_error,
            'countries'          => $this->_parseCountries($rA[0] ?? null),
            'devices'            => $this->_parseDevices($rA[1] ?? null),
            'sources'            => $this->_parseSources($rA[2] ?? null),
            'user_types'         => $this->_parseUserTypes($rA[3] ?? null),
            'summary'            => $this->_parseSummary($rA[4] ?? null),
            'daily_trend'        => $this->_parseDailyTrend($rB[0] ?? null),
            'source_medium'      => $this->_parseSourceMedium($rB[1] ?? null),
        ];
    }

    // ----------------------------------------------------------------
    // 파싱 헬퍼
    // ----------------------------------------------------------------

    private function _parseCountries($report)
    {
        if (empty($report['rows'])) return [];
        $rows = [];
        foreach ($report['rows'] as $row) {
            $name    = $row['dimensionValues'][0]['value'] ?? '';
            $code    = $row['dimensionValues'][1]['value'] ?? '';
            $sessions = (int)($row['metricValues'][0]['value'] ?? 0);
            if (!$name) continue;
            $rows[] = [
                'flag'     => $this->_flagEmoji($code),
                'name'     => $name,
                'sessions' => $sessions,
            ];
        }
        return $rows;
    }

    private function _parseDevices($report)
    {
        $map = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0];
        if (!empty($report['rows'])) {
            foreach ($report['rows'] as $row) {
                $cat = strtolower($row['dimensionValues'][0]['value'] ?? '');
                $val = (int)($row['metricValues'][0]['value'] ?? 0);
                if (isset($map[$cat])) $map[$cat] = $val;
            }
        }
        $total = array_sum($map) ?: 1;
        $icons = ['desktop' => '💻', 'mobile' => '📱', 'tablet' => '📟'];
        $labels= ['desktop' => '데스크톱', 'mobile' => '모바일', 'tablet' => '태블릿'];
        $result = [];
        foreach ($map as $key => $val) {
            $result[] = [
                'icon'    => $icons[$key],
                'label'   => $labels[$key],
                'count'   => $val,
                'percent' => round($val / $total * 100),
            ];
        }
        return $result;
    }

    private function _parseSources($report)
    {
        $label_map = [
            'Direct'              => '직접 접속',
            'Organic Search'      => '검색 (자연)',
            'Paid Search'         => '검색 (광고)',
            'Organic Social'      => '소셜 (자연)',
            'Paid Social'         => '소셜 (광고)',
            'Referral'            => '추천 링크',
            'Email'               => '이메일',
            'Affiliates'          => '제휴',
            'Display'             => '디스플레이',
            'Unassigned'          => '미분류',
        ];
        if (empty($report['rows'])) return [];
        $rows = [];
        $max  = 1;
        foreach ($report['rows'] as $row) {
            $src  = $row['dimensionValues'][0]['value'] ?? '';
            $val  = (int)($row['metricValues'][0]['value'] ?? 0);
            if ($val > $max) $max = $val;
            $rows[] = ['label' => $label_map[$src] ?? $src, 'count' => $val];
        }
        foreach ($rows as &$r) {
            $r['bar_pct'] = round($r['count'] / $max * 100);
        }
        unset($r);
        return $rows;
    }

    private function _parseUserTypes($report)
    {
        $new = 0; $ret = 0;
        if (!empty($report['rows'])) {
            foreach ($report['rows'] as $row) {
                $type = $row['dimensionValues'][0]['value'] ?? '';
                $val  = (int)($row['metricValues'][0]['value'] ?? 0);
                if ($type === 'new')       $new = $val;
                if ($type === 'returning') $ret = $val;
            }
        }
        $total = ($new + $ret) ?: 1;
        return [
            'new'      => $new,
            'ret'      => $ret,
            'new_pct'  => round($new / $total * 100),
            'ret_pct'  => round($ret / $total * 100),
        ];
    }

    private function _parseSummary($report)
    {
        if (empty($report['rows'])) return null;
        $mv = $report['rows'][0]['metricValues'] ?? [];
        $avg_sec = round((float)($mv[3]['value'] ?? 0));
        return [
            'users'         => (int)($mv[0]['value'] ?? 0),
            'sessions'      => (int)($mv[1]['value'] ?? 0),
            'pageviews'     => (int)($mv[2]['value'] ?? 0),
            'avg_sec'       => $avg_sec,
            'avg_time'      => ($avg_sec >= 60)
                                    ? floor($avg_sec / 60) . '분 ' . ($avg_sec % 60) . '초'
                                    : $avg_sec . '초',
            'bounce_rate'   => round((float)($mv[4]['value'] ?? 0) * 100, 1),
        ];
    }

    private function _parseDailyTrend($report)
    {
        if (empty($report['rows'])) return [];
        $rows = [];
        foreach ($report['rows'] as $row) {
            $d = $row['dimensionValues'][0]['value'] ?? '';
            $rows[] = [
                'date'     => (strlen($d) === 8)
                                ? substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2)
                                : $d,
                'sessions' => (int)($row['metricValues'][0]['value'] ?? 0),
                'users'    => (int)($row['metricValues'][1]['value'] ?? 0),
            ];
        }
        return $rows;
    }

    private function _parseSourceMedium($report)
    {
        if (empty($report['rows'])) return [];

        $label_map = [
            '(direct) / (none)'      => '직접 접속',
            '(not set) / (not set)'  => '미분류',
            'google / organic'        => 'Google 검색 (자연)',
            'google / cpc'            => 'Google 검색 (광고)',
            'google / referral'       => 'Google 유입',
            'naver / organic'         => 'Naver 검색 (자연)',
            'naver / referral'        => 'Naver 유입',
            'naver / cpc'             => 'Naver 검색 (광고)',
            'daum / organic'          => 'Daum 검색 (자연)',
            'daum / referral'         => 'Daum 유입',
            'bing / organic'          => 'Bing 검색 (자연)',
            'facebook.com / referral' => 'Facebook 유입',
            't.co / referral'         => 'Twitter(X) 유입',
            'instagram.com / referral'=> 'Instagram 유입',
            'youtube.com / referral'  => 'YouTube 유입',
            'kakao / referral'        => 'Kakao 유입',
        ];

        $rows  = [];
        $total = 0;
        foreach ($report['rows'] as $row) {
            $total += (int)($row['metricValues'][0]['value'] ?? 0);
        }
        $total = $total ?: 1;
        foreach ($report['rows'] as $row) {
            $sm  = $row['dimensionValues'][0]['value'] ?? '(none)';
            $val = (int)($row['metricValues'][0]['value'] ?? 0);
            $rows[] = [
                'source_medium' => $label_map[$sm] ?? $sm,
                'sessions'      => $val,
                'pct'           => round($val / $total * 100, 1),
                'bar_pct'       => round($val / $total * 100),
            ];
        }
        return $rows;
    }

    private function _flagEmoji($code)
    {
        $code = strtoupper(trim($code));
        if (strlen($code) !== 2 || !ctype_alpha($code)) return '🌐';
        $flag = '';
        foreach (str_split($code) as $c) {
            $flag .= mb_chr(ord($c) + 127397, 'UTF-8');
        }
        return $flag;
    }

    // ================================================================
    // GA4 실시간 (접속자 수 + 현재 페이지 목록)
    // ================================================================
    function getGA4Realtime()
    {
        $config = $this->_getConfig();
        if (empty($config->ga4_property_id) || empty($config->ga4_refresh_token)) {
            return ['active_users' => 0, 'pages' => [], 'error' => 'not_configured'];
        }

        $access_token = $this->_getValidAccessToken($config);
        if (!$access_token) {
            return ['active_users' => 0, 'pages' => [], 'error' => '액세스 토큰 없음 — 재연동 필요'];
        }

        require_once RX_BASEDIR . 'modules/counter_plus/lib/GA4Client.php';
        $client = new GA4Client($access_token, $config->ga4_property_id);
        return $client->runRealtimePageReport(15);
    }

    // ================================================================
    // Rhymix 서브탭 — 전체 요약
    // ================================================================
    function getRhymixSummary()
    {
        $oDB       = DB::getInstance();
        $today     = date('Ymd') . '000000';
        $week_ago  = date('Ymd', strtotime('-7 days'))  . '000000';
        $month_ago = date('Ymd', strtotime('-30 days')) . '000000';

        $counts = [];
        foreach (['today' => $today, 'week' => $week_ago, 'month' => $month_ago] as $key => $from) {
            try {
                $stmt = $oDB->query("SELECT COUNT(*) AS cnt FROM `document_readed_log` WHERE regdate >= ?", [$from]);
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_OBJ) : [];
                $counts[$key] = $rows ? (int)$rows[0]->cnt : 0;
            } catch (Exception $e) { $counts[$key] = 0; }
        }

        $hourly = $this->_rxHourlyStats($today);
        return [
            'counts'      => $counts,
            'top5'        => $this->getTopDocuments('week', 0, 5),
            'hourly'      => $hourly['data'],
            'peak_hour'   => $hourly['peak_hour'],
            'peak_count'  => $hourly['peak_count'],
            'boards'      => $this->_rxBoardRanking($week_ago, 5),
        ];
    }

    // ================================================================
    // Rhymix 서브탭 — 시간대별
    // ================================================================
    function getRhymixHourlyStats()
    {
        $from   = date('Ymd', strtotime('-7 days')) . '000000';
        $hourly = $this->_rxHourlyStats($from);
        return [
            'hourly'      => $hourly['data'],
            'peak_hour'   => $hourly['peak_hour'],
            'peak_count'  => $hourly['peak_count'],
            'weekly'      => $this->_rxWeeklyStats($from),
        ];
    }

    // ================================================================
    // Rhymix 서브탭 — 게시판별
    // ================================================================
    function getRhymixBoardStats()
    {
        $from    = date('Ymd', strtotime('-7 days')) . '000000';
        $ranking = $this->_rxBoardRanking($from, 20);

        $board_docs = [];
        foreach (array_slice($ranking, 0, 5) as $board) {
            $board_docs[] = [
                'board' => $board,
                'docs'  => $this->getTopDocuments('week', (int)$board['module_srl'], 5),
            ];
        }

        return ['ranking' => $ranking, 'board_docs' => $board_docs];
    }

    // ================================================================
    // Rhymix 서브탭 — 회원별
    // ================================================================
    function getRhymixMemberStats()
    {
        $oDB  = DB::getInstance();
        $from = date('Ymd', strtotime('-7 days')) . '000000';

        $ratio = ['guest' => 0, 'member' => 0, 'guest_pct' => 0, 'member_pct' => 0];
        try {
            $stmt = $oDB->query(
                "SELECT
                    SUM(CASE WHEN member_srl = 0 OR member_srl IS NULL THEN 1 ELSE 0 END) AS guest,
                    SUM(CASE WHEN member_srl > 0 THEN 1 ELSE 0 END) AS member_count
                 FROM `document_readed_log` WHERE regdate >= ?",
                [$from]
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_OBJ) : [];
            if ($rows) {
                $ratio['guest']  = (int)$rows[0]->guest;
                $ratio['member'] = (int)$rows[0]->member_count;
                $total = ($ratio['guest'] + $ratio['member']) ?: 1;
                $ratio['guest_pct']  = round($ratio['guest']  / $total * 100);
                $ratio['member_pct'] = round($ratio['member'] / $total * 100);
            }
        } catch (Exception $e) {}

        $top_members = [];
        try {
            $stmt = $oDB->query(
                "SELECT r.member_srl, m.nick_name, COUNT(*) AS read_count
                 FROM `document_readed_log` AS r
                 LEFT JOIN `member` AS m ON r.member_srl = m.member_srl
                 WHERE r.member_srl > 0 AND r.regdate >= ?
                 GROUP BY r.member_srl
                 ORDER BY read_count DESC
                 LIMIT 10",
                [$from]
            );
            $top_members = $stmt ? $stmt->fetchAll(PDO::FETCH_OBJ) : [];
        } catch (Exception $e) {}

        return ['ratio' => $ratio, 'top_members' => $top_members];
    }

    // ----------------------------------------------------------------
    // Rhymix 내부 헬퍼
    // ----------------------------------------------------------------
    private function _rxHourlyStats($from)
    {
        $oDB = DB::getInstance();
        try {
            $stmt = $oDB->query(
                "SELECT CAST(SUBSTRING(regdate, 9, 2) AS UNSIGNED) AS hour, COUNT(*) AS cnt
                 FROM `document_readed_log` WHERE regdate >= ?
                 GROUP BY hour ORDER BY hour",
                [$from]
            );
        } catch (Exception $e) { $stmt = null; }

        $map = array_fill(0, 24, 0);
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
                $h = (int)$row->hour;
                if ($h >= 0 && $h <= 23) $map[$h] = (int)$row->cnt;
            }
        }
        $max_count = max($map) ?: 1;
        $peak_hour  = (int)array_search($max_count, $map);
        $peak_count = $max_count === 1 && array_sum($map) === 0 ? 0 : $max_count;
        $result = [];
        for ($h = 0; $h < 24; $h++) {
            $result[] = ['hour' => $h, 'count' => $map[$h], 'pct' => round($map[$h] / $max_count * 100)];
        }
        return ['data' => $result, 'peak_hour' => $peak_hour, 'peak_count' => $peak_count];
    }

    private function _rxWeeklyStats($from)
    {
        $oDB    = DB::getInstance();
        $labels = ['일', '월', '화', '수', '목', '금', '토'];
        try {
            $stmt = $oDB->query(
                "SELECT DAYOFWEEK(STR_TO_DATE(SUBSTRING(regdate,1,8),'%Y%m%d')) AS dow, COUNT(*) AS cnt
                 FROM `document_readed_log` WHERE regdate >= ?
                 GROUP BY dow ORDER BY dow",
                [$from]
            );
        } catch (Exception $e) { $stmt = null; }

        $map = array_fill(1, 7, 0);
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
                $d = (int)$row->dow;
                if ($d >= 1 && $d <= 7) $map[$d] = (int)$row->cnt;
            }
        }
        $max = max($map) ?: 1;
        $result = [];
        for ($d = 1; $d <= 7; $d++) {
            $result[] = ['label' => $labels[$d - 1], 'count' => $map[$d], 'pct' => round($map[$d] / $max * 100)];
        }
        return $result;
    }

    private function _rxBoardRanking($from, $limit)
    {
        $oDB = DB::getInstance();
        try {
            $stmt = $oDB->query(
                "SELECT d.module_srl, m.browser_title AS board_name, m.mid, COUNT(*) AS views
                 FROM `document_readed_log` AS r
                 JOIN `documents` AS d ON r.document_srl = d.document_srl
                 JOIN `modules`   AS m ON d.module_srl   = m.module_srl
                 WHERE r.regdate >= ? AND d.status = 'PUBLIC'
                 GROUP BY d.module_srl ORDER BY views DESC
                 LIMIT " . (int)$limit,
                [$from]
            );
        } catch (Exception $e) { $stmt = null; }

        if (!$stmt) return [];
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $max  = $rows ? (int)$rows[0]['views'] : 1;
        foreach ($rows as &$r) {
            $r['pct'] = round((int)$r['views'] / $max * 100);
        }
        unset($r);
        return $rows;
    }

    function getGA4Config()
    {
        return $this->_getConfig();
    }

    // ================================================================
    // 내부 헬퍼
    // ================================================================

    /**
     * 유효한 access_token 반환 (만료 시 refresh_token으로 재발급)
     */
    function _getValidAccessToken($config)
    {
        // 아직 유효하면 그대로 사용
        $expires_at = $config->ga4_token_expires_at ?? 0;
        if (!empty($config->ga4_access_token) && $expires_at > time()) {
            return $config->ga4_access_token;
        }

        // refresh_token으로 재발급
        if (empty($config->ga4_refresh_token) || empty($config->ga4_client_id) || empty($config->ga4_client_secret)) {
            return null;
        }

        require_once RX_BASEDIR . 'modules/counter_plus/lib/GA4Client.php';
        $tokens = GA4Client::refreshAccessToken(
            $config->ga4_refresh_token,
            $config->ga4_client_id,
            $config->ga4_client_secret
        );

        if (!$tokens) return null;

        // 새 토큰 저장
        $config->ga4_access_token     = $tokens['access_token'];
        $config->ga4_token_expires_at = time() + (int)($tokens['expires_in'] ?? 3600) - 60;
        getController('module')->insertModuleConfig('counter_plus', $config);

        return $config->ga4_access_token;
    }

    /**
     * GA4 pagePath에서 document_srl 추출
     *
     * 지원 형식:
     *   ?document_srl=123          → 쿼리스트링
     *   /xe/board/123              → /베이스/mid/srl 경로 방식
     *   /xe/board/123?foo=bar      → 경로 방식 + 추가 쿼리
     *
     * @return int|null
     */
    private function _extractDocumentSrl($path)
    {
        // 1순위: 쿼리스트링 ?document_srl=N
        $query_str = parse_url($path, PHP_URL_QUERY) ?? '';
        parse_str($query_str, $qs);
        if (!empty($qs['document_srl'])) {
            return (int)$qs['document_srl'];
        }

        // 2순위: 경로 마지막 세그먼트가 순수 숫자 → /xe/mid/12345
        $path_only = parse_url($path, PHP_URL_PATH) ?? $path;
        $parts     = array_values(array_filter(explode('/', $path_only), 'strlen'));

        // 베이스 경로(/xe 등) 제거
        $base = trim(parse_url(RX_BASEURL, PHP_URL_PATH) ?? '', '/');
        if ($base !== '' && isset($parts[0]) && $parts[0] === $base) {
            array_shift($parts);
        }

        // 구조: [mid, document_srl] — 최소 2개 세그먼트 필요
        if (count($parts) >= 2) {
            $last = $parts[count($parts) - 1];
            // 순수 숫자이고 Rhymix srl 최소 길이(4자리) 이상
            if (ctype_digit($last) && (int)$last >= 1000) {
                return (int)$last;
            }
        }

        return null;
    }

    /**
     * 미매칭 경로 목록에서 mid를 추출해 xe_modules와 배치 조회
     * @return array  mid => module (모듈명 문자열)
     */
    private function _buildMidModuleMap(array $unmatched)
    {
        $mids = [];
        foreach ($unmatched as $item) {
            $mid = $this->_extractMid($item['path']);
            if ($mid) $mids[] = $mid;
        }

        if (empty($mids)) return [];

        $unique       = array_unique($mids);
        $placeholders = implode(',', array_fill(0, count($unique), '?'));
        $sql          = "SELECT mid, module FROM `modules` WHERE mid IN ({$placeholders})";

        try {
            $stmt = DB::getInstance()->query($sql, $unique);
        } catch (Exception $e) {
            return [];
        }

        $map = [];
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $mod) {
                $map[$mod->mid] = $mod->module;
            }
        }
        return $map;
    }

    /**
     * pagePath에서 베이스 경로 바로 다음 세그먼트(mid)를 추출
     */
    private function _extractMid($path)
    {
        $path_only = parse_url($path, PHP_URL_PATH) ?? $path;
        $parts     = array_values(array_filter(explode('/', $path_only), 'strlen'));
        $base      = trim(parse_url(RX_BASEURL, PHP_URL_PATH) ?? '', '/');
        if ($base !== '' && isset($parts[0]) && $parts[0] === $base) {
            array_shift($parts);
        }
        return $parts[0] ?? null;
    }

    /**
     * 미매칭 경로를 분류해 표시용 title / board_name 반환
     *
     * 규칙:
     *   베이스 경로 (/, /xe/)        → 메인페이지 / 페이지
     *   mid가 board 모듈             → 경로 / 게시판 목록
     *   mid가 그 외 모듈             → 경로 / 모듈
     *   mid 없음 또는 미등록         → 경로 / 페이지
     */
    private function _classifyPath($path, array $mid_map)
    {
        $path_only = parse_url($path, PHP_URL_PATH) ?? $path;
        $parts     = array_values(array_filter(explode('/', $path_only), 'strlen'));
        $base      = trim(parse_url(RX_BASEURL, PHP_URL_PATH) ?? '', '/');
        if ($base !== '' && isset($parts[0]) && $parts[0] === $base) {
            array_shift($parts);
        }

        // 베이스 경로 (루트)
        if (empty($parts)) {
            return ['title' => '메인페이지', 'board_name' => '페이지'];
        }

        $mid         = $parts[0];
        $module_type = $mid_map[$mid] ?? null;

        if ($module_type === 'board') {
            return ['title' => $path, 'board_name' => '게시판 목록'];
        }
        if ($module_type !== null) {
            return ['title' => $path, 'board_name' => '모듈'];
        }

        // xe_modules에 없는 경로 (정적 페이지, 외부 경로 등)
        return ['title' => $path, 'board_name' => '페이지'];
    }

    private function _getConfig()
    {
        return \ModuleModel::getInstance()->getModuleConfig('counter_plus') ?: new stdClass;
    }

    private function _getDateFrom($period)
    {
        switch ($period) {
            case 'today': return date('Ymd') . '000000';
            case 'week':  return date('Ymd', strtotime('-7 days')) . '000000';
            case 'month': return date('Ymd', strtotime('-30 days')) . '000000';
            default:      return null;
        }
    }
}
