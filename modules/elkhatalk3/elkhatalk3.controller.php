<?php

/**
 * elkhatalk3
 *
 * Copyright (c) 엘카
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Elkhatalk3Controller extends Elkhatalk3
{
	public function init()
	{
		$this->checkUpdateCache();
		if(!$this->toBool())
		{
			return $this;
		}

		$_member_srl = (INT)Context::get('_member_srl');
		$logged_info = Context::get('logged_info');
		if(is_object($logged_info) && $logged_info->member_srl && $logged_info->member_srl!=$_member_srl)
		{
			$member_srl = (INT)Context::get('member_srl');
			if($logged_info->member_srl!=$member_srl)
			{
				return $this->stop('elkhatalk3_invalid_logged_info');
			}
		}
		return $this;
	}

	var $q_regex = '/^\/(귓|q|ㅂ|밴|b|ban|강퇴)[\s\t\v\r\n]+([^\s\t\v\r\n]+)(?:$|[\s\t\v\r\n]+([\s\S]*))/ui'; // |호출|call|무시|ignore|블라인드|얼리기|녹이기|freeze|f|w|밴|b|ban|강퇴|
	public function procElkhatalk3Say2()
	{
		$logged_info = Context::get('logged_info');

		$room = (INT)preg_replace('/[^0-9]+/', '', Context::get('room'));
		if(!$room)
		{
			return $this->stop('Error: ' . __LINE__);
		}

		$msg = trim(Context::get('content'));
		if(!strlen($msg))
		{
			return $this->stop('Error: ' . __LINE__);
		}
		if(mb_strlen($msg, 'UTF-8') > 250)
		{
			return $this->stop('elkhatalk3_too_long_message');
		}

		// 설정에서 로그인 체크하기 (config 에서 지정함)
		$args = new stdClass();
		$args->rooms = $rooms = array( $room );
		$args->member_srls = array(0);
		if(is_object($logged_info) && $logged_info->member_srl)
		{
			$args->member_srls[] = $logged_info->member_srl;
		}
		$grants = $this->get_public_grants($args);
		// 얼림 || 읽기x || 채팅x || 가입
		if($grants->_F[$room] || $grants->_R[$room] || $grants->_W[$room] || $grants->_C[$room] || $grants->_G[$room])
		{
			// 비회원은 아무고토 할 수 없다
			if(!is_object($logged_info) || !$logged_info->member_srl)
			{
				return $this->stop('msg_not_logged');
			}
			// 얼림 상태이면 관리권한 요구.
			if($grants->_F[$room] && $logged_info->is_admin!='Y' && !in_array((INT)$logged_info->member_srl, $grants->M))
			{
				return $this->stop('msg_not_permitted');
			}

			// 방 권한이 그룹 or 회원
			if($grants->_G[$room] || $grants->_C[$room])
			{
				// 승인된 그룹만 가능.
				$cond_g = in_array((INT)$logged_info->member_srl, $grants->M) || ($grants->_G[$room] && count(array_intersect(array_keys($logged_info->group_list), $grants->G)));
				// 승인된 회원만 가능.
				$cond_m = in_array((INT)$logged_info->member_srl, $grants->M) || ($grants->_C[$room] && in_array((INT)$logged_info->member_srl, $grants->C));

				if(!$cond_g && !$cond_m)
				{
					return $this->stop('msg_not_permitted');
				}
			}
		}

		if(in_array((INT)$logged_info->member_srl, $grants->B) && !in_array((INT)$logged_info->member_srl, $grants->M))
		{
			return $this->stop('msg_not_permitted');
		}

		// 허용된 채팅방 번호인지 체크하기. (config 에서 지정함)

		// 명령어 체크: 호출, 귓말, 얼리기, 밴

		$auth_info = $this->auth_info();

		if(preg_match($this->q_regex, $msg, $matches))
		{
			if(!is_object($logged_info) || !$logged_info->member_srl)
			{
				return $this->createObject(-1, 'msg_not_logged');
			}
			if(preg_match('/^(귓|q|ㅂ)$/ui', $matches[1]))
			{
				if(!strlen(trim($matches[3])))
				{
					return $this->createObject(-1, 'msg_empty_content');
				}
				$oMemberModel = getModel('member');
				$target_srl = (INT)$oMemberModel->getMemberSrlByNickName($matches[2]);
				if(!$target_srl)
				{
					return $this->createObject(-1, 'msg_not_exists_member');
				}

				// 1:1 쪽지 보내기.
				Context::set('target_srl', $target_srl);
				Context::set('message', $matches[3]);
				$output = $this->procElkhatalk3Say(FALSE);
				if(!$output->toBool())
				{
					return $output;
				}

				unset($matches[0], $matches[3]);
				$this->add('iText', '/' . $matches[1] . ' ' . $matches[2] . ' ');
			}
			if(preg_match('/^(밴|b|ban|강퇴)$/ui', $matches[1]))
			{
				$oMemberModel = getModel('member');
				$member_srl = (INT)$oMemberModel->getMemberSrlByNickName($matches[2]);
				if($member_srl)
				{
					$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
					if($member_info->is_admin=='Y')
					{
						return $this->createObject(-1, 'msg_not_permitted');
					}
					$args = new stdClass();
					$args->member_srl = $member_srl;
					$args->rooms = $rooms;
					$grants = $this->get_public_grants($args);

					$args = new stdClass();
					$args->member_srl = $member_srl;

					$expdate_ban = (INT)$matches[3];
					if($expdate_ban > 1440 * 365)
					{
						$expdate_ban = 1440 * 365;
					}
					if($expdate_ban)
					{
						$args->expdate = date('Y-m-d H:i:s', time() + $expdate_ban * 60);
						$args->grants = 'B';
					}
					else
					{
						$args->grants = '@';//$args->expdate = NULL;
					}
					if($grants->M)
					{
						return $this->createObject(-1, 'msg_not_permitted');
					}

					// grants -> grants_int
					if(isset($args->grants) && in_array($args->grants, static::$public_grants_int))
					{
						$args->grants_int = array_search($args->grants, static::$public_grants_int);
						unset($args->grants);
					}

					if(@isset($grants->pk[$member_srl][0][0]))
					{
						$args->pk = (INT)$grants->pk[$member_srl][0][0];
						$output = executeQuery('elkhatalk3.updatePublicGrants', $args);
					}
					else
					{
						$args->room = $rooms[0];
						$output = executeQuery('elkhatalk3.insertPublicGrants', $args);
					}
					Context::set('expdate_ban', $expdate_ban);
					Context::set('use_socket', 'Y');
					$this->notify_ban($member_srl, $matches[2], $rooms);
					return $output;
				}
				else
				{
					return $this->createObject(-1, 'msg_not_exists_member');
				}
			}
			return $this->createObject();
		}

		$args = new stdClass();
		$args->room = $room;
		$args->msg = $msg;
		if(is_object($logged_info) && $logged_info->member_srl)
		{
			$args->member_srl = $logged_info->member_srl;
			$args->nick_name = $logged_info->nick_name;
		}
		else
		{
			$args->nick_name = $auth_info->nick_name;
		}
		$regdate = date('Y-m-d H:i:s');
		$time = time();

		$plugins = $this->getPlugin('say2#before');
		foreach($plugins as $key => $val)
		{
			$output = $plugins[$key]($args);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				if($output->getError() != -1)
				{
					return $this->createObject(0, $output->getMessage());
				}
				else
				{
					return $output;
				}
			}
		}

		if(isset($grants->chat_log[$room]) && $grants->chat_log[$room]=='Y')
		{
			$output = executeQuery('elkhatalk3.insertPublicChat', $args);
			if(!$output->toBool())
			{
				return $output;
			}
		}

		$oDB = DB::getInstance();
		$insert_id = (INT)$oDB->getInsertId();

		/*$filename = "public$room/" . ($insert_id % 50) . '.json';

		FileHandler::writeFile($this->cache_path.$filename, json_encode($filename));*/

		$params = array(
			'pk' => $insert_id
			,'regdate' => $regdate
			,'time' => $time
			,'room' => "public$room"
			,'_msg' => $args->msg
			,'auth_info' => json_encode($auth_info)
			,'images' => array()
			,'alts' => array()
		);
		//$params['alts'][] = '치환할 문자 (=alt)';
		//$params['images'][] = '이미지주소';

		$plugins = $this->getPlugin('say2#after');
		foreach($plugins as $key => $val)
		{
			$output = $plugins[$key]($params);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}

		if(Context::get('use_socket')=='Y')
		{
			$this->sendSocket('sendMessage2', $params);
		}
	}
	public function procElkhatalk3Say($get_talk_list = TRUE, $target_srl = 0, $message = '', $me_member_srl = 0)
	{
		$logged_info = Context::get('logged_info');
		if($target_srl)
		{
			if($me_member_srl)
			{
				$member_srl = $me_member_srl;
			}
			else if(is_object($logged_info) && $logged_info->member_srl)
			{
				$member_srl = $logged_info->member_srl;
			}
		}
		else
		{
			$target_srl = (INT)Context::get('target_srl');
			$member_srl = $me_member_srl? $me_member_srl : (INT)Context::get('member_srl');
		}
		$oMemberModel = getModel('member');
		$_logged_info = $me_member_srl? $oMemberModel->getMemberInfoByMemberSrl($me_member_srl) : $logged_info;
		if(!$member_srl || !is_object($_logged_info) || !$_logged_info->member_srl || ($member_srl!=$_logged_info->member_srl && !$me_member_srl))
		{
			return $this->stop('msg_not_logged');
		}

		$member_info = $oMemberModel->getMemberInfoByMemberSrl($target_srl);
		if(!is_object($member_info) || $member_info->member_srl!=$target_srl)
		{
			return $this->stop('msg_not_exists_member');
		}

		if($message=='')
		{
			$message = trim(Context::get('message'));
		}
		if(!strlen($message))
		{
			return $this->stop('Error: ' . __LINE__);
		}
		if(mb_strlen($message, 'UTF-8') > 250)
		{
			return $this->stop('elkhatalk3_too_long_message');
		}

		$output = ModuleHandler::triggerCall('elkhatalk3.procElkhatalk3Say', 'before', $_logged_info);
		if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
		{
			return $output;
		}

		$plugins = $this->getPlugin('say@before');
		foreach($plugins as $key => $val)
		{
			$output = $plugins[$key]($_logged_info);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}

		$oModel = getModel('elkhatalk3');
		$room_info = $this->getRoom($target_srl, $member_srl, TRUE);
		if($room_info->room_no <= 0) // 아마 발생하지 않을 코드라 생각됨
		{
			return $this->stop('Error: ' . __LINE__);
		}

		$is_self = $target_srl==$member_srl;

		// 최근 메세지 목록 가져오기.
		if($get_talk_list)
		{
			$talk_list = $oModel->get_talk($room_info->room_no, $_logged_info);
		}
		else
		{
			$talk_list = array();
		}

		$read_no_max = 0; // 메세지 최대 값.
		foreach($talk_list as $val)
		{
			// 상대방의 메세지를 읽음 처리 하기.
			if($val->read && $val->member_srl!=$member_srl && $read_no_max < $val->no)
			{
				$read_no_max = $val->no;
			}
		}
		if(!$is_self)
		{
			$content_read = array();
			$message_list = $this->readMessage($member_srl, array($target_srl), $room_info->room_no, $read_no_max);
			foreach($message_list as $val)
			{
				$content_read[] = $val->no;
			}
		}

		// $output 임의로 에러 발생시켜서 테스트해보기
		$output = $this->insertChat($member_srl, $target_srl, $room_info->room_no, $message, $is_self);

		$profile_image_list = array();
		$profile_image_list[$member_srl] = $oModel->profile_image($_logged_info);
		$profile_image_list[$member_info->member_srl] = $oModel->profile_image($member_info);

		Context::set('profile_image_list', $profile_image_list);
		Context::set('room_no', $room_info->room_no);

		$config = $this->getConfig();
		if($output->toBool() && isset($this->insert_id) && $this->insert_id > 0)
		{
			$talk_info = new stdClass();
			$talk_info->no = $content_no = (INT)$this->insert_id;
			$talk_info->room_no = $room_info->room_no;
			$talk_info->member_srl = $member_srl;
			$talk_info->content = nl2br(htmlspecialchars($message));
			$talk_info->regdate = $regdate = isset($this->regdate)? $this->regdate : date('ymdHi');
			$talk_info->nick_name = $_logged_info->nick_name;

			if($is_self)
			{
				$room_info->status = 0;
				$talk_info->read = 0;
			}
			else
			{
				$room_info->status++;
				$talk_info->read = 1;
			}

			$talk_list[] = $talk_info;

			// 주의: talk@before 플러그인은 부득이 여러번 호출 됨. 따라서 플러그인에서 1회만 실행하게 처리 필요.
			$plugins = $this->getPlugin('talk@before');
			foreach($plugins as $key => $val)
			{
				$_output = $plugins[$key]($talk_list);
				if(is_object($_output) && method_exists($_output, 'toBool') && !$_output->toBool())
				{
					return $_output;
				}
			}

			$desc = $this->langMessage ( htmlspecialchars(mb_substr($message, 0, 20, 'UTF-8')) );

			$params = array(
				'room_no' => (INT)$room_info->room_no
				,'room_status' => (INT)$room_info->status
				,'me_member_srl' => (INT)$member_srl
				,'you_member_srl' => "$target_srl,$member_srl"
				,'target_srl' => (INT)$target_srl
				,'content_no' => $content_no
				,'content_desc' => $desc
				,'regdate' => $regdate
				,'talk_member_srl' => (INT)$member_srl
				,'hear_member_srl' => (INT)$target_srl
				,'talk_nick_name' => $_logged_info->nick_name
				,'hear_nick_name' => $member_info->nick_name
				,'talk_level' => $this->level_icon($member_srl)
				,'hear_level' => $this->level_icon($member_info->member_srl)
				,'content_for_me' => $talk_info->content
			);
		}
		else
		{
			return $output;
		}

		Context::set('talk_list', $talk_list);

		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->target_srl = $target_srl;
		$args->last_update = date('Y-m-d H:i:s');
		$output2 = executeQuery('elkhatalk3.updateMemberFriend', $args);

		if($get_talk_list)
		{
			$oTemplate = TemplateHandler::getInstance();
			$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-talk-message');
			$tpl = preg_replace('/>[\s\t\v\r\n]+</', ">\n<", $tpl);
			$this->add('tpl_talk_list', $tpl);
		}

		if(!$is_self)
		{
			$params['content_read'] = $content_read;
		}

		foreach($params as $key => $val)
		{
			$this->add($key, $val);
		}

		$cache_key = "$member_srl,$target_srl";
		$this->deleteCache($cache_key);
		$cache_key = "$target_srl,$member_srl";
		$this->deleteCache($cache_key);

		if(Context::get('use_socket')!='N' && $output->toBool())
		{
			$params['secret_idx'] = $config->secret_idx;
			$_output = $this->sendSocket('sendMessage', $params);
		}

		$talk_info->you_member_srl = (INT)$target_srl;

		$output = ModuleHandler::triggerCall('elkhatalk3.procElkhatalk3Say', 'after', $talk_info);
		if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
		{
			return $output;
		}

		$plugins = $this->getPlugin('say@after');
		foreach($plugins as $key => $val)
		{
			$_output = $plugins[$key]($talk_info);
			if(is_object($_output) && method_exists($_output, 'toBool') && !$_output->toBool())
			{
				return $_output;
			}
		}

		return $output;
	}

	/*public function isFriend(INT $member_srl, INT $target_srl) : bool
	{
		static $target_srl_list = array();
		if(!isset($target_srl_list[$target_srl]))
		{
			$target_srl_list[$target_srl] = array();
			$args = new stdClass();
			$args->member_srl = $member_srl;
			$data = executeQueryArray('elkhatalk3.getFriendList', $args, array('target_srl'))->data;
			foreach($data as $val)
			{
				$target_srl_list[$target_srl][] = (INT)$val->target_srl;
			}
		}
		return in_array($target_srl, $target_srl_list[$target_srl]);
	}*/
	public function insertChat(INT $member_srl_me, INT $member_srl_you, INT $room_no, STRING $content, $is_self)
	{
		$args = new stdClass();
		$args->room_no = $room_no;
		$args->content = $content;
		$args->member_srl = $member_srl_me;
		$args->regdate = $this->regdate = (INT)date('ymdHi');
		$args->read = $is_self? 0 : 1;
		$output = executeQuery('elkhatalk3.insertChat', $args);

		if($output->toBool())
		{
			$oDB = DB::getInstance();
			if(method_exists($oDB, 'db_insert_id'))
			{
				$this->insert_id = $oDB->db_insert_id();
			}
			else
			{
				$this->insert_id = $oDB->getInsertID();
			}

			$args = new stdClass();
			$args->member_srl = $member_srl_you;
			$args->room_no = $room_no;
			$args->last_update = date('Y-m-d H:i:s');
			$args->hide = 'N'; // $this->isFriend($member_srl_you, $member_srl_me) ? 2 : 0;
			if($is_self)
			{
				$args->status = 0;
			}
			$output = executeQuery('elkhatalk3.updateMembers', $args);
			if(!$is_self && $output->toBool())
			{
				$args = new stdClass();
				$args->member_srl = $member_srl_me;
				$args->room_no = $room_no;
				$args->last_update = date('Y-m-d H:i:s');
				$args->status = 0;
				$args->hide = 'N'; // $this->isFriend($member_srl_me, $member_srl_you) ? 2 : 0;
				$output = executeQuery('elkhatalk3.updateMembers', $args);
			}
		}
		return $output;
	}
	public function procElkhatalk3Read()
	{
		$logged_info = Context::get('logged_info');
		$target_srl = (INT)Context::get('target_srl');
		$no_max = (INT)Context::get('no_max');

		$member_srl = (INT)Context::get('member_srl');
		if(!$member_srl || !is_object($logged_info) || !$logged_info->member_srl || $member_srl!=$logged_info->member_srl)
		{
			return $this->stop('msg_not_logged');
		}

		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($target_srl);
		$nick_name = is_object($member_info) && $member_info->member_srl? $member_info->nick_name : Context::getLang('elkhatalk3_leave_member');
		$config = $this->getConfig();

		$room_info = $this->getRoom($logged_info->member_srl, $target_srl);
		if($room_info->room_no > 0)
		{
			$content_read = array();
			$message_list = $this->readMessage($logged_info->member_srl, array($target_srl), $room_info->room_no, $no_max);
			foreach($message_list as $val)
			{
				$val->read = 0;
				$val->member_srl = $target_srl;
				$val->room_no = $room_info->room_no;
				$val->nick_name = $nick_name;
				$val->level = $config->cdn_path . $this->level_icon($target_srl);
				$content_read[] = $val->no;
			}
		}
		else
		{
			return $this->stop('Error: ' . __LINE__);
		}

		$params = array(
			'room_no' => (INT)$room_info->room_no
			,'room_status' => 0
			,'me_member_srl' => (INT)$logged_info->member_srl
			,'you_member_srl' => "$target_srl,$logged_info->member_srl"
			,'target_srl' => (INT)$target_srl
			,'content_read' => $content_read
			,'talk_member_srl' => (INT)$target_srl
			,'hear_member_srl' => (INT)$logged_info->member_srl
		);

		foreach($params as $key => $val)
		{
			$this->add($key, $val);
		}

		$cache_key = "$logged_info->member_srl,$target_srl";
		$this->deleteCache($cache_key);
		$cache_key = "$target_srl,$logged_info->member_srl";
		$this->deleteCache($cache_key);

		unset($_COOKIE["elkhatalk3_room_status_$target_srl"]);

		// 읽음 처리 후 소켓
		if(Context::get('use_socket')=='Y')
		{
			$params['secret_idx'] = $config->secret_idx;
			$_output = $this->sendSocket('sendMessage', $params);
		}
		else
		{
			$this->add('message_list', $message_list);
		}
	}

	public function procElkhatalk3Manage()
	{
		$oMemberModel = getModel('member');
		$args = new stdClass();
		$args->rooms = $rooms = array( $room = (INT)preg_replace('/[^0-9]+/', '', Context::get('room')) );
		$grants = $this->get_public_grants($args);
		$logged_info = Context::get('logged_info');
		$is_admin = 'N';
		$config = $this->getConfig();
		if(is_object($logged_info) && $logged_info->member_srl)
		{
			if($logged_info->is_admin=='Y')
			{
				$is_admin = 'Y';
			}
			else
			{
				// 부관리자 체크.
				if(in_array((INT)$logged_info->member_srl, $grants->M))
				{
					$is_admin = 'M';
				}
			}
		}
		if($is_admin=='N')
		{
			return $this->stop('msg_not_permitted');
		}
		if(Context::get('room_clear')=='Y')
		{
			$auth_info = new stdClass();
			$auth_info->member_srl = 0;
			$auth_info->nick_name = Context::getLang('elkhatalk3_notify_bot');
			$auth_info->level = '';
			$params = array(
				'time' => time()
				,'room' => 'public' . $rooms[0]
				,'_msg' => Context::getLang('elkhatalk3_clean')
				,'auth_info' => json_encode($auth_info)
				,'log_clear' => 'Y'
				,'images' => array()
				,'alts' => array()
			);

			if(Context::get('use_socket')=='Y')
			{
				$output2 = $this->sendSocket('sendMessage2', $params);
			}
			return $this->createObject();
		}
		$member_srl_delete = (INT)Context::get('member_srl_delete');
		if($member_srl_delete)
		{
			$args->member_srls = [0, $member_srl_delete];
			$grants = $this->get_public_grants($args);
			// 최고관리자만 부관리자를 해제 가능.
			if($is_admin!='Y' && in_array($member_srl_delete, $grants->M))
			{
				return $this->stop('msg_not_permitted');
			}
			// 해당 권한을 삭제함.
			if(!isset($grants->pk[$member_srl_delete]))
			{
				return $this->stop('Error: ' . __LINE__);
			}
			// 닉네임 항목: C, B, M
			$grants_int = 0;
			$grants_remove = Context::get('grants_remove');
			if($grants_remove=='M')
			{
			}
			else if($grants_remove=='B')
			{
				$args->expdate = NULL;
				if(in_array($member_srl_delete, $grants->C))
				{
					$grants_int = array_search('C', static::$public_grants_int);
				}
			}
			else if($grants_remove=='C')
			{
				if(in_array($member_srl_delete, $grants->B))
				{
					$grants_int = array_search('B', static::$public_grants_int);
				}
			}
			else
			{
				return $this->stop('msg_not_permitted');
			}
			$args = new stdClass();
			$args->pk = $grants->pk[$member_srl_delete][0][0];
			$args->grants_int = $grants_int;
			$output = executeQuery('elkhatalk3.updatePublicGrants', $args);
			if($output->toBool() && isset($grants->B) && isset($grants->B[$args->pk]) && $grants_remove=='B')
			{
				$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl_delete);
				if(is_object($member_info) && $member_info->member_srl==$member_srl_delete)
				{
					$output2 = $this->notify_ban($member_info->member_srl, $member_info->nick_name, $rooms);
				}
			}
			else if(!$output->toBool())
			{
				return $this->stop($output->getMessage());
			}
			return $output;
		}
		$write = Context::get('write')?? 'N';
		$read = Context::get('read')?? 'N';
		$cafe = Context::get('cafe')?? 'N';
		$freeze = (INT)Context::get('freeze');
		if($freeze > 1440 * 7) // 최대 7일 까지만.
		{
			return $this->stop('msg_not_permitted');
		}
		$args_list = array();

		$args = new stdClass();
		$args->rooms = $rooms;
		$args->member_srls = array(0);
		$grants = $this->get_public_grants($args);
		$grant = array();
		if(($grants->_F[$room] && !$freeze) || $freeze > 0) $grant[] = 'F';
		if(($grants->_C[$room] && $cafe!='N') || $cafe=='Y')
		{
			$grant[] = 'C';
		}
		if($is_admin=='Y')
		{
			if(count($group_srls = (array)Context::get('group_srls')))
			{
				$grant[] = 'G';
				foreach($group_srls as $group_srl)
				{
					$key = $rooms[0] . '_' . $group_srl;
					if(!isset($args_list[$key]))
					{
						$args_list[$key] = new stdClass();
						$args_list[$key]->room = $rooms[0];
						$args_list[$key]->member_srl = $group_srl;
						$args_list[$key]->grants = [];
					}
					$args_list[$key]->grants[] = 'G';
				}
			}
			foreach($grants->G as $group_srl)
			{
				if(in_array($group_srl, $group_srls))
				{
					continue;
				}

				$key = $rooms[0] . '_' . $group_srl;
				if(!isset($args_list[$key]))
				{
					$args_list[$key] = new stdClass();
					$args_list[$key]->room = $rooms[0];
					$args_list[$key]->member_srl = $group_srl;
					$args_list[$key]->grants = [];
				}
			}
			if(in_array('G', $grant) && Context::get('group_srl_A')=='Y')
			{
				$grant[] = 'A';
			}
		}
		// group_srl 권한은 is_admin 아니면 건드릴 일이 없다.
		else if(count($group_srls = (array)Context::get('group_srls')))
		{
			foreach($group_srls as $group_srl)
			{
				if(in_array($group_srl, $grants->G))
				{
					$grant[] = 'G';
					break;
				}
			}
		}
		/*if($is_admin!='Y' && in_array('G', $grant))
		{
		}*/
		if(!in_array('C', $grant) && !in_array('G', $grant))
		{
			if(($grants->_R[$room] && $read!='Y') || $read=='N') $grant[] = 'R';
			if(($grants->_W[$room] && $write!='Y') || $write=='N') $grant[] = 'W';
		}
		if(Context::get('chat_log')=='Y' && $is_admin=='Y' && !in_array('R', $grant))
		{
			$grant[] = 'L';
		}
		sort($grant);
		$grant_i = implode('', $grant);

		$message_list = array();
		if(strlen(Context::get('freeze')))
		{
			$exp = $freeze >= 1440? date('m-d H:i:s', time() + $freeze*60) : date('H:i:s', time() + $freeze*60);
			if($freeze) $message_list[] = sprintf(Context::getLang('elkhatalk3_grant_F'), $exp);
			else $message_list[] = Context::getLang('elkhatalk3_grant_F2');
		}
		if(preg_match('/[CG]/', $grant_i))
		{
			$arr = [];
			$arr2 = [];
			$arr[] = !$grants->_C[$room]? 1 : 2;
			$arr[] = !$grants->_G[$room]? 3 : 4;
			$arr2[] = strpos($grant_i, 'C')===FALSE? 1 : 2;
			$arr2[] = strpos($grant_i, 'G')===FALSE? 3 : 4;
			if(count(array_intersect($arr, $arr2)) != 2)
			{
				if(strpos($grant_i, 'C')!==FALSE)
				{
					$message_list[] = Context::getLang('elkhatalk3_grant_C');
				}
				if(strpos($grant_i, 'G')!==FALSE)
				{
					$message_list[] = Context::getLang('elkhatalk3_grant_G');
				}
			}
		}
		else
		{
			$cond1 = strpos($grant_i, 'R')!==FALSE && (!$grants->_R[$room] || $grants->_C[$room] || $grants->_G[$room]);
			$cond2 = strpos($grant_i, 'W')!==FALSE && (!$grants->_W[$room] || $grants->_C[$room] || $grants->_G[$room]);
			if($cond1 && $cond2)
			{
				$message_list[] = Context::getLang('elkhatalk3_grant_C2');
			}
			else
			{
				if($cond1) $message_list[] = Context::getLang('elkhatalk3_grant_R');
				if($cond2) $message_list[] = Context::getLang('elkhatalk3_grant_W');
			}

			if($grants->_R[$room] && strpos($grant_i, 'R')===FALSE) $message_list[] = Context::getLang('elkhatalk3_grant_R2');
			if($grants->_W[$room] && strpos($grant_i, 'W')===FALSE) $message_list[] = Context::getLang('elkhatalk3_grant_W2');
		}

		if($grants->_F[$room] && !preg_match('/[F]/', $grant_i)) $message_list[] = Context::getLang('elkhatalk3_grant_F2');

		if(strlen($grant_i) && !in_array($grant_i, static::$public_grants_int))
		{
			return $this->stop('msg_feature_disabled');
		}

		$args = new stdClass();
		$args->room = $rooms[0];
		$args->grants_int = strlen($grant_i)? array_search($grant_i, static::$public_grants_int) : 0;  // 기본 값: @ (아무 상태 없음)
		$args->member_srl = 0;
		if(isset($grants->pk[0]))
		{
			$args->pk = $grants->pk[0][0][0]; // [member_srl][:first][pk]
		}

		$args_list[] = $args;

		if(strlen($nick_name = Context::get('nick_name_cafe')))
		{
			if($member_srl = (INT)$oMemberModel->getMemberSrlByNickName($nick_name))
			{
				$key = $rooms[0] . '_' . $member_srl;
				if(!isset($args_list[$key]))
				{
					$args_list[$key] = new stdClass();
					$args_list[$key]->room = $rooms[0];
					$args_list[$key]->member_srl = $member_srl;
					$args_list[$key]->grants = [];
				}

				$args_list[$key]->grants[] = 'C';
			}
		}

		if(strlen($nick_name = Context::get('nick_name_ban')))
		{
			if($member_srl = (INT)$oMemberModel->getMemberSrlByNickName($nick_name))
			{
				$key = $rooms[0] . '_' . $member_srl;
				if(!isset($args_list[$key]))
				{
					$args_list[$key] = new stdClass();
					$args_list[$key]->room = $rooms[0];
					$args_list[$key]->member_srl = $member_srl;
					$args_list[$key]->grants = [];
				}

				if($expdate_ban = (INT)Context::get('expdate_ban'))
				{
					if($expdate_ban > 1440 * 365)
					{
						$expdate_ban = 1440 * 365;
					}
					$args_list[$key]->expdate = date('Y-m-d H:i:s', time() + $expdate_ban * 60);
					$args_list[$key]->grants[] = 'B';
				}
				else
				{
					$args_list[$key]->expdate = date('Y-m-d H:i:s');
				}
			}
		}

		if($is_admin=='Y' && strlen($nick_name = Context::get('nick_name_manager')))
		{
			if($member_srl = (INT)$oMemberModel->getMemberSrlByNickName($nick_name))
			{
				$key = $rooms[0] . '_' . $member_srl;
				if(!isset($args_list[$key]))
				{
					$args_list[$key] = new stdClass();
					$args_list[$key]->room = $rooms[0];
					$args_list[$key]->member_srl = $member_srl;
					$args_list[$key]->grants = [];
				}

				$args_list[$key]->grants = ['M'];
			}
		}

		foreach($args_list as $args)
		{
			if(isset($args->grants))
			{
				if(count($args->grants))
				{
					sort($args->grants);
					if(!in_array(implode('', $args->grants), static::$public_grants_int))
					{
						return $this->stop('Error: ' . __LINE__);
					}
					$args->grants_int = array_search(implode('', $args->grants), static::$public_grants_int);
				}
				else
				{
					$args->grants_int = 0;
				}
			}
		}

		foreach($args_list as $args)
		{
			$is_update = FALSE;
			if(isset($args->grants_int) && $args->grants_int >= 0)
			{
				if(strpos(static::$public_grants_int[$args->grants_int], 'F')!==FALSE && strlen(Context::get('freeze')))
				{
					$args->expdate = date('Y-m-d H:i:s', time() + $freeze * 60);
				}
			}
			if(isset($args->pk))
			{
				$is_update = TRUE;
			}
			else
			{
				$args2 = new stdClass();
				$args2->rooms = $rooms;
				$args2->member_srls = [$args->member_srl];
				$grants = $this->get_public_grants($args2);
				if(isset($grants->pk[$args->member_srl]))
				{
					$args->pk = $grants->pk[$args->member_srl][0][0];
					$is_update = TRUE;
					// 부관리자M 권한은 다른 것 부여 못하게.
					if(in_array($args->member_srl, $grants->M))
					{
						continue;
					}

					// B|밴,C|멤버 권한은 유지하게
					$grant_i = static::$public_grants_int[$args->grants_int];
					$grant_i_old = static::$public_grants_int[ $grants->pk[$args->member_srl][0][3] ];
					if(preg_match('/[BC]/', $grant_i_old) && $grant_i!=$grant_i_old)
					{
						$grant_i_arr = str_split($grant_i);
						if(strpos($grant_i, 'B')===FALSE)
						{
							$args->notify_ban = FALSE;
							$grant_i_arr[] = 'B';
						}
						if(strpos($grant_i, 'C')===FALSE)
						{
							$grant_i_arr[] = 'C';
						}
						sort($grant_i_arr);
						if(implode('', $grant_i_arr)!=$grant_i && in_array(implode('', $grant_i_arr), static::$public_grants_int))
						{
							$args->grants_int = array_search(implode('', $grant_i_arr), static::$public_grants_int);
						}
					}
				}
			}

			if(!isset($args->grants_int) || $args->grants_int < 0)
			{
				return $this->stop('Error: ' . __LINE__);
			}

			if($is_update)
			{
				$output = executeQuery('elkhatalk3.updatePublicGrants', $args);
			}
			else
			{
				$output = executeQuery('elkhatalk3.insertPublicGrants', $args);
			}

			if($output->toBool())
			{
				$filename = 'files/cache/elkhatalk3/00.txt';
				FileHandler::removeFile($filename);
				$oCacheHandler = CacheHandler::getInstance();
				if($oCacheHandler->isSupport())
				{
					$cache_key = "object:$filename";
					$oCacheHandler->delete($cache_key);
				}
				if(isset($args->member_srl) && $args->member_srl===0 && count($message_list))
				{
					$auth_info = new stdClass();
					$auth_info->member_srl = 0;
					$auth_info->nick_name = Context::getLang('elkhatalk3_notify_bot');
					$auth_info->level = '';
					$params = array(
						'time' => time()
						,'room' => 'public' . $rooms[0]
						,'_msg' => Context::getLang('elkhatalk3_permit_changed') . implode(', ', $message_list)
						,'auth_info' => json_encode($auth_info)
					);

					if(Context::get('use_socket')=='Y')
					{
						$output2 = $this->sendSocket('sendMessage2', $params);
					}
				}
				else if(isset($args->member_srl) && $args->member_srl)
				{
					$cond = FALSE;
					if(strpos(static::$public_grants_int[$args->grants_int], 'B')!==FALSE)
					{
						$cond = TRUE;
					}
					else if(in_array($args->member_srl, $grants->B))
					{
						$cond = TRUE;
						Context::set('expdate_ban', 0);
					}
					if($cond && is_object($member_info = $oMemberModel->getMemberInfoByMemberSrl($args->member_srl)) && $member_info->member_srl == $args->member_srl)
					{
						if(!isset($args->notify_ban) || $args->notify_ban)
						{
							$this->notify_ban($args->member_srl, $member_info->nick_name, $rooms);
						}
					}
				}

				$notice_changed = FALSE;
				$oModel = getModel('elkhatalk3');
				$obj = $oModel->getElkhatalk3Notice('public' . $rooms[0]);

				$notice_list = array();
				foreach($obj->notice_list as $key => $val)
				{
					$_key = (INT)Context::get("notice_list_$key");
					/*if($key+1 != $_key)
					{
						$notice_changed = TRUE;
					}*/
					$_key *= 1000;
					while(isset($notice_list[$_key]))
					{
						$_key++;
					}
					$notice_list[$_key] = $val;
				}
				ksort($notice_list);
				if(implode($obj->notice_list) != implode($notice_list))
				{
					$notice_changed = TRUE;
					$obj->notice_list = array_values($notice_list);
				}

				if(($notice_text = htmlspecialchars(Context::get('notice-text')))!='')
				{
					$notice_color = htmlspecialchars(Context::get('notice-color'));
					$notice_href = htmlspecialchars(Context::get('notice-href'));
					$notice_inline = Context::get('notice-inline')=='Y'? 'Y' : 'N';
					if(!preg_match('@https?://@', $notice_href))
					{
						$notice_href = '';
					}

					$obj->notice_list[] = array($notice_text, $notice_color, $notice_href, $notice_inline, time());
					$notice_changed = TRUE;
				}
				if($notice_changed)
				{
					FileHandler::writeFile($filename = 'files/elkhatalk3/public'.$rooms[0].'.json', json_encode($obj));

					$params = array(
						'func_name'=> 'ntime'
						,'room' => 'public'.$rooms[0]
						,'params' => filemtime($filename)
					);
					$_output = $this->sendSocket('public_notify', $params);
				}
			}
		}

		if(is_object($output) && method_exists($output, 'toBool'))
		{
			if($output->toBool())
			{
				$this->setRedirectUrl(Context::get('error_return_url'));
				return $output;
			}
			else
			{
				return $this->stop( $output->getMessage() );
			}
		}
		return $this;
	}
	public function notify_ban($member_srl, $nick_name, $rooms)
	{
		/*$member_info = $oMemberModel->getMemberInfoByMemberSrl($args->member_srl);
		$auth_info = new stdClass();
		$auth_info->member_srl = $args->member_srl;
		$auth_info->nick_name = is_object($member_info) && isset($member_info->nick_name)? $member_info->nick_name : '?';
		$auth_info->level = $config->cdn_path.$this->level_icon($args->member_srl);*/
		$auth_info = new stdClass();
		$auth_info->member_srl = 0;
		$auth_info->nick_name = Context::getLang('elkhatalk3_notify_bot');
		$auth_info->level = '';
		$expdate_ban = ((INT)Context::get('expdate_ban'))? date('Y-m-d H:i:s', time() + ((INT)Context::get('expdate_ban')) * 60) : NULL;
		$params = array(
			'time' => time()
			,'room' => 'public' . $rooms[0]
			,'_msg' => ($expdate_ban==NULL? Context::getLang('elkhatalk3_ban_message_cancel') : sprintf(Context::getLang('elkhatalk3_ban_message'), $expdate_ban) ) . strip_tags($nick_name)
			,'auth_info' => json_encode($auth_info)
		);

		$output2 = $this->sendSocket('sendMessage2', $params);
		if($output2->toBool())
		{
			unset($params['_msg']);
			unset($params['auth_info']);
			$params['target_srl'] = $member_srl;
			$params['term'] = ((INT)Context::get('expdate_ban')) * 60; // 초 단위로 보냄.
			$output = $this->sendSocket('ban', $params);
			return $output;
		}
		else
		{
			return $output2;
		}
	}
	public function procElkhatalk3ManageDeleteNotice()
	{
		$oMemberModel = getModel('member');
		$args = new stdClass();
		$args->rooms = $rooms = array( $room = (INT)preg_replace('/[^0-9]+/', '', Context::get('room')) );
		$grants = $this->get_public_grants($args);
		$logged_info = Context::get('logged_info');
		$is_admin = 'N';
		if(is_object($logged_info) && $logged_info->member_srl)
		{
			if($logged_info->is_admin=='Y')
			{
				$is_admin = 'Y';
			}
			else
			{
				// 부관리자 체크.
				if(in_array((INT)$logged_info->member_srl, $grants->M))
				{
					$is_admin = 'M';
				}
			}
		}
		if($is_admin=='N')
		{
			return $this->stop('msg_not_permitted');
		}

		// 위 코드는 복붙임. 삭제는 아래부터 시작.
		$oModel = getModel('elkhatalk3');
		$obj = $oModel->getElkhatalk3Notice('public' . $rooms[0]);
		unset($obj->notice_list[(INT)Context::get('key')]);
		$obj->notice_list = array_values($obj->notice_list); // reset keys
		if(!count($obj->notice_list))
		{
			unset($obj->notice_list);
		}
		$buff = json_encode($obj);
		if($buff=='{}')
		{
			FileHandler::removeFile('files/elkhatalk3/public'.$rooms[0].'.json');
			$ntime = 0;
		}
		else
		{
			FileHandler::writeFile($filename = 'files/elkhatalk3/public'.$rooms[0].'.json', json_encode($obj));
			$ntime = filemtime($filename);
		}
		$params = array(
			'func_name'=> 'ntime'
			,'room' => 'public'.$rooms[0]
			,'params' => $ntime
		);
		$output = $this->sendSocket('public_notify', $params);
	}
	public function triggerAfterSendMessage($obj)
	{
		if(isset($GLOBALS['__ELKHATALK2_NOTIFY__']) && !$GLOBALS['__ELKHATALK2_NOTIFY__'])
		{
			return $this->createObject();
		}
		$logged_info = Context::get('logged_info');
		if(!isset($obj->receiver_srl) || $obj->receiver_srl <= 0 || !is_object($logged_info) || !isset($logged_info->member_srl) || !$logged_info->member_srl || $obj->receiver_srl==$logged_info->member_srl)
		{
			return $this->createObject();
		}

		$target_srl = $obj->receiver_srl;
		$message = $obj->title . "\n" . strip_tags(preg_replace('@(<br[^>]*>)+@', "\n", $obj->content));
		$output = $this->procElkhatalk3Say(FALSE, $target_srl, $message);
	}
	public function triggerAfterInsertNotify($obj)
	{
		$params = array(
			'func_name'=> 'ncenter'
			,'member_srl' => $obj->member_srl
		);
		$output = $this->sendSocket('public_notify', $params);
	}
	public function procElkhatalk3Delete2()
	{
		$args = new stdClass();
		$args->pk = $pk = (INT)Context::get('no');
		$args->list_count = 1;
		$data = executeQuery('elkhatalk3.getPublicChat', $args)->data;
		if(!is_object($data) || !$data->pk)
		{
			return $this->stop('Error: ' . __LINE__);
		}

		$logged_info = Context::get('logged_info');
		if(!is_object($logged_info) || !$logged_info->member_srl)
		{
			return $this->stop('msg_not_logged');
		}

		// 일반 유저는 DB만 지우기.
		if($logged_info->is_admin=='Y' || ($data->member_srl && $data->member_srl==$logged_info->member_srl))
		{
			$args = new stdClass();
			$args->pk = $pk;
			$args->option = 'D';
			$output = executeQuery('elkhatalk3.updatePublicMessage', $args);
			if(!$output->toBool())
			{
				return $output;
			}
		}

		// 최고관리자는 소켓까지 삭제
		$time = (INT)Context::get('time');
		if($logged_info->is_admin=='Y' && $time)
		{
			$params = array(
				'pk' => $pk
				,'evt' => 'deleteMessage'
				,'time' => $time
				,'room' => "public$data->room"
				,'member_srl' => (INT)Context::get('member_srl')
			);

			$output = $this->sendSocket('public_notify', $params);
		}
	}

	// 서드파티 플러그인으로 모듈 조절.
	public function procElkhatalk3Custom()
	{
		$plugin = (STRING)Context::get('plugin');
		$plugins = $this->getPlugin('custom_proc');
		if(isset($plugins[$plugin]))
		{
			$output = $plugins[$plugin]($this);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}
	}

	public function procElkhatalk3MemberHide()
	{
		$target_srl = (INT)Context::get('target_srl');
		$logged_info = Context::get('logged_info');
		if(!is_object($logged_info) || !$logged_info->member_srl)
		{
			return $this->stop('msg_not_logged');
		}

		$room_info = $this->getRoom($logged_info->member_srl, $target_srl);
		if($room_info->status)
		{
			return $this->stop('elkhatalk3_new_message');
		}
		if($room_info->room_no < 0)
		{
			return $this->stop('elkhatalk3_member_not_exists');
		}

		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->member_srl2 = $target_srl;
		$args->hide = 'Y';

		$output = executeQuery('elkhatalk3.updateRoomMembers', $args);
		if(!$output->toBool())
		{
			return $output;
		}
		$cache_key = "$logged_info->member_srl,$target_srl";
		$this->deleteCache($cache_key);
	}

	public function triggerAfterLazypushConfig($args)
	{
		$push_type = Context::get('push_type');
		$push_type['et3'] = [
			'elkhatalk3_chat'
			, ['elkhatalk3.procElkhatalk3Say', 'after', 'controller', 'triggerAfterLazypush', 'elkhatalk3']
			, ['elkhatalk3.readMessage', 'after', 'controller', 'triggerAfterLazypushRead', 'elkhatalk3']
		];
		Context::set('push_type', $push_type);
	}
	public function triggerAfterLazypush($args)
	{
		$obj = new stdClass();
		$obj->member_srl = $args->you_member_srl;
		$obj->type = 'et3';
		$obj->title = Context::getLang('elkhatalk3_new_message');
		$obj->content = $args->nick_name;
		$obj->hash = hash_hmac('sha256', $obj->title . $obj->content, $obj->member_srl . ':' . config('crypto.authentication_key'));
		$oLazyPushController = getController('lazypush');
		$output = $oLazyPushController->queAdd($obj);
	}
	public function triggerAfterLazypushRead($args)
	{
		$target_srls = is_array($args->target_srls)? $args->target_srls : [$args->target_srls];
		$oMemberModel = getModel('member');
		foreach($target_srls as $target_srl)
		{
			if(is_object($member_info = $oMemberModel->getMemberInfoByMemberSrl($target_srl)) && $member_info->member_srl==$target_srl)
			{
				$obj = new stdClass();
				$obj->member_srl = $args->me_member_srl;
				$obj->type = 'et3';
				$obj->hash = hash_hmac('sha256', Context::getLang('elkhatalk3_new_message') . $member_info->nick_name, $args->me_member_srl . ':' . config('crypto.authentication_key'));
				$oLazyPushController = getController('lazypush');
				$output = $oLazyPushController->queDel($obj);
			}
		}
	}

	/**
	 * 트리거 예제: 새 글 작성시 실행
	 *
	 * 주의: 첨부파일이 있는 경우 아직 작성하지 않았어도 이 함수가 실행될 수 있음
	 *
	 * @param object $obj
	 */
	public function triggerAfterInsertDocument($obj)
	{

	}

	/**
	 * 트리거 예제: 글 수정시 실행
	 *
	 * 주의: 첨부파일이 있는 경우 최종 작성 시점에 이 함수가 실행될 수 있음
	 *
	 * @param object $obj
	 */
	public function triggerAfterUpdateDocument($obj)
	{

	}

	/**
	 * 트리거 예제: 글 삭제시 실행
	 *
	 * @param object $obj
	 */
	public function triggerAfterDeleteDocument($obj)
	{

	}

	/**
	 * 트리거 예제: 새 댓글 작성시 실행
	 *
	 * 주의: 첨부파일이 있는 경우 아직 작성하지 않았어도 이 함수가 실행될 수 있음
	 *
	 * @param object $obj
	 */
	public function triggerAfterInsertComment($obj)
	{

	}

	/**
	 * 트리거 예제: 댓글 수정시 실행
	 *
	 * 주의: 첨부파일이 있는 경우 최종 작성 시점에 이 함수가 실행될 수 있음
	 *
	 * @param object $obj
	 */
	public function triggerAfterUpdateComment($obj)
	{

	}

	/**
	 * 트리거 예제: 댓글 삭제시 실행
	 *
	 * @param object $obj
	 */
	public function triggerAfterDeleteComment($obj)
	{

	}
}
