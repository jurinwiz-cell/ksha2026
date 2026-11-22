<?php

declare(strict_types=1);

namespace Richrix\Modules\DbAdminer\Services;

use DB;
use FileHandler;
use ModuleController;
use ModuleModel;
use Rhymix\Framework\Exception;

final class InstallerService
{
    public const TARGET_DIR = RX_BASEDIR . 'files/db_adminer/';
    public const LAUNCHER_TARGET_PATH = self::TARGET_DIR . 'launcher.php';
    public const ADMINER_TARGET_PATH = self::TARGET_DIR . 'adminer.php';
    public const UPDATE_CHECK_INTERVAL = 86400;

    /**
     * 런처 파일과 Adminer 파일이 저장될 디렉토리를 생성합니다.
     *
     * @return void
     * @throws Exception
     */
    public static function installDirectory(): void
    {
        if (!is_dir(self::TARGET_DIR)) {
            if (!FileHandler::makeDir(self::TARGET_DIR)) {
                throw new Exception(lang('msg_db_adminer_directory_creation_failed'));
            }
        }
        if (!is_writable(self::TARGET_DIR)) {
            if (!chmod(self::TARGET_DIR, 0755)) {
                throw new Exception(lang('msg_db_adminer_directory_creation_failed'));
            }
        }
    }

    /**
     * 런처 파일을 설치합니다.
     *
     * @return void
     * @throws Exception
     */
    public static function installLauncher(): void
    {
        self::installDirectory();
        if (!self::checkLauncherUpdateRequired()) {
            return;
        }
        $launcherFileContent = self::getLauncherFileContent();
        if (!FileHandler::writeFile(self::LAUNCHER_TARGET_PATH, $launcherFileContent)) {
            throw new Exception(lang('msg_db_adminer_launcher_write_failed'));
        }
        if (!chmod(self::LAUNCHER_TARGET_PATH, 0600)) {
            throw new Exception(lang('msg_db_adminer_launcher_write_failed'));
        }
    }

    /**
     * 런처 파일이 업데이트가 필요한지 확인합니다.
     *
     * @return bool
     * @throws Exception
     */
    private static function checkLauncherUpdateRequired(): bool
    {
        $launcherFileContent = self::getLauncherFileContent();
        $currentLauncherContent = is_file(self::LAUNCHER_TARGET_PATH)
            ? file_get_contents(self::LAUNCHER_TARGET_PATH)
            : null;
        return !$currentLauncherContent || !hash_equals($launcherFileContent, $currentLauncherContent);
    }

    /**
     * Adminer 파일을 설치합니다.
     *
     * @return void
     * @throws Exception
     */
    public static function installAdminer(): void
    {
        self::installDirectory();
        $releaseInfo = AdminerService::fetchLatestRelease();
        $adminerFileContent = AdminerService::downloadAdminer($releaseInfo->downloadUrl, $releaseInfo->sha256);

        $oDB = DB::getInstance();
        $oDB->beginTransaction();

        $config = ModuleModel::getModuleConfig('db_adminer') ?? new \stdClass();
        $config->fetchedVersion = $releaseInfo->version;
        $config->version = $releaseInfo->version;
        $config->downloadUrl = $releaseInfo->downloadUrl;
        $config->sha256 = $releaseInfo->sha256;
        $config->lastUpdate = date('YmdHis');

        $result = ModuleController::getInstance()->insertModuleConfig('db_adminer', $config);
        if (!$result->toBool()) {
            $oDB->rollback();
            throw new Exception(lang('msg_db_adminer_config_update_failed'));
        }
        if (!FileHandler::writeFile(self::ADMINER_TARGET_PATH, $adminerFileContent)) {
            $oDB->rollback();
            throw new Exception(lang('msg_db_adminer_release_download_failed'));
        }
        if (!@chmod(self::ADMINER_TARGET_PATH, 0600)) {
            $oDB->rollback();
            throw new Exception(lang('msg_db_adminer_release_download_failed'));
        }
        $oDB->commit();
    }

    /**
     * 설치 또는 업데이트가 필요한지 확인합니다.
     *
     * @return bool
     * @throws Exception
     */
    public static function checkUpdateRequired(): bool
    {
        if (!is_dir(self::TARGET_DIR)) {
            return true;
        }
        if (!is_file(self::LAUNCHER_TARGET_PATH) || !is_file(self::ADMINER_TARGET_PATH)) {
            return true;
        }
        if (self::checkLauncherUpdateRequired()) {
            return true;
        }
        $config = ModuleModel::getModuleConfig('db_adminer');
        if (!$config || !isset($config->lastUpdate) || !isset($config->fetchedVersion) || !isset($config->version)) {
            return true;
        }
        $lastUpdate = strtotime((string) $config->lastUpdate);
        if ($lastUpdate === false) {
            return true;
        }
        if ((time() - $lastUpdate) < self::UPDATE_CHECK_INTERVAL) {
            return version_compare(
                version1: (string) $config->fetchedVersion,
                version2: (string) $config->version,
                operator: '>',
            );
        }

        try {
            $releaseInfo = AdminerService::fetchLatestRelease();
        } catch (Exception) {
            return false;
        }
        $config->fetchedVersion = $releaseInfo->version;
        $config->lastUpdate = date('YmdHis');
        ModuleController::getInstance()->insertModuleConfig('db_adminer', $config);

        return version_compare(
            version1: $config->fetchedVersion,
            version2: (string) $config->version,
            operator: '>',
        );
    }

    /**
     * 런처 파일의 템플릿 내용을 반환합니다.
     *
     * @return string
     * @throws Exception
     */
    public static function getLauncherFileContent(): string
    {
        $template = file_get_contents(__DIR__ . '/../resources/launcher.php.tpl');
        if (!$template) {
            throw new Exception(lang('msg_db_adminer_launcher_read_failed'));
        }
        return $template;
    }

    /**
     * 런처 파일과 Adminer 파일을 제거하고, 디렉토리를 삭제합니다.
     *
     * @return void
     * @throws Exception
     */
    public static function uninstall(): void
    {
        if (is_file(self::LAUNCHER_TARGET_PATH)) {
            if (!unlink(self::LAUNCHER_TARGET_PATH)) {
                throw new Exception(lang('msg_db_adminer_uninstall_failed'));
            }
        }
        if (is_file(self::ADMINER_TARGET_PATH)) {
            if (!unlink(self::ADMINER_TARGET_PATH)) {
                throw new Exception(lang('msg_db_adminer_uninstall_failed'));
            }
        }
        if (is_dir(self::TARGET_DIR)) {
            if (!rmdir(self::TARGET_DIR)) {
                throw new Exception(lang('msg_db_adminer_uninstall_failed'));
            }
        }
    }
}
