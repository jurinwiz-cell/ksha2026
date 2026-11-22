<?php
	/**
	 * @class lineadWidget
	 * @author SMaker (dowon2308@paran.com)
	 * @brief 전광판 출력 위젯
	 **/

	if(file_exists(_XE_PATH_.'modules/ad/ad.item.php')) require_once(_XE_PATH_.'modules/ad/ad.item.php');

	class lineadWidget extends WidgetHandler {

		/**
		 * @brief 위젯의 실행 부분
		 * ./widgets/위젯/conf/info.xml에 선언한 extra_vars를 args로 받는다
		 * 결과를 만든후 print가 아니라 return 해주어야 한다
		 **/
		function proc($args) {
			// 언어팩 로드
			Context::loadLang(_XE_PATH_.'widgets/lineadWidget/lang');

			// 광고 모듈이 설치되어 있지 않은 경우 에러 출력 후 실행 중지
			if(!file_exists(_XE_PATH_.'modules/ad/ad.item.php')) return Context::getLang('msg_not_installed_ad_module');

			// 대상 모듈이 있을 경우
			if($args->module_srls) {
				$args->module_srls = (int)$args->module_srls;

				// module_srl로 mid 값 구함
				$oModuleModel = &getModel('module');
				$oModuleInfo= $oModuleModel->getModuleInfoByModuleSrl($args->module_srls);

				// 넘어온 mid 값이 배열인지 확인
				if(is_array($oModuleInfo)) $oModuleInfo = $oModuleInfo[0];

				// 대상 모듈이 광고 모듈인지 확인
				if($oModuleInfo->module != 'ad') return Context::getLang('msg_linead_invalid_target_module');

				$widget_info->linead_mid = $oModuleInfo->mid;
			}

			// module_srl 또는 mid 값을 찾지 못했을 경우 에러
			if(!$args->module_srls || !$widget_info->linead_mid) return Context::getLang('msg_not_founded');

			$oTemplate = &TemplateHandler::getInstance();

			// 넘어온 변수들을 확인하고 기본 값 지정
			$args->subject_cut_size = (int)$args->subject_cut_size;
			if(!$args->subject_cut_size) $args->subject_cut_size = 50; // 내용 길이 제한 (기본값 : 50)
			if($args->scroll_stop != 'N') $args->scroll_stop ='Y'; // 마우스 올릴 시 스크롤 중단 (기본값 : 사용)
			if($args->display_author != 'N') $args->display_author = 'Y'; // 등록자 표시 (기본값 : 사용)
			if($args->display_notice_head != 'N') $args->display_notice_head = 'Y'; // 공지 머릿말 표시 (기본값 : 사용)
			if(!(int)$args->scroll_speed) $args->scroll_speed = 50; // 스크롤 속도 (기본값 : 50)
			if(!(int)$args->scroll_rate) $args->scroll_rate = 3500; // 스크롤 간격 (기본값 : 3500)
			if($args->use_extra_info != 'Y') $args->use_extra_info = 'N'; // // 회원 확장 정보 사용 (기본값 : 사용 안함)
			if(!$args->sort_index) $args->sort_index = 'documents.list_order';
			if(!$args->order_type) $args->order_type = 'desc';
			if($args->sort_index == 'documents.regdate') $args->sort_index = 'documents.list_order';

			// 위젯 정보에 넣기
			$widget_info->subject_cut_size = $args->subject_cut_size;
			$widget_info->display_author = $args->display_author;
			$widget_info->display_notice_head = $args->display_notice_head;
			$widget_info->display_image_nick = $args->display_image_nick;
			$widget_info->scroll_stop = $args->scroll_stop;
			$widget_info->scroll_speed = $args->scroll_speed;
			$widget_info->scroll_rate = $args->scroll_rate;
			$widget_info->use_extra_info = $args->use_extra_info;

			// 광고 모듈의 model 객체 생성
			$oAdModel = &getModel('ad');

			$args->module_srl = $args->module_srls;
			$args->end_date = date('YmdHis');

			$output = $oAdModel->getAdList($args);

			$widget_info->ad_list = $output->data;

			Context::set('colorset', $args->colorset);
			Context::set('widget_info', $widget_info);

			// 템플릿의 스킨 경로를 지정
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
			Context::set('colorset', $args->colorset);

			// 템플릿 컴파일 후 return
			return $oTemplate->compile($tpl_path, 'content');
		}
	}
?>
