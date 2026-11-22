<?php

/**
 * elkhatalk3
 *
 * Copyright (c) 엘카
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Elkhatalk3AdminController extends Elkhatalk3
{
	public function init()
	{
		$this->checkUpdateCache();
		if(!$this->toBool())
		{
			return $this;
		}
	}
	/**
	 * 관리자 설정 저장 액션 예제
	 */
	public function procElkhatalk3AdminInsertConfig()
	{
		// 현재 설정 상태 불러오기
		$config = new stdClass();
		$old_config = $this->getConfig();

		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();

		$config->post_host_callback = $vars->post_host_callback;
		$config->cdn_path = $vars->cdn_path;
		$config->post_host_callback_rooms = $vars->post_host_callback_rooms;
		$config->font_family = (STRING)$vars->font_family;
		$config->is_admin = $vars->is_admin? preg_split('/[^0-9]+/', $vars->is_admin) : array();
		$config->skin = $vars->skin;
		$config->logo = $vars->logo;
		$config->notify_mp3 = $vars->notify_mp3;
		$config->server_time_auth = $vars->server_time_auth;
		$config->use_communication_friends = $vars->use_communication_friends;
		$config->host = $vars->host;
		$config->http_port = $vars->http_port;
		$config->https_port = $vars->https_port;
		$config->your_host = $vars->your_host;
		$config->talk_list_count = $vars->talk_list_count;
		$config->dark_mode = $vars->dark_mode;
		$config->secret_key = $old_config->secret_key;
		$config->secret_idx = $old_config->secret_idx;

		if(strlen($vars->max_blind_hours))
		{
			$config->max_blind_hours = (INT)$config->max_blind_hours;
		}
		//$config->public_chat_log = $vars->public_chat_log;
		if(strlen($vars->popup_size))
		{
			$popup_size = explode(',',$vars->popup_size);
			if(count($popup_size)==2)
			{
				$config->popup_size = $popup_size;
			}
		}
		else
		{
			unset($config->popup_size);
		}

		$oModel = getAdminModel('elkhatalk3');
		$all_plugins = $oModel->all_plugins();
		if(isset($vars->plugins) && is_array($vars->plugins))
		{
			$config->plugins = array();

			foreach($vars->plugins as $plugin)
			{
				foreach($all_plugins[$plugin] as $val)
				{
					if(!isset($config->plugins[$val]))
					{
						$config->plugins[$val] = array();
					}
					if(!in_array($plugin, $config->plugins[$val]))
					{
						$config->plugins[$val][] = $plugin;
					}
				}
			}
		}
		else
		{
			unset($config->plugins);
		}
		$config->plugin_vars = [];
		$all_plugin_vars = $oModel->allPluginVars();
		foreach($all_plugin_vars as $plugin => $val)
		{
			$config->plugin_vars[$plugin] = [];
			foreach($val as $v_name => $v)
			{
				if(is_array($v['vars']))
				{
					$config->plugin_vars[$plugin][$v_name] = [];
					$var = (array)\Context::get("plugin_vars_$plugin" . '_' . $v_name);
					foreach($var as $_var)
					{
						if(strlen($_var))
						{
							$config->plugin_vars[$plugin][$v_name][] = $_var;
						}
					}
				}
				else if(is_int($v['vars']))
				{
					if(strlen(\Context::get("plugin_vars_$plugin" . '_' . $v_name)))
					{
						$config->plugin_vars[$plugin][$v_name] = (INT)\Context::get("plugin_vars_$plugin" . '_' . $v_name);
					}
				}
				else
				{
					if(strlen(\Context::get("plugin_vars_$plugin" . '_' . $v_name)))
					{
						$config->plugin_vars[$plugin][$v_name] = \Context::get("plugin_vars_$plugin" . '_' . $v_name);
					}
				}
			}
		}

		$config->public_chat_menu_list = array();
		if(isset($vars->public_chat_menu_list_room) && is_array($vars->public_chat_menu_list_room) && isset($vars->public_chat_menu_list_text) && is_array($vars->public_chat_menu_list_text))
		{
			foreach($vars->public_chat_menu_list_room as $key => $val)
			{
				if(!strlen($val) || !isset($vars->public_chat_menu_list_text[$key]) || !strlen($vars->public_chat_menu_list_text[$key])) continue;
				$config->public_chat_menu_list[] = array(
					'room'=>$vars->public_chat_menu_list_room[$key],
					'text'=>$vars->public_chat_menu_list_text[$key],
					'href'=> (isset($vars->public_chat_menu_list_href[$key]) && strlen($vars->public_chat_menu_list_href[$key])? $vars->public_chat_menu_list_href[$key] : ''),
					'regex'=> (isset($vars->public_chat_menu_list_regex[$key]) && strlen($vars->public_chat_menu_list_regex[$key])? 'Y' : 'N')
				);
			}
		}

		$config->public_chat_list_count = array();
		if(isset($vars->public_chat_list_count_room) && is_array($vars->public_chat_list_count_room) && isset($vars->public_chat_list_count) && is_array($vars->public_chat_list_count))
		{
			foreach($vars->public_chat_list_count_room as $key => $val)
			{
				if(!strlen($val) || !isset($vars->public_chat_list_count[$key]) || !strlen($vars->public_chat_list_count[$key])) continue;
				$config->public_chat_list_count[] = array(
					'room'=>$vars->public_chat_list_count_room[$key],
					'list_count'=>$vars->public_chat_list_count[$key],
					'regex'=> (isset($vars->public_chat_list_count_regex[$key]) && strlen($vars->public_chat_list_count_regex[$key])? 'Y' : 'N')
				);
			}
		}

		// 변경된 설정을 저장
		$output = $this->setConfig($config);
		if (!$output->toBool())
		{
			return $output;
		}

		if(!$this->getError())
		{
			$this->setMessage('success_registed');
		}
		$this->setRedirectUrl(Context::get('success_return_url'));
	}

	public function procElkhatalk3AdminGetAuthKey()
	{
		$renew = (INT)\Context::get('renew');
		$config = $this->getConfig();
		$secret_key = $renew ? 'TEST' : $config->secret_key;

		$ch = curl_init();
		$param = array
		(
			'secret_key' => $secret_key,
			'your_host' => $config->your_host,
			'xe_path' => \RX_BASEURL
		);
		$url = "http://$config->host:$config->http_port/auth";

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
		//curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
		//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		$res = curl_exec($ch);

		// 2020-05-05
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($res, 0, $header_size);
		$res = substr($res, $header_size);

		curl_close($ch);

		if(strpos($res, 'OK') === 0 && count($data = preg_split('/[\r\n]+/',$res)) >= 2)
		{
			list($config->secret_idx, $config->secret_key) = explode('_', $data[1]);
			$this->setConfig($config);
		}
		else
		{
			$this->setMessage("Socket server no response:\n" . nl2br(htmlspecialchars($res)));
			$this->setError(-1);
		}
		return $this;
	}

	public function procElkhatalk3AdminInstall()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach ($this::$_insert_triggers as $trigger)
		{
			if($oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				continue;
			}
			$oModuleController->insertTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]);
		}

		return $this->createObject(0, 'success_updated');
	}

	public function procElkhatalk3AdminUninstall() : void
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach ($this::$_insert_triggers as $trigger)
		{
			if ($oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				$oModuleController->deleteTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]);
			}
		}
	}

	static $_insert_triggers = array(
		array('moduleHandler.init', 'before', 'class', 'triggerBeforeModuleInit'),
		array('moduleHandler.proc', 'after', 'class', 'triggerAfterModuleProc'),
		array('communication.sendMessage', 'after', 'controller', 'triggerAfterSendMessage'),
		array('ncenterlite._insertNotify', 'after', 'controller', 'triggerAfterInsertNotify'),
		array('lazypush.dispLazypushAdminConfig', 'after', 'controller', 'triggerAfterLazypushConfig'),
		// array('document.insertDocument', 'after', 'controller', 'triggerAfterInsertDocument'),
		// array('document.updateDocument', 'after', 'controller', 'triggerAfterUpdateDocument'),
		// array('document.deleteDocument', 'after', 'controller', 'triggerAfterDeleteDocument'),
	);
	static $_delete_triggers = array(
		// array('comment.insertComment', 'after', 'controller', 'triggerAfterInsertComment'),
		// array('comment.updateComment', 'after', 'controller', 'triggerAfterUpdateComment'),
		// array('comment.deleteComment', 'after', 'controller', 'triggerAfterDeleteComment'),
	);
	public function checkTriggers()
	{
		$oModuleModel = getModel('module');
		foreach ($this::$_insert_triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				return true;
			}
		}
		foreach ($this::$_delete_triggers as $trigger)
		{
			if ($oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				return true;
			}
		}
		return false;
	}


	static $_insert_triggers2 = array(
	);
	static $_delete_triggers2 = array(
	);

	public function checkTables()
	{
		/*$oModuleModel = getModel('module');
		foreach ($this::$_insert_triggers2 as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				return true;
			}
		}
		foreach ($this::$_delete_triggers2 as $trigger)
		{
			if ($oModuleModel->getTrigger($trigger[0], $this->module, $trigger[2], $trigger[3], $trigger[1]))
			{
				return true;
			}
		}*/
		$oDB = DB::getInstance();
		$tables = array('elkhatalk_public','elkhatalk_public_grants','elkhatalk','elkhatalk_content','elkhatalk_members','elkhatalk3_point_game');
		foreach($tables as $table)
		{
			if(!$oDB->isTableExists($table))
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	public function procElkhatalk3AdminInstall2()
	{
		// `use_notice` enum('N','Y') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'N',
		$query_list = array(
			"CREATE TABLE IF NOT EXISTS `%selkhatalk_public` (
			  `pk` bigint unsigned NOT NULL AUTO_INCREMENT,
			  `room` tinyint unsigned NOT NULL,
			  `member_srl` bigint unsigned DEFAULT NULL,
			  `nick_name` varchar(40) DEFAULT NULL,
			  `msg` varchar(255) NOT NULL,
			  `regdate` timestamp NOT NULL DEFAULT current_timestamp(),
			  `option` char(1) DEFAULT NULL COMMENT 'H: html 사용, D: 삭제',
			  PRIMARY KEY (`pk`),
			  KEY `room` (`room`,`pk`),
			  KEY `member_srl` (`member_srl`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

			"CREATE TABLE IF NOT EXISTS `%selkhatalk_public_grants` (
			  `pk` bigint unsigned NOT NULL AUTO_INCREMENT,
			  `room` tinyint unsigned NOT NULL,
			  `member_srl` bigint unsigned NOT NULL,
			  `grants_int` tinyint UNSIGNED NOT NULL DEFAULT 0,
			  `expdate` timestamp NULL DEFAULT NULL,
			  `regdate` timestamp NOT NULL DEFAULT current_timestamp(),
			  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
			  UNIQUE KEY `pk` (`pk`),
			  UNIQUE KEY `room` (`room`,`member_srl`),
			  KEY `room_grants_int` (`room`,`grants_int`),
			  KEY `member_srl_grants_int` (`member_srl`,`grants_int`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

			"CREATE TABLE IF NOT EXISTS `%selkhatalk` (
			  `no` int unsigned NOT NULL AUTO_INCREMENT,
			  `regdate` int unsigned NOT NULL,
			  `extra_vars` varchar(255) NULL DEFAULT NULL,
			  `password` smallint unsigned DEFAULT 0,
			  PRIMARY KEY (`no`),
			  KEY `secret` (`password`),
			  KEY `regdate` (`regdate`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='채팅방 정보';",

			"CREATE TABLE IF NOT EXISTS `%selkhatalk_content` (
			  `no` bigint unsigned NOT NULL AUTO_INCREMENT,
			  `room_no` int unsigned NOT NULL,
			  `content` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
			  `member_srl` int unsigned NOT NULL,
			  `regdate` int unsigned NOT NULL,
			  `read` tinyint unsigned NOT NULL,
			  PRIMARY KEY (`no`),
			  KEY `member_srl` (`member_srl`),
			  KEY `room_no` (`room_no`,`read`,`member_srl`),
			  UNIQUE KEY `idx_room_no_no` (`room_no`,`no`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

			"CREATE TABLE IF NOT EXISTS `%selkhatalk_members` (
			  `pk` bigint unsigned NOT NULL AUTO_INCREMENT,
			  `room_no` int unsigned NOT NULL,
			  `member_srl` bigint NOT NULL,
			  `member_srl2` bigint unsigned NOT NULL DEFAULT 0,
			  `regdate` int unsigned NOT NULL,
			  `last_update` timestamp NOT NULL DEFAULT current_timestamp(),
			  `status` tinyint NOT NULL DEFAULT 0,
			  `blind` timestamp NULL DEFAULT NULL,
			  `hide` ENUM('Y','N') NOT NULL DEFAULT 'N',
			  #`hide` TINYINT UNSIGNED NOT NULL DEFAULT '0' COMMENT '0보이기 1숨기기 2친구',
			  PRIMARY KEY (`pk`),
			  KEY `regdate` (`regdate`),
			  KEY `room_no_last_update` (`room_no`, `last_update` DESC),
			  KEY `member_srl2` (`member_srl2`,`member_srl`),
			  KEY `member_srl_hide_room_no` (`member_srl`, `hide`, `room_no`),
			  KEY `status` (`status`,`member_srl`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

			"CREATE TABLE IF NOT EXISTS `%selkhatalk3_point_game` (
			  `pk` int unsigned NOT NULL AUTO_INCREMENT,
			  `member_srl_me` bigint unsigned NOT NULL,
			  `member_srl_you` bigint unsigned NOT NULL DEFAULT 0,
			  `point_bet` smallint unsigned NOT NULL,
			  `point_before_me` bigint unsigned NOT NULL,
			  `point_before_you` bigint unsigned DEFAULT NULL,
			  `point_after_me` bigint unsigned DEFAULT NULL,
			  `point_after_you` bigint unsigned DEFAULT NULL,
			  `card_me` varchar(10) NOT NULL,
			  `card_you` varchar(10) DEFAULT NULL,
			  `regdate` timestamp NOT NULL DEFAULT current_timestamp(),
			  `last_update` timestamp NULL DEFAULT NULL,
			  `fees` smallint unsigned NOT NULL,
			  `member_srl_win` bigint unsigned DEFAULT NULL,
			  PRIMARY KEY (`pk`),
			  KEY `member_srl_me` (`member_srl_me`),
			  KEY `member_srl_you` (`member_srl_you`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
		);
		$prefix = Rhymix\Framework\Config::get('db')['master']['prefix'] ?? '';
		$oDB = DB::getInstance();
		if(method_exists($oDB,'getHandle'))
		{
			$link = $oDB->getHandle();
			foreach($query_list as $query)
			{
				$link->query(sprintf($query, $prefix));
			}
		}
		else
		{
			foreach($query_list as $query)
			{
				$oDB->query(sprintf($query, $prefix));
			}
		}

		$config = $this->getConfig();
		//$config->public_chat_log = $config->public_chat_log?? 'Y';
		$output = $this->setConfig($config);

		FileHandler::makeDir($this->cache_path);

		return $this->createObject(0, 'success_updated');
	}
	/*public function procElkhatalk3AdminUninstall2()
	{
	}*/

	public function checkUpdate() : bool
	{
		$check_list = [];
		$output = $this->createObject();
		$this->checkUpdateCache($output);
		if($output->toBool())
		{
			$oDB = DB::getInstance();
			if($oDB->isTableExists('elkhatalk_members') && !$oDB->isColumnExists('elkhatalk_members', 'hide'))
			{
				$check_list[] = __LINE__;
			}
			if($oDB->isTableExists('elkhatalk_content') && !$oDB->isIndexExists('elkhatalk_content', 'idx_room_no_no'))
			{
				$check_list[] = __LINE__;
			}
			if($oDB->isTableExists('elkhatalk_public_grants') && !$oDB->isColumnExists('elkhatalk_public_grants', 'grants_int'))
			{
				$check_list[] = __LINE__;
			}
			if($oDB->isTableExists('elkhatalk_public_grants') && !$oDB->isIndexExists('elkhatalk_public_grants', 'room_grants_int'))
			{
				$check_list[] = __LINE__;
			}
			if($oDB->isTableExists('elkhatalk_public_grants') && !$oDB->isIndexExists('elkhatalk_public_grants', 'member_srl_grants_int'))
			{
				$check_list[] = __LINE__;
			}
			// hide 컬럼을 개선하기.
			/*$prefix = Rhymix\Framework\Config::get('db')['master']['prefix'];
			$query = sprintf("SELECT `COLUMN_TYPE` FROM INFORMATION_SCHEMA.COLUMNS WHERE `TABLE_NAME` = '%selkhatalk_members' AND `COLUMN_NAME` = 'hide';", $prefix);
			$pdo = $oDB->getHandle();
			$stmt = $pdo->query($query);

			$row = $oDB->fetch($stmt);
			if(is_object($row) && isset($row->COLUMN_TYPE) && stripos($row->COLUMN_TYPE, 'enum') === 0)
			{
				return TRUE;
			}*/
			if($oDB->isTableExists('member_friend') && !$oDB->isColumnExists('member_friend', 'last_update_elkhatalk3'))
			{
				$check_list[] = __LINE__;
			}
			if($oDB->isTableExists('elkhatalk_members') && !$oDB->isIndexExists('elkhatalk_members', 'member_srl_hide_room_no'))
			{
				$check_list[] = __LINE__;
			}
			if($oDB->isTableExists('elkhatalk_members') && !$oDB->isIndexExists('elkhatalk_members', 'room_no_last_update'))
			{
				$check_list[] = __LINE__;
			}
			if($oDB->isTableExists('elkhatalk_members') && !$oDB->isColumnExists('elkhatalk_members', 'pk'))
			{
				$check_list[] = __LINE__;
			}
		}

		return !!count($check_list);
	}
	public function moduleUpdate()
	{
		$this->checkUpdateCache();
		if(!$this->toBool())
		{
			return $this;
		}
		$oDB = DB::getInstance();
		$logged_info = Context::get('logged_info');
		$prefix = Rhymix\Framework\Config::get('db')['master']['prefix'] ?? '';
		FileHandler::makeDir($this->cache_path);
		if($oDB->isTableExists('elkhatalk_members') && !$oDB->isColumnExists('elkhatalk_members', 'hide'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);
			$query = sprintf("ALTER TABLE `%selkhatalk_members` ADD `hide` ENUM('Y','N') NOT NULL DEFAULT 'N';", $prefix);
			$oDB->query($query);
			$this->clearCache();
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}
		// elkhatalk_members - member_srl 인덱스
		if($oDB->isTableExists('elkhatalk_members') && !$oDB->isIndexExists('elkhatalk_members', 'member_srl_hide_room_no'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);
			$query = sprintf("ALTER TABLE `%selkhatalk_members` ADD INDEX `member_srl_hide_room_no` (`member_srl`, `hide`, `room_no`);", $prefix);
			$oDB->query($query);
			$this->clearCache();
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}
		if($oDB->isTableExists('elkhatalk_members') && $oDB->isIndexExists('elkhatalk_members', 'member_srl'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);
			$oDB->dropIndex('elkhatalk_members', 'member_srl');
			$this->clearCache();
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}

		// elkhatalk_members - room_no인덱스
		if($oDB->isTableExists('elkhatalk_members') && !$oDB->isIndexExists('elkhatalk_members', 'room_no_last_update'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);
			$query = sprintf("ALTER TABLE `%selkhatalk_members` ADD INDEX `room_no_last_update` (`room_no`, `last_update` DESC);", $prefix);
			$oDB->query($query);
			$this->clearCache();
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}
		if($oDB->isTableExists('elkhatalk_members') && $oDB->isIndexExists('elkhatalk_members', 'room_no'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);
			$oDB->dropIndex('elkhatalk_members', 'room_no');
			$this->clearCache();
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}

		// elkhatalk_members - pk 컬럼
		if($oDB->isTableExists('elkhatalk_members') && !$oDB->isColumnExists('elkhatalk_members', 'pk'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);
			$query = sprintf("ALTER TABLE `%selkhatalk_members` ADD `pk` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`pk`);", $prefix);
			$oDB->query($query);
			$this->clearCache();
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}


		if($oDB->isTableExists('elkhatalk_content') && !$oDB->isIndexExists('elkhatalk_content', 'idx_room_no_no'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);

			$query = sprintf("ALTER TABLE `%selkhatalk_content` ADD UNIQUE `idx_room_no_no` (`room_no`, `no`);", $prefix);
			$oDB->query($query);
			$query = sprintf("ALTER TABLE `%selkhatalk_content` DROP PRIMARY KEY, ADD PRIMARY KEY (`no`);", $prefix);
			$oDB->query($query);
			$this->clearCache();
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}
		if($oDB->isTableExists('elkhatalk_public_grants') && !$oDB->isColumnExists('elkhatalk_public_grants', 'grants_int'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);
			$args = new stdClass();
			$output = executeQueryArray('elkhatalk3.getPublicGrants', $args, ['pk','grants','room']);

			$query = sprintf("ALTER TABLE `%selkhatalk_public_grants` DROP COLUMN `grants`, ADD COLUMN `grants_int` TINYINT UNSIGNED NOT NULL DEFAULT 0;", $prefix);
			$oDB->query($query);

			// M관리 C카페 G그룹 | member_srl=0, C카페 G그룹 F얼리기 R읽기금지 W쓰기금지 1FR 2FW 3RW 4FRW
			// 5FRL 6FWL 7RWL 8FRWL , V/CL X/FL Y/RL Z/WL
			$public_grants_reverse = ['@' => 0, '1' => 'FR', '2' => 'FW', '3' => 'RW', '4' => 'FRW', '5' => 'FLR', '6' => 'FLW', '7' => 'LRW', '8' => 'FLRW', 'V' => 'CL', 'X' => 'FL', 'Y' => 'LR', 'Z' => 'LW'];

			foreach($output->data as $val)
			{
				$grant = isset($public_grants_reverse["$val->grants"])? $public_grants_reverse["$val->grants"] : $val->grant;
				$args = new stdClass();
				$args->pk = $val->pk;
				$args->grants_int = in_array($grant, static::$public_grants_int)? (INT)array_search($grant, static::$public_grants_int) : 0;
				$_output = executeQuery('elkhatalk3.updatePublicGrants', $args);
			}
			$this->clearCache();
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}
		if($oDB->isTableExists('elkhatalk_public_grants') && !$oDB->isIndexExists('elkhatalk_public_grants', 'room_grants_int'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);

			$query = sprintf("ALTER TABLE `%selkhatalk_public_grants` ADD INDEX `room_grants_int` (`room`, `grants_int`);", $prefix);
			$oDB->query($query);

			FileHandler::removeFile($this->cache_path.'_update.txt');
		}
		if($oDB->isTableExists('elkhatalk_public_grants') && !$oDB->isIndexExists('elkhatalk_public_grants', 'member_srl_grants_int'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);

			$query = sprintf("ALTER TABLE `%selkhatalk_public_grants` ADD INDEX `member_srl_grants_int` (`member_srl`, `grants_int`);", $prefix);
			$oDB->query($query);

			FileHandler::removeFile($this->cache_path.'_update.txt');
		}
		// hide 컬럼을 개선하기.
		/*$query = sprintf("SELECT `COLUMN_TYPE` FROM INFORMATION_SCHEMA.COLUMNS WHERE `TABLE_NAME` = '%selkhatalk_members' AND `COLUMN_NAME` = 'hide';", $prefix);
		$pdo = $oDB->getHandle();
		$stmt = $pdo->query($query);
		$row = $oDB->fetch($stmt);
		if(is_object($row) && isset($row->COLUMN_TYPE) && stripos($row->COLUMN_TYPE, 'enum') === 0)
		{
			$query = sprintf("UPDATE `%selkhatalk_members` SET `hide` = CASE     WHEN `hide` = 'Y' THEN 1    WHEN `hide` = 'N' THEN 0 END;", $prefix);
			$pdo->query($query);
			$query = sprintf("ALTER TABLE `%selkhatalk_members` CHANGE `hide` `hide` TINYINT UNSIGNED NOT NULL DEFAULT '0' COMMENT '0보이기 1숨기기 2친구';", $prefix);
			$pdo->query($query);
		}*/
		if($oDB->isTableExists('member_friend') && !$oDB->isColumnExists('member_friend', 'last_update_elkhatalk3'))
		{
			FileHandler::writeFile($this->cache_path.'_update.txt', $logged_info->user_id);
			$query = sprintf("ALTER TABLE `%smember_friend` ADD COLUMN `last_update_elkhatalk3` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), ADD INDEX ( `last_update_elkhatalk3` , `member_srl`, `target_srl` );", $prefix);
			$oDB->query($query);
			FileHandler::removeFile($this->cache_path.'_update.txt');
		}

		return $this->createObject(0, 'success_updated');//$this->registerTriggers();
	}
}
