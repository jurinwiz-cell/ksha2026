<?php

declare(strict_types=1);

namespace Richrix\Modules\DbAdminer\Services;

use FileHandler;
use Rhymix\Framework\Exception;
use Richrix\Modules\DbAdminer\Types\LatestReleaseResult;

final class AdminerService
{
    private const RELEASE_API_URL = 'https://api.github.com/repos/vrana/adminer/releases/latest';
    private const DOWNLOAD_URL_PREFIX = 'https://github.com/vrana/adminer/releases/download/';
    public const USER_AGENT = 'Richrix/DbAdminer';

    /**
     * GitHub API를 통해 Adminer의 최신 릴리스 정보를 가져옵니다.
     *
     * @return LatestReleaseResult
     * @throws Exception
     */
    public static function fetchLatestRelease(): LatestReleaseResult
    {
        $response = FileHandler::getRemoteResource(
            self::RELEASE_API_URL,
            null,
            10,
            'GET',
            null,
            [
                'Accept: application/vnd.github.v3+json',
                'User-Agent: ' . self::USER_AGENT,
            ],
        );
        if (!is_string($response)) {
            throw new Exception(lang('msg_db_adminer_release_fetch_failed'));
        }
        return self::parseLatestRelease($response);
    }

    /**
     * GitHub API 응답을 파싱하여 LatestReleaseResult 객체로 변환합니다.
     *
     * @param string $response
     *
     * @return LatestReleaseResult
     * @throws Exception
     */
    private static function parseLatestRelease(string $response): LatestReleaseResult
    {
        $json = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($json)) {
            throw new Exception(lang('msg_db_adminer_release_fetch_failed'));
        }
        if (!isset($json->tag_name) || !isset($json->assets) || !is_array($json->assets)) {
            throw new Exception(lang('msg_db_adminer_release_fetch_failed'));
        }

        $downloadUrl = '';
        $sha256 = '';
        foreach ($json->assets as $asset) {
            $name = (string) ($asset->name ?? '');
            if (!preg_match('/^adminer-\d+(?:\.\d+)*\.php$/', $name)) {
                continue;
            }
            if (!isset($asset->browser_download_url) || !is_string($asset->browser_download_url)) {
                continue;
            }
            $downloadUrl = $asset->browser_download_url;
            $digest = (string) ($asset->digest ?? '');
            if (str_starts_with($digest, 'sha256:')) {
                $sha256 = substr($digest, 7);
            }
            break;
        }
        if (!$downloadUrl) {
            throw new Exception(lang('msg_db_adminer_release_fetch_failed'));
        }
        return new LatestReleaseResult(
            ltrim((string) $json->tag_name, 'v'),
            $downloadUrl,
            $sha256
        );
    }

    /**
     * Adminer 파일을 다운로드합니다.
     *
     * @param string $downloadUrl
     * @param string $sha256
     *
     * @return string
     * @throws Exception
     */
    public static function downloadAdminer(string $downloadUrl, string $sha256): string
    {
        if (!str_starts_with($downloadUrl, self::DOWNLOAD_URL_PREFIX)) {
            throw new Exception(lang('msg_db_adminer_release_download_url_malformed'));
        }
        $content = FileHandler::getRemoteResource(
            $downloadUrl,
            null,
            10,
            'GET',
            null,
            [
                'User-Agent: ' . self::USER_AGENT,
            ],
        );
        if (!is_string($content)) {
            throw new Exception(lang('msg_db_adminer_release_download_failed'));
        }
        if ($sha256 === '') {
            throw new Exception(lang('msg_db_adminer_release_download_contents_malformed'));
        }
        if (!hash_equals($sha256, hash('sha256', $content))) {
            throw new Exception(lang('msg_db_adminer_release_download_contents_malformed'));
        }
        if (!str_starts_with($content, '<?php')) {
            throw new Exception(lang('msg_db_adminer_release_download_contents_malformed'));
        }
        return $content;
    }
}
