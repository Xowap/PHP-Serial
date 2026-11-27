# Changelog

## 2.0.0 — 2026-11-27

The first release of the modernized library, published for PHP Serial's 20th
anniversary. The public API was rebuilt around typed settings, explicit
exceptions and deterministic reads, while the 1.x class name remains available
through a compatibility alias.

### Added

- PSR-4 autoloading under `Hyperthese\PhpSerial`.
- `SerialSettings`, an immutable value object for baud rate, parity, data bits,
  stop bits, flow control, transfer mode and DTR behavior.
- Native enums: `Parity`, `StopBits`, `FlowControl` and `TransferMode`.
- `readPort()` timeouts and an explicit maximum byte count.
- `readLine()` with a configurable delimiter, maximum length and timeout.
- `write()` for immediate, unbuffered writes, plus `flush()` and a
  `serialflush()` compatibility alias.
- `setAutoFlush()` to control whether `sendMessage()` flushes immediately.
- A typed exception hierarchy rooted at `PhpSerialException`.
- Optional PSR-3 logging.
- A platform abstraction with injectable command runners.
- Getters for the resolved device path, device name, platform, settings and
  underlying stream.
- Non-blocking POSIX opens (`r+bn`) and polling-based read timeouts on Windows.
- Documentation, three runnable examples and a CI matrix covering Linux, macOS
  and Windows on PHP 8.1 to 8.4.

### Changed

- The class is now `Hyperthese\PhpSerial\PhpSerial` and is `final`.
- The PHP 4-style constructor was replaced by
  `__construct(?LoggerInterface $logger = null, ?PlatformInterface $platform = null)`.
- Configuration methods are fluent and only update pending settings. Settings
  are applied atomically by `deviceOpen()`.
- Changing configuration while the port is open throws a
  `ConfigurationException` instead of silently reconfiguring it.
- Reads are non-blocking at the stream level and bounded by timeouts.
- `sendMessage()`'s second argument is a post-flush delay and defaults to `0.0`.
- Transfer mode defaults to `binary`; `text` restores canonical mode, echo and
  signal handling.
- DTR handling is explicit through `confDtr()`.
- POSIX configuration uses one `stty` invocation and Windows configuration uses
  one complete `mode` command, so options cannot undo each other.
- The minimum PHP version is 8.1; `psr/log` is the only runtime dependency.

### Fixed

- PHP 8 parse failures caused by the PHP 4 constructor and legacy syntax
  (#37, #40).
- The PHP 4 constructor was replaced by `__construct()`, restoring
  compatibility with current PHP versions (#27, #30).
- `stty` availability is detected with `command -v stty` instead of running
  `stty` on stdin, so it works without a TTY under Apache, php-fpm, cron and
  systemd (#1, #15, #17).
- Windows device handling: `\\.\COM<n>` handles, `COM<n>:` names for `mode`,
  explicit command nesting and support for `COM10` and above
  (#9, #20, #22, #23).
- `readPort()` no longer over-reads past the requested count and cannot block
  forever; timeouts are supported end to end (#13, #23, #31, #33, #34, #39).
- POSIX device name resolution handles `ttyUSB0`, `ttyACM0`, `cu.usbserial-*`,
  `COM<n>` and integer port numbers (#4, #5, #7, #35).

### Credits

This release integrates the intent of many community reports and patches. The
changes were reworked and reconciled with the new architecture rather than
merged verbatim.

Code, patches and review: [@mattficken](https://github.com/mattficken) (#3,
#6), [@Jeroi](https://github.com/Jeroi) (#5),
[@vagovszky](https://github.com/vagovszky) (#8),
[@highTowerSU](https://github.com/highTowerSU) (#16),
[@KamranSyed](https://github.com/KamranSyed) (#25, #26),
[@czb182](https://github.com/czb182) (#10),
[@jakon89](https://github.com/jakon89) (#12),
[@lsolesen](https://github.com/lsolesen) (#19),
[@bit-world](https://github.com/bit-world) (#21),
[@ruscoe](https://github.com/ruscoe) (#24),
[@Zzepish](https://github.com/Zzepish) (#32) and
[@Oscurlo](https://github.com/Oscurlo) (#38).

Bug reports and hardware testing: fgland, nc8q, toggio, pBra1n, tvleavitt,
stollr, jaysongyn and everyone else who reported issues or tested the library
on real devices.

The complete, maintained change history is in
[CHANGELOG.md](https://github.com/Xowap/PHP-Serial/blob/master/CHANGELOG.md).
