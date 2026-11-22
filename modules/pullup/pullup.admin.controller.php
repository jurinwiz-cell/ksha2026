<?php

/**
 * @file pullup.admin.controller.php
 * @brief Admin controller for the document pull-up module.
 */
class PullupAdminController extends Pullup
{
	/**
	 * Initialize the admin controller.
	 *
	 * @return void
	 */
	public function init()
	{
	}

	/**
	 * Save the module configuration.
	 *
	 * @return BaseObject|void
	 */
	public function procPullupAdminInsertConfig()
	{
		$vars = Context::getRequestVars();

		$config = new stdClass;
		$config->point = max(0, intval($vars->point));
		$config->min_days_after_write = max(0, intval($vars->min_days_after_write));
		$config->min_days_after_bump = max(0, intval($vars->min_days_after_bump));
		$config->update_regdate = ($vars->update_regdate === 'Y') ? 'Y' : 'N';

		$target_modules = $vars->target_modules;
		if (!is_array($target_modules))
		{
			$target_modules = $target_modules ? array($target_modules) : array();
		}
		$config->target_modules = array_values(array_unique(array_map('intval', $target_modules)));

		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('pullup', $config);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispPullupAdminConfig'));
	}
}
