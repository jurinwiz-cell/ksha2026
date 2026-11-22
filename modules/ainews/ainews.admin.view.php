<?php
/**
 * @class  ainewsAdminView
 * @author 관리자 (admin@example.com)
 * @brief  ainews module admin view class.
 */
class ainewsAdminView extends ainews
{
	/**
	 * @brief 초기화
	 */
	public function init()
	{
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	/**
	 * @brief 관리자 메인 화면 (설정 및 현황)
	 */
	public function dispAinewsAdminIndex()
	{
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('ainews');
		
		Context::set('config', $config);
		
		// TODO: 기사 수집 통계 및 에이전트 상태 정보 등을 넘겨주기
		
		$this->setTemplateFile('index');
	}
}
