<?php

class Popup_ManagerAdminModel extends Popup_Manager
{
	function init()
	{
	}

	/**
	 * Get popup list with pagination
	 */
	function getPopupList($args = null)
	{
		if (!$args)
		{
			$args = new stdClass();
		}
		if (!$args->page)
		{
			$args->page = 1;
		}
		if (!$args->list_count)
		{
			$args->list_count = 20;
		}
		if (!$args->page_count)
		{
			$args->page_count = 10;
		}
		if (!$args->sort_index)
		{
			$args->sort_index = 'popup_srl';
		}
		if (!$args->order_type)
		{
			$args->order_type = 'desc';
		}

		$output = executeQueryArray('popup_manager.getPopupList', $args);
		return $output;
	}

	/**
	 * Get single popup by popup_srl
	 */
	function getPopup($popup_srl)
	{
		if (!$popup_srl)
		{
			return null;
		}

		$args = new stdClass();
		$args->popup_srl = $popup_srl;

		$output = executeQuery('popup_manager.getPopup', $args);
		if (!$output->toBool() || !$output->data)
		{
			return null;
		}

		// Get target modules
		$output->data->target_modules = $this->getTargetModules($popup_srl);

		return $output->data;
	}

	/**
	 * Get target modules for a popup
	 */
	function getTargetModules($popup_srl)
	{
		$args = new stdClass();
		$args->popup_srl = $popup_srl;

		$output = executeQueryArray('popup_manager.getTargetModules', $args);
		if (!$output->toBool() || !$output->data)
		{
			return [];
		}

		$modules = [];
		foreach ($output->data as $item)
		{
			$modules[] = $item->module_srl;
		}
		return $modules;
	}
}
