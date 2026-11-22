<?php
    class webcon_verticalTab extends WidgetHandler {
        function proc($args) {
			// document 모듈의 model 객체를 받아서 결과를 객체화 시킴
            $oDocumentModel = &getModel('document');
			
			
            // 글자 제목 길이
            $widget_info->subject_cut_size = (int)$args->subject_cut_size;
            if(!$widget_info->subject_cut_size) $widget_info->subject_cut_size = 40;
			
			
            // 썸네일 생성 방법
            $widget_info->thumbnail_type = $args->thumbnail_type;
            if(!$widget_info->thumbnail_type) $widget_info->thumbnail_type = 'crop';

            // 썸네일 가로 크기
            $widget_info->thumbnail_width = (int)$args->thumbnail_width;
            if(!$widget_info->thumbnail_width) $widget_info->thumbnail_width = 50;

            // 썸네일 세로 크기
            $widget_info->thumbnail_height = (int)$args->thumbnail_height;
            if(!$widget_info->thumbnail_height) $widget_info->thumbnail_height = 50;

            // 탭별 목록수
            $widget_info->rows_list_count = (int)$args->rows_list_count;
            if(!$widget_info->rows_list_count) $widget_info->rows_list_count = 8;

			
            // 정렬 대상
            $widget_info->order_target = $args->order_target;
            if(!in_array($widget_info->order_target, array('list_order','update_order'))) $widget_info->order_target = 'list_order';

            // 정렬 순서
            $widget_info->order_type = $args->order_type;
            if(!in_array($widget_info->order_type, array('asc','desc'))) $widget_info->order_type = 'asc';

            // 제목
            $widget_info->title = $args->title;
			// 노출 여부 체크
            if($args->display_author!='Y') $widget_info->display_author = 'N';
            else $widget_info->display_author = 'Y';
            if($args->display_regdate!='Y') $widget_info->display_regdate = 'N';
            else $widget_info->display_regdate = 'Y';
            if($args->display_readed_count!='Y') $widget_info->display_readed_count = 'N';
            else $widget_info->display_readed_count = 'Y';
			if($args->display_browser_title!='Y') $widget_info->display_browser_title = 'N';
            else $widget_info->display_browser_title = 'Y';
			if($args->display_comment_count!='Y') $widget_info->display_comment_count = 'N';
            else $widget_info->display_comment_count = 'Y';
			
			// 탭별 목록수
            $widget_info->widget_position = $args->widget_position;
            if(!$widget_info->widget_position) $widget_info->widget_position = 'left_tab';

	
            // 대상 모듈 (mid_list는 기존 위젯의 호환을 위해서 처리하는 루틴을 유지. module_srl로 위젯에서 변경)
            if($args->mid_list) {
                $mid_list = explode(",",$args->mid_list);
                $oModuleModel = &getModel('module');
                if(count($mid_list)) {
                    $module_srl = $oModuleModel->getModuleSrlByMid($mid_list);
                } else {
                    $site_module_info = Context::get('site_module_info');
                    if($site_module_info) {
                        $margs->site_srl = $site_module_info->site_srl;
                        $oModuleModel = &getModel('module');
                        $output = $oModuleModel->getMidList($margs);
                        if(count($output)) $mid_list = array_keys($output);
                        $module_srl = $oModuleModel->getModuleSrlByMid($mid_list);
                    }
                }
            } else $module_srl = explode(',',$args->module_srls);
			
            $obj->module_srl = implode(",",$module_srl);
            $obj->sort_index = $widget_info->order_target;
            $obj->order_type = $widget_info->order_type=="desc"?"asc":"desc";
            $obj->list_count = $widget_info->rows_list_count;
			
			
			for($i=0; $i<count($module_srl);$i++){
				$midInfo[$i] =  $module_srl[$i];
				$oModuleModel = &getModel('module');
				$module_info[$i] = $oModuleModel->getModuleInfoByModuleSrl($module_srl[$i]);
				$module_name[$i] = $module_info[$i]->browser_title;
				$mid_info[$i] = $module_info[$i]->mid;
			}			
			$widget_info->midCount = $midInfo;
			$widget_info->module_name = $module_name;
			$widget_info->mid_name = $mid_info;
			
			for($i=0; $i<count($module_srl);$i++){
				$obj->module_srl = $widget_info->midCount[$i];
            	$output = executeQueryArray('widgets.webcon_verticalTab.getNewestDocuments', $obj);
				
				if(count($output->data)) {
					foreach($output->data as $key => $attribute) {
						$document_srl = $attribute->document_srl;
						$oDocument = null;
						$oDocument = new documentItem();
						$oDocument->setAttribute($attribute, false);
						$GLOBALS['XE_DOCUMENT_LIST'][$oDocument->document_srl] = $oDocument;
						if($document_srl){
							$document_list[$i][$key] = $oDocument;							
						}						
					}
					$oDocumentModel->setToAllDocumentExtraVars();
				}
				
				$obj->module_srl = $module_srl[$i];
				$output2 = executeQueryArray('widgets.webcon_verticalTab.getCategories',$obj);
				if(count($output2->data)) {
					foreach($output2->data as $key => $val) {	
						$category_lists[$i][$key] = $val->category_srl;
						$category_lists_parentSrl[$val->category_srl] = $val->parent_srl;
						$category_lists_title[$i][$key] = $val->title;
					}
				}
			}
			
			$widget_info->cateInfo = $category_lists;
			$widget_info->cateparentSrl = $category_lists_parentSrl;
			$widget_info->cateTitle = $category_lists_title;
            $widget_info->document_list = $document_list;
			
            Context::set('widget_info', $widget_info);
            $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
            Context::set('colorset', $args->colorset);
            $tpl_file = 'list';
            $oTemplate = &TemplateHandler::getInstance();
            $output = $oTemplate->compile($tpl_path, $tpl_file);
            return $output;
        }
    }
?>
