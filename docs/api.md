# API reference

All classes live under the `Hyperthese\PhpSerial` namespace, except the
platform and I/O classes under `Hyperthese\PhpSerial\Platform` and
`Hyperthese\PhpSerial\Io`. Every signature below matches the 2.0.0 source.

## PhpSerial

`final class Hyperthese\PhpSerial\PhpSerial`

| Member | Signature | Description |
| --- | --- | --- |
| `VERSION` | `public const VERSION = '2.0.0'` | Library version. |
| `__construct` | `__construct(?LoggerInterface $logger = null, ?PlatformInterface $platform = null)` | Creates an instance. The platform is auto-detected when omitted. |
| `__destruct` | `__destruct()` | Closes the port, ignoring any error. |
| `deviceSet` | `deviceSet(string\|int $device): self` | Resolves and checks the device. Throws `ConnectionException` when it does not exist. Cannot be called while open. |
| `deviceOpen` | `deviceOpen(?string $mode = null, ?bool $nonBlocking = null): self` | Applies the settings and opens the port. `$nonBlocking` defaults to `true`. |
| `deviceClose` | `deviceClose(): void` | Closes the port. A no-op when already closed. |
| `readPort` | `readPort(int $count = 0, int\|float\|null $timeout = null): string` | Reads up to `$count` bytes, optionally waiting up to `$timeout` seconds. |
| `readLine` | `readLine(int $maxLength = 0, string $delimiter = "\n", int\|float\|null $timeout = null): string` | Reads one line; includes the delimiter when it fits within `$maxLength`. |
| `sendMessage` | `sendMessage(string $message, int\|float $waitForReply = 0.0): self` | Appends to the write buffer, flushes when auto-flush is on, then sleeps for `$waitForReply` seconds. |
| `write` | `write(string $data): int` | Writes immediately, bypassing the buffer. Returns the byte count. |
| `flush` | `flush(): self` | Writes the buffer and flushes the stream. |
| `serialflush` | `serialflush(): self` | 1.x-compatible alias of `flush()`. |
| `setAutoFlush` | `setAutoFlush(bool $autoFlush): self` | Enables or disables flushing in `sendMessage()`. Default `true`. |
| `confBaudRate` | `confBaudRate(int $rate): self` | Sets the baud rate. Must be greater than zero. |
| `confParity` | `confParity(Parity\|string $parity): self` | Sets the parity. Strings are case-insensitive. |
| `confCharacterLength` | `confCharacterLength(int $bits): self` | Sets the data bits (`5` to `8`). |
| `confStopBits` | `confStopBits(StopBits\|int\|float $length): self` | Sets the stop bits (`1`, `1.5` or `2`). |
| `confFlowControl` | `confFlowControl(FlowControl\|string $mode): self` | Sets the flow control. |
| `confTransferMode` | `confTransferMode(TransferMode\|string $mode): self` | Selects `text` or `binary`. |
| `confDtr` | `confDtr(bool $enabled): self` | Controls DTR and hangup behavior. |
| `getSettings` | `getSettings(): SerialSettings` | Current immutable settings. |
| `getDevice` | `getDevice(): ?string` | Resolved path, for example `/dev/ttyUSB0` or `\\.\COM3`. |
| `getDeviceName` | `getDeviceName(): ?string` | Name as passed to `deviceSet()`, for example `COM3`. |
| `getPlatform` | `getPlatform(): PlatformInterface` | Platform implementation in use. |
| `getPlatformName` | `getPlatformName(): string` | `linux`, `macos` or `windows`. |
| `isConfigured` | `isConfigured(): bool` | Whether a device has been set. Stays `true` after `deviceClose()`. |
| `isOpen` | `isOpen(): bool` | Whether the port is currently open. |
| `getStream` | `getStream()` | Underlying stream `resource`, or `null`. The return type is documented as `resource\|null`. |

All `conf*()` methods throw a `ConfigurationException` when the port is open.
`deviceSet()` throws the same exception while open.

### Open modes

`deviceOpen()` accepts a subset of `fopen()` modes: `r`, `w` or `a`, an
optional `+`, and optional `b`/`n` flags. The library normalizes the result and
always includes `b`:

- POSIX default: `r+bn`.
- Windows default: `r+b`.
- `n` is added only when `$nonBlocking` is `true` and the platform supports it.
- An invalid mode throws a `ConfigurationException`.

## SerialSettings

`final class Hyperthese\PhpSerial\SerialSettings`

Immutable value object. Every `with*` method returns a new instance.

```php
public function __construct(
    private readonly int $baudRate = self::DEFAULT_BAUD_RATE,
    private readonly Parity $parity = Parity::None,
    private readonly int $dataBits = self::DEFAULT_DATA_BITS,
    private readonly StopBits $stopBits = StopBits::One,
    private readonly FlowControl $flowControl = FlowControl::None,
    private readonly TransferMode $transferMode = TransferMode::Binary,
    private readonly bool $dtr = true,
)
```

| Member | Signature | Description |
| --- | --- | --- |
| `DEFAULT_BAUD_RATE` | `public const DEFAULT_BAUD_RATE = 9600` | Default baud rate. |
| `DEFAULT_DATA_BITS` | `public const DEFAULT_DATA_BITS = 8` | Default data bits. |
| `baudRate` | `baudRate(): int` | Baud rate. |
| `parity` | `parity(): Parity` | Parity enum. |
| `dataBits` | `dataBits(): int` | Data bits. |
| `stopBits` | `stopBits(): StopBits` | Stop bits enum. |
| `flowControl` | `flowControl(): FlowControl` | Flow control enum. |
| `transferMode` | `transferMode(): TransferMode` | Transfer mode enum. |
| `dtr` | `dtr(): bool` | DTR behavior. |
| `withBaudRate` | `withBaudRate(int $baudRate): self` | Copy with a new baud rate. |
| `withParity` | `withParity(Parity $parity): self` | Copy with a new parity. |
| `withDataBits` | `withDataBits(int $dataBits): self` | Copy with new data bits. |
| `withStopBits` | `withStopBits(StopBits $stopBits): self` | Copy with new stop bits. |
| `withFlowControl` | `withFlowControl(FlowControl $flowControl): self` | Copy with new flow control. |
| `withTransferMode` | `withTransferMode(TransferMode $transferMode): self` | Copy with a new transfer mode. |
| `withDtr` | `withDtr(bool $dtr): self` | Copy with a new DTR behavior. |

The constructor throws a `ConfigurationException` when the baud rate is not
positive or the data bits are outside 5 to 8.

The `with*` methods take enum instances. String normalization for parity, flow
control and transfer mode happens in `PhpSerial::conf*()` before a new settings
object is created.

## Enums

All four enums are string-backed.

### Parity

| Case | Value |
| --- | --- |
| `Parity::None` | `none` |
| `Parity::Odd` | `odd` |
| `Parity::Even` | `even` |
| `Parity::Mark` | `mark` |
| `Parity::Space` | `space` |

### StopBits

| Case | Value |
| --- | --- |
| `StopBits::One` | `1` |
| `StopBits::OneAndHalf` | `1.5` |
| `StopBits::Two` | `2` |

### FlowControl

| Case | Value |
| --- | --- |
| `FlowControl::None` | `none` |
| `FlowControl::RtsCts` | `rts/cts` |
| `FlowControl::XonXoff` | `xon/xoff` |

### TransferMode

| Case | Value |
| --- | --- |
| `TransferMode::Text` | `text` |
| `TransferMode::Binary` | `binary` |

`DeviceState` is a non-backed enum with the cases `NotSet`, `Set` and `Opened`.
It describes the internal lifecycle and is not needed in application code.

## Exceptions

```text
RuntimeException
└── PhpSerialException
    ├── ConfigurationException
    │   └── UnsupportedConfigurationException
    ├── ConnectionException
    ├── IOException
    └── UnsupportedPlatformException
```

| Exception | Thrown when |
| --- | --- |
| `PhpSerialException` | Base class for every library exception. |
| `ConfigurationException` | A setting is invalid, the device is not set, the port is already open, the port is not open, or a read argument is invalid. |
| `UnsupportedConfigurationException` | A valid setting is not supported by the platform, such as mark/space parity on macOS or 1.5 stop bits with `stty`. Extends `ConfigurationException`. |
| `ConnectionException` | The device does not exist, cannot be opened, or cannot be configured with `stty`/`mode`. |
| `UnsupportedPlatformException` | The operating system is not Linux, macOS or Windows. |
| `IOException` | A stream read or write failed, including a write timeout. `Io\Port` also throws it when used after `close()`. |

## Platform layer

### PlatformInterface

`interface Hyperthese\PhpSerial\Platform\PlatformInterface`

| Method | Signature | Description |
| --- | --- | --- |
| `name` | `name(): string` | Short name: `linux`, `macos` or `windows`. |
| `isAvailable` | `isAvailable(): bool` | Whether the configuration utility (`stty`) is available. Always `true` on Windows. |
| `deviceExists` | `deviceExists(string $device): bool` | Whether the resolved path exists. Always `true` on Windows. |
| `resolveDevice` | `resolveDevice(string\|int $device): string` | Converts a user-supplied name into an `fopen()` path. |
| `configure` | `configure(string $device, SerialSettings $settings): void` | Applies all settings to the device. |
| `defaultOpenMode` | `defaultOpenMode(): string` | `r+bn` on POSIX, `r+b` on Windows. |
| `supportsNonBlockingOpen` | `supportsNonBlockingOpen(): bool` | `true` on POSIX, `false` on Windows. |

`configure()` throws `ConnectionException` when the command fails and
`UnsupportedConfigurationException` when `stty` is missing or an option is not
supported.

### PlatformFactory

`final class Hyperthese\PhpSerial\Platform\PlatformFactory`

```php
public static function create(
    ?string $osFamily = null,
    ?CommandRunnerInterface $runner = null,
): PlatformInterface
```

`$osFamily` defaults to `PHP_OS_FAMILY` and accepts `Linux`, `Darwin` or
`Windows`. Any other value throws `UnsupportedPlatformException`. The optional
runner is mainly useful for tests.

### UnixPlatform

`final class Hyperthese\PhpSerial\Platform\UnixPlatform`

| Member | Signature | Description |
| --- | --- | --- |
| `LINUX` | `public const LINUX = 'linux'` | Linux family name. |
| `MACOS` | `public const MACOS = 'macos'` | macOS family name. |
| `__construct` | `__construct(string $family, CommandRunnerInterface $runner = new ProcessCommandRunner())` | `$family` must be `LINUX` or `MACOS`. |
| `isAvailable` | `isAvailable(): bool` | Runs `command -v stty` and caches the result. |

Configuration uses one `stty` invocation with `-F` on Linux and `-f` on macOS.

### WindowsPlatform

`final class Hyperthese\PhpSerial\Platform\WindowsPlatform`

| Member | Signature | Description |
| --- | --- | --- |
| `__construct` | `__construct(CommandRunnerInterface $runner = new ProcessCommandRunner())` | Creates the Windows implementation. |
| `resolveDevice` | `resolveDevice(string\|int $device): string` | Accepts `COM<n>`, `COM<n>:`, `\\.\COM<n>` or an integer from 1 to 256. |

Configuration uses one `mode COM<n>: ...` command. The `sttyCommand()` and
`modeCommand()` methods are public but marked `@internal` for tests; application
code should call `configure()`.

### Command runners

`interface Hyperthese\PhpSerial\Platform\CommandRunnerInterface`

```php
public function run(string $command): CommandResult
```

`ProcessCommandRunner` implements it with `proc_open()`, capturing stdout,
stderr and the exit code.

`final class Hyperthese\PhpSerial\Platform\CommandResult` exposes readonly
properties `command`, `exitCode`, `stdout` and `stderr`, plus:

| Method | Signature | Description |
| --- | --- | --- |
| `isSuccessful` | `isSuccessful(): bool` | `true` when the exit code is zero. |
| `errorMessage` | `errorMessage(): string` | Trimmed stderr, falling back to stdout or a generic exit-code message. |

## I/O layer

`final class Hyperthese\PhpSerial\Io\Port` wraps an open stream, sets it
non-blocking and provides bounded reads and retrying writes. `PhpSerial` uses it
internally; it is public for advanced integrations.

| Method | Signature | Description |
| --- | --- | --- |
| `__construct` | `__construct($stream)` | Accepts a valid stream `resource`. Throws `ConfigurationException` otherwise. |
| `read` | `read(int $maxBytes = 0, ?float $timeout = null): string` | Reads at most `$maxBytes` bytes. `0` means as much as is available. `null` returns immediately. |
| `readLine` | `readLine(int $maxLength = 0, string $delimiter = "\n", ?float $timeout = null): string` | Reads one line; includes the delimiter when it fits within `$maxLength`. |
| `write` | `write(string $data): int` | Writes the whole payload, retrying partial writes. Returns the byte count. |
| `flush` | `flush(): void` | Flushes the stream's write buffer. |
| `close` | `close(): void` | Closes the stream and clears the read buffer. Idempotent. |
| `isOpen` | `isOpen(): bool` | Whether the stream is open. |
| `resource` | `resource()` | The underlying `resource`, or `null` after `close()`. |

### Read semantics

- Reads never return more than `$maxBytes`.
- With `$maxBytes > 0` and a timeout, the call waits until that many bytes have
  arrived or the timeout expires.
- With `$maxBytes = 0` and a timeout, it waits for the first byte and then
  drains what is buffered.
- A `null` timeout never waits.
- A negative `$maxBytes` throws `ConfigurationException`.
- Data left over by `readLine()` is kept for the next read.

### Line semantics

- The delimiter is included in the returned string when it falls within the
  `$maxLength` cap.
- If `$maxLength` is reached first, the line is truncated and the delimiter is
  not included; it stays in the buffer with the remainder.
- A partial line is returned when the timeout expires.
- `$maxLength = 0` means unlimited; a positive value caps the returned line.
- Bytes after the delimiter remain buffered.
- An empty delimiter throws `ConfigurationException`.

### Write semantics

- Partial writes are retried until the payload is complete.
- A write that stalls after the five-second write deadline throws `IOException`.
- Writing an empty string returns `0`.
- Writing to a closed port throws `IOException`.

The implementation reads in chunks of 8192 bytes and polls every 10 ms while
waiting. These values are internal and may change without notice.

## See also

- [Usage](usage.md) — practical examples.
- [Configuration](configuration.md) — settings and platform limitations.
- [Platforms](platforms.md) — device names and deployment.
- [Migration from 1.x](migration.md) — mapping from the old API.
