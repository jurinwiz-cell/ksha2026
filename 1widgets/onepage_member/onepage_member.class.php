<?php
class onepage_member extends WidgetHandler
{
	function proc($args)
	{
		// Targets to sort
		if(!isset($args->order_target) || !in_array($args->order_target, array('regdate','update_order'))) $args->order_target = 'regdate';
		// Sort order
		if(!isset($args->order_type) || !in_array($args->order_type, array('asc','desc'))) $args->order_type = 'asc';
		// The number of displayed lists
		$args->list_count = (int)($args->list_count ?? 9);
		if(!$args->list_count) $args->list_count = 9;
		// Cut the length of the title
		if(!isset($args->subject_cut_size) || !$args->subject_cut_size) $args->subject_cut_size = 0;
		// Cut the length of contents
		if(!isset($args->content_cut_size) || !$args->content_cut_size) $args->content_cut_size = 100;
		if(!isset($args->use_member_button) || !$args->use_member_button) $args->use_member_button = 'Y';
		if(!isset($args->member_button_name) || !$args->member_button_name) $args->member_button_name = '버튼';
		if(!isset($args->member_button_link) || !$args->member_button_link) $args->member_button_link = '#';

		// Set variables used internally
		$oModuleModel = getModel('module');
		$module_srls = $args->modules_info = $args->module_srls_info = $args->mid_lists = array();
		$site_module_info = Context::get('site_module_info');

		$obj = new stdClass();
		if(!isset($args->module_srls) || !$args->module_srls)
		{
			$obj->site_srl = (int)$site_module_info->site_srl;
			$output = executeQueryArray('widgets.onepage_member.getMids', $obj);
			if($output->data)
			{
				foreach($output->data as $key => $val)
				{
					$args->modules_info[$val->mid] = $val;
					$args->module_srls_info[$val->module_srl] = $val;
					$args->mid_lists[$val->module_srl] = $val->mid;
					$module_srls[] = $val->module_srl;
				}
			}

			$args->modules_info = $oModuleModel->getMidList($obj);
		}
		else
		{
			$obj->module_srls = $args->module_srls;
			$output = executeQueryArray('widgets.onepage_member.getMids', $obj);
			if($output->data)
			{
				foreach($output->data as $key => $val)
				{
					$args->modules_info[$val->mid] = $val;
					$args->module_srls_info[$val->module_srl] = $val;
					$module_srls[] = $val->module_srl;
				}
				$idx = explode(',',$args->module_srls);
				for($i=0,$c=count($idx);$i<$c;$i++)
				{
					$srl = $idx[$i];
					if(!$args->module_srls_info[$srl]) continue;
					$args->mid_lists[$srl] = $args->module_srls_info[$srl]->mid;
				}
			}
		}
		if(!count($args->modules_info)) return Context::get('msg_not_founded');
		$args->module_srl = implode(',',$module_srls);

		$content_items = $this->_getDocumentItems($args);
		$output = $this->_compile($args,$content_items);
		return $output;
	}

	function _getDocumentItems($args)
	{
		$oDocumentModel = getModel('document');
		$oTagModel = getModel('tag');
		$oFileModel = getModel('file');

		$obj = new stdClass();
		$obj->module_srl = $args->module_srl;

		$obj->module_srl = $args->module_srl;
		$obj->sort_index = $args->order_target;
		if($args->order_target == 'list_order' || $args->order_target == 'update_order')
		{
			$obj->order_type = $args->order_type=="desc"?"asc":"desc";
		}
		else
		{
			$obj->order_type = $args->order_type=="desc"?"desc":"asc";
		}
		$obj->list_count = $args->list_count;
		$obj->statusList = array('PUBLIC');
		$output = executeQueryArray('widgets.onepage_member.getNewestDocuments', $obj);
		if(!$output->toBool() || !$output->data) return;

		$content_items = array();
		$document_srls = array();
		if(count($output->data))
		{
			foreach($output->data as $key => $attribute)
			{
				$oDocument = new documentItem();
				$oDocument->setAttribute($attribute, false);
				$GLOBALS['XE_DOCUMENT_LIST'][$oDocument->document_srl] = $oDocument;
				$document_srls[] = $oDocument->document_srl;
			}

			$oDocumentModel->setToAllDocumentExtraVars();

			for($i=0,$c=count($document_srls);$i<$c;$i++)
			{
				$oDocument = $GLOBALS['XE_DOCUMENT_LIST'][$document_srls[$i]];
				$document_srl = $oDocument->document_srl;
				$module_srl = $oDocument->get('module_srl');
				$content_item = new onepageMemberItem( $args->module_srls_info[$module_srl]->browser_title );
				$content_item->add('original_content', $oDocument->get('content'));
				$content_item->setTitle(htmlspecialchars($oDocument->getTitleText()));
				$content_item->setContent($oDocument->getSummary($args->content_cut_size));
				$content_item->setLink( getSiteUrl('','','document_srl',$document_srl) );
				$output = $oTagModel->getDocumentsTagList($oDocument);
				if(!$output->toBool() || !$output->data) $tags = '';
				else $tags = $output->data;

				$content_item->setTags($tags);


				$content_item->add('mid', $args->mid_lists[$module_srl]);
				$files = $oFileModel->getFiles($document_srl);
				$content_item->setFirstFile($files, $this->widget_path, $args->skin);

				$content_items[] = $content_item;
			}

		}

		$oSecurity = new Security($content_items);
		$oSecurity->encodeHTML('..variables.content', '..variables.user_name', '..variables.nick_name');

		return $content_items;
	}

	
	function _compile($args,$content_items)
	{
		$oTemplate = &TemplateHandler::getInstance();
		// Set variables for widget
		$widget_info = new stdClass();
		$widget_info->modules_info = $args->modules_info;
		$widget_info->list_count = $args->list_count;
		$widget_info->subject_cut_size = $args->subject_cut_size;
		$widget_info->content_cut_size = $args->content_cut_size;
 		$widget_info->new_window = $args->new_window ?? 'N';
 		

		$widget_info->mid_lists = $args->mid_lists ?? array();

		$widget_info->show_browser_title = $args->show_browser_title ?? 'N';

		$widget_info->section_title = $args->section_title ?? '';
		$widget_info->section_content = $args->section_content ?? '';
		$widget_info->section_name = $args->section_name ?? '';
		$widget_info->section_background_color = $args->section_background_color ?? '';
		
		$widget_info->use_member_button = $args->use_member_button ?? 'Y';
		$widget_info->member_button_name = $args->member_button_name ?? '';
		$widget_info->member_button_link = $args->member_button_link ?? '';
		$widget_info->new_window_button = $args->new_window_button ?? 'N';
		
		$widget_info->content_items = $content_items;

		
		unset($args->modules_info);

		Context::set('colorset', $args->colorset ?? 'default');
		Context::set('widget_info', $widget_info);

		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin ?? 'default');
		return $oTemplate->compile($tpl_path, "content");
	}
}

class onepageMemberItem extends BaseObject
{
	var $browser_title = null;

	function onepageMemberItem($browser_title='')
	{
		$this->browser_title = $browser_title;
	}

	function setLink($url)
	{
		$this->add('url', strip_tags($url));
	}
	function setTitle($title)
	{
		$this->add('title', strip_tags($title));
	}
	function setContent($content)
	{
		$this->add('content', removeHackTag($content));
	}
	function getBrowserTitle()
	{
		return $this->browser_title;
	}

	function getLink()
	{
		return $this->get('url');
	}
	function getTitle($cut_size = 0, $tail='...')
	{
		$title = strip_tags($this->get('title'));

		if($cut_size) $title = cut_str($title, $cut_size, $tail);

		$attrs = array();
		if($this->get('title_bold') == 'Y') $attrs[] = 'font-weight:bold';
		if($this->get('title_color') && $this->get('title_color') != 'N') $attrs[] = 'color:#'.$this->get('title_color');

		if(count($attrs)) $title = sprintf("<span style=\"%s\">%s</span>", implode(';', $attrs), htmlspecialchars($title));

		return $title;
	}
	function getContent()
	{
		return $this->get('content');
	}
	function setFirstFile($files, $widget_path, $skin){
		$this->add('firstFile', (empty($files)?sprintf('%sskins/%s/images/no_image.png', $widget_path, $skin):$files[0]->uploaded_filename));
	}
	function getFirstFile(){
		return $this->get('firstFile');
	}
	function setTags($tags){
		$this->add('tags', $tags);

	}
	function getTags(){
		return $this->get('tags');
	}
}
