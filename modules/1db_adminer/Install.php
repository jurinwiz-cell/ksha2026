<?php

declare(strict_types=1);

namespace Richrix\Modules\DbAdminer;

use ModuleObject;
use Rhymix\Framework\Exception;
use Richrix\Modules\DbAdminer\Services\InstallerService;

final class Install extends ModuleObject
{
    /** @throws Exception */
    public function moduleInstall(): void
    {
        InstallerService::installLauncher();
    }

    public function checkUpdate(): bool
    {
        return InstallerService::checkUpdateRequired();
    }

    /** @throws Exception */
    public function moduleUpdate(): void
    {
        InstallerService::installLauncher();
        InstallerService::installAdminer();
    }

    /** @throws Exception */
    public function moduleUninstall(): void
    {
        InstallerService::uninstall();
    }
}
