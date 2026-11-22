<?php

declare(strict_types=1);

namespace Richrix\Modules\DbAdminer\Types;

final class LatestReleaseResult
{
    public function __construct(
        public string $version,
        public string $downloadUrl,
        public string $sha256,
    )
    {
    }
}
