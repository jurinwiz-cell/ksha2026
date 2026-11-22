<?php
    /**
     * @class picasa_recent_images
     * @author zero (zero@nzeo.com)
	 * refactoring to Picas webAlbum by Karma(soonj@nate.com)
     * @brief 구글 웹앨벙의 최근 이미지를 출력하는 위젯
     * @version 0.1
     **/

    class picasa_recent_images extends WidgetHandler {

        function proc($args) {
			$oModuleModel = &getModel('module');
	        $config = $oModuleModel->getModuleConfig('gdata');
			if(!$config->pass || !$config->user) return;
			$obj->module = 'gdata';
			$midlist=$oModuleModel->getMidList($obj);
            if(count($midlist)) {
                foreach($midlist as $key=>$val) {
                    $module_info = $oModuleModel->getModuleInfoByModuleSrl($val->module_srl);
                }
            }
            if($module_info) $widget_info->mid =$module_info->mid;
			if(!$module_info->mid) return;

			$clientLibraryPath = realpath('./modules/gdata');
			$oldPath = set_include_path(get_include_path() . PATH_SEPARATOR . $clientLibraryPath);
	        require_once 'Zend/Loader.php';
	        Zend_Loader::loadClass('Zend_Gdata');
    	    Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
        	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	        Zend_Loader::loadClass('Zend_Gdata_Photos');
    	    Zend_Loader::loadClass('Zend_Gdata_Photos_UserQuery');
        	Zend_Loader::loadClass('Zend_Gdata_Photos_AlbumQuery');
	        Zend_Loader::loadClass('Zend_Gdata_Photos_PhotoQuery');
    	    Zend_Loader::loadClass('Zend_Gdata_App_Extension_Category');

            // 글자 제목 길이
            $widget_info->subject_cut_size = (int)$args->subject_cut_size;
            if(!$widget_info->subject_cut_size) $widget_info->subject_cut_size = 10;

            // 세로 이미지 수
            $widget_info->rows_list_count = (int)$args->rows_list_count;
            if(!$widget_info->rows_list_count) $widget_info->rows_list_count = 1;

            // 가로 이미지 수
            $widget_info->cols_list_count = (int)$args->cols_list_count;
            if(!$widget_info->cols_list_count) $widget_info->cols_list_count = 5;

            // 노출 여부 체크
            if($args->display_timestamp!='Y') $widget_info->display_regdate = 'N';
            else $widget_info->display_timestamp = 'Y';

            // 제목
            $widget_info->widgettitle = $args->widgettitle;

            // 변수 정리
            $list_count = $widget_info->rows_list_count * $widget_info->cols_list_count;

			$oGdataModel = &getModel('gdata');
	        $client = $oGdataModel->getClient();	
	
			$output = $oGdataModel->getRecentPhoto($client,$config->user,$list_count);		
			Context::set('output',$output);
            Context::set('widget_info', $widget_info);
            // 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
            $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);

            // 템플릿 파일을 지정
            $tpl_file = 'list';

            // 템플릿 컴파일
            $oTemplate = &TemplateHandler::getInstance();
            $output = $oTemplate->compile($tpl_path, $tpl_file);
            return $output;
        }
    }
?>
