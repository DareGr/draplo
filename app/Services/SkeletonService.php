<?php

namespace App\Services;

use InvalidArgumentException;

class SkeletonService
{
    private const VERSIONS = ['10', '11', '12', '13'];

    private const PHP_VERSIONS = [
        '10' => '8.1',
        '11' => '8.2',
        '12' => '8.3',
        '13' => '8.3',
    ];

    public function getSupportedVersions(): array
    {
        return self::VERSIONS;
    }

    public function getSkeletonPath(string $version): string
    {
        if (!in_array($version, self::VERSIONS, true)) {
            throw new InvalidArgumentException("Unsupported Laravel version: {$version}");
        }

        return storage_path("app/skeletons/laravel-{$version}");
    }

    public function getSharedPath(): string
    {
        return storage_path('app/skeletons/shared');
    }

    public function getPhpVersion(string $laravelVersion): string
    {
        return self::PHP_VERSIONS[$laravelVersion]
            ?? throw new InvalidArgumentException("Unknown Laravel version: {$laravelVersion}");
    }

    public function isVersionCompatible(string $selectedVersion, string $minVersion): bool
    {
        return (int) $selectedVersion >= (int) $minVersion;
    }
}
