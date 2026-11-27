# Platforms

PHP Serial supports Linux, macOS and Windows. The public API is the same
everywhere; this page covers device naming, permissions, platform utilities and
deployment details.

## Linux

### Device names

| Path | Typical device |
| --- | --- |
| `/dev/ttyS0`, `/dev/ttyS1`, ... | Native UARTs. `COM1` maps to `/dev/ttyS0`, `COM2` to `/dev/ttyS1`, and so on. |
| `/dev/ttyUSB0`, `/dev/ttyUSB1`, ... | USB-to-serial bridges such as FTDI, Prolific, CH340/CH341 and CP210x. |
| `/dev/ttyACM0`, `/dev/ttyACM1`, ... | USB CDC-ACM devices, including many Arduino and ESP32 boards. |
| `/dev/serial/by-id/...` | Stable symlinks created by udev. Useful when the `ttyUSB` number changes. |

List the available devices with:

```bash
ls -l /dev/ttyUSB* /dev/ttyACM* /dev/ttyS* 2>/dev/null
ls -l /dev/serial/by-id/ 2>/dev/null
```

`deviceSet()` also accepts the short name (`ttyUSB0`) and prefixes `/dev/`
automatically, and it accepts `/dev/serial/by-id/...` unchanged.

### Permissions

The PHP user must be able to open the device. On most distributions, serial
devices belong to a dedicated group:

| Distribution | Group |
| --- | --- |
| Debian, Ubuntu, Raspberry Pi OS | `dialout` |
| Arch Linux, Manjaro | `uucp` |
| Fedora, RHEL | `dialout` |

Add the user and start a new login session:

```bash
sudo usermod -aG dialout "$USER"
# Log out and back in, or use `newgrp dialout` in the current shell.
id
```

For a service, restart it after changing the group so the new membership is
picked up:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart apache2
```

Check the device permissions directly:

```bash
ls -l /dev/ttyUSB0
# crw-rw---- 1 root dialout 188, 0 Nov 27 10:00 /dev/ttyUSB0
```

If the group is wrong or the mode is too restrictive, fix it with a udev rule
rather than a one-off `chmod`, which is lost when the device is replugged.

!!! tip "brltty and CH340 adapters"

    On some Ubuntu releases, the `brltty` braille service claims CH340/CH341
    adapters and removes their `ttyUSB` node. If the adapter appears in
    `lsusb` but no `/dev/ttyUSB*` exists, check `dmesg` and consider disabling
    or removing `brltty`.

### stty

Settings are applied with a single `stty` invocation. Install coreutils if
`stty` is missing:

```bash
sudo apt install coreutils   # Debian/Ubuntu
sudo dnf install coreutils   # Fedora
```

Availability is detected with `command -v stty`, which works without a TTY.
This is what allows the library to run under Apache, php-fpm, cron and systemd.

### ser2net and remote serial ports

`ser2net` exposes a serial port over TCP. PHP Serial opens local character
devices, not TCP sockets, so a `ser2net` endpoint cannot be passed directly to
`deviceSet()`.

If you need to reach a remote port, bridge the TCP stream to a local PTY and
point the library at that PTY:

```bash
socat pty,link=/tmp/ttyREMOTE,raw,echo=0 tcp:serial-host:7000
```

```php
$serial->deviceSet('/tmp/ttyREMOTE')->deviceOpen();
```

This works because a PTY is a character device, but it adds a network hop and
`ser2net` must be configured for raw, untelneted transport. For production,
prefer a local USB adapter or a dedicated serial daemon.

## macOS

### Device names

macOS creates two nodes per serial device:

| Path | Behavior |
| --- | --- |
| `/dev/cu.*` | Callout device. Opens without waiting for carrier detect. **Use this.** |
| `/dev/tty.*` | Dial-in device. May block in `open()` until carrier detect is asserted. |

Typical names are `/dev/cu.usbserial-1420`, `/dev/cu.usbmodem14201` and
`/dev/cu.SLAB_USBtoUART`. List them with:

```bash
ls -l /dev/cu.* /dev/tty.*
```

Prefer `/dev/cu.*`. A `/dev/tty.*` device can make `deviceOpen()` appear to
hang when no modem asserts DTR.

### Permissions and drivers

Serial devices are normally world-readable and world-writable on macOS. If
yours is not, correct its ownership or permissions. The user running PHP needs
access to the device node.

Many adapters need a vendor driver:

- FTDI: FTDI VCP driver.
- Silicon Labs: CP210x VCP driver.
- Prolific: PL2303 driver (version depends on the chip revision).
- WCH: CH34x driver.

If no `/dev/cu.*` node appears, check whether the adapter is detected at all:

```bash
system_profiler SPUSBDataType
```

Apple Silicon Macs need a driver build that supports the current macOS
version. A device that works on an Intel Mac may not appear without an updated
driver.

### stty on macOS

macOS uses `stty -f <device>` instead of the GNU `stty -F <device>`. The
library selects the correct flag automatically. `stty` is part of the base
system.

### Unsupported settings

- `mark` and `space` parity require `cmspar`, which macOS does not provide.
  Selecting either throws an `UnsupportedConfigurationException`.
- 1.5 stop bits are not supported by `stty`. Use 1 or 2.

## Windows

### Device names

Windows COM ports are named `COM1` through `COM256`. Pass any of these forms to
`deviceSet()`:

```php
$serial->deviceSet('COM3');
$serial->deviceSet('COM3:');
$serial->deviceSet(3);
$serial->deviceSet('\\\\.\\COM3');
```

All resolve to the handle path `\\.\COM<n>`. This namespace also works for
`COM10` and above, where the bare `COM10` name is not a valid file path.

The `mode` command, on the other hand, expects `COM<n>:`. The library converts
the handle path before running `mode`, so you do not need to do it yourself.

List the available ports:

```cmd
mode
```

Show the current settings of one port:

```cmd
mode COM3
```

### Drivers

Install the adapter vendor's driver:

- FTDI VCP
- Prolific PL2303
- WCH CH340/CH341
- Silicon Labs CP210x

Check Device Manager under **Ports (COM & LPT)**. If the adapter appears with a
warning icon, the driver is missing or failed to start. If a port number
changes after replugging, use Device Manager or the vendor tool to assign a
fixed COM number.

If data is truncated, open the adapter's advanced properties in Device Manager
and disable the UART FIFO, or lower the latency timer. Some cheap adapters also
misbehave at high baud rates.

### Binary mode and non-blocking reads

Windows cannot open a COM handle in non-blocking mode, so the library opens it
with `r+b` and implements read timeouts by polling. The `b` flag is always
present, which prevents CRLF translation; every byte is delivered unchanged.

The `stty`-style transfer mode does not apply on Windows. COM handles deliver
raw bytes regardless of `confTransferMode()`.

### Port busy

Only one process can open a COM port at a time. If `deviceOpen()` fails with
`Unable to open ...`, close any other program using the port:

- PuTTY or another terminal emulator.
- The Arduino IDE serial monitor or plotter.
- Vendor configuration tools.
- Another PHP process or service.

A port can also remain busy briefly after a crashed process exits. Wait a
moment and retry.

### COM10 and above

Older serial code often concatenated `COM` with the port number, producing
paths that Windows cannot open above `COM9`. PHP Serial uses `\\.\COM<n>` for
the handle and `COM<n>:` for `mode`, so `COM10`, `COM16` and higher work
without special handling.

## Web servers, PHP-FPM and cron

A web request runs as the web server user, with a minimal environment. Three
things commonly differ from your interactive shell:

1. **Group membership.** `www-data` is usually not in `dialout`.
2. **`PATH`.** The service may not include `/usr/bin` or `/bin`, so
   `command -v stty` fails even though `stty` is installed.
3. **PHP restrictions.** `proc_open()` may be listed in `disable_functions`,
   and `open_basedir` may exclude `/dev`.

### Apache and PHP-FPM

Add the service user to the serial group:

```bash
sudo usermod -aG dialout www-data
sudo systemctl restart apache2
# or
sudo systemctl restart php8.3-fpm
```

For PHP-FPM, you can also set the environment explicitly in the pool
configuration:

```ini
; /etc/php/8.3/fpm/pool.d/www.conf
env[PATH] = /usr/local/bin:/usr/bin:/bin
```

Check the effective restrictions:

```bash
php -i | grep -E 'disable_functions|open_basedir'
```

`proc_open` must not be disabled. If `open_basedir` is set, it must include the
device path (for example `/dev`).

### Long-running access

A serial port is exclusive. Do not open it once per web request and keep it
open across requests: PHP's process model does not give you a stable owner.
Instead:

- Open, use and close the port within one request, with short timeouts.
- Or run a CLI worker/daemon that owns the port and let web requests talk to it
  through a queue, socket or database.

### cron and systemd

Cron jobs often run with `SHELL=/bin/sh` and a minimal `PATH`. Set both the
environment and the user explicitly:

```cron
SHELL=/bin/sh
PATH=/usr/local/bin:/usr/bin:/bin

*/5 * * * * www-data /usr/bin/php /srv/app/bin/poll-serial.php >> /var/log/serial.log 2>&1
```

For systemd, grant the group and a usable `PATH`:

```ini
[Service]
Type=simple
User=www-data
Group=www-data
SupplementaryGroups=dialout
Environment=PATH=/usr/local/bin:/usr/bin:/bin
ExecStart=/usr/bin/php /srv/app/bin/serial-worker.php
Restart=on-failure
```

!!! warning "Sandboxing can hide devices"

    systemd hardening options such as `PrivateDevices=true`, `DevicePolicy=closed`
    and some `Protect*` settings can hide `/dev/ttyUSB*` from the service. Add
    an explicit `DeviceAllow=/dev/ttyUSB0 rw` or relax the relevant option.

After changing group membership, restart the service. A running process keeps
the group list it started with.

## Arduino and ESP boards

Many Arduino and ESP32 boards connect DTR (and sometimes RTS) to the reset
circuit. Opening the port can therefore reset the board, and the bootloader
takes a moment to start.

```php
$serial->deviceSet('/dev/ttyACM0')
    ->confBaudRate(115200)
    ->deviceOpen();

// Wait for the bootloader and the sketch to start.
usleep(2_000_000);

$serial->sendMessage("STATUS\n");
```

Guidelines:

- Sleep one to two seconds after `deviceOpen()` before sending data.
- Read and discard the boot banner if the sketch prints one.
- `confDtr(false)` releases DTR on close (`hupcl`) and can prevent some reset
  behavior, depending on the board and adapter. It must be called before
  `deviceOpen()`.
- On ESP32 boards with native USB CDC, the reset behavior differs from boards
  with a separate USB-serial chip. If the port disappears and reappears, the
  board rebooted.
- If the board resets on every open and that is unacceptable, use a board
  wiring or adapter that does not connect DTR to reset, or keep a worker
  process running and open the port once.

## Containers

Pass the device into the container and make sure the container user can open
it. Docker preserves the host device's ownership and permissions.

```bash
docker run --rm -it \
  --device=/dev/ttyUSB0 \
  --group-add dialout \
  -v "$PWD":/app -w /app \
  php:8.3-cli \
  php script.php
```

If the container's `dialout` group has a different GID from the host's, add the
numeric GID instead:

```bash
--group-add "$(getent group dialout | cut -d: -f3)"
```

With Docker Compose:

```yaml
services:
  app:
    image: php:8.3-cli
    devices:
      - "/dev/ttyUSB0:/dev/ttyUSB0"
    group_add:
      - dialout
    volumes:
      - ".:/app"
    working_dir: /app
```

Notes:

- The device must exist on the host when the container starts. `--device`
  entries are fixed; replugging the adapter may require restarting the
  container.
- Minimal images may not include `stty`. Debian-based images have it through
  coreutils; Alpine provides it through busybox.
- `--privileged` is not required for a single device and is not recommended.
- On Windows, use native Windows PHP rather than a Linux container if the COM
  port is on the host. Linux containers do not receive Windows COM handles.

## See also

- [Installation](installation.md) — requirements and verification.
- [Configuration](configuration.md) — serial settings and platform limitations.
- [Troubleshooting](troubleshooting.md) — permissions, busy ports and empty reads.
- [Usage](usage.md) — lifecycle, reading and writing.
