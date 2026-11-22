<?php

namespace Rhymix\Modules\Devtool\Controllers;

use Context;
use ModuleModel;

/**
 * 개발자도구
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.dev/tools/rxmodulegen
 */
class Index extends Base
{
	/**
	 * 초기화
	 */
	public function init()
	{
		$this->setTemplatePath($this->module_path . 'views/');
	}
	
	/**
	 * 메인 화면 예제
	 */
	public function dispDevtoolIndex()
	{
		// 뷰 파일명 지정
		$this->setTemplateFile('index');
	}
}
