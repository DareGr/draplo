<?php

use App\Services\SkeletonService;

test('getSupportedVersions returns all four versions', function () {
    $service = new SkeletonService();
    expect($service->getSupportedVersions())->toBe(['10', '11', '12', '13']);
});

test('getPhpVersion returns correct PHP for each Laravel version', function () {
    $service = new SkeletonService();
    expect($service->getPhpVersion('10'))->toBe('8.1');
    expect($service->getPhpVersion('11'))->toBe('8.2');
    expect($service->getPhpVersion('12'))->toBe('8.3');
    expect($service->getPhpVersion('13'))->toBe('8.3');
});

test('getPhpVersion throws for invalid version', function () {
    $service = new SkeletonService();
    $service->getPhpVersion('99');
})->throws(\InvalidArgumentException::class);

test('isVersionCompatible checks correctly', function () {
    $service = new SkeletonService();
    expect($service->isVersionCompatible('12', '10'))->toBeTrue();
    expect($service->isVersionCompatible('10', '12'))->toBeFalse();
    expect($service->isVersionCompatible('11', '11'))->toBeTrue();
});

test('getSharedPath returns existing shared directory', function () {
    $service = new SkeletonService();
    $path = $service->getSharedPath();
    expect($path)->toEndWith('skeletons/shared');
    expect(is_dir($path))->toBeTrue();
});
