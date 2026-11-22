<?php
/**
 * @class bannerWidget
 * @author 퍼니XE (funnyxe@simplesoft.io)
 * @brief 배너 출력 위젯
 **/

if(file_exists(_XE_PATH_.'modules/ad/ad.item.php'))
{
	require_once(_XE_PATH_.'modules/ad/ad.item.php');
}

class bannerWidget extends WidgetHandler
{

	/**
	 * @brief 위젯의 실행 부분
	 * ./widgets/위젯/conf/info.xml에 선언한 extra_vars를 args로 받는다
	 * 결과를 만든후 print가 아니라 return 해주어야 한다
	 **/
	public function proc($args)
	{
		// 언어팩 로드
		Context::loadLang(_XE_PATH_.'widgets/bannerWidget/lang');

		// 광고 모듈이 설치되어 있지 않은 경우 에러
		if(!file_exists(_XE_PATH_.'modules/ad/ad.item.php'))
		{
			return Context::getLang('msg_not_installed_ad_module');
		}

		// 대상 모듈
		if($args->module_srls)
		{
			$args->module_srls = (int) $args->module_srls;

			// module_srl로 mid 값 구함
			$oModuleModel = getModel('module');
			$oModuleInfo= $oModuleModel->getModuleInfoByModuleSrl($args->module_srls);

			// 넘어온 mid 값이 배열인지 확인
			if(is_array($oModuleInfo))
			{
				$oModuleInfo = $oModuleInfo[0];
			}

			// 대상 모듈이 광고 모듈인지 확인
			if($oModuleInfo->module != 'ad')
			{
				return Context::getLang('msg_linead_invalid_target_module');
			}

			$widget_info->linead_mid = $oModuleInfo->mid;
		}

		// module_srl 또는 mid 값을 찾지 못했을 경우 에러
		if(!$args->module_srls || !$widget_info->linead_mid)
		{
			return Context::getLang('msg_not_founded');
		}

		$oTemplate = TemplateHandler::getInstance();

		// 넘어온 변수들을 확인하고 잘못되었으면 기본 값 지정
		if($args->scroll_stop != 'N')
		{
			$args->scroll_stop ='Y'; // 마우스 올릴 시 스크롤 중단 (기본값 : 사용)
		}
		if(!(int)$args->scroll_speed) $args->scroll_speed = 50; // 스크롤 속도 (기본값 : 50)
		if(!(int)$args->scroll_rate) $args->scroll_rate = 3500; // 스크롤 간격 (기본값 : 3500)

		// 위젯 정보에 넣기
		$widget_info->scroll_stop = $args->scroll_stop;
		$widget_info->scroll_speed = $args->scroll_speed;
		$widget_info->scroll_rate = $args->scroll_rate;
		$widget_info->slider_width = $args->slider_width;

		// 광고 모듈의 model 객체 생성
		$oAdModel = getModel('ad');

		$args->module_srl = $args->module_srls;
		$args->end_date = date('YmdHis');
		$args->no_page = 'Y';

		$output = $oAdModel->getAdList($args);

		$widget_info->ad_list = $output->data;

		Context::set('colorset', $args->colorset);
		Context::set('widget_info', $widget_info);

		// 템플릿의 스킨 경로를 지정
		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
		Context::set('colorset', $args->colorset);

		// 템플릿 컴파일
		return $oTemplate->compile($tpl_path, 'content');
	}
}
?>
