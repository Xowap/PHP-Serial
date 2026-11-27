<?php

/**
 * Non-blocking read loop.
 *
 * readPort(timeout: 0.2) waits up to 200 ms for data and returns an empty
 * string when nothing arrives, so the loop can be interrupted cleanly.
 *
 * Usage:
 *   php examples/read-loop.php /dev/ttyUSB0
 *
 * Press Ctrl-C to stop (the signal handler closes the port in finally).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Hyperthese\PhpSerial\Exception\PhpSerialException;
use Hyperthese\PhpSerial\PhpSerial;

$device = $argv[1] ?? '/dev/ttyUSB0';

$running = true;

if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use (&$running): void {
        $running = false;
    });
    pcntl_signal(SIGTERM, static function () use (&$running): void {
        $running = false;
    });
}

$serial = new PhpSerial();

try {
    $serial->deviceSet($device)
        ->confBaudRate(9600)
        ->confParity('none')
        ->confCharacterLength(8)
        ->confStopBits(1)
        ->confFlowControl('none')
        ->confTransferMode('binary')
        ->deviceOpen();

    echo "Listening on {$device}, press Ctrl-C to stop.\n";

    while ($running) {
        $data = $serial->readPort(timeout: 0.2);

        if ($data !== '') {
            echo $data;
            flush();
        }
    }

    echo "\nStopped.\n";
} catch (PhpSerialException $exception) {
    fwrite(STDERR, 'Serial error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $serial->deviceClose();
}
