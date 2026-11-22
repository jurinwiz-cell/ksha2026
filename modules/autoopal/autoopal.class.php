<?php
class autoopal extends ModuleObject
{
    /**
     * 모듈 설치 시 실행
     */
    public function moduleInstall()
    {
        return new BaseObject();
    }

    /**
     * 모듈 업데이트 필요 여부 확인
     */
    public function checkUpdate()
    {
        return false;
    }

    /**
     * 모듈 업데이트 실행
     */
    public function moduleUpdate()
    {
        return new BaseObject();
    }

    /**
     * 모듈 삭제 시 실행
     */
    public function moduleUninstall()
    {
        return new BaseObject();
    }
}