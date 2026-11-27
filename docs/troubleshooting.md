# Troubleshooting

Start with the symptom table, then follow the link to the detailed section.

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `No stty available` (1.x) or `The "stty" utility is required...` (2.x) | `stty` is missing, not in the service `PATH`, or `proc_open()` is disabled | Install coreutils, fix `PATH`, check `disable_functions` and `open_basedir` |
| `Specified serial port is not valid` (1.x) or `The serial device ... does not exist` (2.x) | Wrong path, adapter unplugged, missing driver, or a COM name used on POSIX | List the real device nodes; use the exact path |
| `Permission denied` when opening | The PHP user cannot access the device | Add the user to `dialout`/`uucp`, fix udev permissions, restart the service |
| `Unable to open ...` on Windows | The COM port is already open by another program, or the number is wrong | Close PuTTY, the Arduino monitor and vendor tools; check Device Manager |
| `Unable to configure ...` | `stty`/`mode` rejected a setting, or the port is busy | Simplify the settings, verify the port, close other tools |
| Reads always return `''` | Wrong baud rate, no data, `timeout: null`, flow control mismatch, or text-mode line buffering | Try `readPort(timeout: 1.0)`, verify the baud rate, use `none` flow control |
| Garbage or truncated data | Baud mismatch, FIFO enabled, wrong data bits, or a marginal adapter | Match the device settings, disable the driver FIFO, use 8 data bits |
| `deviceOpen()` hangs | Blocking open, macOS `/dev/tty.*` waiting for carrier, or hardware flow control | Use 2.x defaults (`r+bn`), `/dev/cu.*`, and `none` flow control |
| Ctrl-C or Ctrl-Z appears in the payload | `binary` transfer mode disables signal generation on purpose | Use `confTransferMode('text')` if you need terminal signals |
| Ctrl-C does not stop a CLI script | The script is in a blocking read, or Ctrl-C is consumed as data | Use a short read timeout and a `pcntl` signal handler |
| The board resets whenever the port opens | DTR is wired to the Arduino/ESP reset circuit | Sleep after opening; try `confDtr(false)` before opening |
| `Timed out while writing` | The peer is not consuming data, flow control is wrong, or the cable is disconnected | Check CTS/RTS and XON/XOFF, try `confFlowControl('none')` |
| The device disappears after a while | USB power saving, a loose cable, or the board rebooted | Disable USB autosuspend, use a powered hub, reopen after re-enumeration |

## "No stty available"

This is the most frequently reported 1.x failure. The old code ran `stty`
without arguments to test whether the utility existed. `stty` without arguments
inspects its standard input, so it fails whenever PHP has no controlling TTY:
Apache, php-fpm, cron, systemd and most CI runners. The check reported "No stty
available" even when `stty` was installed.

2.x detects the utility with `command -v stty`, which only consults `PATH` and
does not need a TTY. If 2.x still reports that `stty` is required, one of the
following is true.

### `stty` is not installed

```bash
command -v stty
```

If this prints nothing, install coreutils:

```bash
sudo apt install coreutils   # Debian/Ubuntu
sudo dnf install coreutils   # Fedora
sudo apk add coreutils       # Alpine, if you do not use busybox
```

### The service has a restricted PATH

Apache, php-fpm and systemd do not necessarily inherit your shell's `PATH`.
Check the environment of the actual process:

```bash
sudo cat /proc/$(pgrep -f 'php-fpm' | head -1)/environ | tr '\0' '\n' | grep PATH
```

Set it explicitly:

```ini
; PHP-FPM pool
env[PATH] = /usr/local/bin:/usr/bin:/bin
```

```ini
; systemd unit
Environment=PATH=/usr/local/bin:/usr/bin:/bin
```

For Apache, `SetEnv PATH ...` in the virtual host or a wrapper script may be
needed, depending on the SAPI.

### proc_open() is disabled

The library runs `stty` and `mode` through `proc_open()`. Check:

```bash
php -r 'var_dump(function_exists("proc_open"));'
php -i | grep disable_functions
```

If `proc_open` is in `disable_functions`, remove it from the `php.ini` used by
the service and restart it. There is no fallback that avoids `proc_open`, since
PHP has no portable serial configuration API.

### open_basedir blocks the device or the utility

`open_basedir` can prevent both `file_exists()` on `/dev/ttyUSB0` and access to
the device:

```bash
php -i | grep open_basedir
```

Include the device directory in the allowed paths, for example `/dev`, or
disable the restriction for the CLI worker that owns the port. Prefer a
dedicated worker over relaxing `open_basedir` for the whole web server.

### SELinux or AppArmor

A confined service may be denied access to `/dev/ttyUSB*` even when Unix
permissions are correct. Check the audit log:

```bash
sudo ausearch -m avc -ts recent      # SELinux
sudo journalctl -k | grep -i apparmor
```

Add an appropriate policy or label the device. Do not disable the security
module globally.

## "Specified serial port is not valid" or permission denied

In 1.x, an unusable device produced `Specified serial port is not valid`. In
2.x the messages are more specific:

```text
The serial device "/dev/ttyUSB0" resolved to "/dev/ttyUSB0", but it does not exist.
On Linux, make sure the user running PHP belongs to the "dialout" group.
```

or, from `fopen()`:

```text
Unable to open the serial device "/dev/ttyUSB0" in mode "r+bn": ... Permission denied
```

### The device node does not exist

```bash
ls -l /dev/ttyUSB* /dev/ttyACM* 2>/dev/null
lsusb
dmesg | tail -30
```

Common causes:

- The adapter is unplugged or the cable is charge-only.
- The driver is missing; the device appears in `lsusb` but not as a `tty`.
- `brltty` claimed a CH340 adapter (see the Linux notes in
  [Platforms](platforms.md#linux)).
- You used `COM3` on Linux or macOS. That maps to `/dev/ttyS2`, which may not
  exist. Use the real path.
- The path is inside a container that was not started with `--device`.

### Permission denied

```bash
id
ls -l /dev/ttyUSB0
namei -l /dev/ttyUSB0
```

The user must be in the owning group (`dialout` on Debian/Ubuntu, `uucp` on
Arch) or have an ACL granting access:

```bash
sudo usermod -aG dialout "$USER"
sudo systemctl restart php8.3-fpm
```

Remember that group changes apply to new processes. Log out and back in, or
restart the service. For a container, pass the device and the correct group ID;
see [Containers](platforms.md#containers).

Test the device with the same user and a direct command:

```bash
sudo -u www-data stty -F /dev/ttyUSB0 -a
```

If that fails, PHP will fail too.

## Windows device not available or COM10+

### The port is not listed

```cmd
mode
```

`mode` with no arguments lists the COM devices known to Windows. Also check
Device Manager under **Ports (COM & LPT)**. A warning icon means the driver is
missing or failed to start.

Install the vendor driver (FTDI, Prolific, WCH CH340/CH341 or Silicon Labs
CP210x) and replug the adapter. Bluetooth serial devices may expose both an
incoming and an outgoing port; use the outgoing one.

### COM10 and above

1.x built paths such as `COM10`, which Windows cannot open as a file. 2.x
resolves every port to `\\.\COM<n>`, which works from `COM1` to `COM256`, and
passes `COM<n>:` to `mode`.

Pass the name normally:

```php
$serial->deviceSet('COM10');
// or
$serial->deviceSet(10);
```

### The port is busy

Windows allows one handle per COM port. Close:

- PuTTY, Tera Term or another terminal.
- The Arduino IDE serial monitor and plotter.
- Vendor configuration utilities.
- Any other PHP process or Windows service using the port.

If the port stays busy after a crash, wait a few seconds or unplug and replug
the adapter.

### The adapter works, but data is truncated

Open the adapter's advanced properties in Device Manager and:

- Disable the UART FIFO.
- Lower the latency timer (for example from 16 ms to 2 ms).
- Confirm the baud rate matches the device.

Some low-cost adapters produce framing errors at high rates. Test at 9600 baud
before blaming the library.

## Empty reads

`readPort()` returning `''` means no bytes were available within the requested
time. It is not an error.

### Check the timeout semantics

| Call | Behavior |
| --- | --- |
| `readPort()` | Returns immediately with whatever is buffered, often `''`. |
| `readPort(timeout: 0)` | Same as above; zero timeout. |
| `readPort(timeout: 1.0)` | Waits up to one second for the first byte. |
| `readPort(count: 64, timeout: 1.0)` | Waits up to one second for 64 bytes; returns fewer on timeout. |

If you expect data, start with:

```php
$data = $serial->readPort(timeout: 1.0);
```

If that still returns `''`, the device is not sending, the baud rate is wrong,
or the wrong device is open.

### The device sends fewer bytes than requested

Serial data arrives in fragments. `readPort(count: 64, timeout: 1.0)` waits for
64 bytes but returns after the timeout even if only 10 arrived. That is
intentional. Use one of these patterns:

```php
// Take whatever arrives, but wait for the first byte.
$data = $serial->readPort(timeout: 0.2);

// Read a complete line.
$line = $serial->readLine(timeout: 1.0);
```

### Text mode is line-buffered

In `text` transfer mode, the terminal is in canonical mode. Input is delivered
to PHP only when a newline arrives. A device that sends `OK` without a newline
can appear silent. Use the default `binary` mode for protocols that are not
line-oriented, or ensure the device terminates its lines.

### Flow control mismatch

- With `rts/cts`, the port expects the peer to assert CTS. If CTS is not wired,
  writes may block and the peer may never see the request.
- With `xon/xoff`, the peer must honor `0x11`/`0x13` flow control. A binary
  protocol that happens to contain those bytes can stall unexpectedly.

Try `confFlowControl('none')` for a three-wire connection.

### The board was still booting

An Arduino or ESP resets when the port opens. Reading immediately can miss the
first output or send a command before the sketch is ready. Sleep one to two
seconds after `deviceOpen()`; see [Arduino and ESP boards](platforms.md#arduino-and-esp-boards).

### Verify the loopback

Disconnect the device and connect TX to RX on the adapter (or use a
`null-modem` adapter). Then write and read:

```php
$serial->write('loopback');
var_dump($serial->readPort(count: 8, timeout: 1.0));
```

If the loopback works, the library and port settings are fine and the problem
is on the device side. On Linux and macOS you can also test with a `socat` PTY
pair, as described in
[Contributing](contributing.md#unit-and-integration-tests).

## Hangs on open

### POSIX blocking opens

1.x opened the device in a blocking mode by default. Some drivers block in
`open()` until carrier detect or flow control is ready, which looked like a
permanent hang. 2.x opens POSIX devices with `r+bn`, adding `O_NONBLOCK`.
`deviceOpen()` defaults `$nonBlocking` to `true`; leave it alone unless you have
a specific reason to block.

### macOS `/dev/tty.*`

The `/dev/tty.*` node waits for carrier detect. If no modem asserts DTR, the
open can block. Use the callout device:

```php
$serial->deviceSet('/dev/cu.usbserial-1420');
```

### Hardware flow control

With `rts/cts`, the settings include `-clocal crtscts`. If the cable does not
carry CTS, the port may wait indefinitely for the peer. Use:

```php
$serial->confFlowControl('none');
```

`none` sets `clocal`, which ignores modem control lines.

### The device is busy or wedged

- On Windows, a busy port usually fails with `Unable to open ...` rather than
  hanging, but Bluetooth and some drivers can take a long time.
- On Linux, a USB adapter that has crashed may need to be unplugged and
  replugged, or the kernel module reloaded.
- Check `dmesg` for USB disconnect or reset messages.

## Ctrl-C and Ctrl-Z are swallowed

This is expected in `binary` mode. The raw line discipline includes `-isig`,
which disables signal generation, and `-icanon`, which disables line editing.
Bytes `0x03` (Ctrl-C) and `0x1A` (Ctrl-Z) are delivered to PHP as ordinary
data. That is required for binary protocols, file transfers and devices that
use those bytes as payload.

If you want terminal behavior, switch to text mode:

```php
$serial->confTransferMode('text');
```

In text mode, Ctrl-C generates `SIGINT` and Ctrl-Z generates `SIGTSTP` for the
PHP process, and input is line-buffered.

If Ctrl-C does not stop a CLI script that reads in a loop, the script is
probably blocked inside a read or has no signal handler. Use a short timeout:

```php
pcntl_async_signals(true);
pcntl_signal(SIGINT, static function () use (&$running): void {
    $running = false;
});

while ($running) {
    $data = $serial->readPort(timeout: 0.2);
    // ...
}
```

On Windows, `pcntl` is not available. Use a console control handler at the
process level or run the worker as a service that can be stopped externally.

## The board resets on open

DTR is commonly wired to the reset pin on Arduino and ESP boards. Opening the
port toggles it, the board reboots, and the bootloader runs for a moment.

- Sleep one to two seconds after `deviceOpen()`.
- Expect the boot banner in the first read; discard it if necessary.
- Try `confDtr(false)` **before** opening. Whether it prevents the reset
  depends on the board and the USB-serial adapter.
- Some boards reset when opened at 1200 baud; avoid that rate for normal
  communication.
- If the port disappears and reappears, wait for re-enumeration and reopen.
  A short retry loop is more reliable than a single open.

## cron and systemd environments

A job that works in your shell can fail as a service because of:

- A different `PATH` (no `stty`).
- A different user or group list (no device permission).
- A different working directory (relative paths fail).
- Sandboxing that hides devices.
- No TTY (the 1.x `stty` check failed here).

Test with the exact service environment:

```bash
sudo systemd-run --uid=www-data --gid=www-data \
  --property=SupplementaryGroups=dialout \
  --property=Environment=PATH=/usr/local/bin:/usr/bin:/bin \
  /usr/bin/php /srv/app/bin/serial-check.php
```

For systemd units, set `User`, `Group`, `SupplementaryGroups` and `PATH`
explicitly. Avoid `PrivateDevices=true` and `DevicePolicy=closed` for units
that need a serial port, or add `DeviceAllow=/dev/ttyUSB0 rw`.

For cron, set `SHELL` and `PATH` at the top of the crontab and redirect output
to a log. Cron does not send mail reliably in every environment.

## A live server cannot access a client's COM port

This is an architectural limitation, not a library bug. PHP code running on a
web server executes on the server machine. It cannot open a COM port or
`/dev/tty*` device attached to the visitor's computer, even if the visitor is
sitting at a browser connected to your site.

Options:

1. **Browser Web Serial API.** Chromium-based browsers expose serial devices to
   JavaScript after a user gesture, over HTTPS. Use this when the device is on
   the client and the browser can do the work.
2. **A local helper application.** Install a small agent on the client that
   owns the port and exposes a local HTTP or WebSocket endpoint to your web
   application. This requires installation and a security model.
3. **A desktop application.** Use PHP with a GUI toolkit, or a native
   application, on the machine that has the device.
4. **A serial gateway at the server.** If the hardware is actually at the
   server site, expose it through `ser2net` or a serial-to-TCP gateway and
   bridge it to a local PTY (see [Platforms](platforms.md#ser2net-and-remote-serial-ports)).

Do not try to make the web server open a UNC path, a mapped drive or a
remote `COM` name. Windows file sharing does not provide serial port access.

## Collecting diagnostics for a bug report

Include:

- Operating system and version.
- PHP version (`php -v`) and SAPI (`php -i | grep 'Server API'`).
- Device name and, on POSIX, `ls -l` for the device node.
- Output of `stty -F /dev/ttyUSB0 -a` or `mode COM3`.
- Whether `stty`/`mode` is reachable by the same user.
- The exact exception class and message.
- A minimal reproduction, including the configuration calls and the read/write
  sequence.

See [Contributing](contributing.md#reporting-a-bug) for the full checklist.
