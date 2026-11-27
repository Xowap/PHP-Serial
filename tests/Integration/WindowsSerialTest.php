<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Integration;

use Hyperthese\PhpSerial\PhpSerial;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test against two real Windows COM ports wired as a null modem.
 *
 * Hosted GitHub runners cannot install a kernel-mode virtual serial driver
 * (com0com is test-signed and needs a reboot), so this test runs on a
 * self-hosted Windows runner with real hardware. It also works locally with
 * com0com once a port pair has been created.
 *
 * Configure the pair with the PHPSERIAL_TEST_COM_A and PHPSERIAL_TEST_COM_B
 * environment variables (for example COM5 and COM6).
 */
final class WindowsSerialTest extends TestCase
{
    private string $portA;

    private string $portB;

    private ?PhpSerial $serialA = null;

    private ?PhpSerial $serialB = null;

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            self::markTestSkipped('Windows only.');
        }

        $portA = getenv('PHPSERIAL_TEST_COM_A');
        $portB = getenv('PHPSERIAL_TEST_COM_B');

        if (!is_string($portA) || $portA === '' || !is_string($portB) || $portB === '') {
            self::markTestSkipped(
                'Set PHPSERIAL_TEST_COM_A and PHPSERIAL_TEST_COM_B to two COM ports wired as a null modem.',
            );
        }

        $this->portA = $portA;
        $this->portB = $portB;
    }

    protected function tearDown(): void
    {
        $this->serialA?->deviceClose();
        $this->serialB?->deviceClose();
        $this->serialA = null;
        $this->serialB = null;
    }

    public function testBidirectionalCommunicationOverRealComPorts(): void
    {
        $this->openBoth();

        $this->serialA?->sendMessage("hello\r\n");
        self::assertSame("hello\r\n", $this->serialB?->readLine(0, "\n", 3.0));

        $this->serialB?->sendMessage('world');
        self::assertSame('world', $this->serialA?->readPort(5, 3.0));
    }

    public function testBinaryModeDeliversControlCharacters(): void
    {
        $this->openBoth();

        $this->serialA?->sendMessage("\x03\x1a");
        self::assertSame("\x03\x1a", $this->serialB?->readPort(2, 3.0));
    }

    public function testReadTimeout(): void
    {
        $this->openBoth();

        $startedAt = microtime(true);
        $data = $this->serialA?->readPort(0, 0.3) ?? '';
        $elapsed = microtime(true) - $startedAt;

        self::assertSame('', $data);
        self::assertGreaterThanOrEqual(0.2, $elapsed);
    }

    public function testPlatformAndDevicePath(): void
    {
        $this->openBoth();

        self::assertSame('windows', $this->serialA?->getPlatformName());

        $device = $this->serialA?->getDevice() ?? '';
        self::assertStringStartsWith('\\\\.\\COM', $device);
    }

    private function openBoth(): void
    {
        $serialA = new PhpSerial();
        $serialA->deviceSet($this->portA)
            ->confBaudRate(9600)
            ->confParity('none')
            ->confCharacterLength(8)
            ->confStopBits(1)
            ->confFlowControl('none')
            ->confTransferMode('binary')
            ->deviceOpen();

        $serialB = new PhpSerial();
        $serialB->deviceSet($this->portB)
            ->confBaudRate(9600)
            ->confParity('none')
            ->confCharacterLength(8)
            ->confStopBits(1)
            ->confFlowControl('none')
            ->confTransferMode('binary')
            ->deviceOpen();

        $this->serialA = $serialA;
        $this->serialB = $serialB;
    }
}
