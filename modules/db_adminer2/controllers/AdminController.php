<?php

declare(strict_types=1);

namespace Richrix\Modules\DbAdminer\Controllers;

use Context;
use ModuleController;
use ModuleObject;
use Rhymix\Framework\Exception;
use Richrix\Modules\DbAdminer\Services\ConfigService;
use Richrix\Modules\DbAdminer\Services\InstallerService;
use Richrix\Modules\DbAdminer\Services\SessionService;

final class AdminController extends ModuleObject
{
    public function dispDb_adminerAdminIndex(): void
    {
        if (InstallerService::checkUpdateRequired()) {
            InstallerService::installLauncher();
            InstallerService::installAdminer();
        }

        if (ConfigService::hasPassword() && !ConfigService::isPasswordSessionValid()) {
            $this->setTemplatePath($this->module_path . 'views');
            $this->setTemplateFile('password');
            return;
        }

        if (ConfigService::hasPassword()) {
            ConfigService::createPasswordSession();
        }

        SessionService::createTokenSession();
        Context::set('launcher_url', RX_BASEURL . 'files/db_adminer/launcher.php');
        $config = ConfigService::getConfig();
        Context::set('access_password_set', ConfigService::hasPassword());
        Context::set('session_ttl', ConfigService::getSessionTtl());
        $this->setTemplatePath($this->module_path . 'views');
        $this->setTemplateFile('index');
    }

    public function procDb_adminerAdminSaveConfig(): void
    {
        $changePassword = Context::get('change_password') === 'Y';
        $password = (string) (Context::get('access_password') ?? '');
        $sessionTtl = (int) (Context::get('session_ttl') ?? 0);
        if ($sessionTtl < 0) {
            $sessionTtl = 0;
        }

        $config = ConfigService::getConfig();
        $passwordAlreadySet = ConfigService::hasPassword();
        if (!$passwordAlreadySet || $changePassword) {
            if ($password !== '') {
                $config->accessPassword = password_hash($password, PASSWORD_DEFAULT);
                ConfigService::clearPasswordSession();
            } elseif (isset($config->accessPassword)) {
                unset($config->accessPassword);
                ConfigService::clearPasswordSession();
            }
        }
        $config->sessionTtl = $sessionTtl;

        $result = ModuleController::getInstance()->insertModuleConfig('db_adminer', $config);
        if (!$result->toBool()) {
            throw new Exception(lang('msg_db_adminer_config_update_failed'));
        }

        $this->setMessage('success');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDb_adminerAdminIndex'));
    }

    public function procDb_adminerAdminVerifyPassword(): void
    {
        $password = (string) (Context::get('access_password') ?? '');
        if ($password === '' || !ConfigService::verifyPassword($password)) {
            throw new Exception(lang('msg_db_adminer_password_incorrect'));
        }
        ConfigService::createPasswordSession();
        $this->setMessage('success');
        $this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDb_adminerAdminIndex'));
    }
}
