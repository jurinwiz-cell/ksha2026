<?php
if (!defined('__XE__')) exit();
/**
 * @file   counter_plus.class.php
 * @author 불패의초인 (BSplus) <admin@bsplus.net>
 * @brief  counter_plus 모듈 설치/업데이트 처리
 */
class counter_plus extends ModuleObject
{
    function moduleInstall()
    {
        $oModuleController = getController('module');
        $oModuleController->insertTrigger(
            'document.updateReadedCount',
            'counter_plus', 'controller',
            'triggerRecordViewLog', 'after'
        );
        return new BaseObject();
    }

    function checkUpdate()
    {
        $oDB = DB::getInstance();

        // 이전 버전 트리거가 남아 있으면 업데이트 필요
        $old_trigger = ModuleModel::getTrigger(
            'document.updateReadedCount',
            'counter_plus', 'controller',
            'triggerUpdateReadedCount', 'after'
        );
        if ($old_trigger) {
            return true;
        }

        // 이전 버전 테이블이 남아 있으면 업데이트 필요
        if ($oDB->isTableExists('counter_plus_view_log')) {
            return true;
        }

        // 새 트리거가 없으면 업데이트 필요
        $new_trigger = ModuleModel::getTrigger(
            'document.updateReadedCount',
            'counter_plus', 'controller',
            'triggerRecordViewLog', 'after'
        );
        if (!$new_trigger) {
            return true;
        }

        return false;
    }

    function moduleUpdate()
    {
        $oModuleController = getController('module');
        $oDB = DB::getInstance();

        // 구 트리거 제거
        $oModuleController->deleteTrigger(
            'document.updateReadedCount',
            'counter_plus', 'controller',
            'triggerUpdateReadedCount', 'after'
        );

        // 구 테이블 제거
        if ($oDB->isTableExists('counter_plus_view_log')) {
            $oDB->dropTable('counter_plus_view_log');
        }

        // 새 트리거 등록
        $new_trigger = ModuleModel::getTrigger(
            'document.updateReadedCount',
            'counter_plus', 'controller',
            'triggerRecordViewLog', 'after'
        );
        if (!$new_trigger) {
            $oModuleController->insertTrigger(
                'document.updateReadedCount',
                'counter_plus', 'controller',
                'triggerRecordViewLog', 'after'
            );
        }

        return new BaseObject();
    }

    function recompileCache()
    {
        return new BaseObject();
    }
}
