<?php
    /**
     * @author XENARA (kolaskks@naver.com)
     * @version 0.1
     **/

    class widget_update_document extends WidgetHandler {

        function proc($args) {

          //AJAX 설정 코드
          if(Context::get('ajax')=='y'){
            //JSON 기본값 설정
            $result_json = array( 0 => 'fail');

            //문서 업데이트 권한체크
            if($args->update_group){
              $isUpdateGroup = 'N';
              if(Context::get('logged_info')){
                $logged_info = Context::get('logged_info');
                $update_group = explode(',',$args->update_group);
                foreach($update_group as $key => $group_srl){
                  if(array_key_exists($group_srl,$logged_info->group_list)){
                    $isUpdateGroup = 'Y';
                    break;
                  }
                }
              }
              if($isUpdateGroup!='Y'){
                //JSON 파일 출력 설정
                $result_json = json_encode($result_json);
                header('Content-type: application/json');
                echo($result_json);
                exit();
              }
            }

            //문서 업데이트 설정
            $docu_obj = Context::getRequestVars();
            if($docu_obj->item_document_srl){
              $docu_obj->document_srl = $docu_obj->item_document_srl;
              $docu_obj->regdate = $docu_obj->regdate.date('His');
              $docu_obj->last_update = $docu_obj->last_update.date('His');
              $output = executeQuery("widgets.widget_update_document.updateDocument", $docu_obj);
              if($output->toBool()){
                $result_json = array( 0 => 'success');
              }
            }
            //JSON 파일 출력 설정
            $result_json = json_encode($result_json);
            header('Content-type: application/json');
            echo($result_json);
            exit();
          }



          //AJAX 아닌 일반설정 코드
          if(Context::get('ajax')!='y'){
            //기본 객체 설정
            $oDocumentModel = &getModel('document');
            Context::set('oDocumentModel',$oDocumentModel);

            //목록설정 기본값 설정
            if(!$args->module_srls) $args->module_srls = null;
            if(!$args->sort_index) $args->sort_index = 'regdate';
            if(!$args->order_type) $args->order_type = 'desc';
            if(!$args->list_count) $args->list_count = 20;
            if(!$args->page_count) $args->page_count = 10;

            //게시글 목록설정
            $docu_obj = new stdClass();
            if(Context::get('selected_module_srl')){
              $docu_obj->module_srl = Context::get('selected_module_srl');
            } else{
              $docu_obj->module_srl = $args->module_srls;
            }
            if(Context::get('sort_index')){
              $docu_obj->sort_index = Context::get('sort_index');
            } else{
              $docu_obj->sort_index = $args->sort_index;
            }
            if(Context::get('order_type')){
              $docu_obj->order_type = Context::get('order_type');
            } else{
              $docu_obj->order_type = $args->order_type;
            }
            $docu_obj->list_count = $args->list_count;
            $docu_obj->page_count = $args->page_count;
            if(Context::get('page')){
              $docu_obj->page = Context::get('page');
              $page = Context::get('page');
            } else{
              $docu_obj->page = 1;
              $page = 1;
            }
            $docu_output = executeQueryArray('document.getDocumentList', $docu_obj);
            Context::set('document_list',$docu_output->data);
            Context::set('page_navigation',$docu_output->page_navigation);
            Context::set('page',$page);
            Context::set('total_count',$docu_output->total_count);
            Context::set('total_page_count',$docu_output->total_page_count);

            //선택한 모듈정보 설정
            $module_obj = new stdClass();
            $module_obj->module_srls = $args->module_srls;
            $module_output = executeQueryArray('module.getMidList', $module_obj);
            Context::set('module_list',$module_output->data);

            //권한설정 기본값 설정
            if(!$args->update_group) $args->update_group = null;

            //기타설정 기본값 설정
            if(!$args->subject_cut_size) $args->subject_cut_size = null;

            //템플릿 공통 출력변수 설정
            $widget_info->skin = $args->skin;
            $widget_info->module_srls = $args->module_srls;
            $widget_info->sort_index = $args->sort_index;
            $widget_info->order_type = $args->order_type;
            $widget_info->list_count = $args->list_count;
            $widget_info->page_count = $args->page_count;
            $widget_info->update_group = $args->update_group;
            $widget_info->subject_cut_size = $args->subject_cut_size;
            Context::set('widget_info', $widget_info);

            //템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
            $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
            Context::set('colorset', $args->colorset);

            //템플릿 파일을 지정
            $tpl_file = 'update_document';

            //템플릿 컴파일
            $oTemplate = &TemplateHandler::getInstance();
            $output = $oTemplate->compile($tpl_path, $tpl_file);
            return $output;
          }

        }
    }
?>
