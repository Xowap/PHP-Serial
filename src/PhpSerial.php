<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\Exception\ConnectionException;
use Hyperthese\PhpSerial\Io\Port;
use Hyperthese\PhpSerial\Platform\PlatformFactory;
use Hyperthese\PhpSerial\Platform\PlatformInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Multi-platform serial port access.
 *
 * ```php
 * $serial = new PhpSerial();
 * $serial->deviceSet('/dev/ttyUSB0')
 *        ->confBaudRate(115200)
 *        ->confParity('none')
 *        ->confCharacterLength(8)
 *        ->confStopBits(1)
 *        ->confFlowControl('none')
 *        ->deviceOpen();
 *
 * $serial->sendMessage("AT\r\n");
 * $reply = $serial->readPort(timeout: 1.0);
 * $serial->deviceClose();
 * ```
 */
final class PhpSerial
{
    public const VERSION = '2.0.0';

    private DeviceState $state = DeviceState::NotSet;
    private ?string $deviceName = null;
    private ?string $device = null;
    private ?Port $port = null;
    private SerialSettings $settings;
    private readonly PlatformInterface $platform;
    private readonly ?LoggerInterface $logger;
    private bool $autoFlush = true;
    private string $buffer = '';

    public function __construct(?LoggerInterface $logger = null, ?PlatformInterface $platform = null)
    {
        $this->settings = new SerialSettings();
        $this->platform = $platform ?? PlatformFactory::create();
        $this->logger = $logger;
    }

    public function __destruct()
    {
        try {
            $this->deviceClose();
        } catch (Throwable) {
            // Destructors must never throw.
        }
    }

    /**
     * Sets the device to use.
     *
     * Accepted formats: "/dev/ttyUSB0", "ttyACM0", "cu.usbserial-1420",
     * "COM3" (mapped to /dev/ttyS2 on POSIX) or an integer port number.
     */
    public function deviceSet(string|int $device): self
    {
        $this->assertNotOpen('set another device');

        $resolved = $this->platform->resolveDevice($device);

        if (!$this->platform->deviceExists($resolved)) {
            throw new ConnectionException(sprintf(
                'The serial device "%s" resolved to "%s", but it does not exist. '
                . 'On Linux, make sure the user running PHP belongs to the "dialout" group.',
                (string) $device,
                $resolved,
            ));
        }

        $this->deviceName = (string) $device;
        $this->device = $resolved;
        $this->state = DeviceState::Set;

        $this->logger?->debug('Serial device set to {device} ({path}).', [
            'device' => $this->deviceName,
            'path' => $this->device,
        ]);

        return $this;
    }

    /**
     * Opens the device and applies the current configuration.
     *
     * The open call is non-blocking by default on POSIX (`r+bn`), which avoids
     * the hang described in issue #34.
     */
    public function deviceOpen(?string $mode = null, ?bool $nonBlocking = null): self
    {
        if ($this->state === DeviceState::Opened) {
            throw new ConfigurationException('The serial device is already open.');
        }

        if ($this->device === null) {
            throw new ConfigurationException('The serial device must be set before it can be opened.');
        }

        $nonBlocking ??= true;
        $this->platform->configure($this->device, $this->settings);

        $openMode = $this->normalizeOpenMode(
            $mode ?? $this->platform->defaultOpenMode(),
            $nonBlocking && $this->platform->supportsNonBlockingOpen(),
        );

        error_clear_last();
        $stream = @fopen($this->device, $openMode);

        if ($stream === false) {
            $error = error_get_last();

            throw new ConnectionException(sprintf(
                'Unable to open the serial device "%s" in mode "%s"%s',
                $this->device,
                $openMode,
                $error === null ? '.' : ': ' . $error['message'],
            ));
        }

        $this->port = new Port($stream);
        $this->state = DeviceState::Opened;

        $this->logger?->info('Opened serial device {path} in mode {mode}.', [
            'path' => $this->device,
            'mode' => $openMode,
        ]);

        return $this;
    }

    /**
     * Closes the device. Calling it on a closed device is a no-op.
     */
    public function deviceClose(): void
    {
        if ($this->state !== DeviceState::Opened) {
            return;
        }

        if ($this->buffer !== '') {
            try {
                $this->port?->write($this->buffer);
            } catch (Throwable $exception) {
                $this->logger?->error('Unable to flush buffered data while closing {path}.', [
                    'path' => $this->device,
                    'exception' => $exception,
                ]);
            }

            $this->buffer = '';
        }

        $this->port?->close();
        $this->port = null;
        $this->state = $this->device === null ? DeviceState::NotSet : DeviceState::Set;

        $this->logger?->info('Closed serial device {path}.', ['path' => $this->device]);
    }

    /**
     * Reads up to $count bytes, optionally waiting for data.
     *
     * @param int             $count   Maximum number of bytes; 0 means "everything available".
     * @param int|float|null  $timeout Seconds to wait for data. Null returns immediately.
     */
    public function readPort(int $count = 0, int|float|null $timeout = null): string
    {
        $data = $this->requirePort()->read($count, $timeout === null ? null : (float) $timeout);

        $this->logger?->debug('Read {bytes} byte(s) from {path}.', [
            'bytes' => strlen($data),
            'path' => $this->device,
        ]);

        return $data;
    }

    /**
     * Reads one line. The delimiter is included when it fits within the limit.
     *
     * @param int             $maxLength Maximum number of bytes; 0 means unlimited.
     * @param int|float|null  $timeout   Seconds to wait for data.
     */
    public function readLine(int $maxLength = 0, string $delimiter = "\n", int|float|null $timeout = null): string
    {
        return $this->requirePort()->readLine(
            $maxLength,
            $delimiter,
            $timeout === null ? null : (float) $timeout,
        );
    }

    /**
     * Appends a message to the write buffer and flushes it when auto-flush is on.
     */
    public function sendMessage(string $message, int|float $waitForReply = 0.0): self
    {
        $this->buffer .= $message;

        if ($this->autoFlush) {
            $this->serialflush();
        }

        if ($waitForReply > 0) {
            usleep((int) round((float) $waitForReply * 1_000_000));
        }

        return $this;
    }

    /**
     * Writes a payload immediately, bypassing the buffer.
     */
    public function write(string $data): int
    {
        $written = $this->requirePort()->write($data);

        $this->logger?->debug('Wrote {bytes} byte(s) to {path}.', [
            'bytes' => $written,
            'path' => $this->device,
        ]);

        return $written;
    }

    /**
     * Alias kept for 1.x compatibility.
     */
    public function serialflush(): self
    {
        return $this->flush();
    }

    /**
     * Sends the buffered data and flushes the stream.
     */
    public function flush(): self
    {
        $port = $this->requirePort();

        if ($this->buffer !== '') {
            $port->write($this->buffer);
            $this->buffer = '';
        }

        $port->flush();

        return $this;
    }

    public function setAutoFlush(bool $autoFlush): self
    {
        $this->autoFlush = $autoFlush;

        return $this;
    }

    public function confBaudRate(int $rate): self
    {
        $this->assertNotOpen('change the baud rate');
        $this->settings = $this->settings->withBaudRate($rate);

        return $this;
    }

    public function confParity(Parity|string $parity): self
    {
        $this->assertNotOpen('change the parity');
        $this->settings = $this->settings->withParity($this->normalizeParity($parity));

        return $this;
    }

    public function confCharacterLength(int $bits): self
    {
        $this->assertNotOpen('change the character length');
        $this->settings = $this->settings->withDataBits($bits);

        return $this;
    }

    public function confStopBits(StopBits|int|float $length): self
    {
        $this->assertNotOpen('change the stop bits');
        $this->settings = $this->settings->withStopBits($this->normalizeStopBits($length));

        return $this;
    }

    public function confFlowControl(FlowControl|string $mode): self
    {
        $this->assertNotOpen('change the flow control');
        $this->settings = $this->settings->withFlowControl($this->normalizeFlowControl($mode));

        return $this;
    }

    /**
     * Selects binary (raw) or text (canonical) line discipline.
     */
    public function confTransferMode(TransferMode|string $mode): self
    {
        $this->assertNotOpen('change the transfer mode');
        $this->settings = $this->settings->withTransferMode($this->normalizeTransferMode($mode));

        return $this;
    }

    public function confDtr(bool $enabled): self
    {
        $this->assertNotOpen('change the DTR behaviour');
        $this->settings = $this->settings->withDtr($enabled);

        return $this;
    }

    public function getSettings(): SerialSettings
    {
        return $this->settings;
    }

    /**
     * Resolved device path, for example "/dev/ttyUSB0" or "\\.\COM3".
     */
    public function getDevice(): ?string
    {
        return $this->device;
    }

    /**
     * Device name as provided by the caller, for example "COM3".
     */
    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform;
    }

    public function getPlatformName(): string
    {
        return $this->platform->name();
    }

    public function isConfigured(): bool
    {
        return $this->device !== null;
    }

    public function isOpen(): bool
    {
        return $this->port?->isOpen() ?? false;
    }

    /**
     * Underlying stream, for advanced stream options (stream_set_timeout, ...).
     *
     * @return resource|null
     */
    public function getStream()
    {
        return $this->port?->resource();
    }

    private function requirePort(): Port
    {
        if ($this->port === null) {
            throw new ConfigurationException('The serial device must be opened before it can be used.');
        }

        return $this->port;
    }

    private function assertNotOpen(string $action): void
    {
        if ($this->state === DeviceState::Opened) {
            throw new ConfigurationException(sprintf('Close the serial device before you %s.', $action));
        }
    }

    private function normalizeOpenMode(string $mode, bool $nonBlocking): string
    {
        if (preg_match('/^([rwa])([+bn]*)$/', $mode, $matches) !== 1) {
            throw new ConfigurationException(sprintf(
                'Invalid opening mode "%s". Use fopen() modes such as "r+b" or "r+bn".',
                $mode,
            ));
        }

        $flags = $matches[2];

        if (substr_count($flags, '+') > 1 || substr_count($flags, 'b') > 1 || substr_count($flags, 'n') > 1) {
            throw new ConfigurationException(sprintf('Invalid opening mode "%s".', $mode));
        }

        return $matches[1]
            . (str_contains($flags, '+') ? '+' : '')
            . 'b'
            . ($nonBlocking ? 'n' : '');
    }

    private function normalizeParity(Parity|string $parity): Parity
    {
        if ($parity instanceof Parity) {
            return $parity;
        }

        return Parity::tryFrom(strtolower(trim($parity))) ?? throw new ConfigurationException(sprintf(
            'Unsupported parity mode "%s". Use none, odd, even, mark or space.',
            $parity,
        ));
    }

    private function normalizeStopBits(StopBits|int|float $length): StopBits
    {
        if ($length instanceof StopBits) {
            return $length;
        }

        $value = rtrim(rtrim(sprintf('%.1F', (float) $length), '0'), '.');

        return StopBits::tryFrom($value) ?? throw new ConfigurationException(sprintf(
            'Unsupported stop bit length "%s". Use 1, 1.5 or 2.',
            (string) $length,
        ));
    }

    private function normalizeFlowControl(FlowControl|string $mode): FlowControl
    {
        if ($mode instanceof FlowControl) {
            return $mode;
        }

        return FlowControl::tryFrom(strtolower(trim($mode))) ?? throw new ConfigurationException(sprintf(
            'Unsupported flow control mode "%s". Use none, rts/cts or xon/xoff.',
            $mode,
        ));
    }

    private function normalizeTransferMode(TransferMode|string $mode): TransferMode
    {
        if ($mode instanceof TransferMode) {
            return $mode;
        }

        return TransferMode::tryFrom(strtolower(trim($mode))) ?? throw new ConfigurationException(sprintf(
            'Unsupported transfer mode "%s". Use text or binary.',
            $mode,
        ));
    }
}
