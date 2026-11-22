<?php
class youtube2AdminController extends youtube2 {
	public function init() {
	}
	
	public function procYoutube2AdminConfig() {
		$args = Context::getRequestVars();
		$config = new stdClass();
		$config->api_key = trim($args->api_key ?? '');
		$config->cache_ttl = (int)($args->cache_ttl ?? 3600);
		if($config->cache_ttl <= 0) $config->cache_ttl = 3600;
		$config->default_channel_id = trim($args->default_channel_id ?? '');

		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('youtube2', $config);
		
		if (!$output->toBool()) {
			return $output;
		}

		$this->setMessage('success_updated');
		if(Context::get('success_return_url')) {
			$this->setRedirectUrl(Context::get('success_return_url'));
		} else {
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'module', 'act', 'dispYoutube2AdminIndex'));
		}
	}
}
