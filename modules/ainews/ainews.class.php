<?php
/**
 * @class  ainews
 * @author 관리자 (admin@example.com)
 * @brief  ainews module high class.
 */
class ainews extends ModuleObject
{
	/**
	 * @brief 모듈 설치 시 실행
	 */
	public function moduleInstall()
	{
		// 스키마 설치 로직
		$oModuleController = getController('module');
		$oModuleController->insertTrigger('document.insertDocument', 'ainews', 'controller', 'triggerInsertDocument', 'after');
		
		// 크론잡 등록 (예: 1시간마다 뉴스 수집)
		$oModuleController->insertCron('ainews', 'controller', 'cronCollectNews', '*/60 * * * *');
		
		return new BaseObject();
	}

	/**
	 * @brief 업데이트가 필요한지 확인
	 */
	public function checkUpdate()
	{
		$oModuleModel = getModel('module');
		
		if(!$oModuleModel->getTrigger('document.insertDocument', 'ainews', 'controller', 'triggerInsertDocument', 'after'))
		{
			return true;
		}
		
		if(!$oModuleModel->getCron('ainews', 'controller', 'cronCollectNews'))
		{
			return true;
		}

		return false;
	}

	/**
	 * @brief 모듈 업데이트 실행
	 */
	public function moduleUpdate()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		if(!$oModuleModel->getTrigger('document.insertDocument', 'ainews', 'controller', 'triggerInsertDocument', 'after'))
		{
			$oModuleController->insertTrigger('document.insertDocument', 'ainews', 'controller', 'triggerInsertDocument', 'after');
		}

		if(!$oModuleModel->getCron('ainews', 'controller', 'cronCollectNews'))
		{
			$oModuleController->insertCron('ainews', 'controller', 'cronCollectNews', '*/60 * * * *');
		}

		return new BaseObject();
	}

	/**
	 * @brief 캐시 파일 재생성
	 */
	public function recompileCache()
	{
	}
}
