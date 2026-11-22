<?php

/**
 * @file pullup.model.php
 * @brief Model for the document pull-up module.
 */
class PullupModel extends Pullup
{
	/**
	 * Initialize the model.
	 *
	 * @return void
	 */
	public function init()
	{
	}

	/**
	 * Get the module configuration with defaults applied.
	 *
	 * @return object
	 */
	public static function getConfig(): object
	{
		$config = ModuleModel::getModuleConfig('pullup');
		if (!is_object($config))
		{
			$config = new stdClass;
		}
		$config->point = intval($config->point ?? 0);
		$config->target_modules = is_array($config->target_modules ?? null) ? $config->target_modules : array();
		$config->min_days_after_write = intval($config->min_days_after_write ?? 0);
		$config->min_days_after_bump = intval($config->min_days_after_bump ?? 0);
		$config->update_regdate = ($config->update_regdate ?? 'N') === 'Y' ? 'Y' : 'N';
		return $config;
	}

	/**
	 * Check whether the pull-up feature is enabled for the given module.
	 *
	 * @param int $module_srl
	 * @return bool
	 */
	public static function isTargetModule(int $module_srl): bool
	{
		$config = self::getConfig();
		return in_array($module_srl, array_map('intval', $config->target_modules), true);
	}

	/**
	 * Get the most recent pull-up timestamp for a document.
	 *
	 * @param int $document_srl
	 * @return object|null { regdate: string } or null when never bumped
	 */
	public static function getLastLog(int $document_srl): ?object
	{
		$args = new stdClass;
		$args->document_srl = $document_srl;
		$output = executeQuery('pullup.getLastLog', $args);
		if (!$output->toBool() || !$output->data || intval($output->data->log_count) < 1)
		{
			return null;
		}
		$last = new stdClass;
		$last->regdate = $output->data->last_regdate;
		return $last;
	}

	/**
	 * Decide whether the pull-up menu item should be visible to the user.
	 * This only checks the board target and author/manager permission, NOT
	 * the timing restrictions, so the button is always shown to eligible
	 * users and the reason for any block is surfaced on click instead.
	 *
	 * @param object $oDocument document item object
	 * @param object $logged_info current member info
	 * @return bool
	 */
	public static function canSeeMenu($oDocument, $logged_info): bool
	{
		if (!$oDocument || !$oDocument->isExists())
		{
			return false;
		}
		if (!self::isTargetModule(intval($oDocument->get('module_srl'))))
		{
			return false;
		}
		if (!$logged_info || !$logged_info->member_srl)
		{
			return false;
		}

		if (abs($oDocument->get('member_srl')) === abs($logged_info->member_srl))
		{
			return true;
		}
		if (($logged_info->is_admin ?? 'N') === 'Y')
		{
			return true;
		}
		$module_info = ModuleModel::getModuleInfoByModuleSrl(intval($oDocument->get('module_srl')));
		if ($module_info)
		{
			$grant = ModuleModel::getGrant($module_info, $logged_info);
			if (!empty($grant->manager))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Decide whether the current user may bump the given document and why not.
	 *
	 * @param object $oDocument document item object
	 * @param object $logged_info current member info
	 * @return object { allowed: bool, error: string, point: int }
	 */
	public static function getBumpAvailability($oDocument, $logged_info): object
	{
		$result = new stdClass;
		$result->allowed = false;
		$result->error = '';
		$result->message = '';
		$result->is_manager = false;
		$config = self::getConfig();
		$result->point = $config->point;

		if (!$oDocument || !$oDocument->isExists())
		{
			$result->error = 'msg_invalid_request';
			return $result;
		}

		$module_srl = intval($oDocument->get('module_srl'));
		if (!self::isTargetModule($module_srl))
		{
			$result->error = 'msg_pullup_not_target';
			return $result;
		}

		if (!$logged_info || !$logged_info->member_srl)
		{
			$result->error = 'msg_not_logged';
			return $result;
		}

		// Author or manager only.
		$is_author = (abs($oDocument->get('member_srl')) === abs($logged_info->member_srl));
		$is_manager = false;
		if (($logged_info->is_admin ?? 'N') === 'Y')
		{
			$is_manager = true;
		}
		else
		{
			$module_info = ModuleModel::getModuleInfoByModuleSrl($module_srl);
			if ($module_info)
			{
				$grant = ModuleModel::getGrant($module_info, $logged_info);
				$is_manager = !empty($grant->manager);
			}
		}
		if (!$is_author && !$is_manager)
		{
			$result->error = 'msg_pullup_not_permitted';
			return $result;
		}
		$result->is_manager = $is_manager;

		// Apply timing restrictions to authors and managers alike.
		$last_log = self::getLastLog(intval($oDocument->get('document_srl')));

		$now = time();
		if ($config->min_days_after_write > 0)
		{
			$allow_from = self::_toTimestamp($oDocument->get('regdate')) + ($config->min_days_after_write * 86400);
			if ($now < $allow_from)
			{
				$result->error = 'msg_pullup_too_early_write';
				$result->message = sprintf(lang('msg_pullup_too_early_write'), self::_formatDuration($allow_from - $now), date('Y-m-d H:i', $allow_from));
				return $result;
			}
		}
		if ($config->min_days_after_bump > 0 && $last_log)
		{
			$allow_from = self::_toTimestamp($last_log->regdate) + ($config->min_days_after_bump * 86400);
			if ($now < $allow_from)
			{
				$result->error = 'msg_pullup_too_early_bump';
				$result->message = sprintf(lang('msg_pullup_too_early_bump'), self::_formatDuration($allow_from - $now), date('Y-m-d H:i', $allow_from));
				return $result;
			}
		}

		$result->allowed = true;
		return $result;
	}

	/**
	 * Format a duration in seconds into a human-readable string
	 * such as "2일 5시간" / "3시간 10분" / "12분".
	 *
	 * @param int $seconds
	 * @return string
	 */
	protected static function _formatDuration(int $seconds): string
	{
		if ($seconds < 0)
		{
			$seconds = 0;
		}
		$days = intdiv($seconds, 86400);
		$hours = intdiv($seconds % 86400, 3600);
		$minutes = intdiv($seconds % 3600, 60);

		$parts = array();
		if ($days > 0)
		{
			$parts[] = $days . lang('unit_day');
		}
		if ($hours > 0)
		{
			$parts[] = $hours . lang('unit_hour');
		}
		if (!$days && $minutes > 0)
		{
			$parts[] = $minutes . lang('unit_minute');
		}
		if (!count($parts))
		{
			$parts[] = '1' . lang('unit_minute');
		}
		return implode(' ', $parts);
	}

	/**
	 * Convert a Rhymix date string (YYYYMMDDHHmmss) to a Unix timestamp.
	 *
	 * @param string $date
	 * @return int
	 */
	protected static function _toTimestamp($date): int
	{
		$date = preg_replace('/[^0-9]/', '', strval($date));
		if (strlen($date) < 8)
		{
			return 0;
		}
		$date = str_pad($date, 14, '0');
		$year = intval(substr($date, 0, 4));
		$month = intval(substr($date, 4, 2));
		$day = intval(substr($date, 6, 2));
		$hour = intval(substr($date, 8, 2));
		$min = intval(substr($date, 10, 2));
		$sec = intval(substr($date, 12, 2));
		if (!$year || !$month || !$day)
		{
			return 0;
		}
		return mktime($hour, $min, $sec, $month, $day, $year);
	}
}
