<?php

/**
 * elkhatalk3
 *
 * Copyright (c) 엘카
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Elkhatalk3Model extends Elkhatalk3
{
	public function init() : \baseObject
	{
		static $init;
		if(!isset($init))
		{
			$this->checkUpdateCache();
			if(!$this->toBool())
			{
				return $this;
			}

			$logged_info = Context::get('logged_info');
			$member_srl = (INT)Context::get('member_srl');
			if(!$member_srl || !is_object($logged_info) || !$logged_info->member_srl || $member_srl!=$logged_info->member_srl)
			{
				Context::set('member_srl', 0);
				Context::set('target_srl', 0);
				$init = $this->createObject(-1, 'msg_not_logged');
			}
			else
			{
				$init = $this->createObject();
			}
		}

		return $init;
	}
	public function getElkhatalk3PublicMenu() : \baseObject
	{
		$mid = is_string(\Context::get('current_mid'))? \Context::get('current_mid') : '';
		if(defined('RX_VERSION'))
		{
			$request_uri = \Context::get('request_uri');
		}
		else if(isset($_SERVER['HTTP_HOST']))
		{
			$request_uri = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO']=='https') || $_SERVER['HTTPS'] == 'on'? 'https//':'http://';
			$request_uri .= $_SERVER['HTTP_HOST'];
			$request_uri .= $this->web_path();
		}
		else
		{
			$request_uri = getNotEncodedFullUrl('');
		}

		$plugins = $this->getPlugin('menu#before');
		foreach($plugins as $key => $val)
		{
			$plugins[$key]($this);
		}

		$config = $this->getConfig();
		$room = \Context::get('room');
		$menu_list = array();
		foreach($config->public_chat_menu_list as $val)
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
			$menu_list[] = $val;
		}
		$logged_info = \Context::get('logged_info');
		$query = [];
		if($mid!='')
		{
			$query['mid'] = $mid;
		}
		$hide_old_manager = 'N';
		if(is_object($logged_info) && ($logged_info->member_srl ?? 0))
		{
			$query['act'] = 'dispMemberInfo';
			$menu_list[] = array( 'href'=> $request_uri . '?' . http_build_query($query),'text'=> Context::getLang('member_info') );
			$is_manager = ($logged_info->is_admin ?? '') == 'Y';
			if(!$is_manager)
			{
				$args = new stdClass();
				$args->rooms = [(INT)preg_replace('/[^0-9]+/u', '', Context::get('room'))];
				$args->member_srls = [0, $logged_info->member_srl];
				$grants = $this->get_public_grants($args);
				$is_manager = in_array($logged_info->member_srl, $grants->M);
			}
			if($is_manager)
			{
				$_query = [];
				if($mid!='')
				{
					$_query['mid'] = $mid;
				}
				$_query['act'] = 'dispElkhatalk3Manager';
				$_query['room'] = $room;
				if(strlen($room_title = Context::get('room_title')))
				{
					$_query['room_title'] = $room_title;
				}
				$menu_list[] = array( 'href'=> $request_uri . '?' . http_build_query($_query),'text'=> Context::getLang('admin') );
				$hide_old_manager = 'Y';
				$this->add('lang_admin', \Context::getLang('admin'));
			}
		}
		else
		{
			$query['act'] = 'dispMemberLoginForm';
			$menu_list[] = array( 'href'=> $request_uri . '?' . http_build_query($query),'text'=> Context::getLang('cmd_login') );
			$query['act'] = 'dispMemberSignUpForm';
			$menu_list[] = array( 'href'=> $request_uri . '?' . http_build_query($query),'text'=> Context::getLang('cmd_signup') );
		}
		$this->add('hide_old_manager', $hide_old_manager);
		$this->add('menu_list', $menu_list);
		$this->add('show_help', \Context::getLang('elkhatalk3_show_help'));
		$this->add('open_popup', \Context::getLang('elkhatalk3_open_popup'));
		$this->add('help', \Context::getLang('elkhatalk3_help'));
		if($config->dark_mode == 'A')
		{
			$site_module_info = \Context::get('site_module_info');
			if(!strlen($site_module_info->settings->color_scheme ?? '') || $site_module_info->settings->color_scheme == 'auto')
			{
				$this->add('lang_dark_mode', \Context::getLang('elkhatalk3_dark_mode')); // $config->dark_mode ?? 'N'
			}
		}

		return $this;
	}
	/*public function getElkhatalk3PublicChat()
	{
		$config = $this->getConfig();
		if($config->public_chat_log!='Y')
		{
			return $this->stop('Error: ' . __LINE__);
		}
		$room = (INT)preg_replace('/^public_?/u', '', Context::get('room'));
		if(!$room)
		{
			return $this->stop('Error: ' . __LINE__);
		}
		$args = new stdClass();
		$args->room = $room;
		$args->list_count = $config->public_chat_list_count;
		$output = executeQueryArray('elkhatalk3.getPublicChat', $args);
		if(!$output->toBool())
		{
			$logged_info = Context::get('logged_info');
			if(is_object($logged_info) && $logged_info->is_admin=='Y')
			{
				return $output;
			}
			else
			{
				return $this->stop('Error: ' . __LINE__);
			}
		}
		foreach($output->data as $val)
		{
			if($val->member_srl)
			{
				$val->level = $config->cdn_path . $this->level_icon($val->member_srl);
			}
			else
			{
				$val->level = '';
			}
		}
		$this->add('data', $output->data);
	}*/
	public function getElkhatalk3AllList() : \baseObject
	{
		$output_member = $this->getElkhatalk3MemberList();
		$output_talk = $this->getElkhatalk3TalkList();
		return $this->createObject();
	}
	public function getElkhatalk3Member() : object
	{
		$output = $this->init();
		if(!$output->toBool())
		{
			return $output;
		}

		$oMemberModel = getModel('member');
		$config = $this->getConfig();
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		$target_srl = (INT)Context::get('target_srl');

		$_member_info = $oMemberModel->getMemberInfoByMemberSrl($target_srl);
		$member_info = new stdClass();
		$member_info->status = 0;
		if(is_object($_member_info) && ($_member_info->member_srl ?? 0) && $_member_info->member_srl == $target_srl)
		{
			$member_info->nick_name = $_member_info->nick_name;
			$member_info->member_srl = $target_srl;
			$member_info->profile_image = $this->profile_image($_member_info);
			$member_info->regdate = $_member_info->regdate;
		}
		else
		{
			$member_info->nick_name = '?';
			$member_info->member_srl = $target_srl;
			$member_info->profile_image = $config->logo;
		}

		$room_info = $this->getRoom($member_srl, $target_srl);
		if($room_info->room_no > 0)
		{
			$member_info->status = $room_info->status;
			$member_info->room_no = $room_info->room_no;
			if(isset($room_info->hide))
			{
				$member_info->hide = $room_info->hide;
			}
			$args = new stdClass();
			$args->room_no = $room_info->room_no;
			$args->list_count = 1;
			$_data = executeQuery('elkhatalk3.getContent', $args)->data;
			if(is_object($_data) && isset($_data->no))
			{
				$member_info->content = $this->langMessage( htmlspecialchars(mb_substr($_data->content, 0, 20, 'UTF-8')) );
				$member_info->content_member_srl = $_data->member_srl;
				$member_info->content_no = (INT)$_data->no;
				$member_info->content_read = (INT)$_data->read;
				$member_info->content_time = (INT)'20'.$_data->regdate.'00';
			}
		}

		if(Context::get('act')==__FUNCTION__)
		{
			Context::set('member_list', array($member_info));
			Context::set('target_srl', 0);
			$oTemplate = TemplateHandler::getInstance();
			$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-member-li');
			$this->add('tpl_member_list', $tpl);
			return $this;
		}
		else
		{
			/* html view 말고 채팅 목록 뿌려질 때 ajax 로 처리하자
			if(is_object($member_info) && isset($member_info->room_no))
			{
				$no_list = $this->readMessage($member_srl, $member_info->room_no);
			}*/
			return $member_info;
		}
	}
	public function getElkhatalk3MemberList() : \baseObject
	{
		$config = $this->getConfig();
		$oTemplate = TemplateHandler::getInstance();
		$output = $this->init();

		if(!$output->toBool())
		{
			Context::set('message', $output->getMessage());
			$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-member.error');
			$this->add('tpl_member_list', $tpl);
			return $this/*->stop($message)*/; // ajax callback 함수 실행되야 하므로 error 처리는 안 함.
		}

		$oMemberModel = getModel('member');
		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		$target_srl = (INT)Context::get('target_srl');
		$nick_name = is_string(Context::get('nick_name'))? (STRING)Context::get('nick_name') : '';

		if(!$target_srl)
		{
			$this->add('tpl_talk_list', '');
		}

		if(strlen($nick_name))
		{
			$args = new stdClass();
			$args->nick_name = $nick_name;
			$args->list_count = 1;
			$data = executeQueryArray('elkhatalk3.getMemberInfoByNickName', $args, array('member_srl','nick_name','regdate'))->data;

			$args = new stdClass();
			$args->nick_name_like = $nick_name;
			$args->list_count = 10;
			$data2 = executeQueryArray('elkhatalk3.getMemberInfoByNickName', $args, array('member_srl','nick_name','regdate'))->data;
			$member_list = array_merge($data, $data2);
			foreach($member_list as $val)
			{
				$info = new stdClass();
				$info->profile_image = $oMemberModel->getProfileImage($val->member_srl);
				$val->profile_image = $this->profile_image($info);
			}

			Context::set('mpage', 0);
			Context::set('member_list_count', 0);
			Context::set('nick_name', $nick_name);
			Context::set('member_list', $member_list);
			$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-member');
			$this->add('tpl_member_list', $tpl);
			$this->add('tpl_member_page', '');

			return $this;
		}

		$args = new stdClass();
		$args->member_srl = $member_srl;
		$args->page = $page = abs((INT)\Context::get('mpage')) ?: 1;
		$args->list_count = $list_count = 20;

		$member_list = [];
		if($target_srl)
		{
			$list_count++;
			$member_list[] = $this->getElkhatalk3Member();
			$args->not_member_srl = $target_srl;
		}

		if(Context::get('friends') == 'Y')
		{
			$output = executeQueryArray('elkhatalk3.getFriendList', $args);
		}
		else
		{
			$output = executeQueryArray('elkhatalk3.getMemberList', $args);
		}
		foreach($output->data as $val)
		{
			// 대화방이 존재하지 않는 경우 (예: 친구 목록)
			if(isset($val->friend_srl) && !$val->room_no)
			{
				$val->member_srl = $val->f_target_srl;
				$val->content_time = $val->f_regdate;
			}
			else
			{
				$val->member_srl = $val->member_srl == $member_srl? $val->member_srl2 : $val->member_srl;
				$val->content_time = '20'.$val->content_time.'00';

				if(isset($val->content) && is_string($val->content))
				{
					$val->content = $this->langMessage( htmlspecialchars(mb_substr($val->content, 0, 20, 'UTF-8')) );
				}
			}
			$val->member_srl2 = $member_srl;

			$member_list[] = $val;
		}

		foreach($member_list as $val)
		{
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($val->member_srl);
			if(is_object($member_info) && isset($member_info->member_srl) && $member_info->member_srl)
			{
				$val->profile_image = $this->profile_image($member_info);
				$val->nick_name = $member_info->nick_name;
			}
			else
			{
				$val->profile_image = $config->logo;
				$val->nick_name = '?';
			}
		}



		Context::set('member_list', $member_list);
		if($page <= 1 && count($member_list) < $list_count)
		{
			Context::set('mpage', 0);
			Context::set('member_list_count', 0);
			$this->add('tpl_member_page', '');
		}
		else
		{
			Context::set('mpage', $page);
			Context::set('member_list_count', $list_count);

			$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-navi');
			$this->add('tpl_member_page', $tpl);
		}

		$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-member');
		$this->add('tpl_member_list', $tpl);

		return $this;
	}

	public function profile_image($member_info) : string
	{
		$config = $this->getConfig();
		if(is_object($member_info) && isset($member_info->profile_image) && is_object($member_info->profile_image) && isset($member_info->profile_image->src) && $member_info->profile_image->src)
		{
			$strpos = strpos($member_info->profile_image->src, $this->web_path());
			if($strpos===FALSE)
			{
				$src = $member_info->profile_image->src;
			}
			else
			{
				$src = substr_replace($member_info->profile_image->src, '', $strpos, strlen($this->web_path()));
			}
			return $config->cdn_path . $src;
		}
		else
		{
			return $config->logo;
		}
	}

	public function getElkhatalk3TalkList() : \baseObject
	{
		$this->add('mpage', (INT)\Context::get('mpage') > 1 ? (INT)\Context::get('mpage') : '');
		$config = $this->getConfig();
		$oTemplate = TemplateHandler::getInstance();
		$output = $this->init();

		$member_srl = (INT)Context::get('member_srl');
		$target_srl = (INT)Context::get('target_srl');
		$return = FALSE;

		// 대상 회원이 없으면 [공지 플러그인 검사] OR [없다고 띄우기]
		if($target_srl <= 0)
		{
			// 공지 플러그인 먼저 체크.
			$notice_content = array();
			$plugins = $this->getPlugin('notice@');
			foreach($plugins as $key => $val)
			{
				$plugins[$key]($notice_content);
			}
			if(count($notice_content))
			{
				ksort($notice_content);
				$this->add('notice_content', 1);
				$this->add('tpl_talk_list', implode('', $notice_content));
				return $this;
			}
			$return = TRUE;
		}

		if(!$output->toBool())
		{
			Context::set('message', $output->getMessage());
			$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-talk.error');
			$this->add('tpl_talk_list', $tpl);
			$return = TRUE;
		}

		if($return)
		{
			return $this;
		}

		$logged_info = Context::get('logged_info');
		$content_no = (INT)Context::get('content_no');
		$this->add('notice_content', 0);

		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($target_srl);
		if(!is_object($member_info) || !isset($member_info->member_srl) || !$member_info->member_srl)
		{
			$nick_name = Context::getLang('elkhatalk3_leave_member');
		}
		else
		{
			$nick_name = $member_info->nick_name;
		}

		$profile_image_list = array();
		$profile_image_list[$member_srl] = $this->profile_image($logged_info);
		$profile_image_list[$target_srl] = $this->profile_image($member_info);

		$room_info = $this->getRoom($member_srl, $target_srl);
		if($room_info->room_no > 0)
		{
			$talk_list = $this->get_talk($room_info->room_no, $logged_info);
		}
		else
		{
			$talk_list = array();
		}
		Context::set('room_no', $room_info->room_no);
		Context::set('talk_list', $talk_list);
		Context::set('profile_image_list', $profile_image_list);

		if($content_no = (INT)Context::get('content_no'))
		{
			$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-talk-message');
			$this->add('tpl_talk_list', $tpl);
			$this->add('content_no', $content_no);
		}
		else
		{
			$tpl = $oTemplate->compile(_XE_PATH_."modules/elkhatalk3/skins/$config->skin/", 'widget.list-talk');
			$this->add('tpl_talk_list', $tpl);
		}
		$this->add('room_status', 0);
		$this->add('target_srl', $target_srl);

		return $this;
	}
	public function get_talk($room_no, $member_info) : array
	{
		$config = $this->getConfig();
		$args = new stdClass();
		$args->room_no = $room_no;
		$args->list_count = $config->talk_list_count;
		if($content_no = (INT)Context::get('content_no'))
		{
			$args->no = $content_no;
		}
		else if($no_below = (INT)Context::get('no_min'))
		{
			$args->no_below = $no_below;
		}
		else if($no_max = (INT)Context::get('no_max'))
		{
			$args->no_excess = $no_max;
		}
		$talk_list = array_reverse(executeQueryArray('elkhatalk3.getContent', $args)->data);
		$oMemberModel = getModel('member');

		$set_read = Context::get('act')=='getElkhatalk3TalkList';
		$no_max = 0;
		foreach($talk_list as $key => $val)
		{
			if($val->member_srl==$member_info->member_srl)
			{
				$val->nick_name = $member_info->nick_name;
			}
			else
			{
				$_member_info = $oMemberModel->getMemberInfoByMemberSrl($val->member_srl);
				if(!is_object($_member_info) || !isset($_member_info->member_srl) || !$_member_info->member_srl)
				{
					$val->nick_name = '?';
				}
				else
				{
					$val->nick_name = $_member_info->nick_name;
				}
				// 읽음처리하기
				if($val->read > 0 && $set_read)
				{
					$val->read = 0;
					if($val->no > $no_max)
					{
						$no_max = $val->no;
					}
				}
			}

			$val->content = $this->langMessage( nl2br(htmlspecialchars($val->content)) );
		}
		if($no_max > 0)
		{
			Context::set('no_max', $no_max);
			$oController = getController('elkhatalk3');
			$oController->procElkhatalk3Read();
		}

		return $talk_list;
	}
	// 소켓에 의해 미리 읽어진 특정 메세지를 가져오기.
	public function getElkhatalk3Message() : \baseObject
	{
		$output = $this->init();
		if(!$output->toBool())
		{
			return $output;
		}

		$logged_info = Context::get('logged_info');
		$member_srl = $logged_info->member_srl;
		$target_srl = (INT)Context::get('target_srl');
		$room_info = $this->getRoom($member_srl, $target_srl);
		if($room_info->room_no <= 0 || $room_info->room_no!=(INT)Context::get('room_no'))
		{
			return $this->stop('Error: ' . __LINE__);
		}

		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($target_srl);
		if(!is_object($member_info) || !$member_info->member_srl)
		{
			$nick_name = Context::getLang('elkhatalk3_leave_member');
		}
		else
		{
			$nick_name = $member_info->nick_name;
		}

		$config = $this->getConfig();

		$args = new stdClass();
		$args->room_no = $room_info->room_no;
		$args->no = explode(',', Context::get('no_list'));
		$data = array_reverse(executeQueryArray('elkhatalk3.getContent', $args, array('no','read','content','regdate','member_srl'))->data);

		foreach($data as $val)
		{
			$val->time = strtotime('20' . $val->regdate . '00');
			$val->read = 0;
			$val->room_no = $room_info->room_no;
			$val->nick_name = $nick_name;
			$val->level = $config->cdn_path . $this->level_icon($target_srl);
			$val->content = nl2br(htmlspecialchars($val->content));
		}

		// 주의: talk@before 플러그인은 부득이 여러번 호출 됨. 따라서 플러그인에서 1회만 실행하게 처리 필요.
		$plugins = $this->getPlugin('talk@before');
		foreach($plugins as $key => $val)
		{
			$output = $plugins[$key]($data);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}

		$this->add('message_list', $data);
		return $this;
	}
	public function getElkhatalk3RetryAuth() : \baseObject
	{
		$auth_info = $this->auth_info();
		$this->add('auth_info', $auth_info);
		return $this;
	}
	public function getElkhatalk3MemberMenu() : \baseObject
	{
		$output = $this->init();
		if(!$output->toBool())
		{
			return $output;
		}

		$target_srl = (INT)Context::get('target_srl');
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($target_srl);
		if(!is_object($member_info) || !$member_info->member_srl || $member_info->member_srl!=$target_srl)
		{
			return $this->stop('msg_not_exists_member');
		}
		$logged_info = Context::get('logged_info');
		$message_box_href = Context::get('m')? getNotEncodedFullUrl('','act','dispElkhatalk3Index','member_srl',$logged_info->member_srl, 'target_srl',$target_srl) : getNotEncodedFullUrl('','module','elkhatalk3','popup','Y','member_srl',$logged_info->member_srl, 'target_srl',$target_srl);
		$config = $this->getConfig();
		$menu_list = array(
			20 => array( 'href'=> getNotEncodedFullUrl('','act','dispMemberInfo','member_srl', $target_srl),'text'=> Context::getLang('member_info') )
			, 30 => array( 'href' => $message_box_href, 'popup' => (Context::get('m')?'N':'Y'), 'width'=> $config->popup_size[0], 'height'=> $config->popup_size[1], 'text'=> Context::getLang('elkhatalk3_message_box') )
			, 40 => array( 'iText' => "/call $member_info->nick_name" , 'text'=> Context::getLang('elkhatalk3_call') )
			, 50 => array( 'iText' => "/q $member_info->nick_name " , 'text'=> Context::getLang('elkhatalk3_q') )
			, 60 => array( 'iText' => "/ignore $member_info->nick_name 1" , 'text'=> Context::getLang('elkhatalk3_ignore') )
		);

		$plugins = $this->getPlugin('menu_list#member');
		foreach($plugins as $key => $val)
		{
			$output = $plugins[$key]($menu_list);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}

		$this->add('menu_list', $menu_list);
		return $this;
	}
	public function getElkhatalk3NickName() : \baseObject
	{
		$output = $this->init();
		if(!$output->toBool())
		{
			return $output;
		}

		$args = new stdClass();
		$args->nick_name = trim(Context::get('nick_name'));
		$data = executeQuery('elkhatalk3.getMemberInfoByNickName', $args, array('member_srl','nick_name','regdate'))->data;
		if(is_object($data) && isset($data->member_srl))
		{
			$config = $this->getConfig();
			$this->add('member_srl', $data->member_srl);
			$this->add('nick_name', $data->nick_name);
			$this->add('regdate', date('Y-m-d H:i:s', strtotime($data->regdate)));
			$this->add('level_icon', $config->cdn_path . $this->level_icon($data->member_srl));
		}
		else
		{
			Context::loadLang($this->web_path() . 'modules/member/lang');
			return $this->stop('msg_not_exists_member');
		}
		return $this;
	}
	public function getElkhatalk3Notice($public_room) : object
	{
		$public_room = preg_replace('/[^0-9a-z\-_]+/i', '', $public_room);
		if(FileHandler::exists($filename = "files/elkhatalk3/$public_room.json"))
		{
			$obj = json_decode(FileHandler::readFile($filename));
		}
		else
		{
			$obj = new stdClass();
		}
		if(!isset($obj->notice_list))
		{
			$obj->notice_list = array();
		}
		return $obj;
	}

	// 세션만 연장.
	public function getElkhatalk3PingSession()
	{
	}

	// 서드파티 플러그인으로 모듈 조절.
	public function getElkhatalk3Custom(): \baseObject
	{
		$plugin = (STRING)Context::get('plugin');
		$plugins = $this->getPlugin('custom_get');
		if(isset($plugins[$plugin]))
		{
			$output = $plugins[$plugin]($this);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}
		return $this;
	}

	public function getElkhatalk3HostCount($is_crontab = FALSE) : object
	{
		$oCacheHandler = CacheHandler::getInstance();
		if($oCacheHandler->isSupport())
		{
			$cache_key = 'object:getElkhatalk3HostCount';
			if(!$is_crontab)
			{
				$cache_val = $oCacheHandler->get($cache_key);
				if(is_object($cache_val))
				{
					return $cache_val;
				}
			}
		}
		if($is_crontab)
		{
			$params = array();
			$output = $this->sendSocket('hosts_clients', $params);

			$cache_val = @json_decode($output->getMessage());
			if(is_object($cache_val))
			{
				$oCacheHandler->put($cache_key, $cache_val, 300);
				return $cache_val;
			}
		}
		$cache_val = new stdClass();
		$cache_val->PC = '?';
		$cache_val->Mobile = '?';
		return $cache_val;
	}
}
