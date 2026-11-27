<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Integration;

use Hyperthese\PhpSerial\PhpSerial;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the full stack against a real PTY pair created by socat.
 */
final class PosixSerialTest extends TestCase
{
    /** @var resource|null */
    private $socat = null;

    /** @var array<int, resource> */
    private array $socatPipes = [];

    private string $directory;
    private string $leftPath;
    private string $rightPath;
    private ?PhpSerial $left = null;
    private ?PhpSerial $right = null;

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('PTY integration tests require Linux or macOS.');
        }

        if (trim((string) shell_exec('command -v socat 2>/dev/null')) === '') {
            self::markTestSkipped('socat is required for PTY integration tests.');
        }

        $this->directory = sys_get_temp_dir() . '/phpserial-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->leftPath = $this->directory . '/left';
        $this->rightPath = $this->directory . '/right';

        $process = proc_open(
            [
                'socat',
                'pty,raw,echo=0,link=' . $this->leftPath,
                'pty,raw,echo=0,link=' . $this->rightPath,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            self::markTestSkipped('Unable to start socat.');
        }

        $this->socat = $process;
        $this->socatPipes = $pipes;

        foreach ($this->socatPipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            if (file_exists($this->leftPath) && file_exists($this->rightPath)) {
                return;
            }

            usleep(50_000);
        }

        $error = isset($this->socatPipes[2]) ? (string) stream_get_contents($this->socatPipes[2]) : '';

        self::markTestSkipped('socat did not create the PTY pair: ' . $error);
    }

    protected function tearDown(): void
    {
        $this->left?->deviceClose();
        $this->right?->deviceClose();
        $this->left = null;
        $this->right = null;

        if ($this->socat !== null) {
            proc_terminate($this->socat);
            foreach ($this->socatPipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($this->socat);
            $this->socat = null;
        }

        if (isset($this->directory)) {
            @unlink($this->leftPath);
            @unlink($this->rightPath);
            @rmdir($this->directory);
        }
    }

    public function testBidirectionalCommunicationOverARealPty(): void
    {
        $this->openBoth();

        $this->left?->sendMessage("hello\n");
        self::assertSame("hello\n", $this->right?->readLine(0, "\n", 2.0));

        $this->right?->sendMessage('world');
        self::assertSame('world', $this->left?->readPort(5, 2.0));
    }

    public function testBinaryModeDeliversControlCharactersUntouched(): void
    {
        $this->openBoth();

        $this->left?->sendMessage("\x03\x1a");
        self::assertSame("\x03\x1a", $this->right?->readPort(2, 2.0));
    }

    public function testReadTimesOutWhenNothingArrives(): void
    {
        $this->openBoth();

        $startedAt = microtime(true);
        $data = $this->left?->readPort(0, 0.2) ?? '';
        $elapsed = microtime(true) - $startedAt;

        self::assertSame('', $data);
        self::assertGreaterThanOrEqual(0.15, $elapsed);
    }

    public function testPlatformIsDetected(): void
    {
        $this->openBoth();

        self::assertContains($this->left?->getPlatformName(), ['linux', 'macos']);
    }

    private function openBoth(): void
    {
        $left = new PhpSerial();
        $left->deviceSet($this->leftPath)
            ->confBaudRate(9600)
            ->confParity('none')
            ->confCharacterLength(8)
            ->confStopBits(1)
            ->confFlowControl('none')
            ->confTransferMode('binary')
            ->deviceOpen();

        $right = new PhpSerial();
        $right->deviceSet($this->rightPath)
            ->confBaudRate(9600)
            ->confParity('none')
            ->confCharacterLength(8)
            ->confStopBits(1)
            ->confFlowControl('none')
            ->confTransferMode('binary')
            ->deviceOpen();

        $this->left = $left;
        $this->right = $right;
    }
}
