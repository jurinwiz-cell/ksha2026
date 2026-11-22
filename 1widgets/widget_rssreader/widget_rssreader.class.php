<?php
    /**
     * @author XENARA (kolaskks@naver.com)
     * @version 0.1
     **/


    class widget_rssreader extends WidgetHandler {

        function proc($args) {
          //기본객체 설정
          $oModuleModel = &getModel('module');
          Context::set('oModuleModel', $oModuleModel);
          $oDocumentModel = &getModel('document');
          Context::set('oDocumentModel', $oDocumentModel);

          //모바일 체크 변수 설정
          $mobile_check = 'N';
          if(!Mobile::isMobilePadCheckByAgent() && (Mobile::isMobileCheckByAgent() || Context::get('m')==1)){
            $mobile_check = 'Y';
          }
          Context::set('mobile_check', $mobile_check);


          //RSS 목록 설정
          if($args->output_rsssearch=='Y'){
            if(Context::get('rss_url')){
              $rss_url = Context::get('rss_url');
            } else{
              $rss_url = $args->rss_url;
            }
          } else{
            $rss_url = $args->rss_url;
          }
          if($rss_url){
        		$rss_url = str_replace('&amp;','&',Context::convertEncodingStr($rss_url));
		        $buff = FileHandler::getRemoteResource($rss_url, null, 3, 'GET', 'application/xml');
            $buff = str_replace("\r","",$buff);
            $buff = str_replace("\n","",$buff);
            $buff = str_replace("\t","",$buff);
            $buff = str_replace("\r\n","",$buff);
            $buff = str_replace("\n\r","",$buff);

        		$encoding = preg_match("/<\?xml.*encoding=\"(.+)\".*\?>/i", $buff, $matches);
        		if($encoding && stripos($matches[1], "UTF-8") === FALSE) $buff = Context::convertEncodingStr($buff);
            $buff = preg_replace("/<\?xml.*\?>/i", "", $buff);

            $xml = simplexml_load_string((string)$buff, null, LIBXML_NOCDATA);
            $xml_json = json_encode($xml);
            $json_array = json_decode($xml_json,TRUE);
            //한글깨짐 해결(EUC-KR일 경우, UTF-8 변경) - 한겨레,대구시청 등
            if(!$json_array){
              $buff = iconv("EUC-KR", "UTF-8", $buff);
              $xml = simplexml_load_string((string)$buff, null, LIBXML_NOCDATA);
              $xml_json = json_encode($xml);
              $json_array = json_decode($xml_json,TRUE);
            }

            //최종 RSS 설정
            if($json_array){
              //RSS 정보 설정
              $rss_info = array();
              $rss_info['title'] = $json_array['channel']['title'];
              $rss_info['link'] = $json_array['channel']['link'];
              $rss_info['description'] = $json_array['channel']['description'];
              $rss_info['copyright'] = $json_array['channel']['copyright'];
              $rss_info['lastBuildDate'] = $json_array['channel']['lastBuildDate'];
              Context::set('json_array', $json_array);
              //RSS 아이템 설정(item['author'],item['category'],item['title'],item['link'],item['pubDate'],item['description'])
              $rss_items = $json_array['channel']['item'];
            }
            Context::set('rss_info', $rss_info);
            Context::set('rss_items', $rss_items);
          }


          //템플릿 RSS 목록 설정 변수 설정
          $rss_obj->rss_url = $args->rss_url;
          if($args->list_count){
            $rss_obj->list_count = $args->list_count;
          } else{
            $rss_obj->list_count = count($rss_items);
          }
          if($args->subject_cut_size){
            $rss_obj->subject_cut_size = $args->subject_cut_size;
          } else{
            $rss_obj->subject_cut_size = 0;
          }
          if($args->output_pubdate){
            $rss_obj->output_pubdate = $args->output_pubdate;
          } else{
            $rss_obj->output_pubdate = 'Y';
          }
          if($args->pubdate_cut_size){
            $rss_obj->pubdate_cut_size = $args->pubdate_cut_size;
          } else{
            $rss_obj->pubdate_cut_size = 15;
          }

          //템플릿 기타 변수 설정
          $rss_obj->output_rsssearch = $args->output_rsssearch;
          if($args->rss_title){
            $rss_obj->rss_title = $args->rss_title;
          } else{
            $rss_obj->rss_title = $rss_info['title'];
          }
          if($args->rss_title_url){
            $rss_obj->rss_title_url = $args->rss_title_url;
          } else{
            $rss_obj->rss_title_url = $rss_info['link'];
          }
          //템플릿 문구 변수 설정
          if($args->text_rss_url){
            $rss_obj->text_rss_url = $args->text_rss_url;
          } else{
            $rss_obj->text_rss_url = 'RSS URL';
          }
          //템플릿 변수 객체 설정
          Context::set('rss_obj', $rss_obj);


          //템플릿 객체생성 및 스킨파일 컴파일//
          $oTemplate = &TemplateHandler::getInstance();
          $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
          return $oTemplate->compile($tpl_path, "rssreader");
      }

    }
?>
