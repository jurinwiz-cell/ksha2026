<?php

class Popup_ManagerModel extends Popup_Manager
{
	/**
	 * Get active popups for current page
	 * 대상 모듈이 지정되지 않으면 전체 페이지에 표시
	 * 대상 모듈이 지정되어 있으면 해당 모듈에서만 표시
	 */
	function getActivePopupsForModule($current_module_srl)
	{
		$args = new stdClass();
		$args->is_enabled = 'Y';
		$args->sort_index = 'display_order';
		$args->order_type = 'asc';

		$output = executeQueryArray('popup_manager.getActivePopups', $args);
		if (!$output->toBool() || !$output->data)
		{
			return [];
		}

		$current_datetime = date('YmdHis');
		$popups = [];

		foreach ($output->data as $popup)
		{
			// Check date-time range (start_date/end_date stored as YmdHis)
			$start_date = $popup->start_date ? str_pad(substr($popup->start_date, 0, 14), 14, '0', STR_PAD_RIGHT) : '';
			$end_date = $popup->end_date ? str_pad(substr($popup->end_date, 0, 14), 14, '0', STR_PAD_RIGHT) : '';

			// 시작일시가 있고 현재 일시가 시작일시보다 이전이면 스킵
			if ($start_date && $current_datetime < $start_date)
			{
				continue;
			}

			// 종료일시가 있고 현재 일시가 종료일시보다 이후이면 스킵
			if ($end_date && $current_datetime > $end_date)
			{
				continue;
			}

			// Get target modules
			$target_modules = $this->getTargetModules($popup->popup_srl);
			$target_type = $popup->target_type ?: 'include';

			// 대상 모듈이 지정된 경우에만 필터링 (미지정 시 전체 페이지에 표시)
			if (!empty($target_modules))
			{
				$in_target = in_array($current_module_srl, $target_modules);
				// include: 선택한 모듈에서만 표시 / exclude: 선택한 모듈 제외하고 표시
				if ($target_type === 'exclude' && $in_target)
				{
					continue;
				}
				if ($target_type !== 'exclude' && !$in_target)
				{
					continue;
				}
			}

			// Note: the "don't show again" preference is intentionally NOT checked
			// here. It is evaluated on the client (popup_manager.js) via cookie so
			// that per-user state never gets baked into the shared page cache.

			$popups[] = $popup;
		}

		return $popups;
	}

	/**
	 * Get target modules (cached version for frontend)
	 */
	function getTargetModules($popup_srl)
	{
		static $cache = [];

		if (!isset($cache[$popup_srl]))
		{
			$args = new stdClass();
			$args->popup_srl = $popup_srl;

			$output = executeQueryArray('popup_manager.getTargetModules', $args);
			$cache[$popup_srl] = [];
			if ($output->toBool() && $output->data)
			{
				foreach ($output->data as $item)
				{
					$cache[$popup_srl][] = $item->module_srl;
				}
			}
		}

		return $cache[$popup_srl];
	}
}
