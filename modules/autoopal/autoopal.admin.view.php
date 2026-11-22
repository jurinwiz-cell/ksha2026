<?php
/**
 * @class  autoopalAdminView
 * @author 개발자
 * @brief  autoopal 모듈의 관리자 View 클래스
 */
class autoopalAdminView extends autoopal
{

    /**
     * 초기화
     */
    public function init()
    {
        // 템플릿 경로 설정 (modules/autoopal/tpl)
        $this->setTemplatePath($this->module_path . 'tpl');
    }

    /**
     * 관리자 메인 페이지 (Opal 문서 생성 폼)
     */
    public function dispAutoopalAdminIndex()
    {
        // 1. 문서/페이지를 작성할 수 있는 사이트 내 전체 모듈(게시판/페이지 등) 목록 조회
        $oModuleModel = getModel('module');

        // 게시판 및 페이지 모듈 검색
        $mid_list = $oModuleModel->getMidList();
        $module_list = array();
        if ($mid_list) {
            foreach ($mid_list as $mid_info) {
                if (in_array($mid_info->module, array('board', 'page', 'article', 'kin'))) {
                    $module_list[] = $mid_info;
                }
            }
        }

        // 템플릿 변수로 전달
        Context::set('module_list', $module_list);

        // 출력할 템플릿 파일 지정 (tpl/index.html)
        $this->setTemplateFile('index');
    }
}