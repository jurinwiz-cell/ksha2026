<?php

/**
 * elkhatalk3
 *
 * Copyright (c) 엘카
 *
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Elkhatalk3AdminView extends Elkhatalk3
{
	/**
	 * 초기화
	 */
	public function init()
	{
		$this->checkUpdateCache();
		if(!$this->toBool())
		{
			return $this;
		}

		// 관리자 화면 템플릿 경로 지정
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	/**
	 * 관리자 설정 화면 예제
	 */
	public function dispElkhatalk3AdminConfig()
	{
		// 현재 설정 상태 불러오기
		$config = $this->getConfig();

		$oModel = getAdminModel('elkhatalk3');
		$all_plugins = $oModel->all_plugins();
		Context::set('all_plugins', $all_plugins);

		// Context에 세팅
		Context::set('config', $config);

		// 스킨 파일 지정
		$this->setTemplateFile('config');
	}

}
