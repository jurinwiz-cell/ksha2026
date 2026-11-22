<?php

namespace Rhymix\Modules\Devtool\Controllers;

use Rhymix\Framework\DB;

/**
 * 개발자도구
 * 
 * Copyright (c) Waterticket
 * 
 * Generated with https://www.poesis.dev/tools/rxmodulegen
 */
class Install extends Base
{
	/**
	 * 모듈 설치 콜백 함수.
	 * 
	 * @return object
	 */
	public function moduleInstall()
	{
		$this->moduleUpdate();
	}
	
	/**
	 * 모듈 업데이트 확인 콜백 함수.
	 * 
	 * @return bool
	 */
	public function checkUpdate()
	{
		if (!self::isModuleMidExists()) {
			return true;
		}

		return false;
	}
	
	/**
	 * 모듈 업데이트 콜백 함수.
	 * 
	 * @return object
	 */
	public function moduleUpdate()
	{
		if (!self::isModuleMidExists()) {
			self::createDefaultMid();
		}
	}
	
	/**
	 * 캐시파일 재생성 콜백 함수.
	 * 
	 * @return void
	 */
	public function recompileCache()
	{
		
	}

	protected static function isModuleMidExists(): bool
	{
		try {
			$oDB = DB::getInstance();
			$stmt = $oDB->query("SELECT COUNT(*) FROM modules WHERE `module` = 'devtool' LIMIT 1");
			if (!$stmt) {
				return false;
			}

			$result = $stmt->fetchColumn();
			$stmt->closeCursor();

			return (bool) $result;
		} catch (\Exception $e) {
			// 예외 발생 시 false 반환
			return false;
		}
	}

	protected static function createDefaultMid(): void
	{
		$oModuleController = \ModuleController::getInstance();
		$args = new \stdClass;
		$args->mid = 'devtool';
		$args->module = 'devtool';
		$args->browser_title = '개발자도구';
		$args->site_srl = 0;
		$args->layout_srl = -1;
		$args->skin = 'default';
		$oModuleController->insertModule($args);
	}
}