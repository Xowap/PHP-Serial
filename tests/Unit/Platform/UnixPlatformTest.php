<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Unit\Platform;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\Exception\ConnectionException;
use Hyperthese\PhpSerial\Exception\UnsupportedConfigurationException;
use Hyperthese\PhpSerial\FlowControl;
use Hyperthese\PhpSerial\Parity;
use Hyperthese\PhpSerial\Platform\UnixPlatform;
use Hyperthese\PhpSerial\SerialSettings;
use Hyperthese\PhpSerial\StopBits;
use Hyperthese\PhpSerial\Tests\Fake\RecordingCommandRunner;
use Hyperthese\PhpSerial\TransferMode;
use PHPUnit\Framework\TestCase;

final class UnixPlatformTest extends TestCase
{
    private RecordingCommandRunner $runner;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->runner = new RecordingCommandRunner();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $this->temporaryFiles = [];
    }

    public function testResolveDeviceMapsComNamesToTtySPorts(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame('/dev/ttyS0', $platform->resolveDevice('COM1'));
    }

    public function testResolveDeviceMapsIntegerPortNumbers(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame('/dev/ttyS1', $platform->resolveDevice(2));
    }

    public function testResolveDeviceKeepsAbsolutePathsUnchanged(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame('/dev/ttyUSB0', $platform->resolveDevice('/dev/ttyUSB0'));
    }

    public function testResolveDevicePrefixesRelativeDeviceNames(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame('/dev/ttyACM0', $platform->resolveDevice('ttyACM0'));
    }

    public function testResolveDevicePrefixesMacStyleDeviceNames(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame('/dev/cu.usbserial-1420', $platform->resolveDevice('cu.usbserial-1420'));
    }

    public function testResolveDeviceRejectsEmptyString(): void
    {
        $platform = $this->createLinuxPlatform();

        $this->expectException(ConfigurationException::class);

        $platform->resolveDevice('');
    }

    public function testResolveDeviceRejectsPortZero(): void
    {
        $platform = $this->createLinuxPlatform();

        $this->expectException(ConfigurationException::class);

        $platform->resolveDevice(0);
    }

    public function testNameReflectsTheUnixFamily(): void
    {
        self::assertSame('linux', $this->createLinuxPlatform()->name());
        self::assertSame('macos', $this->createMacPlatform()->name());
    }

    public function testOpenModeAndNonBlockingSupport(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame('r+bn', $platform->defaultOpenMode());
        self::assertTrue($platform->supportsNonBlockingOpen());
    }

    public function testDeviceExistsForRealAndMissingPaths(): void
    {
        $platform = $this->createLinuxPlatform();

        $existing = tempnam(sys_get_temp_dir(), 'php-serial-');
        self::assertIsString($existing);
        $this->temporaryFiles[] = $existing;

        self::assertTrue($platform->deviceExists($existing));
        self::assertFalse($platform->deviceExists($existing . '.missing'));
    }

    public function testLinuxSttyCommandForBinaryDefaults(): void
    {
        $platform = $this->createLinuxPlatform();
        $settings = new SerialSettings(
            9600,
            Parity::None,
            8,
            StopBits::One,
            FlowControl::None,
            TransferMode::Binary,
            true,
        );

        self::assertSame(
            "stty -F '/dev/ttyS0' 9600 cs8 -parenb -cstopb clocal -crtscts -ixon -ixoff "
            . '-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk -hupcl',
            $platform->sttyCommand('/dev/ttyS0', $settings),
        );
    }

    public function testMacOsSttyCommandUsesLowercaseDeviceFlag(): void
    {
        $platform = $this->createMacPlatform();
        $settings = new SerialSettings();

        self::assertSame(
            "stty -f '/dev/ttyS0' 9600 cs8 -parenb -cstopb clocal -crtscts -ixon -ixoff "
            . '-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk -hupcl',
            $platform->sttyCommand('/dev/ttyS0', $settings),
        );
    }

    public function testLinuxSttyCommandForTextModeWithEveryNonDefaultSetting(): void
    {
        $platform = $this->createLinuxPlatform();
        $settings = new SerialSettings(
            115200,
            Parity::Even,
            7,
            StopBits::Two,
            FlowControl::RtsCts,
            TransferMode::Text,
            false,
        );

        self::assertSame(
            "stty -F '/dev/ttyUSB0' 115200 cs7 parenb -parodd cstopb -clocal crtscts -ixon -ixoff "
            . 'icanon echo echoe echok iexten isig opost icrnl brkint hupcl',
            $platform->sttyCommand('/dev/ttyUSB0', $settings),
        );
    }

    public function testOddParityFlags(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame(
            "stty -F '/dev/ttyS0' 9600 cs8 parenb parodd -cstopb clocal -crtscts -ixon -ixoff "
            . '-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk -hupcl',
            $platform->sttyCommand('/dev/ttyS0', (new SerialSettings())->withParity(Parity::Odd)),
        );
    }

    public function testMarkParityUsesCmsparOnLinux(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame(
            "stty -F '/dev/ttyS0' 9600 cs8 cmspar parenb parodd -cstopb clocal -crtscts -ixon -ixoff "
            . '-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk -hupcl',
            $platform->sttyCommand('/dev/ttyS0', (new SerialSettings())->withParity(Parity::Mark)),
        );
    }

    public function testMarkParityIsRejectedOnMacOs(): void
    {
        $platform = $this->createMacPlatform();

        $this->expectException(UnsupportedConfigurationException::class);

        $platform->sttyCommand('/dev/ttyS0', (new SerialSettings())->withParity(Parity::Mark));
    }

    public function testSpaceParityUsesCmsparOnLinux(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertSame(
            "stty -F '/dev/ttyS0' 9600 cs8 cmspar parenb -parodd -cstopb clocal -crtscts -ixon -ixoff "
            . '-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk -hupcl',
            $platform->sttyCommand('/dev/ttyS0', (new SerialSettings())->withParity(Parity::Space)),
        );
    }

    public function testOneAndHalfStopBitsAreRejectedOnLinux(): void
    {
        $platform = $this->createLinuxPlatform();

        $this->expectException(UnsupportedConfigurationException::class);

        $platform->sttyCommand('/dev/ttyS0', (new SerialSettings())->withStopBits(StopBits::OneAndHalf));
    }

    public function testOneAndHalfStopBitsAreRejectedOnMacOs(): void
    {
        $platform = $this->createMacPlatform();

        $this->expectException(UnsupportedConfigurationException::class);

        $platform->sttyCommand('/dev/ttyS0', (new SerialSettings())->withStopBits(StopBits::OneAndHalf));
    }

    public function testIsAvailableUsesCommandVAndCachesTheResult(): void
    {
        $platform = $this->createLinuxPlatform();

        self::assertTrue($platform->isAvailable());
        self::assertSame(['command -v stty'], $this->runner->commands);

        self::assertTrue($platform->isAvailable());
        self::assertSame(['command -v stty'], $this->runner->commands);
    }

    public function testIsAvailableIsFalseWhenSttyIsMissing(): void
    {
        $this->runner->queueResult(1);

        $platform = $this->createLinuxPlatform();

        self::assertFalse($platform->isAvailable());
        self::assertSame(['command -v stty'], $this->runner->commands);
    }

    public function testConfigureRunsTheSttyCommand(): void
    {
        $platform = $this->createLinuxPlatform();
        $settings = new SerialSettings();

        $platform->configure('/dev/ttyS0', $settings);

        self::assertSame(
            [
                'command -v stty',
                "stty -F '/dev/ttyS0' 9600 cs8 -parenb -cstopb clocal -crtscts -ixon -ixoff "
                . '-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk -hupcl',
            ],
            $this->runner->commands,
        );
    }

    public function testConfigureWrapsSttyFailuresInConnectionException(): void
    {
        $platform = $this->createLinuxPlatform();
        $this->runner->queueResult(0);
        $this->runner->queueResult(1, '', 'stty: /dev/ttyS0: No such device or address');

        try {
            $platform->configure('/dev/ttyS0', new SerialSettings());
            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('stty: /dev/ttyS0: No such device or address', $exception->getMessage());
        }
    }

    public function testConfigureFailsWhenSttyIsUnavailable(): void
    {
        $this->runner->queueResult(1);

        $platform = $this->createLinuxPlatform();

        try {
            $platform->configure('/dev/ttyS0', new SerialSettings());
            self::fail('Expected an UnsupportedConfigurationException.');
        } catch (UnsupportedConfigurationException $exception) {
            self::assertStringContainsString('stty', $exception->getMessage());
        }

        self::assertSame(['command -v stty'], $this->runner->commands);
    }

    public function testDevicePathsWithSpacesAreEscaped(): void
    {
        $platform = $this->createLinuxPlatform();

        $command = $platform->sttyCommand('/tmp/my port', new SerialSettings());

        self::assertSame(
            "stty -F '/tmp/my port' 9600 cs8 -parenb -cstopb clocal -crtscts -ixon -ixoff "
            . '-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk -hupcl',
            $command,
        );
    }

    private function createLinuxPlatform(): UnixPlatform
    {
        return new UnixPlatform(UnixPlatform::LINUX, $this->runner);
    }

    private function createMacPlatform(): UnixPlatform
    {
        return new UnixPlatform(UnixPlatform::MACOS, $this->runner);
    }
}
