# Usage

This page covers the full request lifecycle: choosing a device, opening and
closing it, writing data, reading with timeouts, handling errors and adding
logging.

## Device names

`deviceSet()` accepts a path, a short POSIX name, a Windows COM name or an
integer port number. The platform implementation resolves it to a path that can
be passed to `fopen()`.

| Input | Linux / macOS | Windows |
| --- | --- | --- |
| `/dev/ttyUSB0` | `/dev/ttyUSB0` | Invalid; Windows expects a COM name |
| `ttyUSB0` | `/dev/ttyUSB0` | Invalid |
| `ttyACM0` | `/dev/ttyACM0` | Invalid |
| `cu.usbserial-1420` | `/dev/cu.usbserial-1420` | Invalid |
| `/dev/serial/by-id/usb-...` | Used as-is | Invalid |
| `COM3` | `/dev/ttyS2` (legacy mapping) | `\\.\COM3` |
| `3` | `/dev/ttyS2` (legacy mapping) | `\\.\COM3` |
| `COM10` | `/dev/ttyS9` (legacy mapping) | `\\.\COM10` |
| `\\.\COM3` | Invalid | `\\.\COM3` |

!!! note "COM names on POSIX"

    The `COM<n>` to `/dev/ttyS<n-1>` mapping exists for portable configuration
    files. It only makes sense for native UARTs. On Linux and macOS, prefer the
    real device path such as `/dev/ttyUSB0` or `/dev/cu.usbserial-1420`.

On POSIX, `deviceSet()` checks that the resolved path exists and throws a
`ConnectionException` when it does not. On Windows a COM port cannot be
`stat`ed reliably, so existence is checked when the port is opened.

## Open and close lifecycle

```php
<?php

declare(strict_types=1);

use Hyperthese\PhpSerial\PhpSerial;

$serial = new PhpSerial();

$serial->deviceSet('/dev/ttyUSB0')
    ->confBaudRate(115200)
    ->confParity('none')
    ->confCharacterLength(8)
    ->confStopBits(1)
    ->confFlowControl('none')
    ->deviceOpen();

// ... use the port ...

$serial->deviceClose();
```

The lifecycle has three states:

1. **Not configured.** No device has been set. `deviceOpen()` and all I/O
   methods throw a `ConfigurationException`.
2. **Configured.** `deviceSet()` succeeded. Configuration can be changed, but
   I/O is not possible yet.
3. **Open.** `deviceOpen()` applied the settings and opened the stream. I/O is
   possible, but `deviceSet()` and every `conf*()` method throw.

`deviceClose()` is idempotent: calling it on a closed device does nothing. The
device name and settings are retained, so the port can be reopened later,
including with a different configuration:

```php
$serial->deviceClose();
$serial->confBaudRate(57600);
$serial->deviceOpen();
```

The destructor also closes the port, but do not rely on it for deterministic
cleanup. Close the device in a `finally` block.

!!! warning "Close before reconfiguring"

    Settings are applied by `deviceOpen()`. Calling a `conf*()` method while
    the port is open throws a `ConfigurationException` instead of silently
    reconfiguring the device underneath an active transfer.

## Writing

### sendMessage()

`sendMessage()` appends data to the write buffer and flushes it immediately
when auto-flush is enabled, which is the default. It returns the instance, so
calls can be chained.

```php
$serial->sendMessage("AT\r\n");
```

The second argument is a delay in seconds applied **after** flushing. It does
not read a reply:

```php
// Flush, then wait 100 ms before returning.
$serial->sendMessage("AT\r\n", 0.1);

// Collect the reply separately.
$reply = $serial->readPort(timeout: 1.0);
```

Prefer `readPort()` or `readLine()` for replies. A fixed sleep is only useful
when a device needs a short pause between commands.

### write()

`write()` bypasses the buffer and sends the payload immediately. It returns the
number of bytes written.

```php
$written = $serial->write("\x02\x10\x03");
```

The underlying writer retries partial writes until the whole payload is sent.
If a write stalls after the five-second write deadline, it throws an
`IOException`.

!!! note "write() does not flush the buffer"

    If auto-flush is disabled and `sendMessage()` has left data in the buffer,
    `write()` sends only its own payload. Call `flush()` to send the buffered
    data as well.

### Buffering and auto-flush

Disable auto-flush when a command must be assembled from several pieces:

```php
$serial->setAutoFlush(false);

$serial->sendMessage('AT+CMGS="');
$serial->sendMessage('+33600000000');
$serial->sendMessage("\"\r\n");

$serial->flush(); // Sends everything buffered so far.
```

`serialflush()` is a 1.x-compatible alias of `flush()`. Both write the buffer
and flush the stream, then return the instance.

Re-enable auto-flush with `setAutoFlush(true)`. The setting is independent of
the device lifecycle and survives close/reopen.

## Reading

### readPort()

```php
public function readPort(int $count = 0, int|float|null $timeout = null): string
```

`$count` is a maximum, never a minimum: the method never returns more than
`$count` bytes. `$timeout` is expressed in seconds and accepts an integer or a
float. A `null` timeout means "return immediately".

| `$count` | `$timeout` | Behavior |
| --- | --- | --- |
| `0` | `null` | Returns the bytes already available, including any internal buffer. Does not wait. |
| `0` | `> 0` | Waits up to the timeout for the first byte, then drains what is buffered and returns. Returns `''` if nothing arrives. |
| `> 0` | `null` | Returns up to `$count` bytes from what is immediately available. May return fewer, including `''`. |
| `> 0` | `> 0` | Waits until `$count` bytes have arrived or the timeout expires. Returns a shorter string on timeout. |
| `> 0` | `0` | Returns immediately with up to `$count` available bytes. |

Examples:

```php
// Non-blocking drain.
$data = $serial->readPort();

// Wait up to one second for the first byte, then drain.
$data = $serial->readPort(timeout: 1.0);

// Wait up to one second for exactly 64 bytes (or return fewer on timeout).
$data = $serial->readPort(count: 64, timeout: 1.0);

// Never wait.
$data = $serial->readPort(count: 64, timeout: 0);
```

A negative `$count` throws a `ConfigurationException`.

### readLine()

```php
public function readLine(
    int $maxLength = 0,
    string $delimiter = "\n",
    int|float|null $timeout = null,
): string
```

`readLine()` reads until the delimiter is found, `$maxLength` bytes have been
collected, or the timeout expires:

- The delimiter is included in the returned string when it falls within the
  `$maxLength` cap.
- If `$maxLength` is reached first, the returned line is truncated and does not
  include the delimiter. The remainder, including the delimiter, stays in the
  buffer for the next read.
- A partial line is returned when the timeout expires.
- Bytes after the delimiter stay in the internal buffer for the next read.
- `$maxLength = 0` means unlimited.
- An empty delimiter throws a `ConfigurationException`.

```php
// Read one newline-terminated line.
$line = $serial->readLine(timeout: 2.0);

// Read a CRLF-terminated line, capped at 256 bytes.
$line = $serial->readLine(maxLength: 256, delimiter: "\r\n", timeout: 2.0);
```

### Leftover buffer

`readLine()` may read more bytes from the stream than the line it returns.
Those extra bytes are retained and returned by the next `readPort()` or
`readLine()` call:

```php
// Device sends "OK\r\nREADY\r\n" in one burst.
$first = $serial->readLine(delimiter: "\r\n", timeout: 1.0);  // "OK\r\n"
$second = $serial->readLine(delimiter: "\r\n", timeout: 1.0); // "READY\r\n"
```

The buffer belongs to the open port and is discarded by `deviceClose()`.

### Non-blocking polling loop

Use a short timeout to poll without blocking the process:

```php
<?php

declare(strict_types=1);

use Hyperthese\PhpSerial\Exception\PhpSerialException;
use Hyperthese\PhpSerial\PhpSerial;

$running = true;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use (&$running): void {
        $running = false;
    });
}

$serial = new PhpSerial();

try {
    $serial->deviceSet('/dev/ttyUSB0')
        ->confBaudRate(9600)
        ->deviceOpen();

    while ($running) {
        $chunk = $serial->readPort(timeout: 0.2); // 200 ms poll

        if ($chunk !== '') {
            echo $chunk;
        }
    }
} catch (PhpSerialException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
} finally {
    $serial->deviceClose();
}
```

`readPort()` returns `''` when the timeout expires, which makes the loop easy
to interrupt. On Windows the same pattern works; the timeout is implemented by
polling because COM handles cannot be opened non-blocking.

## Exceptions and error handling

All library exceptions extend
`Hyperthese\PhpSerial\Exception\PhpSerialException`, which extends
`RuntimeException`.

| Exception | Typical cause |
| --- | --- |
| `ConfigurationException` | Invalid setting, device not set, device already open, I/O attempted on a closed port, invalid read arguments. |
| `ConnectionException` | Device does not exist, `fopen()` failed, or `stty`/`mode` failed to apply the settings. |
| `UnsupportedConfigurationException` | A valid setting is not supported by the platform, such as mark parity on macOS or 1.5 stop bits with `stty`. |
| `UnsupportedPlatformException` | The operating system is not Linux, macOS or Windows. |
| `IOException` | A stream read or write failed, including a write timeout. |

Catch the base class for a single recovery path, or catch specific subclasses
when the response differs:

```php
use Hyperthese\PhpSerial\Exception\ConnectionException;
use Hyperthese\PhpSerial\Exception\PhpSerialException;

try {
    $serial->deviceSet($device)->deviceOpen();
} catch (ConnectionException $exception) {
    // Device missing, busy or not permitted.
    error_log($exception->getMessage());
    return;
} catch (PhpSerialException $exception) {
    // Invalid configuration or unsupported platform.
    error_log($exception->getMessage());
    return;
} finally {
    $serial->deviceClose();
}
```

`deviceClose()` itself never throws, which makes it safe in `finally`.

## PSR-3 logging

Pass any PSR-3 logger as the first constructor argument:

```php
<?php

declare(strict_types=1);

use Hyperthese\PhpSerial\PhpSerial;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$logger = new Logger('serial');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

$serial = new PhpSerial($logger);
```

Monolog is only an example; the library depends on `psr/log`, not on a specific
implementation. The logger receives:

- `debug` when a device is selected and after each read or write, including
  byte counts and the device path.
- `info` when the port is opened or closed.

Payload contents are not logged.

## Accessing the underlying stream

`getStream()` returns the `resource` used by the port, or `null` when the
device is closed. It is intended for advanced stream options that the library
does not expose:

```php
$stream = $serial->getStream();

if ($stream !== null) {
    stream_set_write_buffer($stream, 0); // Unbuffered writes.
}
```

The stream is already in non-blocking mode. Changing it back to blocking mode
with `stream_set_blocking($stream, true)` defeats the library's timeout
handling and is not recommended. `stream_set_timeout()` has no effect on a
non-blocking stream and is not used by the library.

You can also wait on the resource with `stream_select()`:

```php
$read = [$serial->getStream()];
$write = null;
$except = null;

if (stream_select($read, $write, $except, 1, 0) > 0) {
    echo $serial->readPort();
}
```

Some Windows COM handles cannot be selected. The library falls back to polling
internally, so prefer `readPort(timeout: ...)` for portable code.

Do not call `fclose()` on the resource returned by `getStream()`. Use
`deviceClose()` so the port state and buffer stay consistent.

## Arduino example

Opening a USB serial port usually toggles DTR and resets an Arduino or ESP32.
Wait for the bootloader before sending commands:

```php
$serial->deviceSet('/dev/ttyACM0')
    ->confBaudRate(115200)
    ->deviceOpen();

// Allow the board to reboot after the DTR toggle.
usleep(2_000_000);

$serial->sendMessage("PING\n");
$reply = $serial->readLine(timeout: 2.0);

echo $reply;
```

If the reset is undesirable, call `confDtr(false)` **before** `deviceOpen()`.
Whether this prevents the reset depends on the board and the USB-serial adapter.
See [Platforms](platforms.md#arduino-and-esp-boards) for details.

## AT modem example

AT commands are line-oriented, but a reply does not always end with a newline.
Read lines until the final result code appears or the timeout expires:

```php
$sendCommand = static function (PhpSerial $serial, string $command, float $timeout = 2.0): string {
    $serial->write($command);
    $response = '';

    while (true) {
        $line = $serial->readLine(delimiter: "\n", timeout: $timeout);

        if ($line === '') {
            break;
        }

        $response .= $line;

        if (str_contains($line, 'OK') || str_contains($line, 'ERROR')) {
            break;
        }
    }

    return $response;
};

$serial->deviceSet('/dev/ttyUSB0')
    ->confBaudRate(115200)
    ->deviceOpen();

echo $sendCommand($serial, "AT\r\n");
echo $sendCommand($serial, "AT+CSQ\r\n");
```

For an SMS example that handles the `>` prompt and Ctrl-Z terminator, see
`examples/sms.php` in the repository.

## See also

- [Configuration](configuration.md) — every `conf*()` method and its defaults.
- [Platforms](platforms.md) — device permissions and OS-specific behavior.
- [Troubleshooting](troubleshooting.md) — empty reads, busy ports and resets.
- [API reference](api.md) — exact signatures and return types.
