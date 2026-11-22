<?php
class OpalImporterController extends ModuleObject {

    public function procOpalImporterCreatePage() {
        // 1. 권한 체크 (관리자만 실행 가능하도록)
        $logged_info = Context::get('logged_info');
        if (!$logged_info || $logged_info->is_admin != 'Y') {
            return new BaseObject(-1, '권한이 없습니다.');
        }

        // 2. 요청 파라미터 수신
        $target_module_srl = Context::get('target_module_srl'); // 생성할 게시판/페이지의 module_srl
        $title = Context::get('title');                       // 문서 제목
        $opal_xml_content = Context::get('opal_xml_content');   // Opal XML 또는 파싱된 HTML

        if (!$target_module_srl || !$title || !$opal_xml_content) {
            return new BaseObject(-1, '필수 항목이 누락되었습니다.');
        }

        // 3. Opal XML/HTML 추출 및 정제 (HTML 컨텐츠 파싱)
        $parsed_html = $this->extractHtmlFromOpalContent($opal_xml_content);

        // 4. 라이믹스 문서(Document) 객체 생성 및 저장
        $oDocumentController = getController('document');
        $obj = new stdClass();
        $obj->module_srl = $target_module_srl;
        $obj->title = $title;
        $obj->content = $parsed_html;
        $obj->member_srl = $logged_info->member_srl;
        $obj->nick_name = $logged_info->nick_name;
        $obj->is_notice = 'N';
        $obj->allow_comment = 'Y';
        $obj->status = 'PUBLIC'; // 즉시 출판

        $output = $oDocumentController->insertDocument($obj);

        if (!$output->toBool()) {
            return $output;
        }

        $this->setMessage('성공적으로 페이지가 생성되었습니다. (문서 번호: ' . $output->get('document_srl') . ')');
    }

    /**
     * Opal XML에서 HTML/PHP 템플릿 내용을 파싱 및 정합하는 메서드
     */
    private function extractHtmlFromOpalContent($content) {
        // XML 파싱 logic 또는 정규식을 통해 HTML 추출
        // 라이믹스 본문에 맞게 Clean-up 후 반환
        return $content; 
    }
}