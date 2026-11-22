<?php

/**
 * @file pullup.controller.php
 * @brief Controller for the document pull-up module.
 */
class PullupController extends Pullup
{
	/**
	 * Initialize the controller.
	 *
	 * @return void
	 */
	public function init()
	{
	}

	/**
	 * Add a "pull-up" item to the document popup menu (document.getDocumentMenu after).
	 *
	 * @param array $menu_list
	 * @return BaseObject
	 */
	public function triggerAddDocumentMenu(&$menu_list)
	{
		$document_srl = intval(Context::get('target_srl'));
		if (!$document_srl)
		{
			return new BaseObject();
		}

		Context::loadLang('./modules/pullup/lang');

		$oDocument = DocumentModel::getDocument($document_srl);
		$logged_info = Context::get('logged_info');
		if (!PullupModel::canSeeMenu($oDocument, $logged_info))
		{
			return new BaseObject();
		}

		$config = PullupModel::getConfig();
		if ($config->point > 0)
		{
			$message = sprintf(lang('msg_pullup_confirm_point'), number_format($config->point));
		}
		else
		{
			$message = lang('msg_pullup_confirm');
		}
		$message = str_replace(array("\r", "\n", "'"), array('', '', "\\'"), $message);

		$url = sprintf(
			"if(!confirm('%s')) return; var params = {}; params['document_srl'] = '%d'; exec_json('pullup.procPullupDocument', params, function(){ location.reload(); });",
			$message,
			$document_srl
		);

		$oDocumentController = getController('document');
		$oDocumentController->addDocumentPopupMenu($url, 'cmd_pullup', '', 'javascript');

		return new BaseObject();
	}

	/**
	 * Bump a document to the top of the list.
	 *
	 * @return BaseObject|void
	 */
	public function procPullupDocument()
	{
		$document_srl = intval(Context::get('document_srl'));
		if (!$document_srl)
		{
			throw new \Rhymix\Framework\Exceptions\InvalidRequest;
		}

		$oDocument = DocumentModel::getDocument($document_srl);
		$logged_info = Context::get('logged_info');
		$availability = PullupModel::getBumpAvailability($oDocument, $logged_info);
		if (!$availability->allowed)
		{
			return new BaseObject(-1, $availability->message ?: $availability->error);
		}

		$config = PullupModel::getConfig();
		$module_srl = intval($oDocument->get('module_srl'));
		$member_srl = abs($logged_info->member_srl);

		// Deduct points for everyone allowed to bump (author or manager).
		$point = 0;
		if ($config->point > 0)
		{
			$point = $config->point;
			$current_point = PointModel::getPoint($member_srl);
			if ($current_point < $point)
			{
				return new BaseObject(-1, 'msg_pullup_not_enough_point');
			}
		}

		$now = date('YmdHis');

		// Board lists are sorted by list_order ASC, so the smallest (most
		// negative) value appears at the very top. Take the current minimum
		// within this board and go one lower so the document jumps to the top.
		$min_args = new stdClass;
		$min_args->module_srl = $module_srl;
		$min_output = executeQuery('pullup.getMinListOrder', $min_args);
		$min_list_order = isset($min_output->data->min_list_order) ? intval($min_output->data->min_list_order) : 0;
		$min_update_order = isset($min_output->data->min_update_order) ? intval($min_output->data->min_update_order) : 0;

		// Move the document to the top by lowering list_order / update_order.
		$update_args = new stdClass;
		$update_args->document_srl = $document_srl;
		$update_args->list_order = $min_list_order - 1;
		$update_args->update_order = $min_update_order - 1;

		// Optionally refresh the published date so the document reads as freshly written.
		// The regdate column is only updated when this value is set (see updateDocumentOrder query).
		if ($config->update_regdate === 'Y')
		{
			$update_args->regdate = $now;
		}

		$oDB = DB::getInstance();
		$oDB->begin();

		$output = executeQuery('pullup.updateDocumentOrder', $update_args);
		if (!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		// Deduct the configured points.
		if ($point > 0)
		{
			PointController::setPoint($member_srl, $point, 'minus');
		}

		// Record the bump.
		$log_args = new stdClass;
		$log_args->log_srl = getNextSequence();
		$log_args->document_srl = $document_srl;
		$log_args->module_srl = $module_srl;
		$log_args->member_srl = $member_srl;
		$log_args->point = $point;
		$log_args->origin_regdate = $oDocument->get('regdate');
		$log_args->regdate = $now;
		$output = executeQuery('pullup.insertLog', $log_args);
		if (!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$oDB->commit();

		// Clear the document cache so the new order/date is reflected.
		DocumentController::clearDocumentCache($document_srl);

		$this->setMessage('success_pullup');
		if (Context::get('cur_url'))
		{
			$this->setRedirectUrl(Context::get('cur_url'));
		}
	}
}
