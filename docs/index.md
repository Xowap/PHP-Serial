# PHP Serial

PHP Serial gives PHP applications direct access to serial ports on Linux, macOS
and Windows. It provides a small, typed API for opening a device, applying line
settings, writing arbitrary bytes and reading with explicit timeouts, while
handling the platform differences behind a single interface. The library
supports PHP 8.1 through 8.4 and is released as
[`hyperthese/php-serial`](https://packagist.org/packages/hyperthese/php-serial)
under the `Hyperthese\PhpSerial` namespace.

## Features

- **Linux, macOS and Windows.** `stty` is used on POSIX systems and `mode` on
  Windows, with correct handling of `COM10` and above.
- **Non-blocking operation and timeouts.** POSIX devices open with `r+bn`;
  every read accepts an optional timeout, so a missing device cannot block a
  request forever.
- **Raw and text transfer modes.** Binary mode delivers control bytes such as
  Ctrl-C and Ctrl-Z untouched; text mode restores canonical line handling.
- **Typed, modern PHP.** PSR-4 autoloading, PSR-12 style, native enums,
  immutable settings and a `final` class.
- **Optional PSR-3 logging.** Inject any PSR-3 logger to trace device and I/O
  operations.
- **Typed exceptions.** Failures raise exceptions instead of warnings or
  `false` return values.
- **Fluent configuration.** `conf*()` methods return the instance and are
  applied atomically when the device is opened.

## Quick start

Install the package with Composer:

```bash
composer require hyperthese/php-serial
```

Then open a device, send a command and read the reply:

```php
<?php

declare(strict_types=1);

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

    // Wait up to one second for the first byte, then drain what is available.
    $reply = $serial->readPort(timeout: 1.0);

    echo $reply === '' ? "No reply\n" : $reply;
} catch (PhpSerialException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
} finally {
    $serial->deviceClose();
}
```

Configuration methods only update the pending settings; `deviceOpen()` runs
`stty` or `mode` and then opens the port. See [Usage](usage.md) for the full
lifecycle and [Configuration](configuration.md) for every setting.

## Why 2.0?

The 2.0 release fixes six families of long-standing reports that could not be
addressed safely in the 1.x code base:

- **PHP 8 parse error.** The PHP 4-style constructor and legacy syntax no
  longer parse on current PHP versions.
- **PHP 4 constructor.** Replaced by `__construct()`, with optional logger and
  platform injection.
- **`stty` outside a TTY.** Availability is detected with `command -v stty`
  instead of running `stty` on stdin, so Apache, php-fpm, cron and systemd
  work.
- **Windows paths and mode.** Handles are opened as `\\.\COM<n>`, `mode` gets
  `COM<n>:`, and full settings are sent in one command.
- **`readPort()` over-read.** Reads never return more than the requested byte
  count and are bounded by an explicit timeout.
- **POSIX device names.** `ttyUSB0`, `ttyACM0`, `cu.usbserial-*`, `COM<n>` and
  integer port numbers all resolve correctly.

!!! note "Upgrading from 1.x"

    The class moved to `Hyperthese\PhpSerial\PhpSerial`, errors are now
    exceptions and configuration is applied when the port is opened. The
    global `PhpSerial` alias is still available through Composer for existing
    code. See [Migration from 1.x](migration.md) for a side-by-side guide.

## Where to go next

- [Installation](installation.md) — requirements, Composer and manual setup.
- [Usage](usage.md) — opening, reading, writing, logging and polling loops.
- [Configuration](configuration.md) — settings, enums and platform caveats.
- [Platforms](platforms.md) — device names, permissions and deployment notes.
- [Troubleshooting](troubleshooting.md) — symptoms and fixes for common reports.
- [Migration from 1.x](migration.md) — what changed and how to update code.
- [API reference](api.md) — every public class and method.
