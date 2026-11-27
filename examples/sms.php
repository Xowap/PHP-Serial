<?php

/**
 * Sends an SMS through a modem (or a GSM module) using AT commands.
 *
 * Usage:
 *   php examples/sms.php /dev/ttyUSB0 +33600000000 "Hello from PHP"
 *
 * The port must belong to the user running PHP (dialout group on Linux).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Hyperthese\PhpSerial\Exception\PhpSerialException;
use Hyperthese\PhpSerial\PhpSerial;

$device = $argv[1] ?? '/dev/ttyUSB0';
$number = $argv[2] ?? '+33600000000';
$message = $argv[3] ?? 'Hello from PHP Serial';

$serial = new PhpSerial();

/**
 * Sends an AT command and reads a single response line.
 *
 * Modems answer with "\r\n", and not every reply ends with a newline, so
 * readLine() may return a partial line when its timeout expires.
 */
$sendCommand = static function (PhpSerial $serial, string $command, float $timeout = 2.0): string {
    $serial->write($command);
    $response = '';

    while (true) {
        $line = $serial->readLine(delimiter: "\n", timeout: $timeout);

        if ($line === '') {
            break;
        }

        $response .= $line;

        if (str_contains($line, 'OK') || str_contains($line, 'ERROR') || str_contains($line, '>')) {
            break;
        }
    }

    return $response;
};

try {
    $serial->deviceSet($device)
        ->confBaudRate(115200)
        ->confParity('none')
        ->confCharacterLength(8)
        ->confStopBits(1)
        ->confFlowControl('none')
        // Binary/raw mode is required so Ctrl-Z (0x1A) reaches the modem
        // instead of being interpreted as EOF by the terminal driver.
        ->confTransferMode('binary')
        ->deviceOpen();

    echo $sendCommand($serial, "AT\r\n");
    echo $sendCommand($serial, "AT+CMGF=1\r\n"); // Text mode instead of PDU.

    $prompt = $sendCommand($serial, 'AT+CMGS="' . $number . "\"\r\n", 5.0);

    if (!str_contains($prompt, '>')) {
        throw new PhpSerialException('The modem did not return the AT+CMGS ">" prompt.');
    }

    // Ctrl-Z (0x1A) commits the message.
    echo $sendCommand($serial, $message . chr(26), 10.0);
} catch (PhpSerialException $exception) {
    fwrite(STDERR, 'SMS error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $serial->deviceClose();
}
