<?php

/**
 * @file pullup.admin.view.php
 * @brief Admin view for the document pull-up module.
 */
class PullupAdminView extends Pullup
{
	/**
	 * Initialize the admin view.
	 *
	 * @return void
	 */
	public function init()
	{
		$this->setTemplatePath(sprintf('%stpl/', $this->module_path));
	}

	/**
	 * Display the configuration form.
	 *
	 * @return BaseObject|void
	 */
	public function dispPullupAdminConfig()
	{
		$config = PullupModel::getConfig();

		// Collect board module instances for target selection.
		$args = new stdClass;
		$args->module = 'board';
		$mid_list = ModuleModel::getMidList($args, array('module_srl', 'mid', 'browser_title'));
		$board_list = is_array($mid_list) ? array_values($mid_list) : array();

		Context::set('config', $config);
		Context::set('board_list', $board_list);
		$this->setTemplateFile('config');
	}

	/**
	 * Display the recent pull-up log.
	 *
	 * @return BaseObject|void
	 */
	public function dispPullupAdminList()
	{
		$args = new stdClass;
		$args->module_srl = Context::get('module_srl') ?: null;
		$args->page = Context::get('page') ?: 1;
		$args->list_count = 20;
		$output = executeQueryArray('pullup.getLogList', $args);

		Context::set('log_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);
		$this->setTemplateFile('list');
	}
}
