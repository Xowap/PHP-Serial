<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Integration;

use Hyperthese\PhpSerial\Platform\PlatformFactory;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the "No stty available" reports (#15, #17).
 *
 * The old check ran bare `stty`, which fails whenever stdin is not a TTY.
 * Detection must succeed from Apache, cron and CI.
 */
final class SttyAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('stty is only used on Linux and macOS.');
        }
    }

    public function testPlatformReportsSttyAsAvailable(): void
    {
        $platform = PlatformFactory::create();

        self::assertTrue($platform->isAvailable());
    }

    public function testDetectionWorksWithoutATty(): void
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $family = PHP_OS_FAMILY === 'Darwin' ? 'macos' : 'linux';

        $code = sprintf(
            'require %s; $platform = new Hyperthese\PhpSerial\Platform\UnixPlatform(%s); '
            . 'exit($platform->isAvailable() ? 0 : 1);',
            var_export($autoload, true),
            var_export($family, true),
        );

        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );

        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(
            0,
            proc_close($process),
            'stty detection failed without a TTY: ' . $stdout . $stderr,
        );
    }
}
