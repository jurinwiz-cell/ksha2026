<?php
    /**
     * @class lavalic_show
     * @author VHAIN (vhain6512@gmail.com)
     * @brief lavalic 모듈 연동 플레이어 보이기
     * @version 0.6
     * @updated PHP 8.4 / Rhymix 최신 버전 호환
     **/

    class lavalic_show extends WidgetHandler {

        /**
         * @brief 위젯의 실행 부분
         **/
        function proc($args) {
            // 스킨 경로 설정
            $tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);

            Context::set('lavalic_module',     $args->lavalic_module);
            Context::set('lavalic_popup_text', $args->lavalic_popup_text);

            // zbxe_folder 대신 라이믹스 방식으로 기본 URL 전달
            Context::set('zbxe_folder', RX_BASEURL);

            // 템플릿 컴파일
            $oTemplate = TemplateHandler::getInstance();
            return $oTemplate->compile($tpl_path, 'lavalic');
        }
    }
?>
