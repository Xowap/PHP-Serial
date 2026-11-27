# PHP Serial

A small, dependency-light library to talk to serial ports from PHP on Linux, macOS and Windows.

[![CI](https://github.com/Xowap/PHP-Serial/actions/workflows/ci.yml/badge.svg)](https://github.com/Xowap/PHP-Serial/actions/workflows/ci.yml)
[![Documentation](https://img.shields.io/badge/docs-xowap.github.io%2FPHP--Serial-blue)](https://xowap.github.io/PHP-Serial/)
[![Packagist](https://img.shields.io/packagist/v/hyperthese/php-serial)](https://packagist.org/packages/hyperthese/php-serial)
[![PHP](https://img.shields.io/badge/php-%5E8.1-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](LICENSE)

Full documentation lives at **<https://xowap.github.io/PHP-Serial/>**.

## Features

- PSR-4 autoloading and PSR-12 code style, `final` class with a typed API.
- PHP 8.1 to 8.4, tested on Linux, macOS and Windows.
- Non-blocking opens (POSIX `r+bn`) and explicit read timeouts, so reads no longer hang.
- Raw/binary transfer mode: control bytes such as Ctrl-C and Ctrl-Z pass through untouched.
- Typed exceptions instead of warnings and `false` return values.
- Optional PSR-3 logger for debugging device and I/O operations.
- Immutable `SerialSettings` value object and native enums for parity, stop bits, flow control and transfer mode.
- Fluent configuration, applied atomically when the device is opened.

## Why 2.0?

2.0 fixes the six root causes behind most of the reports filed since 2014:

| Symptom in 1.x | Cause | Fixed by |
| --- | --- | --- |
| Fatal parse error on PHP 8 | Curly-brace string offsets (`$parity{0}`) | [#37](https://github.com/Xowap/PHP-Serial/issues/37), [#40](https://github.com/Xowap/PHP-Serial/issues/40) |
| Configuration silently ignored | PHP 4 constructor never ran | [#27](https://github.com/Xowap/PHP-Serial/issues/27), [#30](https://github.com/Xowap/PHP-Serial/issues/30) |
| "No stty available" under Apache/cron | `stty` ran without a TTY | [#15](https://github.com/Xowap/PHP-Serial/issues/15), [#17](https://github.com/Xowap/PHP-Serial/issues/17) |
| Windows: "device not valid", no reads, COM10+ | Broken `\.com` path and nested `exec("mode")` | [#9](https://github.com/Xowap/PHP-Serial/issues/9), [#20](https://github.com/Xowap/PHP-Serial/issues/20), [#23](https://github.com/Xowap/PHP-Serial/issues/23) |
| Reads return too much, block or miss data | `readPort()` chunk accounting, no timeouts | [#13](https://github.com/Xowap/PHP-Serial/issues/13), [#33](https://github.com/Xowap/PHP-Serial/issues/33), [#34](https://github.com/Xowap/PHP-Serial/issues/34) |
| `ttyACM0`/`ttyUSB0` rejected | Device setter only understood `COMx` | [#4](https://github.com/Xowap/PHP-Serial/issues/4), [#7](https://github.com/Xowap/PHP-Serial/issues/7), [#35](https://github.com/Xowap/PHP-Serial/issues/35) |

See the [changelog](CHANGELOG.md) for the full list and the contributors whose
reports and patches shaped this release.

## Requirements

- PHP >= 8.1 with `proc_open()` enabled.
- Linux/macOS: the `stty` utility (coreutils, busybox or the system's base utilities).
- Windows: the `mode` command (part of `cmd.exe`).
- A serial device the PHP user is allowed to open.

## Installation

```bash
composer require hyperthese/php-serial
```

The 1.x global class `PhpSerial` is still aliased by `src/deprecated.php`, but new code should use the namespaced class.

## Quickstart

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Hyperthese\PhpSerial\Exception\PhpSerialException;
use Hyperthese\PhpSerial\PhpSerial;

$serial = new PhpSerial();

try {
    $serial->deviceSet('/dev/ttyUSB0') // or 'COM3', or 3
        ->confBaudRate(115200)
        ->confParity('none')
        ->confCharacterLength(8)
        ->confStopBits(1)
        ->confFlowControl('none')
        ->deviceOpen();

    $serial->sendMessage("AT\r\n");
    $reply = $serial->readPort(timeout: 1.0);

    echo $reply;
} catch (PhpSerialException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
} finally {
    $serial->deviceClose();
}
```

Configuration methods only change the pending `SerialSettings`; `deviceOpen()` runs `stty`/`mode` and then opens the port.

## Reading data

```php
// Return whatever is already buffered, without waiting.
$data = $serial->readPort();

// Wait up to 1 second for the first byte, then drain what is available.
$data = $serial->readPort(timeout: 1.0);

// Read at most 64 bytes, waiting up to 1 second for them.
$data = $serial->readPort(count: 64, timeout: 1.0);

// Read a line including its delimiter ("\n" by default).
$line = $serial->readLine(timeout: 2.0);

// Custom delimiter, maximum length, partial line returned on timeout.
$line = $serial->readLine(maxLength: 256, delimiter: "\r\n", timeout: 2.0);
```

`readPort()` never returns more than `count` bytes. With `count: 0` (the default) it waits for the first byte and then drains what is buffered. With a positive `count` and a timeout, it waits until that many bytes have arrived or the timeout expires. A `null` timeout returns immediately.

`readLine()` includes the delimiter when it is found within the maximum length, returns a partial line when the timeout expires, and keeps any bytes after the delimiter for the next read.

### Non-blocking loop

```php
$running = true;

while ($running) {
    $chunk = $serial->readPort(timeout: 0.2); // 200 ms poll

    if ($chunk !== '') {
        echo $chunk;
    }
}
```

Note that `readPort()` throws when the port is not open, so open it before the loop and close it in a `finally` block.

## Configuration

| Method | Accepted values | Default |
| --- | --- | --- |
| `confBaudRate(int $rate)` | any positive integer | `9600` |
| `confParity(Parity\|string $parity)` | `none`, `odd`, `even`, `mark`, `space` | `none` |
| `confCharacterLength(int $bits)` | `5` to `8` | `8` |
| `confStopBits(StopBits\|int\|float $length)` | `1`, `1.5`, `2` | `1` |
| `confFlowControl(FlowControl\|string $mode)` | `none`, `rts/cts`, `xon/xoff` | `none` |
| `confTransferMode(TransferMode\|string $mode)` | `text`, `binary` | `binary` |
| `confDtr(bool $enabled)` | `true`, `false` | `true` |

All `conf*()` methods throw a `ConfigurationException` when the device is already open. Close it first, change the configuration, then open it again.

Enums are available for autocompletion and typo safety:

```php
use Hyperthese\PhpSerial\FlowControl;
use Hyperthese\PhpSerial\Parity;
use Hyperthese\PhpSerial\StopBits;
use Hyperthese\PhpSerial\TransferMode;

$serial->confParity(Parity::Even)
    ->confStopBits(StopBits::Two)
    ->confFlowControl(FlowControl::RtsCts)
    ->confTransferMode(TransferMode::Text);
```

Transfer modes:

- `binary` (default) puts the terminal in raw mode: no echo, no line editing, no signal generation. Every byte is delivered to PHP.
- `text` keeps canonical mode, echo and signal handling. It is convenient for an interactive terminal but unsuitable for binary protocols.

## Platform notes

### Linux

- The PHP user must be able to open the device. Add it to the `dialout` group (Debian/Ubuntu) or `uucp` group (Arch), then log out and back in.
- Common names: `/dev/ttyUSB0` (USB adapter), `/dev/ttyACM0` (CDC/Arduino), `/dev/ttyS0` (native UART).
- `COM1` is mapped to `/dev/ttyS0`, `COM2` to `/dev/ttyS1`, and so on; an integer `1` is treated as `COM1`.
- `mark` and `space` parity require `cmspar` and are Linux-only.
- `stty` is detected with `command -v stty`, which works even without a TTY (Apache, php-fpm, cron).

### macOS

- Prefer `/dev/cu.*` over `/dev/tty.*`. The `tty.*` variant waits for carrier detect and can make `open()` hang when no modem asserts DTR.
- `stty -f` is used instead of `stty -F`.
- `mark`, `space` parity and 1.5 stop bits are not supported.
- Serial devices are normally world-writable on macOS; if yours is not, fix its ownership or permissions.

### Windows

- Pass `COM3` or `3` to `deviceSet()`. The resolved handle is `\\.\COM<n>`, which also works for `COM10` and above.
- Configuration is applied with `mode COM<n>: ...`, which resets unlisted options, so the library sends the full setting set in a single command.
- Windows cannot open a COM port in non-blocking mode; reads still accept a timeout, implemented with polling.
- Install the adapter driver (FTDI, Prolific, CH340/CH341, Silicon Labs). Disable the UART FIFO in the driver's advanced settings if you see truncated data.
- Only one process can open a COM port at a time. Close PuTTY, the Arduino IDE serial monitor or any other tool first.

### Arduino / ESP boards

Opening the port may toggle DTR and reset the board. Sleep 1-2 seconds after `deviceOpen()` before sending data, or use `confDtr(false)` if the board's reset behaviour gets in the way. On POSIX, `confDtr(true)` passes `-hupcl` (DTR is not dropped on close); `confDtr(false)` passes `hupcl`.

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `The "stty" utility is required...` or, in 1.x, `No stty available` | Non-interactive PHP (Apache, php-fpm, cron) has a restricted `PATH`, or `proc_open` is disabled | Ensure `stty` is installed and reachable in the web server's `PATH`; check `disable_functions` and `open_basedir` |
| `Specified serial port is not valid` (1.x) / `The serial device ... does not exist` (2.x) | Wrong path, unplugged adapter, or `COM<n>` on a non-Windows OS | Check `ls /dev/ttyUSB* /dev/ttyACM*` or the Windows device manager; use the exact device name |
| `Permission denied` when opening | The PHP user cannot access the device | Add the user to `dialout`/`uucp`, or fix the device permissions; log out and back in |
| Reads always return an empty string | Wrong baud rate, no data being sent, `count`/`timeout` too small, or flow control mismatch | Verify the device's baud rate and handshake; try `readPort(timeout: 1.0)`; check `confFlowControl()` |
| Ctrl-C or Ctrl-Z appears in the payload | `binary` transfer mode disables `isig` on purpose | Use `confTransferMode('text')` if you rely on terminal signals |
| `Ctrl-C` does not stop a PHP CLI script | The script is stuck in a blocking read, not in the terminal driver | Use a timeout in `readPort()`/`readLine()` and a signal handler (see `examples/read-loop.php`) |
| Open hangs forever | Hardware flow control waits for CTS, or the device is a macOS `/dev/tty.*` waiting for DTR | 2.x opens with `r+bn` on POSIX; use `/dev/cu.*` on macOS and check the cable/handshake |
| `Unable to configure ...` / `mode` error | Wrong or busy COM port, or the port does not support the requested parity/stop bits | Close other applications, verify the port number, simplify the settings |
| `Unable to open ...` on Windows | COM port already opened by another program | Close PuTTY, the Arduino monitor, vendor tools, etc. |
| Garbage or truncated data | Baud mismatch, FIFO enabled, or 5/6/7 data bits | Match the device settings, disable the driver FIFO, use 8 data bits |
| Timeout while writing | The device is not consuming data (flow control, unplugged) | Check CTS/RTS wiring or set `confFlowControl('none')` |

## API reference

### Construction and lifecycle

| Method | Signature | Description |
| --- | --- | --- |
| `__construct` | `(?LoggerInterface $logger = null, ?PlatformInterface $platform = null)` | Creates the instance; the platform is auto-detected, the logger is optional. |
| `deviceSet` | `deviceSet(string\|int $device): self` | Resolves and checks the device. Throws `ConnectionException` if it does not exist. |
| `deviceOpen` | `deviceOpen(?string $mode = null, ?bool $nonBlocking = null): self` | Applies the settings and opens the port (`r+bn` on POSIX by default). |
| `deviceClose` | `deviceClose(): void` | Closes the port; a no-op when already closed. |
| `isConfigured` | `isConfigured(): bool` | Whether a device has been set. |
| `isOpen` | `isOpen(): bool` | Whether the port is currently open. |
| `getDevice` | `getDevice(): ?string` | Resolved path, e.g. `/dev/ttyUSB0` or `\\.\COM3`. |
| `getDeviceName` | `getDeviceName(): ?string` | Name as passed to `deviceSet()`, e.g. `COM3`. |
| `getSettings` | `getSettings(): SerialSettings` | Current (immutable) settings object. |
| `getPlatform` | `getPlatform(): PlatformInterface` | Platform implementation in use. |
| `getPlatformName` | `getPlatformName(): string` | `linux`, `macos` or `windows`. |
| `getStream` | `getStream()` | Underlying stream resource, or `null`. |

### Configuration

| Method | Signature | Description |
| --- | --- | --- |
| `confBaudRate` | `confBaudRate(int $rate): self` | Sets the baud rate. |
| `confParity` | `confParity(Parity\|string $parity): self` | Sets the parity (`none`, `odd`, `even`, `mark`, `space`). |
| `confCharacterLength` | `confCharacterLength(int $bits): self` | Sets the data bits (`5`-`8`). |
| `confStopBits` | `confStopBits(StopBits\|int\|float $length): self` | Sets the stop bits (`1`, `1.5`, `2`). |
| `confFlowControl` | `confFlowControl(FlowControl\|string $mode): self` | Sets the flow control (`none`, `rts/cts`, `xon/xoff`). |
| `confTransferMode` | `confTransferMode(TransferMode\|string $mode): self` | Selects `binary` (raw) or `text` (canonical) mode. |
| `confDtr` | `confDtr(bool $enabled): self` | Controls DTR/hangup behaviour. |
| `setAutoFlush` | `setAutoFlush(bool $autoFlush): self` | Whether `sendMessage()` flushes immediately (default `true`). |

### Input/output

| Method | Signature | Description |
| --- | --- | --- |
| `sendMessage` | `sendMessage(string $message, int\|float $waitForReply = 0.0): self` | Appends to the write buffer, flushes if auto-flush is on, then sleeps for `$waitForReply` seconds. |
| `write` | `write(string $data): int` | Writes immediately, bypassing the buffer; returns the byte count. |
| `readPort` | `readPort(int $count = 0, int\|float\|null $timeout = null): string` | Reads up to `$count` bytes (`0` = available), waiting `$timeout` seconds. |
| `readLine` | `readLine(int $maxLength = 0, string $delimiter = "\n", int\|float\|null $timeout = null): string` | Reads one line including the delimiter. |
| `serialflush` | `serialflush(): self` | 1.x-compatible alias of `flush()`. |
| `flush` | `flush(): self` | Writes the buffered data and flushes the stream. |

### Exceptions

All exceptions extend `Hyperthese\PhpSerial\Exception\PhpSerialException`, itself a `RuntimeException`.

| Exception | Thrown when |
| --- | --- |
| `ConfigurationException` | The configuration is invalid, the port is already open, or the port is not open. |
| `ConnectionException` | The device does not exist, cannot be opened, or cannot be configured. |
| `UnsupportedConfigurationException` | A valid option is not supported by the platform (e.g. mark parity on macOS, 1.5 stop bits with `stty`). |
| `UnsupportedPlatformException` | The operating system is not Linux, macOS or Windows. |
| `IOException` | A stream read/write/flush operation fails. |

`UnsupportedConfigurationException` extends `ConfigurationException`; `PhpSerialException` extends `RuntimeException`.

## Migration from 1.x

```php
// 1.x
include 'PhpSerial.php';
$serial = new PhpSerial;
$serial->deviceSet('COM4');
$serial->deviceOpen('w+');
$serial->confBaudRate(9600); // changed the port immediately

// 2.x
use Hyperthese\PhpSerial\PhpSerial;

$serial = new PhpSerial();
$serial->deviceSet('COM4')
    ->confBaudRate(9600)
    ->deviceOpen();
```

- **Namespace:** the class is now `Hyperthese\PhpSerial\PhpSerial`. The global `PhpSerial` alias is kept for Composer users through `src/deprecated.php`.
- **Constructor:** the PHP 4-style `PhpSerial()` method is now `__construct()`; it takes an optional PSR-3 logger and platform implementation.
- **Errors:** warnings, `trigger_error()` and `false` returns are replaced by typed exceptions.
- **Fluent API:** every `conf*()` method returns `$this`, so calls can be chained.
- **Configuration timing:** `conf*()` only updates settings. They are applied by `deviceOpen()`, so call them before opening; calling them while open throws.
- **Reading:** `readPort()` now takes an optional timeout and never returns more than `count` bytes. `readLine()` is new. The 1.x loop could over-read and block indefinitely; 2.x reads are bounded by both `count` and the timeout.
- **`sendMessage()` delay:** the second argument remains a sleep after flushing (it never read the reply), but now defaults to `0.0`. Use `readPort(timeout: ...)` to collect the reply.
- **Removed:** public properties (`$_device`, `$_dHandle`, `$_os`, ...), `setSetserialFlag()`, and the internal `_ckOpened()`, `_ckClosed()` and `_exec()` helpers. Use the getters and exceptions instead.
- **Platform:** PHP >= 8.1 is required; PHP 4/5-style code no longer parses.

## Testing

```bash
composer check   # coding standards, static analysis, PHPUnit
composer test    # PHPUnit only
```

Integration tests create their own `socat` pseudo-terminal pair; install
`socat` (`apt install socat`, `brew install socat`) and run the suite. On
Windows the PTY tests are skipped, but the socket-based I/O tests and the
Windows platform tests still run.

CI runs the suite on Ubuntu, Windows and macOS against PHP 8.1, 8.2, 8.3 and 8.4.
The real Windows COM test is separate: it targets a self-hosted Windows runner
with a null-modem pair, configured with `PHPSERIAL_TEST_COM_A` and
`PHPSERIAL_TEST_COM_B`.

## Contributing

Bug reports, patches and device reports are welcome on [GitHub](https://github.com/Xowap/PHP-Serial/issues). Please keep the existing code style (`composer cs`), add tests when possible and run `composer check` before opening a pull request.

The branch model is simple: work happens on `develop`, releases are merged to
`master`, and a `v*` tag publishes a GitHub release (and Packagist, when the
package hook is configured). See the [contributing guide](https://xowap.github.io/PHP-Serial/contributing/)
for details.

## Credits

PHP Serial was created by Rémy Sanchez in 2007. Version 2.0 integrates the
intent of many community pull requests and issue reports; see
[CHANGELOG.md](CHANGELOG.md#credits) and [CONTRIBUTORS](CONTRIBUTORS) for the
full list. Thanks to everyone who tested the library against real hardware.

## License

PHP Serial is distributed under the Apache License, version 2.0. See [LICENSE](LICENSE) and [NOTICE](NOTICE).

PHP Serial, Copyright (C) 2007-2026 PHP Serial's contributors.
