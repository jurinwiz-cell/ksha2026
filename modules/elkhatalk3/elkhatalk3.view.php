<?php

/**
 * elkhatalk3
 *
 * Copyright (c) 엘카
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Elkhatalk3View extends Elkhatalk3
{
	/**
	 * 초기화
	 */
	public function init()
	{
		$config = $this->getConfig();
		// 스킨 경로 지정
		$this->setTemplatePath(__DIR__ . "/skins/$config->skin");

		$this->checkUpdateCache();
		if(!$this->toBool())
		{
			return $this;
		}
	}

	/**
	 * 메인 화면 예제
	 */
	public function dispElkhatalk3Index()
	{
		$logged_info = Context::get('logged_info');
		if(is_object($logged_info) && $logged_info->member_srl)
		{
			Context::set('member_srl', $logged_info->member_srl);
		}
		else
		{
			Context::set('member_srl', 0);
		}

		Context::addBodyClass('body-elkhatalk3');

		// 스킨 파일명 지정
		$this->setTemplateFile('widget');
	}

	public function dispElkhatalk3Popup()
	{
		Context::set('oView', $this);
		$this->module_info->layout_srl = 0;
		$this->module_info->mlayout_srl = 0;
		$this->dispElkhatalk3Index();
		$this->setTemplateFile('popup');
	}

	public function dispElkhatalk3SecretKey()
	{
		// 무한 대입 방지
		$oCacheHandler = CacheHandler::getInstance();
		if($oCacheHandler->isSupport())
		{
			$cache_key = 'object:secret_key=' . $_SERVER['REMOTE_ADDR'];
			$cache_val = $oCacheHandler->get($cache_key);
			if(!$cache_val)
			{
				$cache_val = 0;
			}
			$cache_val++;
			$oCacheHandler->put($cache_key, $cache_val, 60);
			if($cache_val > 5)
			{
				echo __LINE__;
				exit;
			}
		}
		$config = $this->getConfig();
		if($config->secret_key==Context::get('secret_key'))
		{
			$config->secret_key_send = 'Y';
			$output = $this->setConfig($config);
			if($output->toBool())
			{
				echo 'OK';
				exit;
			}
		}
		echo __LINE__;
		exit;
	}

	public function dispElkhatalk3Manager()
	{
		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->rooms = array( (INT)preg_replace('/[^0-9]+/u', '', Context::get('room')) );
		$grants = $this->get_public_grants($args);
		if(is_object($logged_info) && $logged_info->member_srl && ($logged_info->is_admin=='Y' || in_array($logged_info->member_srl, $grants->M)))
		{
			$oMemberModel = getModel('member');
			$exp_list = array();

			// 관리자
			$manager_list = array();
			foreach($grants->M as $member_srl)
			{
				$manager_list[] = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
			}
			Context::set('manager_list', $manager_list);

			// 카페 회원
			$cafe_list = array();
			foreach($grants->C as $member_srl)
			{
				$cafe_list[] = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
			}
			Context::set('cafe_list', $cafe_list);

			// 밴 목록
			$ban_list = array();
			foreach($grants->B as $pk => $member_srl)
			{
				$ban_list[] = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
				foreach($grants->pk[$member_srl] as $arr)
				{
					$exp_list[$member_srl] = $arr[2];
				}
			}
			Context::set('ban_list', $ban_list);
			Context::set('exp_list', $exp_list);

			Context::set('room_grants', $grants);

			$oModel = getModel('elkhatalk3');
			$obj = $oModel->getElkhatalk3Notice('public'.$args->rooms[0]);
			Context::set('notice_list', $obj->notice_list);

			$this->setTemplateFile(__FUNCTION__);
		}
		else
		{
			return $this->stop('msg_not_permitted');
		}
	}

	// 서드파티 플러그인으로 모듈 조절.
	public function dispElkhatalk3Custom()
	{
		$plugin = (STRING)Context::get('plugin');
		$plugins = $this->getPlugin('custom_disp');
		if(isset($plugins[$plugin]))
		{
			$output = $plugins[$plugin]($this);
			if(is_object($output) && method_exists($output, 'toBool') && !$output->toBool())
			{
				return $output;
			}
		}
	}
}
