<?php

/**
 * Minimal write/read example.
 *
 * Usage:
 *   php examples/basic.php /dev/ttyUSB0
 *   php examples/basic.php COM3
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Hyperthese\PhpSerial\Exception\PhpSerialException;
use Hyperthese\PhpSerial\PhpSerial;

$device = $argv[1] ?? '/dev/ttyUSB0';

$serial = new PhpSerial();

try {
    $serial->deviceSet($device)
        ->confBaudRate(9600)
        ->confParity('none')
        ->confCharacterLength(8)
        ->confStopBits(1)
        ->confFlowControl('none')
        ->deviceOpen();

    $serial->sendMessage("Hello from PHP Serial\r\n");

    // Wait up to one second for the first byte, then drain what is available.
    $reply = $serial->readPort(timeout: 1.0);

    if ($reply === '') {
        echo "No data received.\n";
    } else {
        echo "Received:\n" . $reply . "\n";
    }
} catch (PhpSerialException $exception) {
    fwrite(STDERR, 'Serial error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $serial->deviceClose();
}
