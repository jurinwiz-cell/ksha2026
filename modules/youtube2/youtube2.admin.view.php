<?php
class youtube2AdminView extends youtube2 {
	public function init() {
		$this->setTemplatePath($this->module_path . 'tpl');
	}
	
	public function dispYoutube2AdminIndex() {
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('youtube2');
		
		if(!$config) {
			$config = new stdClass();
			$config->api_key = '';
			$config->cache_ttl = 3600;
			$config->default_channel_id = '';
		}
		
		Context::set('config', $config);
		$this->setTemplateFile('index');
	}
}
