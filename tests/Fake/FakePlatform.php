<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Fake;

use Hyperthese\PhpSerial\Platform\PlatformInterface;
use Hyperthese\PhpSerial\SerialSettings;

/**
 * In-memory platform used by tests that do not care about the OS layer.
 */
final class FakePlatform implements PlatformInterface
{
    /** @var array<int, SerialSettings> */
    public array $configuredSettings = [];

    /** @var array<int, string> */
    public array $resolvedDevices = [];

    public function __construct(
        private readonly string $devicePath = '/dev/null',
        private readonly bool $exists = true,
    ) {
    }

    public function name(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function deviceExists(string $device): bool
    {
        return $this->exists;
    }

    public function resolveDevice(string|int $device): string
    {
        $this->resolvedDevices[] = (string) $device;

        return $this->devicePath;
    }

    public function configure(string $device, SerialSettings $settings): void
    {
        $this->configuredSettings[] = $settings;
    }

    public function defaultOpenMode(): string
    {
        return 'r+b';
    }

    public function supportsNonBlockingOpen(): bool
    {
        return false;
    }
}
