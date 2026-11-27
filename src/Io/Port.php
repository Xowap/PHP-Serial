<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Io;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\Exception\IOException;

/**
 * Buffered, non-blocking wrapper around an open stream.
 *
 * Reads never return more bytes than requested and can optionally wait for
 * data. Writes retry partial writes until the payload is fully sent or a
 * short timeout expires.
 */
final class Port
{
    private const CHUNK_SIZE = 8192;
    private const POLL_INTERVAL = 0.01;
    private const WRITE_TIMEOUT = 5.0;

    /** @var resource|null */
    private $stream;

    private string $readBuffer = '';

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new ConfigurationException('A valid stream resource is required.');
        }

        $this->stream = $stream;
        stream_set_blocking($stream, false);
    }

    /**
     * Reads at most $maxBytes bytes.
     *
     * @param int        $maxBytes Maximum number of bytes to return; 0 means "as much as available".
     * @param float|null $timeout  Seconds to wait for data. Null returns immediately.
     *                             With $maxBytes = 0 the call waits for the first byte and then
     *                             drains what is buffered; with $maxBytes > 0 it waits until that
     *                             many bytes arrived or the timeout expires.
     */
    public function read(int $maxBytes = 0, ?float $timeout = null): string
    {
        if ($maxBytes < 0) {
            throw new ConfigurationException('The maximum number of bytes must be zero or greater.');
        }

        $data = '';

        if ($this->readBuffer !== '') {
            $data = $this->readBuffer;
            $this->readBuffer = '';

            if ($maxBytes > 0 && strlen($data) >= $maxBytes) {
                $this->readBuffer = substr($data, $maxBytes);

                return substr($data, 0, $maxBytes);
            }
        }

        $remaining = $maxBytes > 0 ? $maxBytes - strlen($data) : 0;
        $effectiveTimeout = $maxBytes === 0 && $data !== '' ? 0.0 : $timeout;

        return $data . $this->readFromStream($remaining, $effectiveTimeout);
    }

    /**
     * Reads a single line.
     *
     * The delimiter is included when it fits within $maxLength. Bytes
     * following the delimiter are kept for the next read call.
     *
     * @param int        $maxLength Maximum number of bytes to return; 0 means unlimited.
     * @param string     $delimiter End-of-line sequence.
     * @param float|null $timeout   Seconds to wait for data.
     */
    public function readLine(int $maxLength = 0, string $delimiter = "\n", ?float $timeout = null): string
    {
        if ($maxLength < 0) {
            throw new ConfigurationException('The maximum line length must be zero or greater.');
        }

        if ($delimiter === '') {
            throw new ConfigurationException('The line delimiter cannot be empty.');
        }

        $deadline = $timeout === null ? null : microtime(true) + max(0.0, $timeout);
        $length = strlen($this->readBuffer);

        while (true) {
            $position = strpos($this->readBuffer, $delimiter);

            if ($position !== false) {
                $length = $position + strlen($delimiter);

                if ($maxLength > 0) {
                    $length = min($length, $maxLength);
                }

                break;
            }

            if ($maxLength > 0 && strlen($this->readBuffer) >= $maxLength) {
                $length = $maxLength;
                break;
            }

            $remainingTimeout = null;

            if ($deadline !== null) {
                $remainingTimeout = $deadline - microtime(true);

                if ($remainingTimeout <= 0) {
                    $length = strlen($this->readBuffer);
                    break;
                }
            }

            $chunk = $this->readFromStream(self::CHUNK_SIZE, $remainingTimeout);

            if ($chunk === '') {
                $length = strlen($this->readBuffer);
                break;
            }

            $this->readBuffer .= $chunk;
        }

        $line = substr($this->readBuffer, 0, $length);
        $this->readBuffer = (string) substr($this->readBuffer, $length);

        return $line;
    }

    /**
     * Writes the whole payload, retrying partial writes.
     *
     * @return int Number of bytes written.
     */
    public function write(string $data): int
    {
        $length = strlen($data);
        $written = 0;
        $deadline = microtime(true) + self::WRITE_TIMEOUT;

        while ($written < $length) {
            $result = @fwrite($this->stream(), substr($data, $written));

            if ($result === false) {
                throw new IOException('Unable to write to the serial port.');
            }

            if ($result === 0) {
                if (microtime(true) >= $deadline) {
                    throw new IOException('Timed out while writing to the serial port.');
                }

                usleep(1000);
                continue;
            }

            $written += $result;
        }

        return $written;
    }

    /**
     * Flushes any stream-level write buffer.
     */
    public function flush(): void
    {
        @fflush($this->stream());
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }

        $this->readBuffer = '';
    }

    public function isOpen(): bool
    {
        return $this->stream !== null;
    }

    /**
     * @return resource|null
     */
    public function resource()
    {
        return $this->stream;
    }

    private function readFromStream(int $maxBytes, ?float $timeout): string
    {
        $data = '';
        $deadline = $timeout === null ? null : microtime(true) + max(0.0, $timeout);

        while (true) {
            if ($maxBytes > 0 && strlen($data) >= $maxBytes) {
                break;
            }

            $size = $maxBytes > 0
                ? max(1, min(self::CHUNK_SIZE, $maxBytes - strlen($data)))
                : self::CHUNK_SIZE;

            $chunk = @fread($this->stream(), $size);

            if ($chunk === false) {
                throw new IOException('Unable to read from the serial port.');
            }

            if ($chunk !== '') {
                $data .= $chunk;
                continue;
            }

            if (feof($this->stream())) {
                break;
            }

            if ($deadline === null) {
                break;
            }

            if ($maxBytes === 0 && $data !== '') {
                break;
            }

            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                break;
            }

            $this->waitForData(min($remaining, self::POLL_INTERVAL));
        }

        return $data;
    }

    /**
     * Waits until the stream is readable or the slice expires.
     *
     * Some stream types (notably COM handles on Windows) cannot be selected;
     * in that case we fall back to sleeping for the same slice.
     */
    private function waitForData(float $seconds): void
    {
        $read = [$this->stream()];
        $write = null;
        $except = null;

        $whole = (int) floor($seconds);
        $fraction = (int) round(($seconds - $whole) * 1_000_000);

        if ($fraction >= 1_000_000) {
            $whole++;
            $fraction -= 1_000_000;
        }

        $ready = @stream_select($read, $write, $except, $whole, $fraction);

        if ($ready === false) {
            usleep((int) round(min($seconds, self::POLL_INTERVAL) * 1_000_000));
        }
    }

    /**
     * @return resource
     */
    private function stream()
    {
        if ($this->stream === null) {
            throw new IOException('The serial port is closed.');
        }

        return $this->stream;
    }
}
