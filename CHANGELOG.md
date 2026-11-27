# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-11-27

First release of the modernized library, published for PHP Serial's 20th
anniversary. The public API was rebuilt around typed settings, explicit
exceptions and deterministic reads, while keeping the 1.x class name available
through a compatibility alias.

### Added

- PSR-4 autoloading under the `Hyperthese\PhpSerial` namespace.
- `SerialSettings`, an immutable value object holding baud rate, parity, data
  bits, stop bits, flow control, transfer mode and DTR behaviour.
- Native enums: `Parity`, `StopBits`, `FlowControl` and `TransferMode`.
- `readPort()` timeouts and an explicit maximum byte count.
- `readLine()` with a configurable delimiter, maximum length and timeout.
- `write()` for immediate, unbuffered writes, plus `flush()` and a
  `serialflush()` compatibility alias.
- `setAutoFlush()` to control whether `sendMessage()` flushes immediately.
- Typed exception hierarchy: `PhpSerialException`, `ConfigurationException`,
  `ConnectionException`, `UnsupportedConfigurationException`,
  `UnsupportedPlatformException` and `IOException`.
- Optional PSR-3 logger (`psr/log` ^1.1 || ^2.0 || ^3.0).
- Platform abstraction (`PlatformInterface`, `UnixPlatform`,
  `WindowsPlatform`, `PlatformFactory`) with an injectable command runner.
- Getters for the resolved device path, device name, platform, settings and
  underlying stream.
- Non-blocking POSIX opens (`r+bn`) and polling-based read timeouts on Windows.
- Documentation, a changelog, a contributor list and three runnable examples
  (`basic.php`, `sms.php`, `read-loop.php`).
- CI matrix covering Linux, macOS and Windows on PHP 8.1 to 8.4.

### Changed

- The class is now `Hyperthese\PhpSerial\PhpSerial` and is `final`.
- The PHP 4-style constructor was replaced by `__construct(?LoggerInterface
  $logger = null, ?PlatformInterface $platform = null)`.
- Configuration methods are fluent and only update pending settings.
  Settings are applied atomically by `deviceOpen()`.
- Changing configuration while the port is open now throws a
  `ConfigurationException` instead of silently reconfiguring it.
- Reads are non-blocking at the stream level and bounded by timeouts, so they
  no longer depend on `stream_set_timeout()` or `_dHandle`.
- `sendMessage()`'s second argument is now a post-flush delay; it does not
  read the reply. Use `readPort(timeout: ...)` for that.
- Transfer mode defaults to `binary` (raw line discipline). `text` restores
  canonical mode, echo and signal handling.
- DTR handling is explicit: `confDtr(true)` keeps DTR asserted (`-hupcl` on
  POSIX, `DTR=ON` on Windows), `confDtr(false)` releases it.
- POSIX device configuration is performed with one `stty` invocation instead
  of one call per option, so options can no longer undo each other.
- Windows configuration is performed with one complete `mode` command for the
  same reason.
- Minimum PHP version is 8.1; `psr/log` is the only runtime dependency.
- The project is now licensed under the Apache License 2.0 (it was GPL-2.0-only
  up to 1.x). See `LICENSE` and `NOTICE`.
- Composer scripts provide `composer check`, `composer test`, `composer cs`,
  `composer analyse` and `composer cs:fix`.

### Fixed

- PHP 8 fatal parse error caused by curly-brace string offsets (`$parity{0}`,
  `$return{0}`), which were removed in PHP 8.0 (#37, #40).
- PHP 4 constructor replaced by `__construct()`, restoring compatibility with
  current PHP versions (#27, #30).
- `stty` availability is detected with `command -v stty` instead of running
  `stty` on stdin, so it works without a TTY under Apache, php-fpm, cron and
  systemd (#1, #15, #17).
- Windows device handling: `\\.\COM<n>` handle paths, `COM<n>:` names for
  `mode`, explicit nesting of commands, and support for `COM10` and above
  (#9, #20, #22, #23).
- `readPort()` no longer over-reads past the requested byte count, and reads
  can no longer block forever: timeouts are supported end to end
  (#13, #23, #31, #33, #34, #39).
- POSIX device name resolution handles `ttyUSB0`, `ttyACM0`,
  `cu.usbserial-*`, `COM<n>` and integer port numbers (#4, #5, #7, #35).

### Removed

- Public 1.x properties (`$_device`, `$_winDevice`, `$_dHandle`, `$_dState`,
  `$_buffer`, `$_os`, `$autoFlush`).
- `setSetserialFlag()` and the internal `_ckOpened()`, `_ckClosed()` and
  `_exec()` helpers.
- Global `SERIAL_DEVICE_*` constants; use `isConfigured()` and `isOpen()`
  instead.
- The old `include 'PhpSerial.php'` workflow and the outdated examples.
- `trigger_error()` / `E_USER_ERROR` error reporting and `false` return
  values on failure.

### Credits

This release integrates the intent of many community reports and patches. The
changes were reworked and conflicts resolved for consistency with the new
architecture; they were not merged verbatim.

Code, patches and review:

- [@mattficken](https://github.com/mattficken) (#3, #6)
- [@Jeroi](https://github.com/Jeroi) (#5)
- [@vagovszky](https://github.com/vagovszky) (#8)
- [@highTowerSU](https://github.com/highTowerSU) (#16)
- [@KamranSyed](https://github.com/KamranSyed) (#25, #26)
- [@czb182](https://github.com/czb182) (#10)
- [@jakon89](https://github.com/jakon89) (#12)
- [@lsolesen](https://github.com/lsolesen) (#19)
- [@bit-world](https://github.com/bit-world) (#21)
- [@ruscoe](https://github.com/ruscoe) (#24)
- [@Zzepish](https://github.com/Zzepish) (#32)
- [@Oscurlo](https://github.com/Oscurlo) (#38)

Bug reports and testing:

- fgland, nc8q, toggio, pBra1n, tvleavitt, stollr, jaysongyn and everyone else
  who reported issues or tested the library on real hardware.

[Unreleased]: https://github.com/Xowap/PHP-Serial/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/Xowap/PHP-Serial/releases/tag/v2.0.0
