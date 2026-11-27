# Configuration

Configuration is held in an immutable `SerialSettings` value object. The
`conf*()` methods replace that object with a modified copy; nothing is sent to
the device until `deviceOpen()`.

## Defaults

| Setting | `conf*()` method | Default | Accepted values |
| --- | --- | --- | --- |
| Baud rate | `confBaudRate()` | `9600` | Any positive integer |
| Data bits | `confCharacterLength()` | `8` | `5` to `8` |
| Parity | `confParity()` | `none` | `none`, `odd`, `even`, `mark`, `space` |
| Stop bits | `confStopBits()` | `1` | `1`, `1.5`, `2` |
| Flow control | `confFlowControl()` | `none` | `none`, `rts/cts`, `xon/xoff` |
| Transfer mode | `confTransferMode()` | `binary` | `binary`, `text` |
| DTR | `confDtr()` | `true` | `true`, `false` |

New instances start with the defaults above:

```php
use Hyperthese\PhpSerial\PhpSerial;

$serial = new PhpSerial();

$settings = $serial->getSettings();
// baudRate 9600, dataBits 8, parity None, stopBits One,
// flowControl None, transferMode Binary, dtr true.
```

## When configuration is applied

`conf*()` only updates pending settings. `deviceOpen()` then:

1. Runs one platform configuration command with the complete settings:
   `stty` on Linux/macOS or `mode` on Windows.
2. Opens the device with the resolved `fopen()` mode.
3. Marks the port as open.

```php
$serial->deviceSet('/dev/ttyUSB0')
    ->confBaudRate(115200)
    ->confParity('even')
    ->confCharacterLength(8)
    ->confStopBits(1)
    ->confFlowControl('none')
    ->confTransferMode('binary')
    ->confDtr(true)
    ->deviceOpen();
```

Sending the full setting set in one command matters: both `stty` and `mode`
reset options that are not mentioned, so applying settings one at a time could
make later calls undo earlier ones.

!!! warning "Configuration cannot change while open"

    Every `conf*()` method calls an internal state check. If the port is open,
    it throws a `ConfigurationException` with a message such as
    `Close the serial device before you change the baud rate.` Close the
    device, change the settings, then reopen it.

```php
$serial->deviceClose();
$serial->confBaudRate(57600);
$serial->deviceOpen();
```

`deviceSet()` follows the same rule: selecting a different device while the
port is open throws a `ConfigurationException`.

### Validation

Invalid values are rejected immediately by the `conf*()` method, before any
device is touched:

```php
$serial->confBaudRate(0);        // ConfigurationException
$serial->confCharacterLength(9); // ConfigurationException
$serial->confParity('invalid');  // ConfigurationException
$serial->confStopBits(3);        // ConfigurationException
```

The baud rate must be greater than zero and the data bits must be between 5 and
8. Parity, stop bits, flow control and transfer mode are checked against their
supported values.

## Configuring each setting

### Baud rate

```php
$serial->confBaudRate(115200);
```

Any positive integer is accepted by the library. The operating system may
reject a rate it cannot generate; that failure surfaces as a
`ConnectionException` from `deviceOpen()`. Common rates are 9600, 19200, 38400,
57600, 115200 and 230400.

### Data bits

```php
$serial->confCharacterLength(8);
```

Five, six, seven and eight data bits are accepted. Most devices and protocols
use eight. On POSIX this becomes `cs5` through `cs8`; on Windows it becomes
`DATA=5` through `DATA=8`.

### Parity

```php
use Hyperthese\PhpSerial\Parity;

$serial->confParity('none');
$serial->confParity(Parity::Even);
```

Strings are trimmed and matched case-insensitively. The native enum is
recommended for autocompletion and typo safety.

### Stop bits

```php
use Hyperthese\PhpSerial\StopBits;

$serial->confStopBits(1);
$serial->confStopBits(1.5);
$serial->confStopBits(2);
$serial->confStopBits(StopBits::Two);
```

Integers and floats are normalized to the enum. The value `1.5` is accepted by
the API but is not supported by `stty` on Linux or macOS; see the platform
caveats below.

### Flow control

```php
use Hyperthese\PhpSerial\FlowControl;

$serial->confFlowControl('none');
$serial->confFlowControl('rts/cts');
$serial->confFlowControl('xon/xoff');
$serial->confFlowControl(FlowControl::RtsCts);
```

| Value | POSIX flags | Windows `mode` flags |
| --- | --- | --- |
| `none` | `clocal -crtscts -ixon -ixoff` | `XON=OFF OCTS=OFF RTS=ON` |
| `rts/cts` | `-clocal crtscts -ixon -ixoff` | `XON=OFF OCTS=ON RTS=HS` |
| `xon/xoff` | `-clocal -crtscts ixon ixoff` | `XON=ON OCTS=OFF RTS=ON` |

With `none`, the port ignores modem control lines. With `rts/cts`, opening or
writing can wait for the peer to assert CTS; if the cable does not carry CTS,
the device may appear to hang. Use `none` for a simple three-wire connection.

### Transfer mode

```php
use Hyperthese\PhpSerial\TransferMode;

$serial->confTransferMode('binary'); // Default.
$serial->confTransferMode('text');
$serial->confTransferMode(TransferMode::Binary);
```

| Mode | POSIX line discipline | Effect |
| --- | --- | --- |
| `binary` | `-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk` | Raw bytes. No line editing, no echo, no signal generation. Ctrl-C (`0x03`) and Ctrl-Z (`0x1A`) are delivered to PHP. |
| `text` | `icanon echo echoe echok iexten isig opost icrnl brkint` | Canonical mode with echo and signal handling. Input is line-buffered and Ctrl-C/Ctrl-Z act as terminal signals. |

Binary is the default because serial protocols usually carry arbitrary bytes.
Text mode is useful when the port is connected to an interactive terminal or
when a device expects canonical line handling.

!!! note "Windows and transfer mode"

    Transfer mode describes a POSIX terminal line discipline. On Windows the
    setting is stored and returned by `getSettings()`, but the `mode` command
    has no equivalent and the flag is not applied. COM handles deliver raw
    bytes in both modes.

### DTR

```php
$serial->confDtr(true);  // Default: keep DTR asserted, do not drop it on close.
$serial->confDtr(false); // Release DTR / allow hangup on close.
```

On POSIX, `true` passes `-hupcl` and `false` passes `hupcl`. On Windows it maps
to `DTR=ON` and `DTR=OFF`.

DTR is also the line used by many Arduino and ESP boards to trigger a reset
when the port is opened. See [Platforms](platforms.md#arduino-and-esp-boards).

## Enums or strings

Parity, flow control and transfer mode accept either a native enum or a string.
Stop bits accept an enum, an integer or a float. Baud rate and data bits are
always integers.

```php
use Hyperthese\PhpSerial\FlowControl;
use Hyperthese\PhpSerial\Parity;
use Hyperthese\PhpSerial\StopBits;
use Hyperthese\PhpSerial\TransferMode;

$serial->confParity(Parity::Odd)
    ->confStopBits(StopBits::One)
    ->confFlowControl(FlowControl::XonXoff)
    ->confTransferMode(TransferMode::Text);
```

The enum values are also the accepted strings:

| Enum | Cases |
| --- | --- |
| `Parity` | `None` = `none`, `Odd` = `odd`, `Even` = `even`, `Mark` = `mark`, `Space` = `space` |
| `StopBits` | `One` = `1`, `OneAndHalf` = `1.5`, `Two` = `2` |
| `FlowControl` | `None` = `none`, `RtsCts` = `rts/cts`, `XonXoff` = `xon/xoff` |
| `TransferMode` | `Text` = `text`, `Binary` = `binary` |

## Platform caveats

### Stop bits

`stty` only supports one or two stop bits. `StopBits::OneAndHalf` is accepted by
the API but throws an `UnsupportedConfigurationException` on Linux and macOS.
The Windows `mode` command supports `STOP=15`, so 1.5 stop bits work there.

### Parity

`mark` and `space` parity require the `cmspar` capability:

- **Linux:** supported when the driver and UART expose `cmspar`.
- **macOS:** not available. Selecting `mark` or `space` throws an
  `UnsupportedConfigurationException`.
- **Windows:** `mode` accepts `PARITY=M` and `PARITY=S`.

`none`, `odd` and `even` work on all supported platforms.

### Baud rates

The library passes the requested rate to `stty` or `mode`; the hardware and
driver decide whether it can be generated. USB adapters commonly support a
different set of rates from native UARTs. If `deviceOpen()` throws a
`ConnectionException` mentioning the configuration command, try a standard rate
such as 9600 or 115200 before investigating the wiring.

### Reading the current settings

`getSettings()` returns the current immutable snapshot:

```php
$settings = $serial->getSettings();

printf(
    "%d %d %s %s\n",
    $settings->baudRate(),
    $settings->dataBits(),
    $settings->parity()->value,
    $settings->stopBits()->value,
);
```

Because `SerialSettings` is immutable, a snapshot obtained earlier is not
changed by later `conf*()` calls.

## See also

- [Usage](usage.md) — writing, reading and timeouts.
- [Platforms](platforms.md) — device permissions and OS-specific notes.
- [Troubleshooting](troubleshooting.md) — configuration failures at open time.
- [API reference](api.md) — `SerialSettings` and enum definitions.
