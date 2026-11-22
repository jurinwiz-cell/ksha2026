<?php
if (!defined('__XE__')) exit();
/**
 * @file   counter_plus.controller.php
 * @author 불패의초인 (BSplus) <admin@bsplus.net>
 */
class counter_plusController extends counter_plus
{
    function init() {}

    /**
     * document.updateReadedCount 트리거 — 포인트 설정과 무관하게 조회 로그 기록
     */
    function triggerRecordViewLog($obj)
    {
        $document_srl = (int)($obj->document_srl ?? 0);
        if (!$document_srl) return;

        $logged_info = Context::get('logged_info');
        $member_srl  = $logged_info ? (int)$logged_info->member_srl : 0;
        $ipaddress   = \RX_CLIENT_IP ?: '0.0.0.0';

        if ($member_srl)
        {
            // 로그인 사용자: 이미 기록이 있으면 스킵 (point 모듈과 중복 방지)
            $check = new stdClass;
            $check->member_srl   = $member_srl;
            $check->document_srl = $document_srl;
            $output = executeQuery('document.getDocumentReadedLogInfo', $check);
            if ($output->data && $output->data->count) return;
        }

        $args = new stdClass;
        $args->document_srl = $document_srl;
        $args->member_srl   = $member_srl;
        $args->ipaddress    = $ipaddress;
        $args->regdate      = date('YmdHis');
        executeQuery('document.insertDocumentReadedLog', $args);
    }

}
