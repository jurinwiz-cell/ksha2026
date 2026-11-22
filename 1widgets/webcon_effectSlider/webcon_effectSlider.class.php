<?php
    class webcon_effectSlider extends WidgetHandler {
        function proc($args) {
			// 슬라이드 종류
            $widget_info->effect_target = $args->effect_target;
            if(!$widget_info->effect_target) $widget_info->effect_target = 'scrollHorz';
		
			// 슬라이드 딜레이 시간
            $widget_info->slide_delay = (int)$args->slide_delay;
            if(!$widget_info->slide_delay) $widget_info->slide_delay = 5000;
			
			//슬라이드 자동스크롤
			if($args->slide_autoscroll!='Y') $widget_info->slide_autoscroll = 'N';
            else $widget_info->slide_autoscroll = 'Y';
			
			//슬라이드 네비버튼
			if($args->slide_navi!='Y') $widget_info->slide_navi = 'N';
            else $widget_info->slide_navi = 'Y';
			
            // 썸네일 생성 방법
            $widget_info->thumbnail_type = $args->thumbnail_type;
            if(!$widget_info->thumbnail_type) $widget_info->thumbnail_type = 'crop';

            // 썸네일 가로 크기
            $widget_info->thumbnail_width = (int)$args->thumbnail_width;
            if(!$widget_info->thumbnail_width) $widget_info->thumbnail_width = 990;

            // 썸네일 세로 크기
            $widget_info->thumbnail_height = (int)$args->thumbnail_height;
            if(!$widget_info->thumbnail_height) $widget_info->thumbnail_height = 410;

            // 목록수
            $widget_info->img_list_count = (int)$args->img_list_count;
            if(!$widget_info->img_list_count) $widget_info->img_list_count = 5;

			
            // 정렬 대상
            $widget_info->order_target = $args->order_target;
            if(!in_array($widget_info->order_target, array('list_order','update_order'))) $widget_info->order_target = 'list_order';

            // 정렬 순서
            $widget_info->order_type = $args->order_type;
            if(!in_array($widget_info->order_type, array('asc','desc'))) $widget_info->order_type = 'asc';

		
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
            $obj->list_count = $widget_info->img_list_count;

            $output = executeQueryArray('widgets.webcon_effectSlider.getNewestDocuments', $obj);

            // document 모듈의 model 객체를 받아서 결과를 객체화 시킴
            $oDocumentModel = &getModel('document');

            // 오류가 생기면 그냥 무시
            if(!$output->toBool()) return;

            // 결과가 있으면 각 문서 객체화를 시킴
            if(count($output->data)) {
                foreach($output->data as $key => $attribute) {
                    $document_srl = $attribute->document_srl;

                    $oDocument = null;
                    $oDocument = new documentItem();
                    $oDocument->setAttribute($attribute, false);
                    $GLOBALS['XE_DOCUMENT_LIST'][$oDocument->document_srl] = $oDocument;

                    $document_list[$key] = $oDocument;
                }
                $oDocumentModel->setToAllDocumentExtraVars();
            } else {

                $document_list = array();
                
            }

            $document_count = count($document_list);
            $total_count = $widget_info->img_list_count ;
            for($i=$document_count;$i<$total_count;$i++) {
				if($document_list[$i]){
					$document_list[] = new DocumentItem();
				}
			}

            $widget_info->document_list = $document_list;

            Context::set('widget_info', $widget_info);

            // 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
            $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
            Context::set('colorset', $args->colorset);

            // 템플릿 파일을 지정
            $tpl_file = 'list';

            // 템플릿 컴파일
            $oTemplate = &TemplateHandler::getInstance();
            $output = $oTemplate->compile($tpl_path, $tpl_file);
            return $output;
        }
    }
?>
