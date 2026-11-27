<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Platform;

use Hyperthese\PhpSerial\Exception\UnsupportedPlatformException;

/**
 * Creates the platform implementation for the current operating system.
 */
final class PlatformFactory
{
    public static function create(
        ?string $osFamily = null,
        ?CommandRunnerInterface $runner = null,
    ): PlatformInterface {
        $family = $osFamily ?? PHP_OS_FAMILY;
        $runner ??= new ProcessCommandRunner();

        return match ($family) {
            'Linux' => new UnixPlatform(UnixPlatform::LINUX, $runner),
            'Darwin' => new UnixPlatform(UnixPlatform::MACOS, $runner),
            'Windows' => new WindowsPlatform($runner),
            default => throw new UnsupportedPlatformException(sprintf(
                'The operating system "%s" is not supported. Only Linux, macOS and Windows are.',
                $family,
            )),
        };
    }
}
