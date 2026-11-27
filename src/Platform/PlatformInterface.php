<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Platform;

use Hyperthese\PhpSerial\SerialSettings;

/**
 * Operating-system specific serial port behaviour.
 */
interface PlatformInterface
{
    /**
     * Short platform name, for example "linux", "macos" or "windows".
     */
    public function name(): string;

    /**
     * Whether the utility required to configure ports is available.
     */
    public function isAvailable(): bool;

    /**
     * Whether the resolved device path currently exists.
     *
     * Always true on platforms (Windows) where a COM port cannot be stat'ed.
     */
    public function deviceExists(string $device): bool;

    /**
     * Turns a user supplied device name (COM3, /dev/ttyUSB0, 2, ...) into a
     * path that can be passed to fopen().
     */
    public function resolveDevice(string|int $device): string;

    /**
     * Applies the settings to an existing device.
     *
     * @throws \Hyperthese\PhpSerial\Exception\ConnectionException
     * @throws \Hyperthese\PhpSerial\Exception\UnsupportedConfigurationException
     */
    public function configure(string $device, SerialSettings $settings): void;

    /**
     * fopen() mode used when the caller does not provide one.
     */
    public function defaultOpenMode(): string;

    /**
     * Whether fopen() can be made non-blocking with the "n" mode flag.
     */
    public function supportsNonBlockingOpen(): bool;
}
