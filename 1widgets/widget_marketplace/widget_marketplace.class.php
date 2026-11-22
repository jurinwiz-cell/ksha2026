<?php
    /**
     * @author XENARA (kolaskks@naver.com)
     * @version 0.1
     **/

 


    class widget_marketplace extends WidgetHandler {

        function proc($args) {

            $oDocumentModel = &getModel('document');
            $oMarketPlaceModel = &getModel('marketplace');

            //목록 변수 설정
            $market_obj->module_srl = $args->module_srls;
            if($args->category_srls){
              $market_obj->category_srl = $args->category_srls;
            }
            if($args->item_status){
              $market_obj->item_status = $args->item_status;
            } else{
              $market_obj->item_status = '';
            }
            if($args->list_count){
              $market_obj->list_count = $args->list_count;
            } else{
              $market_obj->list_count = 20;
            }
            if($args->order_target){
              $market_obj->sort_index = $args->order_target;
            } else{
              $market_obj->sort_index = 'regdate';
            }
            if($args->order_type){
              $market_obj->order_type = $args->order_type;
            } else{
              $market_obj->order_type = 'desc';
            }


            //마켓플레이스 리스트 쿼리 및 변수 설정
            //$marketplace_output = executeQueryArray('margetpace.getMarketplaceItemList', $market_obj);
            $marketplace_output = $oMarketPlaceModel->getMarketplaceItemList($market_obj);
            $marketplace_list = $marketplace_output->data;
            if($args->shuffle_type){
              $market_obj->shuffle_type = $args->shuffle_type;
            } else{
              $market_obj->shuffle_type = 'N';
            }
            if($args->shuffle_type=='Y') shuffle($marketplace_list);


            //제목내용 변수 설정
            if($args->subject_cut_size){
              $market_obj->subject_cut_size = $args->subject_cut_size;
            } else{
              $market_obj->subject_cut_size = 0;
            }
            if($args->content_cut_size){
              $market_obj->content_cut_size = $args->content_cut_size;
            } else{
              $market_obj->content_cut_size = 50;
            }


            //썸네일 변수 설정
            if($args->thumbnail_width){
              $market_obj->thumbnail_width = $args->thumbnail_width;
            } else{
              $market_obj->thumbnail_width = 100;
            }
            if($args->thumbnail_height){
              $market_obj->thumbnail_height = $args->thumbnail_height;
            } else{
              $market_obj->thumbnail_height = 100;
            }
            if($args->thumbnail_type){
              $market_obj->thumbnail_type = $args->thumbnail_type;
            } else{
              $market_obj->thumbnail_type = 'crop';
            }


            //사용자정의 출력 설정
            if($args->extra_show_type){
              $market_obj->extra_show_type = $args->extra_show_type;
            } else{
              $market_obj->extra_show_type = 'all';
            }
            if($args->extra_eid){
              $market_obj->extra_eid = $args->extra_eid;
              $market_obj->extra_eids = explode('',$market_obj->extra_eid);
            }


            //기타 설정
            if($args->new_window){
              $market_obj->new_window = $args->new_window;
            } else{
              $market_obj->new_window = '_self';
            }
            if($args->duration_new){
              $market_obj->duration_new = $args->duration_new*3600;
            } else{
              $market_obj->duration_new = 12*3600;
            }
            if($args->date_format){
              $market_obj->date_format = $args->date_format;
            } else{
              $market_obj->date_format = 'Y.m.d';
            }


            //문구 설정
            if($args->won_format_text){
              $market_obj->won_format_text = $args->won_format_text;
            } else{
              $market_obj->won_format_text = '원';
            }
            if($args->product_price_text){
              $market_obj->product_price_text = $args->product_price_text;
            } else{
              $market_obj->product_price_text = '상품가격';
            }
            if($args->product_soldout_text){
              $market_obj->product_soldout_text = $args->product_soldout_text;
            } else{
              $market_obj->product_soldout_text = '판매완료';
            }
            if($args->product_cancel_text){
              $market_obj->product_cancel_text = $args->product_cancel_text;
            } else{
              $market_obj->product_cancel_text = '판매중단';
            }
            if($args->product_selling_text){
              $market_obj->product_selling_text = $args->product_selling_text;
            } else{
              $market_obj->product_selling_text = '판매중';
            }


            //스킨명 변수설정
            $market_obj->skin = $args->skin;

            //템플릿 변수 설정
            Context::set('market_obj', $market_obj);
            Context::set('colorset', $args->colorset);
            Context::set('marketplace_list', $marketplace_list);


            //템플릿 객체생성 및 스킨파일 컴파일
            $oTemplate = &TemplateHandler::getInstance();
            $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
            return $oTemplate->compile($tpl_path, "marketplace");

        }
    }
?>
