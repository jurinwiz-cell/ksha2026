<?php
class youtube2Model extends youtube2 {
	public function init() {
	}
	
	/**
	 * 위젯 및 외부에서 채널의 최신 비디오들을 가져옵니다.
	 * $channel_id: 가져올 채널 ID (예: UC...)
	 * $max_count: 가져올 비디오 개수
	 */
	public function getChannelVideos($channel_id = null, $max_count = 5) {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('youtube2');
		
		if(!$channel_id) {
			$channel_id = $config->default_channel_id;
		}
		
		if(!$channel_id || !$config->api_key) {
			return array();
		}

		$cache_key = 'youtube2_channel_videos_' . $channel_id . '_' . $max_count;
		
		if(class_exists('\Rhymix\Framework\Cache')) {
			$cached_data = \Rhymix\Framework\Cache::get($cache_key);
			if($cached_data !== false && $cached_data !== null) {
				return $cached_data;
			}
		}

		// 1. Channel API를 호출하여 uploads 재생목록 ID 찾기
		$channel_url = 'https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=' . urlencode($channel_id) . '&key=' . $config->api_key;
		
		$body = FileHandler::getRemoteResource($channel_url, null, 5, 'GET');
		if(!$body) return array();
		
		$channel_data = json_decode($body);
		if(!$channel_data || empty($channel_data->items)) return array();
		
		$uploads_playlist_id = $channel_data->items[0]->contentDetails->relatedPlaylists->uploads ?? null;
		if(!$uploads_playlist_id) return array();

		// 2. PlaylistsItems API 호출하여 비디오 가져오기
		return $this->getPlaylistVideos($uploads_playlist_id, $max_count, $config);
	}
	
	/**
	 * 재생목록의 최신 비디오들을 가져옵니다.
	 */
	public function getPlaylistVideos($playlist_id, $max_count = 5, $config = null) {
		if($config === null) {
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('youtube2');
		}

		if(!$playlist_id || !$config->api_key) return array();

		$cache_key = 'youtube2_playlist_videos_' . $playlist_id . '_' . $max_count;
		
		if(class_exists('\Rhymix\Framework\Cache')) {
			$cached_data = \Rhymix\Framework\Cache::get($cache_key);
			if($cached_data !== false && $cached_data !== null) {
				return $cached_data;
			}
		}

		$url = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=' . urlencode($playlist_id) . '&maxResults=' . intval($max_count) . '&key=' . $config->api_key;
		
		$body = FileHandler::getRemoteResource($url, null, 5, 'GET');
		if(!$body) return array();
		
		$data = json_decode($body);
		if(!$data || empty($data->items)) return array();

		$videos = array();
		foreach($data->items as $item) {
			$video = new stdClass();
			$video->video_id = $item->snippet->resourceId->videoId;
			$video->title = $item->snippet->title;
			$video->description = $item->snippet->description;
			$video->thumbnail = $item->snippet->thumbnails->high->url ?? ($item->snippet->thumbnails->medium->url ?? $item->snippet->thumbnails->default->url);
			$video->published_at = $item->snippet->publishedAt;
			$video->channel_title = $item->snippet->channelTitle;
			$videos[] = $video;
		}

		// Rhymix 캐시에 저장
		if(class_exists('\Rhymix\Framework\Cache')) {
			$ttl = (int)($config->cache_ttl ?? 3600);
			if($ttl <= 0) $ttl = 3600;
			\Rhymix\Framework\Cache::set($cache_key, $videos, $ttl);
		}
		
		return $videos;
	}
}
