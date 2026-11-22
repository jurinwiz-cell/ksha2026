<?php

/**
 * elkhatalk3
 *
 * Copyright (c) 엘카
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Elkhatalk3 extends ModuleObject
{
	var $cache_path = 'files/cache/elkhatalk3/';
	static $plugins = [];
	static $plugins_skip = [];
	static $plugin_skip = FALSE;
	protected static $_plugin_vars = [];
	public function init()
	{
		$logged_info = Context::get('logged_info');
		$member_srl = (INT)Context::get('member_srl');
		if(!$member_srl || !is_object($logged_info) || !$logged_info->member_srl || $member_srl!=$logged_info->member_srl)
		{
			return $this->stop('msg_not_logged');
		}
	}
	static function plugin_skip($is_admin):void
	{
		static::$plugin_skip = $is_admin;
	}
	public static function addPlugin($name, $type, $func):void
	{
		if(static::$plugin_skip)
		{
			if(!isset(static::$plugins_skip[$type]))
			{
				static::$plugins_skip[$type] = [];
			}
			static::$plugins_skip[$type][$name] = $func;
		}
		else
		{
			if(!isset(static::$plugins[$type]))
			{
				static::$plugins[$type] = [];
			}
			static::$plugins[$type][$name] = $func;
		}
	}
	public function getPlugin($type) : array
	{
		$config = $this->getConfig();
		if(isset($config->plugins) && is_array($config->plugins) && isset($config->plugins[$type]))
		{
			foreach($config->plugins[$type] as $plugin)
			{
				if(isset(static::$plugins_skip[$type]) && isset(static::$plugins_skip[$type][$plugin]))
				{
					if(!isset(static::$plugins[$type]))
					{
						static::$plugins[$type] = [];
					}
					static::$plugins[$type][$plugin] = static::$plugins_skip[$type][$plugin];
				}
				if(file_exists($filename = __DIR__."/plugins/$plugin/$plugin.php"))
				{
					require_once($filename);
				}
			}
		}
		return isset(static::$plugins[$type])? static::$plugins[$type] : [];
	}

	public static function setPluginVars(string $plugin, array $plugin_vars) : void
	{
		static::$_plugin_vars[$plugin] = $plugin_vars;
	}
	public static function getPluginVars(string $plugin) : array
	{
		$config = getClass('elkhatalk3')->getConfig();
		if(!isset($config->plugin_vars[$plugin]) && isset(static::$_plugin_vars[$plugin]))
		{
			$config->plugin_vars[$plugin] = [];
		}
		if(isset(static::$_plugin_vars[$plugin]))
		{
			foreach(static::$_plugin_vars[$plugin] as $key => $val)
			{
				if(!isset($config->plugin_vars[$plugin][$key]) || gettype($config->plugin_vars[$plugin][$key]) != gettype($val['vars']))
				{
					$config->plugin_vars[$plugin][$key] = $val['vars'];
				}
			}
		}
		return $config->plugin_vars[$plugin] ?? [];
	}

	public function web_path():string
	{
		return parse_url(getFullUrl(''), PHP_URL_PATH);
	}
	public function load_js():void
	{
		static $load = FALSE;
		if($load)
		{
			return;
		}
		$load = TRUE;

		$config = $this->getConfig();
		if(file_exists($filename = __DIR__.'/debug/'.__FUNCTION__.'.php'))
		{
			require($filename);
		}
		else
		{
			$filmetime = filemtime(__DIR__.'/tpl/common.min.js');
			Context::addHtmlHeader(sprintf('<script src="%smodules/elkhatalk3/tpl/common.min.js?c=%d" async></script>', $config->cdn_path, $filmetime));
		}
	}
	public function config_web():stdClass
	{
		$config = $this->getConfig();
		$logged_info = Context::get('logged_info');
		$site_module_info = Context::get('site_module_info');
		$mid = Context::get('mid');

		if(defined('RX_VERSION'))
		{
			$request_uri = Context::get('request_uri');
		}
		else
		{
			if(isset($_SERVER['HTTP_HOST']))
			{
				$request_uri = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO']=='https') || $_SERVER['HTTPS'] == 'on'? 'https//':'http://';
				$request_uri .= $_SERVER['HTTP_HOST'];
				$request_uri .= $this->web_path();
			}
			else
			{
				$request_uri = Context::getFullUrl('');
			}
		}

		$config_web = new stdClass();
		$config_web->host = $config->host;
		$config_web->http_port = (INT)$config->http_port;
		$config_web->https_port = (INT)$config->https_port;
		$config_web->cdn_path = $config->cdn_path;
		$config_web->skin = $config->skin;
		$config_web->secret_key_send = $config->secret_key_send;
		$config_web->notify_mp3 = $config->notify_mp3;
		$config_web->popup_size = $config->popup_size;
		$config_web->dark_mode = $config->dark_mode;

		$config_web->is_mobile = !!Mobile::isFromMobilePhone();
		$config_web->auth_info = $this->auth_info();
		//$config_web->public_chat_log = $config->public_chat_log?? 'N';
		$config_web->public_chat_list_count = $config->public_chat_list_count;
		$config_web->request_uri = $request_uri;//Context::get('request_uri');
		$config_web->ping_timeout = round( 900 * (INT)ini_get('session.gc_maxlifetime') ); // 세션 만기가 90% 가까워지면 reload
		if($site_module_info->mid==$mid)
		{
			$config_web->my_info_url = $request_uri . '?' . http_build_query(array('act'=>'dispMemberInfo'));
		}
		else
		{
			//$config_web->mid = $mid;
			$config_web->my_info_url = $request_uri . '?' . http_build_query(array('act'=>'dispMemberInfo','mid'=>$mid));
		}

		// 언어팩.
		$lang_type = Context::get('lang_type');
		if(!file_exists($filename = __DIR__."/lang/$lang_type.lang.php"))
		{
			$lang_type = 'ko';
		}
		$source_filename = __DIR__."/lang/$lang_type.lang.php";
		$source_filemtime = filemtime($source_filename);
		$target_filename = "files/cache/elkhatalk3/$lang_type.json";
		$target_filemtime = 0;
		// 언어 캐시 파일이 없거나 최신이면.
		if(!FileHandler::exists($target_filename) || $source_filemtime > ($target_filemtime = filemtime(_XE_PATH_.$target_filename)))
		{
			$lang = new stdClass();
			require($source_filename);
			FileHandler::writeFile($target_filename, json_encode($lang));
			$target_filemtime = filemtime(_XE_PATH_.$target_filename);
		}
		$config_web->lang_type = $lang_type;
		$config_web->lang_cache = $target_filemtime;

		// 신규 쪽지.
		$config_web->room_status = 0;
		if(is_object($logged_info) && $logged_info->member_srl)
		{
			$args = new stdClass();
			$args->member_srl = $logged_info->member_srl;
			$args->list_count = 20; // 새 메세지 너무 많은 경우를 방지.
			$config_web->new_messages = executeQueryArray('elkhatalk3.getNewMessageCount', $args)->data;

			foreach($config_web->new_messages as $val)
			{
				$val->level_icon = $this->level_icon($val->target_srl);
				$val->time = strtotime($val->last_update);
				// 기존 기능 호환.
				$config_web->room_status += (INT)$val->count;
			}
		}
		else
		{
			$config_web->new_messages = [];
		}
		return $config_web;
	}
	public function triggerBeforeModuleInit($oModule)
	{
		if(isset($oModule->module) && $oModule->module=='admin')
		{
			return $this->createObject();
		}
		/*if(!preg_match('/^(GET|POST|HTML)$/i', Context::getRequestMethod()))
		{
			return $this->createObject();
		}*/
		$logged_info = Context::get('logged_info');
		$config = $this->getConfig();

		$plugins = $this->getPlugin('init_before');
		foreach($plugins as $key => $val)
		{
			$output = $plugins[$key]($oModule);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}

		if(Context::getResponseMethod() === 'HTML')
		{
			$config_web = $this->config_web();
			Context::set('elkhatalk_count', $config_web->room_status);
			Context::addHtmlHeader("<script>//<![CDATA[\nwindow.elkhatalk_config=".json_encode($config_web).";\n//]]></script>");
		}

		$this->load_js();

		if($oModule->act=='getMemberMenu' && is_object($logged_info) && $logged_info->member_srl)
		{
			$member_srl = (INT)Context::get('target_srl');

			$oMemberController = getController('member');
			// 내 대화함
			if(is_object($logged_info) && $logged_info->member_srl == $member_srl)
			{
				// <span>아이콘</span>
				$oMemberController->addMemberPopupMenu(
					'#elkhatalk_'.$member_srl,
					//'<span class="elkhatalk elkhatalk_'.$member_srl.'" style="display:block">대화함</span>',
					Context::getLang('elkhatalk3_my_message'),
					'elkhatalk',
					'_self'
				);
			}

			// 대화하기
			else
			{
				$oMemberController->addMemberPopupMenu(
					'#elkhatalk_'.$member_srl,
					//'<span class="elkhatalk elkhatalk_'.$member_srl.'" style="display:block">대화하기</span>',
					Context::getLang('elkhatalk3_talk'),
					'elkhatalk',
					'_self'
				);
			}
		}

		$plugins = $this->getPlugin('init_after');
		foreach($plugins as $key => $val)
		{
			$output = $plugins[$key]($oModule);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}
	}
	public function triggerAfterModuleProc($oModule)
	{
		$plugins = $this->getPlugin('proc');
		foreach($plugins as $key => $val)
		{
			$plugins[$key]($oModule);
		}
	}
	public function level_icon($member_srl)
	{
		static $cache_list = [];
		if(!isset($cache_list[$member_srl]))
		{
			$cache_list[$member_srl] = [];
			$plugins = $this->getPlugin(__FUNCTION__);
			foreach($plugins as $key => $val)
			{
				$plugins[$key]($member_srl, $cache_list[$member_srl]);
			}
		}
		return implode('#', $cache_list[$member_srl]);
	}
	public function auth_info()
	{
		static $auth_info;
		if(isset($auth_info) && is_object($auth_info))
		{
			return $auth_info;
		}
		$auth_info = new stdClass();
		$logged_info = Context::get('logged_info');
		$is_admin = [];
		$config = $this->getConfig();
		if(is_object($logged_info) && $logged_info->member_srl && $config->secret_key_send == 'Y')
		{
			$member_srl = $logged_info->member_srl;
			$nick_name = $logged_info->nick_name;

			$grants = $this->get_public_grants();
			$group_list = &$logged_info->group_list;
		}
		else
		{
			$valid = FALSE;
			if(isset($_COOKIE['elkhatalk3_rand2']) && preg_match('/^[0-9]+$/', $_COOKIE['elkhatalk3_rand2']) && isset($_COOKIE['elkhatalk3_hash2']))
			{
				$valid = md5($_SERVER['REMOTE_ADDR'] . $_COOKIE['elkhatalk3_rand2'] . $config->secret_key . $config->your_host) == $_COOKIE['elkhatalk3_hash2'];
			}
			if($valid)
			{
				$rand = $_COOKIE['elkhatalk3_rand2'];
				$hash = $_COOKIE['elkhatalk3_hash2'];
			}
			else
			{
				$rand = (STRING)mt_rand(0,999999999);
				$hash = md5($_SERVER['REMOTE_ADDR'] . $rand . $config->secret_key . $config->your_host);
				setCookie('elkhatalk3_rand2', $rand, time() + 86400, '/');
				setCookie('elkhatalk3_hash2', $hash, time() + 86400, '/');
			}
			$member_srl = -1 * (INT)$rand;
			$nick_name = '손님_' . substr($hash, 0, 6);
			$grants = $this->get_public_grants();
			$group_list = [];
		}
		if(!in_array('Y', $is_admin))
		{
			// $is_admin 배열 값에 숫자만 있다면 해당 방에 권한이 없는 것으로 한다.
			$_member_srl = $member_srl < 0? 0 : $member_srl;
			$rooms = $this->public_grants_rooms($grants, array('M','C','B','G'), $_member_srl, $group_list); // M{관리권한} C{승인} B{밴} G{그룹}
			foreach($rooms as $grant => $val)
			{
				foreach($val as $room)
				{
					if($grant=='B')
					{
						$is_admin[] = $room;
					}
					else if($grant=='G' && !in_array("C$room", $is_admin))
					{
						$is_admin[] = "C$room";
					}
					else
					{
						$is_admin[] = $grant . $room;
					}
				}
			}
			// 승인된 회원인지 체크.
			foreach($grants->_C as $room => $val)
			{
				if($val && !in_array("C$room", $is_admin) && !in_array("M$room", $is_admin))
				{
					$is_admin[] = $room;
				}
			}
			// 승인된 그룹인지 체크.
			foreach($grants->_G as $room => $val)
			{
				if($val && !in_array("C$room", $is_admin) && !in_array("M$room", $is_admin))
				{
					$is_admin[] = $room;
				}
			}
			foreach($grants->_R as $room => $val)
			{
				if($val && $member_srl <= 0)
				{
					$is_admin[] = $room;
				}
			}
		}
		if(is_object($logged_info) && is_string($logged_info->is_admin) && $logged_info->is_admin=='Y')
		{
			$is_admin[] = 'Y';
		}

		$auth_info->member_srl = $member_srl;
		$auth_info->nick_name = $nick_name;
		$auth_info->time = $time = isset($config->server_time_auth) && $config->server_time_auth=='N'? 999999999999 : time();
		$auth_info->host = $config->your_host;
		$auth_info->is_admin = implode(',', $is_admin);
		$auth_info->mtime = (INT)Context::get('mtime');
		$auth_info->channel_srl = 0;
		$auth_info->channel_mid = '';

		$plugins = $this->getPlugin('auth_info');
		foreach($plugins as $key => $val)
		{
			$plugins[$key]($auth_info);
		}
		$auth_info->level = $this->level_icon($member_srl);

		$auth_info->hash = $config->secret_idx . '_' . md5("$config->secret_key.$auth_info->host.$auth_info->member_srl.$auth_info->nick_name.$auth_info->level.$auth_info->time.$auth_info->is_admin.$auth_info->mtime.$auth_info->channel_srl.$auth_info->channel_mid");

		return $auth_info;
	}
	public function public_grants_rooms($grants, $type, $member_srl, &$group_list)
	{
		$rooms = new stdClass();
		foreach($type as $grant)
		{
			$rooms->{$grant} = [];
		}
		foreach($grants->pk as $target_srl => $val)
		{
			foreach($val as $key => $v)
			{
				foreach($type as $grant)
				{
					// $v[0] = $pk, $v[1] = $room
					if($target_srl > 0)
					{
						if($target_srl==$member_srl && isset($grants->{$grant}[$v[0]]) && $grants->{$grant}[$v[0]] == $member_srl)
						{
							$rooms->{$grant}[] = $v[1];
						}
						else if(isset($group_list[$target_srl]) && isset($grants->{$grant}[$v[0]]) && isset($group_list[$grants->{$grant}[$v[0]]]))
						{
							$rooms->{$grant}[] = $v[1];
						}
					}
					else if(!$target_srl)
					{
						if(isset($grants->{$grant}[$v[0]]) && !$grants->{$grant}[$v[0]])
						{
							$rooms->{"_$grant"}[] = $v[1];
						}
					}
				}
			}
		}
		return $rooms;
	}

	public function langMessage($content) : string
	{
		if(preg_match_all('/\{([a-z_]+[0-9a-z_@]*)\}/iu', $content, $matches))
		{
			foreach($matches[1] as $k => $v)
			{
				if(Context::getLang("elkhatalk3_$v") != $v)
				{
					$content = str_replace($matches[0][$k], '<span class="elkhatalk3_'.$v.'">[' . Context::getLang("elkhatalk3_$v") . ']</span>', $content);
				}
				if(Context::getLang($v) != $v)
				{
					$content = str_replace($matches[0][$k], '<span class="'.$v.'">[' . Context::getLang($v) . ']</span>', $content);
				}
			}
		}
		return $content;
	}
	public function readMessage($me_member_srl, $target_srls, $room_no, $no_max = 0)
	{
		$args = new stdClass();
		$args->room_no = $room_no;
		$args->status = 0;
		$args->member_srl = $me_member_srl;
		$output = executeQuery('elkhatalk3.updateMembers', $args);

		$args = new stdClass();
		$args->room_no = $room_no;
		$args->target_srls = $target_srls;
		if($no_max > 0)
		{
			$args->no_max = $no_max;
		}
		// no_max 이하 가져오기
		$data = array_reverse(executeQueryArray('elkhatalk3.getReadMessage', $args, array('no','read','content','regdate'))->data);

		$no_list = [];
		foreach($data as $val)
		{
			$val->time = strtotime('20' . $val->regdate . '00');
			$no_list[] = (INT)$val->no;
		}

		if(count($no_list))
		{
			$args = new stdClass();
			$args->no_max = max($no_list);
			$args->room_no = $room_no;
			$args->target_srls = $target_srls;
			// no_max 이하 읽음 처리.
			$output = executeQuery('elkhatalk3.updateReadMessage', $args);

			if($output->toBool())
			{
				$args->me_member_srl = $me_member_srl; // 트리거 용.

				$output = ModuleHandler::triggerCall('elkhatalk3.readMessage', 'after', $args);

				$plugins = $this->getPlugin('read@after');
				foreach($plugins as $key => $val)
				{
					$plugins[$key]($args);
				}
			}
		}
		return $data;
	}
	public function sendSocket($func, $params)
	{
		$config = $this->getConfig();

		$params['phptime'] = $time = time();
		$params['secret_key_hash'] = md5($config->secret_idx . '_' . $config->secret_key . $time);
		$params['your_host'] = $config->your_host;
		if($config->secret_idx)
		{
			$params['secret_idx'] = $config->secret_idx;
		}

		$host = $config->ip ?? $config->host;
		$url = "http://$host:$config->http_port/$func";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		if(isset($config->ip))
		{
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Host: $config->host"));
		}
		$res = curl_exec($ch);

		// 2020-05-05
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		//$header = substr($res, 0, $header_size);
		$res = substr($res, $header_size);

		$error = curl_error($ch);
		curl_close($ch);

		if(strpos($res, 'OK') !== 0)
		{
			return $this->createObject(-1, strlen($res)? $res : $error);
		}
		$output = $this->createObject();
		$output->setMessage($res);
		return $output;
	}
	public function getRoom($member_srl, $member_srl2, $create = FALSE, $columns = array('room_no', 'status', 'blind', 'hide'))
	{
		$cache_key = "$member_srl,$member_srl2";
		$cache_val = $this->getCache($cache_key);
		if(!is_object($cache_val) || !isset($cache_val->room_no))
		{
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$args->member_srl2 = $member_srl2;
			$args->list_count = 1;
			$cache_val = executeQuery('elkhatalk3.getRoom', $args, $columns)->data;

			if(is_object($cache_val) && isset($cache_val->room_no))
			{
				$this->setCache($cache_key, $cache_val);
			}
		}

		if(!$create && !(is_object($cache_val) && isset($cache_val->room_no)))
		{
			$cache_val = new stdClass();
			$cache_val->room_no = -1;
			$cache_val->status = 0;
			$cache_val->blind = NULL;
			return $cache_val;
		}

		if(is_object($cache_val) && isset($cache_val->room_no))
		{
			return $cache_val;
		}

		// 아래서 부터는 방 생성하는 코드임.

		// 어설픈 락 구현하기.
		$member_srls = array($member_srl, $member_srl2);
		sort($member_srls);
		$filename = implode('-',$member_srls).'.lock';
		FileHandler::makeDir($this->cache_path);
		$i = 0;
		while(FileHandler::exists($this->cache_path.$filename))
		{
			$i++;
			sleep(1);
			if($i > 10) // 실패
			{
				break;
			}
		}

		$room_info = executeQuery('elkhatalk3.getRoom', $args, $columns)->data;
		if(is_object($room_info) && isset($room_info->room_no))
		{
			$this->setCache($cache_key, $room_info);
			return $room_info;
		}

		FileHandler::writeFile($this->cache_path.$filename, time());
		$args = new stdClass();
		$args->regdate = (INT)date('ymdHi');
		$output = executeQuery('elkhatalk3.insertRoom', $args);
		$insert_id = -1;
		if($output->toBool())
		{
			$oDB = DB::getInstance();
			if(method_exists($oDB,'db_insert_id'))
			{
				$insert_id = $oDB->db_insert_id();
			}
			else
			{
				$insert_id = $oDB->getInsertID();
			}

			$args->room_no = $insert_id;

			$args->member_srl = $member_srl;
			$args->member_srl2 = $member_srl2;
			$output = executeQuery('elkhatalk3.insertMembers', $args);

			if($member_srl!=$member_srl2)
			{
				$args->member_srl = $member_srl2;
				$args->member_srl2 = $member_srl;
				$output = executeQuery('elkhatalk3.insertMembers', $args);
			}
		}
		FileHandler::removeFile($this->cache_path.$filename);
		$room_info = new stdClass();
		$room_info->status = 0;
		$room_info->room_no = $insert_id;
		$room_info->blind = NULL;
		$this->setCache($cache_key, $room_info);
		return $room_info;
	}

	public function load_css()
	{
		static $init = FALSE;
		if($init)
		{
			return '';
		}
		$init = TRUE;
		$config = $this->getConfig();
		$widget_css = "/skins/$config->skin/widget.css";
		return '<link rel="stylesheet" href="' . $config->cdn_path . "modules/elkhatalk3$widget_css?c=" . filemtime(__DIR__.$widget_css) . '" />';
	}

	// Context::addHtmlHeader 사용할 수 없다면 $header_script = FALSE 넣을 것.
	public function public_iframe($room, $title, $width = 0, $height = 0, $is_popup = 'N', $header_script = TRUE)
	{
		$room = preg_replace('/[^0-9a-z\-_]+/iu', '', $room);
		$room_title = str_replace(['+','_','.','-'], ['%20','%5F','%2E','%2D'], urlencode($title));
		$config = $this->getConfig();

		if($is_popup=='a')
		{
			$room_clients = FALSE;
			$oCacheHandler = CacheHandler::getInstance();
			$filename = "files/cache/elkhatalk3/count_$room.txt";
			if($oCacheHandler->isSupport())
			{
				$cache_key = "object:elkhacord:$filename";
				$room_clients = $oCacheHandler->get($cache_key);
			}
			else if(FileHandler::exists($filename) && time() - filemtime(_XE_PATH_.$filename) < 60)
			{
				$room_clients = (INT)FileHandler::readFile($filename);
			}
			if(!is_int($room_clients))
			{
				$params = array('room' => $room);
				$output = $this->sendSocket('clients', $params);
				$room_clients = (INT)$output->getMessage();
				if($oCacheHandler->isSupport())
				{
					$oCacheHandler->put($cache_key, $room_clients, 30 + mt_rand(0, 30));
				}
				else
				{
					FileHandler::writeFile($filename, $room_clients);
				}
			}
			return '<a href="'.getUrl('','module','elkhatalk3','room',$room,'room_title',$room_title,'popup','Y').'" class="elkhacord_a"><span class="elkhacord_a-title">'.$title.'</span> <span class="elkhacord_a-clients">['.$room_clients.']</span></a>';
		}
		$auth_info = $this->auth_info();
		$this->load_js();

		if(preg_match('/^[0-9]+$/', $width) && (INT)$width) $width .= 'px';
		if(preg_match('/^[0-9]+$/', $height) && (INT)$height) $height .= 'px';

		$list_count = 20;
		foreach($config->public_chat_list_count as $val)
		{
			if($val['regex']=='Y')
			{
				if(!preg_match($val['room'], $room))
				{
					continue;
				}
			}
			else
			{
				if($val['room']!=$room)
				{
					continue;
				}
			}
			$list_count = (INT)$val['list_count'];
		}

		// 공지 쓴 시각.
		$ntime = FileHandler::exists($filename = "files/elkhatalk3/$room.json")? filemtime(_XE_PATH_.$filename) : 0;

		$args = new stdClass();
		$args->components = [];
		$args->src = "//$config->host/elkhacord.html";
		$args->params = array(
			'member_srl' => $auth_info->member_srl
			,'room'=> $room
			,'room_title' => htmlspecialchars($room_title)
			,'host'=>$config->your_host
			,'is_popup'=>$is_popup
			,'list_count'=>$list_count
			,'ntime'=>$ntime
			,'v'=>'1.1.4' // invalidate cache
		);

		// css.
		$room_no = (INT)preg_replace('/[^0-9]/iu', '', $room);
		$styles = [
			"/public_chat/{$room}/style.min.css",
			"/public_chat/{$room_no}/style.min.css",
			'/public_chat/0/style.min.css',
			// 구버전 스킨 호환
			"/public_chat/{$room}/style.css",
			"/public_chat/{$room_no}/style.css",
			'/public_chat/0/style.css'
		];
		$style =  '/public_chat/default/style.min.css';
		foreach($styles as $_style)
		{
			if(\FileHandler::exists(__DIR__. $_style))
			{
				$style = $_style;
				break;
			}
		}

		$style = $config->cdn_path."modules/elkhatalk3{$style}?c=" . filemtime(__DIR__. $style);

		// 로고.
		if(FileHandler::exists(__DIR__."/public_chat/$room.png")) $args->params['logo'] = "$room.png";
		else if(FileHandler::exists(__DIR__.sprintf('/public_chat/%d.png', $room_no = (INT)preg_replace('/[^0-9]/iu', '', $room)))) $args->params['logo'] = "$room_no.png";
		else if(FileHandler::exists(__DIR__.'/public_chat/0.png')) $args->params['logo'] = '0.png';

		$headers = [];
		$plugins = $this->getPlugin('components#');
		foreach($plugins as $key => $val)
		{
			$plugins[$key]($args, $headers);
		}
		ksort($args->components);
		$args->components = array_values($args->components);

		$buff = json_encode($args);
		$md5 = md5($buff);

		$div = sprintf('<div class="elkhacord" data-room="%s" data-iframe="%s" data-style="%s" style="display:table;width:%s;height:%s;"></div><script>;(function(){ if(typeof(window.elkhacord_init)=="function") window.elkhacord_init(); })();</script>', htmlspecialchars(strip_tags($room)), $md5, $style, $width, $height);
		$headers[] = "<script>//<![CDATA[\n;(function(){window.elkhacord_$md5=$buff;})();\n//]]></script>";
		if($header_script)
		{
			$headers[] = $this->load_css();
			Context::addHtmlHeader(implode("\n", $headers));
			return $div;
		}
		else
		{
			return implode("\n", $headers).$div;
		}
	}
	/**
	 * 등록할 트리거를 여기에 선언하면 자동으로 등록된다.
	 * checkUpdate(), moduleUpdate() 등에서 체크 및 생성 루틴을 중복으로 작성하지 않아도 된다.
	 */
	protected static $_insert_triggers = array(
		// array('document.insertDocument', 'after', 'controller', 'triggerAfterInsertDocument'),
		// array('document.updateDocument', 'after', 'controller', 'triggerAfterUpdateDocument'),
		// array('document.deleteDocument', 'after', 'controller', 'triggerAfterDeleteDocument'),
	);

	/**
	 * 이전 버전에서 등록했던 트리거를 삭제하려면 위와 동일한 문법으로 여기에 선언하면 된다.
	 * 사용하지 않는 트리거는 삭제해 주는 것이 성능에 도움이 된다.
	 */
	protected static $_delete_triggers = array(
		// array('comment.insertComment', 'after', 'controller', 'triggerAfterInsertComment'),
		// array('comment.updateComment', 'after', 'controller', 'triggerAfterUpdateComment'),
		// array('comment.deleteComment', 'after', 'controller', 'triggerAfterDeleteComment'),
	);

	// =========================== 이 부분 아래는 수정하지 않아도 된다 ============================

	/**
	 * 모듈 설정 캐시를 위한 변수.
	 */
	protected static $_config_cache = null;

	/**
	 * 캐시 핸들러 캐시를 위한 변수.
	 */
	protected static $_cache_handler_cache = null;

	/**
	 * 모듈 설정을 가져오는 함수.
	 *
	 * 캐시 처리되기 때문에 ModuleModel을 직접 호출하는 것보다 효율적이다.
	 * 모듈 내에서 설정을 불러올 때는 반드시 이 함수를 사용하도록 한다.
	 *
	 * @return object
	 */
	public function getConfig()
	{
		if (self::$_config_cache === null)
		{
			self::$_config_cache = \ModuleModel::getModuleConfig($this->module) ?: new stdClass;
		}
		self::$_config_cache->host = self::$_config_cache->host?? 'chat.elkha.kr';
		self::$_config_cache->your_host = self::$_config_cache->your_host?? getenv('HTTP_HOST');
		//self::$_config_cache->your_scheme = self::$_config_cache->your_scheme?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']? 'https' : 'http');
		self::$_config_cache->http_port = self::$_config_cache->http_port?? 8080;
		self::$_config_cache->https_port = self::$_config_cache->https_port?? 8081;
		self::$_config_cache->cdn_path = self::$_config_cache->cdn_path?? Context::getDefaultUrl();
		self::$_config_cache->skin = self::$_config_cache->skin?? 'default';
		self::$_config_cache->logo = self::$_config_cache->logo?? self::$_config_cache->cdn_path . 'modules/elkhatalk3/skins/' . self::$_config_cache->skin . '/logo.png';
		self::$_config_cache->notify_mp3 = self::$_config_cache->notify_mp3?? self::$_config_cache->cdn_path . 'modules/elkhatalk3/notify_mp3/default.mp3';
		self::$_config_cache->public_chat_list_count = !isset(self::$_config_cache->public_chat_list_count) || !count(self::$_config_cache->public_chat_list_count)? array(
			['room'=>'/^public/','regex'=>'Y','list_count'=>20]
		) : self::$_config_cache->public_chat_list_count;
		self::$_config_cache->public_chat_menu_list = !isset(self::$_config_cache->public_chat_menu_list)? array(
			['room'=>'/^public/','regex'=>'Y','href'=>getNotEncodedFullUrl(),'text'=>Context::getLang('homepage')]
		) : self::$_config_cache->public_chat_menu_list;
		self::$_config_cache->popup_size = isset(self::$_config_cache->popup_size)? self::$_config_cache->popup_size : array(400,600);
		self::$_config_cache->max_blind_hours = self::$_config_cache->max_blind_hours?? 720;
		self::$_config_cache->talk_list_count = self::$_config_cache->talk_list_count?? 100;
		self::$_config_cache->server_time_auth = self::$_config_cache->server_time_auth?? 'Y';
		self::$_config_cache->dark_mode = self::$_config_cache->dark_mode ?? 'N';
		self::$_config_cache->use_communication_friends = self::$_config_cache->use_communication_friends?? 'default';
		self::$_config_cache->plugin_vars = self::$_config_cache->plugin_vars ?? [];
		if(isset(self::$_config_cache->secret_idx) && self::$_config_cache->secret_idx > 0)
		{
			self::$_config_cache->secret_key_send = 'Y';
		}
		else
		{
			self::$_config_cache->secret_idx = 0;
			self::$_config_cache->secret_key = 'TEST';
			self::$_config_cache->secret_key_send = 'N';
		}

		return self::$_config_cache;
	}

	public function getUrl(array $params, $func = 'getUrl') : string
	{
		$params['member_srl'] = $params['member_srl'] ?? (is_object($logged_info = \Context::get('logged_info')) && $logged_info->member_srl ? $logged_info->member_srl : '');
		$params['target_srl'] = $params['target_srl'] ?? '';
		$params['nick_name'] = $params['nick_name'] ?? '';
		$params['friends'] = $params['friends'] ?? '';
		$params['mpage'] = $params['mpage'] ?? '';
		$params['act'] = $params['act'] ?? 'dispElkhatalk3Index';
		$args = [];
		foreach($params as $key => $val)
		{
			$args[] = $key;
			$args[] = $val;
		}
		return call_user_func_array($func, $args);
	}

	// M관리 C카페 G그룹 B밴 | member_srl=0, C카페 G그룹 F얼리기 R읽기금지 W쓰기금지 A최고관리자
	static $public_grants_int = ['@', 'M', 'C', 'G', 'F', 'W', 'L', 'FW', 'RW', 'FRW', 'FLW', 'LRW', 'FLRW', 'FL', 'LW' , 'B', 'BC', 'CG', 'CF']; // , 'AG', 'ACG'
	public function get_public_grants($args = NULL)
	{
		$use_cache = FALSE;
		if($args===NULL)
		{
			$args = new stdClass();
			//$args->rooms = (INT)preg_replace('/[^0-9]+/', '', Context::get('room'));
			$args->member_srls = [0];
			$logged_info = Context::get('logged_info');
			if(is_object($logged_info) && $logged_info->member_srl)
			{
				$args->member_srls[] = $logged_info->member_srl;
				$args->group_srls = array_keys($logged_info->group_list);
			}
			else
			{
				$use_cache = TRUE;
				$grants = FALSE;
				// 비회원은 object cache 사용
				$filename = 'files/cache/elkhatalk3/00.txt';
				$oCacheHandler = CacheHandler::getInstance();
				if($oCacheHandler->isSupport())
				{
					$cache_key = "object:$filename";
					$grants = $oCacheHandler->get($cache_key);
				}
				else if(FileHandler::exists($filename))
				{
					$buff = FileHandler::readFile($filename);
					if(is_string($buff) && $buff!='')
					{
						$grants = @json_decode($buff);
					}
				}
				if(is_object($grants))
				{
					return $grants;
				}
			}
		}
		if(isset($args->rooms))
		{
			if(isset($args->member_srls))
			{
				if(
					(is_array($args->member_srls) && in_array(0, $args->member_srls))
					|| ((is_int($args->member_srls) || is_string($args->member_srls)) && "$args->member_srls"==='0')
				)
				{
					$args->grants_int = [ array_search('G', static::$public_grants_int) ];
				}
			}
			// 버그 고치기.
			if(isset($args->grants_int))
			{
				$args->rooms_0 = $args->rooms;
			}
		}
		if(isset($args->group_srls) && count($args->group_srls))
		{
			$args->grants_int_m = [ array_search('G', static::$public_grants_int) ];
		}
		$output = executeQueryArray('elkhatalk3.getPublicGrants', $args);
		// M관리 C카페 G그룹 | member_srl=0, C카페 G그룹 F얼리기 R읽기금지 W쓰기금지 1FR 2FW 3RW 4FRW
		// 5FRL 6FWL 7RWL 8FRWL , V/CL X/FL Y/RL Z/WL
		$grants = new stdClass();
		$grants->_F = []; // TRUE 이면 얼려짐.
		$grants->_R = []; // TRUE 이면 비회원 읽기 금지
		$grants->_W = []; // TRUE 이면 비회원 쓰기 금지
		$grants->_C = []; // TRUE 이면 권한이 [CM] 만 가능.
		$grants->_G = []; // TRUE 이면 해당 그룹만 2023-07-19
		//$grants->_A = []; // TRUE 이면 그룹 설정된 방에 최고관리자도 접근 가능
		$grants->M = []; // 관리자 member_srl 목록
		$grants->C = []; // 카페 member_srl 목록
		$grants->G = []; // 그룹 member_srl 목록
		$grants->W = []; // 쓰기 금지 member_srl 목록 (=밴)
		$grants->R = []; // 읽기 금지 member_srl 목록 (아마도 사용 안함)
		$grants->F = []; // 사용 안함.
		$grants->B = []; // 밴
		$grants->pk = [];
		$grants->expdate = [];
		$grants->chat_log = [];

		if(isset($args->rooms))
		{
			$rooms = is_array($args->rooms)? $args->rooms : array($args->rooms);
			foreach($rooms as $room)
			{
				$grants->_F[$room] = FALSE;
				$grants->_R[$room] = FALSE;
				$grants->_W[$room] = FALSE;
				$grants->_C[$room] = FALSE;
				$grants->_G[$room] = FALSE;
				//$grants->_A[$room] = FALSE;
			}
		}
		foreach($output->data as $val)
		{
			$room = (INT)$val->room;
			if(!isset($grants->_F[$room]))
			{
				$grants->_F[$room] = FALSE;
				$grants->_R[$room] = FALSE;
				$grants->_W[$room] = FALSE;
				$grants->_C[$room] = FALSE;
				$grants->_G[$room] = FALSE;
			}

			for($i = 0; $i < strlen( static::$public_grants_int[$val->grants_int] ); $i++)
			{
				if((INT)$val->member_srl)
				{
					if(!$val->expdate || time() < strtotime($val->expdate))
					{
						$grants->{static::$public_grants_int[$val->grants_int][$i]}[$val->pk] = (INT)$val->member_srl;
					}
				}
				else if(static::$public_grants_int[$val->grants_int][$i]=='L')
				{
					$grants->chat_log[$room] = 'Y';
				}
				else
				{
					if(static::$public_grants_int[$val->grants_int][$i]=='F' && $val->expdate && time() > strtotime($val->expdate))
					{
						$grants->{'_' . static::$public_grants_int[$val->grants_int][$i]}[$room] = FALSE;
					}
					else
					{
						$grants->{'_' . static::$public_grants_int[$val->grants_int][$i]}[$room] = TRUE;
					}
				}
			}
			$grants->chat_log[$room] = $grants->chat_log[$room] ?? 'N';
			if(!$val->member_srl && preg_match('/[CG]/', static::$public_grants_int[$val->grants_int]))
			{
				$grants->_R[$room] = $grants->_W[$room] = TRUE;
			}

			if(!isset($grants->pk[$val->member_srl]))
			{
				$grants->pk[$val->member_srl] = [];
			}
			$grants->pk[$val->member_srl][] = array($val->pk, $room, $val->expdate, $val->grants_int);
		}
		// 승인된 회원이어도, 체크 안되어 있다면 비활성화.
		foreach($grants->_C as $room => $val)
		{
			if(!$val && isset($args->member_srls) && count($grants->C))
			{
				$grants->C = [];
			}
		}
		if($use_cache)
		{
			if($oCacheHandler->isSupport())
			{
				$oCacheHandler->put($cache_key, $grants, 3600);
			}
			if(!FileHandler::exists($filename))
			{
				FileHandler::writeFile($filename, json_encode($grants));
			}
		}
		return $grants;
	}

	/**
	 * 모듈 설정을 저장하는 함수.
	 *
	 * 설정을 변경할 필요가 있을 때 ModuleController를 직접 호출하지 말고 이 함수를 사용한다.
	 * getConfig()으로 가져온 설정을 적절히 변경하여 setConfig()으로 다시 저장하는 것이 정석.
	 *
	 * @param object $config
	 * @return object
	 */
	public function setConfig($config)
	{
		$oModuleController = getController('module');
		$result = $oModuleController->insertModuleConfig($this->module, $config);
		if ($result->toBool())
		{
			self::$_config_cache = $config;
		}
		return $result;
	}

	/**
	 * 오브젝트 캐시에서 값을 가져오는 함수.
	 *
	 * 그룹 키를 지정하지 않으면 자동으로 현재 모듈 이름이 그룹 키로 사용되므로
	 * 필요시 그룹 키를 비움으로써 신속하게 캐시를 갱신할 수 있다.
	 *
	 * @param string $key
	 * @param int $ttl
	 * @param string $group_key (optional)
	 * @return mixed
	 */
	public function getCache($key, $ttl = 86400, $group_key = null)
	{
		if (self::$_cache_handler_cache === null)
		{
			self::$_cache_handler_cache = CacheHandler::getInstance('object');
		}

		if (self::$_cache_handler_cache->isSupport())
		{
			$group_key = $group_key ?: $this->module;
			return self::$_cache_handler_cache->get(self::$_cache_handler_cache->getGroupKey($group_key, $key), $ttl);
		}
		else
		{
			return false;
		}
	}

	/**
	 * 오브젝트 캐시에 값을 저장하는 함수.
	 *
	 * 그룹 키를 지정하지 않으면 자동으로 현재 모듈 이름이 그룹 키로 사용되므로
	 * 필요시 그룹 키를 비움으로써 신속하게 캐시를 갱신할 수 있다.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @param string $group_key (optional)
	 * @return bool
	 */
	public function setCache($key, $value, $ttl = 86400, $group_key = null)
	{
		if (self::$_cache_handler_cache === null)
		{
			self::$_cache_handler_cache = CacheHandler::getInstance('object');
		}

		if (self::$_cache_handler_cache->isSupport())
		{
			$group_key = $group_key ?: $this->module;
			return self::$_cache_handler_cache->put(self::$_cache_handler_cache->getGroupKey($group_key, $key), $value, $ttl);
		}
		else
		{
			return false;
		}
	}

	/**
	 * 오브젝트 캐시에서 개별 키를 삭제하는 함수.
	 *
	 * @param string $key
	 * @param string $group_key (optional)
	 * @return bool
	 */
	public function deleteCache($key, $group_key = null)
	{
		if (self::$_cache_handler_cache === null)
		{
			self::$_cache_handler_cache = CacheHandler::getInstance('object');
		}

		if (self::$_cache_handler_cache->isSupport())
		{
			$group_key = $group_key ?: $this->module;
			return self::$_cache_handler_cache->delete(self::$_cache_handler_cache->getGroupKey($group_key, $key));
		}
		else
		{
			return false;
		}
	}

	/**
	 * 오브젝트 캐시를 비우는 함수.
	 *
	 * 지정된 그룹 키에 소속된 데이터만 삭제한다.
	 * 현재 모듈에서 저장한 데이터만 삭제하는 것이 기본값이다.
	 *
	 * @param string $group_key (optional)
	 * @return bool
	 */
	public function clearCache($group_key = null)
	{
		if (self::$_cache_handler_cache === null)
		{
			self::$_cache_handler_cache = CacheHandler::getInstance('object');
		}

		if (self::$_cache_handler_cache->isSupport())
		{
			$group_key = $group_key ?: $this->module;
			return self::$_cache_handler_cache->invalidateGroupKey($group_key) ? true : false;
		}
		else
		{
			return false;
		}
	}

	/**
	 * XE Object를 생성하여 반환한다.
	 *
	 * XE 1.8 이하, XE 1.9 이상, PHP 7.1 이하, PHP 7.2 이상 모두 호환된다.
	 * 기본적인 사용법은 return new Object(-1, 'error'); 라고 쓸 자리에
	 * return $this->createObject(-1, 'error'); 라고 쓰면 된다.
	 *
	 * 반환할 언어 내용 중 %s, %d 등 변수를 치환하는 부분이 있다면
	 * 치환할 내용을 추가 파라미터로 넘겨주면 sprintf()의 역할까지 해준다.
	 *
	 * @param string $message
	 * @param $arg1, $arg2 ...
	 * @return object
	 */
	public function createObject($status = 0, $message = 'success' /* $arg1, $arg2 ... */)
	{
		$args = func_get_args();
		if (count($args) > 2)
		{
			global $lang;
			$message = vsprintf($lang->$message, array_slice($args, 2));
		}
		return class_exists('BaseObject') ? new BaseObject($status, $message) : new Object($status, $message);
	}

	/**
	 * 모듈 설치 콜백 함수.
	 *
	 * 트리거 등록 외에 따로 할 일이 없다면 수정할 필요 없다.
	 *
	 * @return object
	 */
	public function moduleInstall()
	{
		return $this->moduleUpdate();
	}

	/**
	 * 모듈 업데이트 확인 콜백 함수.
	 *
	 * 트리거 등록 외에 따로 할 일이 없다면 수정할 필요 없다.
	 *
	 * @return bool
	 */
	public function checkUpdate()
	{
		$oAdminController = getAdminController('elkhatalk3');
		return $oAdminController->{__FUNCTION__}();
	}

	public function checkUpdateCache($object = NULL)
	{
		if(FileHandler::exists($this->cache_path.'_update.txt'))
		{
			$logged_info = Context::get('logged_info');
			if(is_object($logged_info) && $logged_info->is_admin=='Y')
			{
				$message = Context::getLang('elkhatalk3_update') . "\n" . Context::getLang('elkhatalk3_update_admin') . "\n" . $this->cache_path.'_update.txt';
			}
			else
			{
				$message = 'elkhatalk3_update';
			}
			if($object===NULL)
			{
				return $this->stop($message);
			}
			$object->setError(-1);
			$object->setMessage($message);
		}
		return $object===NULL? $this : $object;
	}

	/**
	 * 모듈 업데이트 콜백 함수.
	 *
	 * 트리거 등록 외에 따로 할 일이 없다면 수정할 필요 없다.
	 *
	 * @return object
	 */
	public function moduleUpdate()
	{
		$oAdminController = getAdminController('elkhatalk3');
		return $oAdminController->{__FUNCTION__}();
	}

	/**
	 * 캐시파일 재생성 콜백 함수.
	 *
	 * @return void
	 */
	public function recompileCache()
	{

	}
}
