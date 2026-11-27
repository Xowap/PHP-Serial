# Contributing

Bug reports, patches and device reports are welcome on
[GitHub](https://github.com/Xowap/PHP-Serial/issues).

## Development setup

Requirements:

- PHP 8.1 or newer with `proc_open()` enabled.
- Composer 2.
- `stty` on Linux/macOS; `mode` on Windows.
- `socat` for the integration tests on POSIX.

```bash
git clone https://github.com/Xowap/PHP-Serial.git
cd PHP-Serial
composer install
```

The repository's `composer.json` defines the PSR-4 autoloader for
`Hyperthese\PhpSerial\` and the test namespace `Hyperthese\PhpSerial\Tests\`.

## Checks

Run the full suite before opening a pull request:

```bash
composer check
```

`composer check` runs, in order:

1. `composer cs` — PHP_CodeSniffer with PSR-12.
2. `composer cs:check` — php-cs-fixer in dry-run mode.
3. `composer analyse` — PHPStan at level 8 over `src/`.
4. `composer test` — PHPUnit.

Individual commands are available:

```bash
composer cs          # Check coding standards.
composer cs:check    # Verify formatting without changing files.
composer cs:fix      # Fix coding standards with php-cs-fixer.
composer analyse     # Static analysis.
composer test        # All PHPUnit tests.
composer test:coverage
```

## Unit and integration tests

The PHPUnit configuration defines two test suites:

| Suite | Directory | What it covers |
| --- | --- | --- |
| `unit` | `tests/Unit` | Settings, lifecycle, platform command generation, `Port` semantics, using fakes and local sockets. |
| `integration` | `tests/Integration` | The full stack against a real PTY pair, plus `stty` availability without a TTY. |

Run only one suite:

```bash
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration
```

### socat

The POSIX integration tests create a pseudo-terminal pair with `socat` and skip
when it is not installed:

```bash
sudo apt install socat   # Debian/Ubuntu
brew install socat       # macOS
```

The test suite starts and stops its own `socat` process. To experiment
manually, create a pair and use one of the printed `/dev/pts/N` paths:

```bash
socat -d -d pty,raw,echo=0 pty,raw,echo=0
```

Windows integration tests are skipped because they require a real COM port and
PTY pairs are not available.

## Windows hardware tests

Hosted Windows runners cannot install a kernel-mode virtual serial driver:
com0com is test-signed and needs test signing (or Secure Boot disabled) plus a
reboot. The real COM port test is therefore a separate, manually triggered
workflow:

| Requirement | Value |
| --- | --- |
| Runner labels | `self-hosted`, `windows`, `x64`, `serial` |
| Repository variables | `PHPSERIAL_TEST_COM_A`, `PHPSERIAL_TEST_COM_B` |
| Wiring | Two COM ports connected as a null modem (TX↔RX, GND↔GND) |
| Workflow | **Windows hardware serial** → *Run workflow* |

The test opens both ports through `PhpSerial`, checks the `\\.\COM<n>` handle
and `mode` configuration, then sends data and control bytes in both
directions.

For local development com0com can provide a port pair. On Windows 11 with
Secure Boot enabled the legacy cross-signed driver may be rejected with
error Code 52; use test signing in a disposable VM or real USB-serial
adapters instead.

## Coding standards

- PSR-12, enforced by PHP_CodeSniffer (`phpcs.xml.dist`).
- `declare(strict_types=1);` in every PHP file.
- PHPStan level 8 for `src/`.
- No unused imports; imports are sorted alphabetically.
- Keep public APIs typed and documented with PHPDoc where a native type cannot
  express the contract, such as `resource` returns.

`composer cs:fix` applies the php-cs-fixer rules, while `composer cs` and
`composer cs:check` are the authorities used by CI.

## Branch model

| Branch | Purpose |
| --- | --- |
| `feature/*`, `fix/*` | Short-lived work branches. Open a pull request against `develop`. |
| `develop` | Integration branch for the next release. |
| `master` | Stable branch. Documentation is deployed from it. |
| `v*` tags | Releases. Pushing a tag such as `v2.0.0` triggers the release workflow. |

Flow:

1. Branch from `develop`.
2. Add tests and documentation with the change.
3. Run `composer check`.
4. Open a pull request against `develop`.
5. Releases are merged to `master` and tagged `v*`.

Keep commits focused and write a clear description of the device or platform
used for testing when the change is hardware-specific.

## Reporting a bug

A useful report lets a maintainer reproduce the problem without guessing.
Include:

- **Operating system and version.** Linux distribution, macOS version or
  Windows version.
- **PHP version and SAPI.** `php -v` and `php -i | grep 'Server API'`.
- **Device.** The exact name passed to `deviceSet()`, and on POSIX the output
  of `ls -l` for the device node.
- **Port settings.** Output of `stty -F /dev/ttyUSB0 -a` on Linux,
  `stty -f /dev/cu.usbserial-1420 -a` on macOS, or `mode COM3` on Windows.
- **Environment.** Whether the code runs in CLI, Apache, php-fpm, cron, systemd
  or a container, and as which user.
- **Error.** The exact exception class and message, plus the stack trace.
- **Reproduction.** A minimal script with the configuration calls and the
  read/write sequence. Remove credentials and personal data.

Useful diagnostics:

```bash
php -v
php -i | grep -E 'Server API|disable_functions|open_basedir'
command -v stty
ls -l /dev/ttyUSB0
stty -F /dev/ttyUSB0 -a
id
dmesg | tail -30
```

On Windows:

```cmd
php -v
mode
mode COM3
```

If the problem is device-specific, state the adapter chip (FTDI, CH340,
CP210x, PL2303), the board and its firmware, the cable, and whether a loopback
test works. Reports from real hardware are especially valuable because CI
cannot cover every adapter.

## Documentation

The documentation lives in `docs/` and is built with Zensical:

```bash
pip install zensical
zensical build --clean
```

Update the relevant page when behavior, settings or limitations change. The
navigation in `zensical.toml` expects the existing file names; add new pages to
the nav as well.

## License

By contributing, you agree that your contribution is licensed under the
Apache License, version 2.0. See [`LICENSE`](https://github.com/Xowap/PHP-Serial/blob/develop/LICENSE)
and [`NOTICE`](https://github.com/Xowap/PHP-Serial/blob/develop/NOTICE).
