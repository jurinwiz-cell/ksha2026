<?php

class Popup_Manager extends ModuleObject
{
	function moduleInstall()
	{
		return new BaseObject();
	}

	function checkUpdate()
	{
		$oModuleModel = getModel('module');

		// Check if trigger exists
		if (!$oModuleModel->getTrigger('display', 'popup_manager', 'view', 'triggerDisplayPopups', 'before'))
		{
			return true;
		}

		// Check if target_type column exists
		$oDB = DB::getInstance();
		if (!$oDB->isColumnExists('popups', 'target_type'))
		{
			return true;
		}

		return false;
	}

	function moduleUpdate()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		// Register display trigger
		if (!$oModuleModel->getTrigger('display', 'popup_manager', 'view', 'triggerDisplayPopups', 'before'))
		{
			$oModuleController->insertTrigger('display', 'popup_manager', 'view', 'triggerDisplayPopups', 'before');
		}

		// Add target_type column for include/exclude target mode
		$oDB = DB::getInstance();
		if (!$oDB->isColumnExists('popups', 'target_type'))
		{
			$oDB->addColumn('popups', 'target_type', 'varchar', 20, 'include', true, 'end_date');
		}

		return new BaseObject();
	}

	function recompileCache()
	{
		return new BaseObject();
	}
}
