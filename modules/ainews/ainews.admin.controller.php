<?php
/**
 * @class  ainewsAdminController
 * @author 관리자 (admin@example.com)
 * @brief  ainews module admin controller class.
 */
class ainewsAdminController extends ainews
{
	/**
	 * @brief 초기화
	 */
	public function init()
	{
	}

	/**
	 * @brief 관리자 설정 저장
	 */
	public function procAinewsAdminSaveConfig()
	{
		$args = Context::getRequestVars();
		
		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('ainews', $args);
		
		$this->setMessage('success_saved');
		
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispAinewsAdminIndex');
		$this->setRedirectUrl($returnUrl);
	}
}
