<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Unit;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\FlowControl;
use Hyperthese\PhpSerial\Parity;
use Hyperthese\PhpSerial\SerialSettings;
use Hyperthese\PhpSerial\StopBits;
use Hyperthese\PhpSerial\TransferMode;
use PHPUnit\Framework\TestCase;

final class SerialSettingsTest extends TestCase
{
    public function testDefaultsMatchTheDocumentedEightNOne(): void
    {
        $settings = new SerialSettings();

        self::assertSame(9600, $settings->baudRate());
        self::assertSame(Parity::None, $settings->parity());
        self::assertSame(8, $settings->dataBits());
        self::assertSame(StopBits::One, $settings->stopBits());
        self::assertSame(FlowControl::None, $settings->flowControl());
        self::assertSame(TransferMode::Binary, $settings->transferMode());
        self::assertTrue($settings->dtr());
    }

    public function testRejectsNonPositiveBaudRate(): void
    {
        $this->expectException(ConfigurationException::class);
        new SerialSettings(0);
    }

    public function testRejectsDataBitsOutsideFiveToEight(): void
    {
        $this->expectException(ConfigurationException::class);
        new SerialSettings(9600, Parity::None, 4);
    }

    public function testRejectsMoreThanEightDataBits(): void
    {
        $this->expectException(ConfigurationException::class);
        new SerialSettings(9600, Parity::None, 9);
    }

    public function testWithMethodsReturnNewInstances(): void
    {
        $original = new SerialSettings();

        $changed = $original
            ->withBaudRate(115200)
            ->withParity(Parity::Even)
            ->withDataBits(7)
            ->withStopBits(StopBits::Two)
            ->withFlowControl(FlowControl::RtsCts)
            ->withTransferMode(TransferMode::Text)
            ->withDtr(false);

        self::assertSame(9600, $original->baudRate());
        self::assertSame(Parity::None, $original->parity());
        self::assertSame(8, $original->dataBits());
        self::assertSame(StopBits::One, $original->stopBits());
        self::assertSame(FlowControl::None, $original->flowControl());
        self::assertSame(TransferMode::Binary, $original->transferMode());
        self::assertTrue($original->dtr());

        self::assertSame(115200, $changed->baudRate());
        self::assertSame(Parity::Even, $changed->parity());
        self::assertSame(7, $changed->dataBits());
        self::assertSame(StopBits::Two, $changed->stopBits());
        self::assertSame(FlowControl::RtsCts, $changed->flowControl());
        self::assertSame(TransferMode::Text, $changed->transferMode());
        self::assertFalse($changed->dtr());
    }
}
