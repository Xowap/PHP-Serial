<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Unit;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\Exception\ConnectionException;
use Hyperthese\PhpSerial\FlowControl;
use Hyperthese\PhpSerial\Parity;
use Hyperthese\PhpSerial\PhpSerial;
use Hyperthese\PhpSerial\StopBits;
use Hyperthese\PhpSerial\Tests\Fake\FakePlatform;
use Hyperthese\PhpSerial\TransferMode;
use PHPUnit\Framework\TestCase;

final class PhpSerialTest extends TestCase
{
    private string $deviceFile;

    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'phpserial-');
        self::assertIsString($file);
        $this->deviceFile = $file;
        file_put_contents($this->deviceFile, 'response');
    }

    protected function tearDown(): void
    {
        @unlink($this->deviceFile);
    }

    public function testDeviceSetRecordsTheResolvedPath(): void
    {
        $serial = $this->createSerial();

        self::assertSame($serial, $serial->deviceSet('/dev/fake'));
        self::assertSame($this->deviceFile, $serial->getDevice());
        self::assertSame('/dev/fake', $serial->getDeviceName());
        self::assertTrue($serial->isConfigured());
        self::assertFalse($serial->isOpen());
        self::assertSame('fake', $serial->getPlatformName());
    }

    public function testDeviceSetThrowsWhenTheDeviceDoesNotExist(): void
    {
        $serial = $this->createSerial(false);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('dialout');

        $serial->deviceSet('/dev/missing');
    }

    public function testDeviceCannotBeOpenedBeforeItIsSet(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->createSerial()->deviceOpen();
    }

    public function testConfigurationIsAppliedExactlyOnceWhenOpening(): void
    {
        $platform = new FakePlatform($this->deviceFile);
        $serial = $this->createSerialWith($platform);

        $serial
            ->deviceSet('/dev/fake')
            ->confBaudRate(115200)
            ->confParity('even')
            ->confCharacterLength(7)
            ->confStopBits(2)
            ->confFlowControl('rts/cts')
            ->confTransferMode('text')
            ->confDtr(false)
            ->deviceOpen();

        self::assertCount(1, $platform->configuredSettings);

        $settings = $platform->configuredSettings[0];
        self::assertSame(115200, $settings->baudRate());
        self::assertSame(Parity::Even, $settings->parity());
        self::assertSame(7, $settings->dataBits());
        self::assertSame(StopBits::Two, $settings->stopBits());
        self::assertSame(FlowControl::RtsCts, $settings->flowControl());
        self::assertSame(TransferMode::Text, $settings->transferMode());
        self::assertFalse($settings->dtr());
    }

    public function testReadPortReadsTheStream(): void
    {
        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen();

        self::assertSame('response', $serial->readPort());
        self::assertSame('', $serial->readPort());
    }

    public function testReadPortHonoursTheRequestedLength(): void
    {
        file_put_contents($this->deviceFile, 'abcdef');

        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen();

        self::assertSame('abc', $serial->readPort(3));
        self::assertSame('def', $serial->readPort(3));
        self::assertSame('', $serial->readPort(3));
    }

    public function testReadLineKeepsTheRemainderForTheNextRead(): void
    {
        file_put_contents($this->deviceFile, "line\nrest");

        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen();

        self::assertSame("line\n", $serial->readLine());
        self::assertSame('rest', $serial->readPort());
    }

    public function testSendMessageWritesWhenAutoFlushIsEnabled(): void
    {
        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen('a+b');

        $serial->sendMessage('ping');

        self::assertStringEndsWith('ping', (string) file_get_contents($this->deviceFile));
    }

    public function testSendMessageBuffersWhenAutoFlushIsDisabled(): void
    {
        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen('a+b');
        $serial->setAutoFlush(false);

        $serial->sendMessage('ping');
        self::assertStringEndsWith('response', (string) file_get_contents($this->deviceFile));

        $serial->flush();
        self::assertStringEndsWith('ping', (string) file_get_contents($this->deviceFile));
    }

    public function testWriteReturnsTheNumberOfBytesWritten(): void
    {
        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen('a+b');

        self::assertSame(3, $serial->write('abc'));
    }

    public function testCloseFlushesBufferedData(): void
    {
        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen('a+b');
        $serial->setAutoFlush(false);
        $serial->sendMessage('late');

        $serial->deviceClose();

        self::assertStringEndsWith('late', (string) file_get_contents($this->deviceFile));
    }

    public function testConfigurationCannotBeChangedWhileTheDeviceIsOpen(): void
    {
        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen();

        $this->expectException(ConfigurationException::class);
        $serial->confBaudRate(115200);
    }

    public function testDeviceCannotBeChangedWhileOpen(): void
    {
        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen();

        $this->expectException(ConfigurationException::class);
        $serial->deviceSet('/dev/other');
    }

    public function testCloseIsIdempotent(): void
    {
        $serial = $this->createSerial();
        $serial->deviceSet('/dev/fake')->deviceOpen();

        self::assertNotNull($serial->getStream());

        $serial->deviceClose();
        $serial->deviceClose();

        self::assertFalse($serial->isOpen());
        self::assertNull($serial->getStream());
        self::assertTrue($serial->isConfigured());
    }

    public function testReadWithoutOpenThrows(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->createSerial()->readPort();
    }

    public function testAcceptsEnumsAsWellAsStrings(): void
    {
        $serial = $this->createSerial();
        $serial
            ->confParity(Parity::Odd)
            ->confStopBits(StopBits::OneAndHalf)
            ->confFlowControl(FlowControl::XonXoff)
            ->confTransferMode(TransferMode::Binary);

        self::assertSame(Parity::Odd, $serial->getSettings()->parity());
        self::assertSame(StopBits::OneAndHalf, $serial->getSettings()->stopBits());
        self::assertSame(FlowControl::XonXoff, $serial->getSettings()->flowControl());
        self::assertSame(TransferMode::Binary, $serial->getSettings()->transferMode());
    }

    public function testRejectsInvalidParity(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->createSerial()->confParity('invalid');
    }

    public function testRejectsInvalidStopBits(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->createSerial()->confStopBits(3);
    }

    public function testRejectsInvalidFlowControl(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->createSerial()->confFlowControl('invalid');
    }

    public function testRejectsInvalidTransferMode(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->createSerial()->confTransferMode('invalid');
    }

    public function testRejectsInvalidBaudRate(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->createSerial()->confBaudRate(0);
    }

    public function testRejectsInvalidCharacterLength(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->createSerial()->confCharacterLength(9);
    }

    private function createSerial(bool $exists = true): PhpSerial
    {
        return $this->createSerialWith(new FakePlatform($this->deviceFile, $exists));
    }

    private function createSerialWith(FakePlatform $platform): PhpSerial
    {
        return new PhpSerial(null, $platform);
    }
}
