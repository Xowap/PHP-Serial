# Migration from 1.x

Version 2.0 keeps the concepts of 1.x but rebuilds the public API around typed
settings, explicit exceptions and deterministic reads. This page lists every
change that can affect existing code.

## Requirements

- **PHP 8.1 or newer.** The 1.x code contained a PHP 4-style constructor and
  other syntax that no longer parses on PHP 8. There is no compatibility mode
  for older PHP versions.
- **Composer is recommended.** It provides PSR-4 autoloading and the legacy
  class alias. Manual installations must register their own autoloader; see
  [Installation](installation.md#manual-installation).

```bash
composer require "hyperthese/php-serial:^2.0"
```

## Class name and namespace

The class is now `Hyperthese\PhpSerial\PhpSerial` and is `final`.

```php
// 1.x
$serial = new PhpSerial();

// 2.x
use Hyperthese\PhpSerial\PhpSerial;

$serial = new PhpSerial();
```

Composer loads `src/deprecated.php`, which registers a global `PhpSerial` alias
when the class does not already exist. This keeps old code working:

```php
// Still works with Composer, but is deprecated.
$serial = new \PhpSerial();
```

The alias is a transition aid, not a permanent API. New code should use the
namespaced class. If you install manually, the alias is not registered
automatically; call `class_alias()` yourself if you need it.

!!! warning "Do not define your own PhpSerial class"

    The alias is only created when no global `PhpSerial` class exists. If your
    project defines one, the compatibility alias is skipped and the two classes
    will diverge.

## Constructor

The PHP 4-style method named after the class is gone. Use `__construct()`,
which accepts an optional PSR-3 logger and an optional platform implementation:

```php
// 1.x
$serial = new PhpSerial();

// 2.x
$serial = new PhpSerial();                       // Auto-detected platform.
$serial = new PhpSerial($logger);                // With a PSR-3 logger.
$serial = new PhpSerial($logger, $platform);     // With a custom platform.
```

See [Usage](usage.md#psr-3-logging) for a logging example.

## Errors are exceptions

1.x reported problems with `trigger_error()`, `E_USER_ERROR`, warnings and
`false` return values. 2.x throws exceptions that extend
`Hyperthese\PhpSerial\Exception\PhpSerialException`:

```php
use Hyperthese\PhpSerial\Exception\PhpSerialException;

try {
    $serial->deviceSet('/dev/ttyUSB0')->deviceOpen();
    $serial->sendMessage("AT\r\n");
    $reply = $serial->readPort(timeout: 1.0);
} catch (PhpSerialException $exception) {
    error_log($exception->getMessage());
} finally {
    $serial->deviceClose();
}
```

| Situation | 1.x | 2.x |
| --- | --- | --- |
| Device missing | Warning / `false` | `ConnectionException` |
| Invalid setting | Warning / `false` | `ConfigurationException` |
| Unsupported option | Warning | `UnsupportedConfigurationException` |
| Read or write failure | Warning / `false` | `IOException` |
| Unsupported OS | Warning | `UnsupportedPlatformException` |
| Port not open | Warning / `false` | `ConfigurationException` |

`UnsupportedConfigurationException` extends `ConfigurationException`, so a
single catch for `ConfigurationException` covers both.

## Fluent return values

Every `conf*()` method and the lifecycle methods `deviceSet()` and
`deviceOpen()` return `$this`. Existing sequential code still works, and new
code can chain:

```php
// 1.x style, still valid.
$serial->deviceSet('/dev/ttyUSB0');
$serial->confBaudRate(115200);
$serial->confParity('none');
$serial->deviceOpen();

// 2.x style.
$serial->deviceSet('/dev/ttyUSB0')
    ->confBaudRate(115200)
    ->confParity('none')
    ->deviceOpen();
```

`sendMessage()`, `flush()` and `serialflush()` also return the instance.
`write()` returns the number of bytes written; `readPort()` and `readLine()`
return strings.

## Configuration is applied when the port opens

This is the most important behavioral change. In 1.x, a `conf*()` call
reconfigured the port immediately. In 2.x, it only updates the pending
`SerialSettings` object; `deviceOpen()` applies everything in one `stty` or
`mode` command.

```php
// 1.x: this changed the baud rate on the already-open port.
$serial->deviceOpen('w+');
$serial->confBaudRate(115200);

// 2.x: configure first, then open.
$serial->confBaudRate(115200)
    ->deviceOpen('w+b');
```

Calling a `conf*()` method while the port is open throws a
`ConfigurationException`. Close the device first:

```php
$serial->deviceClose();
$serial->confBaudRate(57600);
$serial->deviceOpen();
```

`deviceSet()` follows the same rule. You cannot select another device while the
current one is open.

## `readPort()` signature and semantics

```php
// 2.x
public function readPort(int $count = 0, int|float|null $timeout = null): string
```

- `$count` is a maximum. The method never returns more than `$count` bytes.
- `$timeout` is new. `null` returns immediately; a positive value waits for
  data or until the timeout expires.
- With `$count = 0` and a timeout, it waits for the first byte and then drains
  what is buffered.
- With `$count > 0` and a timeout, it waits until that many bytes arrive or the
  timeout expires.
- Reads can no longer block indefinitely, and they no longer over-read past the
  requested count.

Typical replacements:

```php
// 1.x: read whatever was available (could block or over-read).
$data = $serial->readPort();

// 2.x: return immediately with what is available.
$data = $serial->readPort();

// 2.x: wait up to one second for data.
$data = $serial->readPort(timeout: 1.0);

// 2.x: wait for up to 64 bytes.
$data = $serial->readPort(count: 64, timeout: 1.0);

// 2.x: read a line, including its delimiter.
$line = $serial->readLine(timeout: 1.0);
```

`readLine()` is new in 2.0. It includes the delimiter when found (unless
`$maxLength` truncates the line first), returns a partial line on timeout, and
keeps leftover bytes for the next read.

## `sendMessage()` delay

The second argument remains a sleep after flushing, but it no longer reads the
reply and now defaults to `0.0`. Pass a value explicitly if a device needs a
pause:

```php
// Sleep 100 ms after flushing.
$serial->sendMessage("AT\r\n", 0.1);

// Collect the reply with a timeout.
$reply = $serial->readPort(timeout: 1.0);
```

If 1.x code relied on the default delay, add the delay explicitly rather than
depending on the new default.

## Removed public properties

The underscore-prefixed public properties are gone. Use the getters instead:

| 1.x | 2.x |
| --- | --- |
| `$_device` | `getDevice()` |
| `$_winDevice` | No equivalent; the resolved Windows path is returned by `getDevice()` |
| `$_dHandle` | `getStream()` |
| `$_dState` | `isConfigured()` and `isOpen()` |
| `$_buffer` | No public access; use `flush()` and `write()` |
| `$_os` | `getPlatformName()` |
| `$autoFlush` | `setAutoFlush()` |

```php
// 1.x
$handle = $serial->_dHandle;
$path = $serial->_device;

// 2.x
$handle = $serial->getStream();   // resource|null
$path = $serial->getDevice();     // string|null
```

Do not close the resource returned by `getStream()` directly. Use
`deviceClose()`.

## Removed methods and constants

The following 1.x members have no direct replacement:

- `setSetserialFlag()` — configure the port with the operating system tools
  (`setserial`, udev rules or the driver) before opening it.
- `_ckOpened()`, `_ckClosed()` and `_exec()` — internal helpers. State checks
  are now enforced by exceptions.
- `SERIAL_DEVICE_*` constants — use `isConfigured()` and `isOpen()`.
- The old `include 'PhpSerial.php'` workflow and the outdated examples.

## Windows path changes

1.x built device paths by concatenation, which failed for `COM10` and above.
2.x resolves every port to `\\.\COM<n>` and passes `COM<n>:` to `mode`.

```php
// 1.x
$serial->deviceSet('COM4');
$serial->deviceOpen('w+');

// 2.x
$serial->deviceSet('COM4')   // or 4, 'COM4:', '\\\\.\\COM4'
    ->deviceOpen();
```

The resolved handle path is available through `getDevice()`, for example
`\\.\COM4`. The name you passed is available through `getDeviceName()`, for
example `COM4`.

## Before and after

A complete 1.x program:

```php
<?php

include 'PhpSerial.php';

$serial = new PhpSerial;
$serial->deviceSet('COM4');
$serial->deviceOpen('w+');
$serial->confBaudRate(9600);
$serial->confParity('none');
$serial->confCharacterLength(8);
$serial->confStopBits(1);
$serial->confFlowControl('none');

$serial->sendMessage("AT\r\n", 0.1);
$reply = $serial->readPort();

echo $reply;
$serial->deviceClose();
```

The same program in 2.x:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Hyperthese\PhpSerial\Exception\PhpSerialException;
use Hyperthese\PhpSerial\PhpSerial;

$serial = new PhpSerial();

try {
    $serial->deviceSet('COM4')
        ->confBaudRate(9600)
        ->confParity('none')
        ->confCharacterLength(8)
        ->confStopBits(1)
        ->confFlowControl('none')
        ->deviceOpen('r+b');

    $serial->sendMessage("AT\r\n");
    $reply = $serial->readPort(timeout: 1.0);

    echo $reply;
} catch (PhpSerialException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
} finally {
    $serial->deviceClose();
}
```

`deviceOpen('r+b')` is optional here. On Windows the default is `r+b`; on POSIX
it is `r+bn`, which adds non-blocking mode. The library always adds the `b`
flag, and adds `n` when non-blocking mode is enabled.

## Migration checklist

- [ ] Require PHP 8.1 or newer.
- [ ] Import `Hyperthese\PhpSerial\PhpSerial`, or confirm the Composer alias is
      loaded.
- [ ] Move all `conf*()` calls before `deviceOpen()`.
- [ ] Close the port before changing settings or selecting another device.
- [ ] Replace warnings/`false` checks with `try`/`catch` and `finally`.
- [ ] Replace `$_dHandle` with `getStream()`, `$_device` with `getDevice()`,
  `$_os` with `getPlatformName()`.
- [ ] Replace `$autoFlush` with `setAutoFlush()`.
- [ ] Add an explicit timeout to reads that must not block.
- [ ] Add a delay argument to `sendMessage()` if the device needs one.
- [ ] Replace `setSetserialFlag()` and `SERIAL_DEVICE_*` usages.
- [ ] Test under the same user and environment as production.

## See also

- [Usage](usage.md) — the 2.x lifecycle and I/O patterns.
- [Configuration](configuration.md) — settings and platform limitations.
- [API reference](api.md) — exact signatures.
- [Changelog](changelog.md) — the complete 2.0.0 change list.
