<?php

/**
 * @file pullup.class.php
 * @brief Base class for the document pull-up (끌어올리기) module.
 */
class Pullup extends ModuleObject
{
	/**
	 * Default configuration values.
	 *
	 * @var array
	 */
	protected $_default_config = array(
		'point' => 0,
		'target_modules' => array(),
		'min_days_after_write' => 0,
		'min_days_after_bump' => 0,
		'update_regdate' => 'N',
	);

	/**
	 * Install the module.
	 *
	 * @return BaseObject|void
	 */
	public function moduleInstall()
	{
		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('pullup', (object) $this->_default_config);
	}

	/**
	 * Check whether the module needs to be updated.
	 *
	 * @return bool
	 */
	public function checkUpdate()
	{
		if (!is_object(ModuleModel::getModuleConfig('pullup')))
		{
			return true;
		}
		return false;
	}

	/**
	 * Update the module.
	 *
	 * @return BaseObject|void
	 */
	public function moduleUpdate()
	{
		$config = ModuleModel::getModuleConfig('pullup');
		if (!is_object($config))
		{
			$oModuleController = getController('module');
			$oModuleController->insertModuleConfig('pullup', (object) $this->_default_config);
		}
	}

	/**
	 * Uninstall the module.
	 *
	 * @return BaseObject|void
	 */
	public function moduleUninstall()
	{
	}
}
