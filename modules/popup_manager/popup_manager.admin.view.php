<?php

class Popup_ManagerAdminView extends Popup_Manager
{
	function init()
	{
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	/**
	 * Display popup list
	 */
	function dispPopup_managerAdminList()
	{
		$args = new stdClass();
		$args->page = Context::get('page');
		$args->list_count = 20;
		$args->page_count = 10;
		$args->sort_index = Context::get('sort_index') ?: 'popup_srl';
		$args->order_type = Context::get('order_type') ?: 'desc';

		// Search
		$search_target = Context::get('search_target');
		$search_keyword = Context::get('search_keyword');
		if ($search_target === 'title' && $search_keyword)
		{
			$args->s_title = $search_keyword;
		}

		$is_enabled = Context::get('is_enabled');
		if ($is_enabled)
		{
			$args->is_enabled = $is_enabled;
		}

		// Get popup list
		$oModel = getAdminModel('popup_manager');
		$output = $oModel->getPopupList($args);

		Context::set('popup_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
		Context::set('search_target', $search_target);
		Context::set('search_keyword', $search_keyword);

		$this->setTemplateFile('popup_list');
	}

	/**
	 * Display popup edit form
	 */
	function dispPopup_managerAdminEdit()
	{
		$popup_srl = Context::get('popup_srl');
		$popup = null;

		if ($popup_srl)
		{
			// Get popup data
			$oModel = getAdminModel('popup_manager');
			$popup = $oModel->getPopup($popup_srl);
			Context::set('popup', $popup);
		}

		// Get editor (no file upload - HTML content only)
		$oEditorModel = getModel('editor');

		$option = new stdClass();
		$option->primary_key_name = 'popup_srl';
		$option->content_key_name = 'content';
		$option->allow_fileupload = false;
		$option->enable_autosave = false;
		$option->enable_default_component = true;
		$option->enable_component = true;
		$option->resizable = true;
		$option->height = 400;
		$option->editor_height = 400;
		$option->skin = 'ckeditor';
		$option->editor_skin = 'ckeditor';
		$option->colorset = 'moono-lisa';
		$option->editor_colorset = 'moono-lisa';
		$option->allow_html = true;

		$editor = $oEditorModel->getEditor(0, $option);
		Context::set('editor', $editor);

		// Get module list for target selector, grouped by module category (addon-style)
		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'module_category_srl', 'mid', 'browser_title');
		$mid_list = $oModuleModel->getMidList(new stdClass(), $columnList);

		$module_categories = $oModuleModel->getModuleCategories();
		if (is_array($mid_list))
		{
			foreach ($mid_list as $module_srl => $module)
			{
				if (!isset($module_categories[$module->module_category_srl]))
				{
					$module_categories[$module->module_category_srl] = (object)['title' => '', 'list' => []];
				}
				if (!isset($module_categories[$module->module_category_srl]->list))
				{
					$module_categories[$module->module_category_srl]->list = [];
				}
				$module_categories[$module->module_category_srl]->list[$module_srl] = $module;
			}
		}

		// Drop empty categories so only categories that actually contain modules are shown
		foreach ($module_categories as $key => $category)
		{
			if (empty($category->list) || !is_array($category->list))
			{
				unset($module_categories[$key]);
			}
		}

		Context::set('mid_list', $module_categories);

		$this->setTemplateFile('popup_edit');
	}
}
