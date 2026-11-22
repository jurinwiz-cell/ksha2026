<?php
/**
 * @class  ainewsView
 * @author 관리자 (admin@example.com)
 * @brief  ainews module view class.
 */
class ainewsView extends ainews
{
	/**
	 * @brief 초기화
	 */
	public function init()
	{
		$this->setTemplatePath($this->module_path . 'skins/' . ($this->module_info->skin ?: 'default'));
	}

	/**
	 * @brief 뉴스레터 열람 메인 화면
	 */
	public function dispAinewsIndex()
	{
		$oAinewsModel = getModel('ainews');
		
		$args = new stdClass();
		$args->module_srl = $this->module_info->module_srl;
		$args->status = 'PUBLIC';
		
		$newsletters = $oAinewsModel->getNewsletters($args);
		Context::set('newsletters', $newsletters);
		
		$this->setTemplateFile('index');
	}
}
