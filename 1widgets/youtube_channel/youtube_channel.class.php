<?php
/**
 * @class youtube_channel
 * @author 개발자이름 (your@email.com)
 * @brief 유튜브 채널 위젯
 */
class youtube_channel extends WidgetHandler
{
    /**
     * @brief 위젯 실행
     */
    function proc($args)
    {
        // 1. 설정값 가져오기
        $api_key = $args->api_key;
        $channel_id = $args->channel_id;
        $max_results = $args->max_results ? (int)$args->max_results : 5;
        $skin = $args->skin ? $args->skin : 'default';

        if (!$api_key || !$channel_id) {
            return '<p>유튜브 위젯 설정에서 API Key와 채널 ID를 입력해주세요.</p>';
        }

        // 2. 캐시 설정 (API 할당량 초과 방지를 위해 1시간 캐시 적용)
        $cache_key = 'youtube_widget_' . md5($channel_id . $max_results);
        $cache_time = 3600; // 1시간 (초 단위)
        $oCacheHandler = CacheHandler::getInstance('object');
        
        $items = false;
        if ($oCacheHandler->isSupport()) {
            $items = $oCacheHandler->get($cache_key);
        }

        // 3. 캐시가 없으면 API 호출
        if ($items === false) {
            $url = sprintf(
                '[https://www.googleapis.com/youtube/v3/search?key=%s&channelId=%s&part=snippet,id&order=date&maxResults=%d&type=video](https://www.googleapis.com/youtube/v3/search?key=%s&channelId=%s&part=snippet,id&order=date&maxResults=%d&type=video)',
                $api_key,
                $channel_id,
                $max_results
            );

            // 라이믹스 내장 함수를 이용해 외부 API 호출
            $response = FileHandler::getRemoteResource($url);
            $data = json_decode($response, true);

            $items = array();
            if (isset($data['items'])) {
                $items = $data['items'];
                // 캐시에 저장
                if ($oCacheHandler->isSupport()) {
                    $oCacheHandler->put($cache_key, $items, $cache_time);
                }
            }
        }

        // 4. 템플릿에 데이터 전달
        Context::set('youtube_items', $items);

        // 5. 스킨 파일 경로 설정 및 렌더링
        $tpl_path = sprintf('%sskins/%s', $this->widget_path, $skin);
        $tpl_file = 'list';

        $oTemplate = TemplateHandler::getInstance();
        return $oTemplate->compile($tpl_path, $tpl_file);
    }
}

