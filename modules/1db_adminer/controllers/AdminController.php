<?php

declare(strict_types=1);

namespace Richrix\Modules\DbAdminer\Controllers;

use ModuleObject;
use Rhymix\Framework\Exception;
use Richrix\Modules\DbAdminer\Services\InstallerService;
use Richrix\Modules\DbAdminer\Services\SessionService;

final class AdminController extends ModuleObject
{
    /** @throws Exception */
    public function dispDb_adminerAdminIndex(): void
    {
        if (InstallerService::checkUpdateRequired()) {
            InstallerService::installLauncher();
            InstallerService::installAdminer();
        }
        SessionService::createTokenSession();
        $this->setRedirectUrl(RX_BASEURL . 'files/db_adminer/launcher.php');
    }
}
