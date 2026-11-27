<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Unit\Platform;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\Exception\ConnectionException;
use Hyperthese\PhpSerial\FlowControl;
use Hyperthese\PhpSerial\Parity;
use Hyperthese\PhpSerial\Platform\WindowsPlatform;
use Hyperthese\PhpSerial\SerialSettings;
use Hyperthese\PhpSerial\StopBits;
use Hyperthese\PhpSerial\Tests\Fake\RecordingCommandRunner;
use Hyperthese\PhpSerial\TransferMode;
use PHPUnit\Framework\TestCase;

final class WindowsPlatformTest extends TestCase
{
    private RecordingCommandRunner $runner;

    private WindowsPlatform $platform;

    protected function setUp(): void
    {
        $this->runner = new RecordingCommandRunner();
        $this->platform = new WindowsPlatform($this->runner);
    }

    public function testResolveDeviceAddsTheLongPathPrefix(): void
    {
        self::assertSame('\\\\.\\COM3', $this->platform->resolveDevice('COM3'));
    }

    public function testResolveDeviceAcceptsIntegerPortNumbers(): void
    {
        self::assertSame('\\\\.\\COM10', $this->platform->resolveDevice(10));
    }

    public function testResolveDeviceNormalisesCaseAndTrailingColon(): void
    {
        self::assertSame('\\\\.\\COM12', $this->platform->resolveDevice('com12:'));
    }

    public function testResolveDeviceAcceptsAnAlreadyResolvedPath(): void
    {
        self::assertSame('\\\\.\\COM3', $this->platform->resolveDevice('\\\\.\\COM3'));
    }

    public function testResolveDeviceRejectsNonComNames(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->platform->resolveDevice('ttyS0');
    }

    public function testResolveDeviceRejectsPortZero(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->platform->resolveDevice('COM0');
    }

    public function testResolveDeviceRejectsPortsAbove256(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->platform->resolveDevice('COM300');
    }

    public function testPlatformMetadata(): void
    {
        self::assertSame('windows', $this->platform->name());
        self::assertSame('r+b', $this->platform->defaultOpenMode());
        self::assertFalse($this->platform->supportsNonBlockingOpen());
        self::assertTrue($this->platform->deviceExists('\\\\.\\COM3'));
        self::assertTrue($this->platform->isAvailable());
    }

    public function testModeCommandForDefaultSettings(): void
    {
        self::assertSame(
            'mode COM3: BAUD=9600 PARITY=N DATA=8 STOP=1 XON=OFF OCTS=OFF RTS=ON DTR=ON',
            $this->platform->modeCommand('\\\\.\\COM3', new SerialSettings()),
        );
    }

    public function testModeCommandForEveryNonDefaultSetting(): void
    {
        $settings = new SerialSettings(
            115200,
            Parity::Mark,
            7,
            StopBits::OneAndHalf,
            FlowControl::XonXoff,
            TransferMode::Binary,
            false,
        );

        self::assertSame(
            'mode COM12: BAUD=115200 PARITY=M DATA=7 STOP=15 XON=ON OCTS=OFF RTS=ON DTR=OFF',
            $this->platform->modeCommand('\\\\.\\COM12', $settings),
        );
    }

    public function testConfigureRunsTheModeCommand(): void
    {
        $settings = new SerialSettings(
            115200,
            Parity::Mark,
            7,
            StopBits::OneAndHalf,
            FlowControl::XonXoff,
            TransferMode::Binary,
            false,
        );

        $this->platform->configure('\\\\.\\COM12', $settings);

        self::assertSame(
            ['mode COM12: BAUD=115200 PARITY=M DATA=7 STOP=15 XON=ON OCTS=OFF RTS=ON DTR=OFF'],
            $this->runner->commands,
        );
    }

    public function testConfigureWrapsModeFailuresInConnectionException(): void
    {
        $this->runner->queueResult(1, '', 'Device COM3 is not currently available.');

        try {
            $this->platform->configure('\\\\.\\COM3', new SerialSettings());
            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('Device COM3 is not currently available.', $exception->getMessage());
        }
    }

    public function testConfigureDoesNotThrowOnSuccess(): void
    {
        $this->platform->configure('\\\\.\\COM3', new SerialSettings());

        self::assertCount(1, $this->runner->commands);
    }

    public function testModeCommandRejectsPathsWithoutTheLongPathPrefix(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->platform->modeCommand('COM3', new SerialSettings());
    }
}
