<?php

class Popup_ManagerView extends Popup_Manager
{
	/**
	 * Trigger to display popups before page renders
	 */
	function triggerDisplayPopups()
	{
		// Skip admin pages
		$module = Context::get('module');
		$act = Context::get('act');

		if ($module === 'admin' || strpos($act, 'Admin') !== false)
		{
			return new BaseObject();
		}

		// Get current module info
		$current_module_info = Context::get('current_module_info');
		$current_module_srl = $current_module_info->module_srl ?? 0;

		// Get active popups for this module
		$oModel = getModel('popup_manager');
		$popups = $oModel->getActivePopupsForModule($current_module_srl);

		if (empty($popups))
		{
			return new BaseObject();
		}

		// Add CSS
		Context::addCSSFile('./modules/popup_manager/tpl/css/popup_manager.css');

		// Add JS
		Context::addJSFile('./modules/popup_manager/tpl/js/popup_manager.js');

		// Build popup data
		$popup_data = [];
		foreach ($popups as $popup)
		{
			$popup_data[] = [
				'srl' => $popup->popup_srl,
				'type' => $popup->popup_type ?: 'html',
				'title' => $popup->title,
				'content' => $popup->content,
				'image_url' => $popup->image_url,
				'image_link' => $popup->image_link,
				'link_target' => $popup->link_target ?: '_self',
				'top' => (int)$popup->position_top,
				'left' => (int)$popup->position_left,
				'width' => (int)$popup->width,
				'height' => (int)$popup->height,
				'cookie_days' => (int)$popup->cookie_days,
			];
		}

		// Add popup data as JavaScript variable
		$script = '<script>var popup_manager_popups = ' . json_encode($popup_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
		Context::addHtmlHeader($script);

		return new BaseObject();
	}
}
