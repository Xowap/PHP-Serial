<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Unit\Platform;

use Hyperthese\PhpSerial\Exception\UnsupportedPlatformException;
use Hyperthese\PhpSerial\Platform\PlatformFactory;
use Hyperthese\PhpSerial\Platform\UnixPlatform;
use Hyperthese\PhpSerial\Platform\WindowsPlatform;
use Hyperthese\PhpSerial\Tests\Fake\RecordingCommandRunner;
use PHPUnit\Framework\TestCase;

final class PlatformFactoryTest extends TestCase
{
    public function testCreateBuildsTheLinuxPlatform(): void
    {
        $platform = PlatformFactory::create('Linux', new RecordingCommandRunner());

        self::assertInstanceOf(UnixPlatform::class, $platform);
        self::assertSame('linux', $platform->name());
    }

    public function testCreateBuildsTheMacOsPlatform(): void
    {
        $platform = PlatformFactory::create('Darwin', new RecordingCommandRunner());

        self::assertInstanceOf(UnixPlatform::class, $platform);
        self::assertSame('macos', $platform->name());
    }

    public function testCreateBuildsTheWindowsPlatform(): void
    {
        $platform = PlatformFactory::create('Windows', new RecordingCommandRunner());

        self::assertInstanceOf(WindowsPlatform::class, $platform);
    }

    public function testCreateRejectsUnknownPlatforms(): void
    {
        $this->expectException(UnsupportedPlatformException::class);

        PlatformFactory::create('Plan9', new RecordingCommandRunner());
    }
}
