<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Unit\Io;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\Exception\IOException;
use Hyperthese\PhpSerial\Io\Port;
use PHPUnit\Framework\TestCase;

final class PortTest extends TestCase
{
    /** @var resource */
    private $server;

    /** @var resource */
    private $peer;

    /** @var resource */
    private $client;

    private Port $port;

    protected function setUp(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        self::assertIsResource($server, $errorMessage);

        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);

        $client = stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 2.0);
        self::assertIsResource($client, $errorMessage);

        $peer = stream_socket_accept($server, 2.0);
        self::assertIsResource($peer);

        $this->server = $server;
        $this->client = $client;
        $this->peer = $peer;
        $this->port = new Port($client);
    }

    protected function tearDown(): void
    {
        $this->port->close();
        fclose($this->peer);
        fclose($this->server);
    }

    public function testReadReturnsNothingImmediatelyWhenNoDataIsAvailable(): void
    {
        self::assertSame('', $this->port->read());
    }

    public function testReadReturnsAvailableData(): void
    {
        fwrite($this->peer, 'hello');

        self::assertSame('hello', $this->port->read(0, 1.0));
    }

    public function testReadNeverReturnsMoreThanRequested(): void
    {
        fwrite($this->peer, 'abcdef');

        self::assertSame('abc', $this->port->read(3, 1.0));
        self::assertSame('def', $this->port->read(3, 1.0));
    }

    public function testReadWaitsForDataUntilTheTimeout(): void
    {
        $startedAt = microtime(true);
        $data = $this->port->read(0, 0.15);
        $elapsed = microtime(true) - $startedAt;

        self::assertSame('', $data);
        self::assertGreaterThanOrEqual(0.10, $elapsed);
        self::assertLessThan(2.0, $elapsed);
    }

    public function testReadWithCountWaitsUntilTheCountIsReached(): void
    {
        fwrite($this->peer, 'abc');

        $startedAt = microtime(true);
        $data = $this->port->read(6, 0.15);
        $elapsed = microtime(true) - $startedAt;

        self::assertSame('abc', $data);
        self::assertGreaterThanOrEqual(0.10, $elapsed);
    }

    public function testReadLineReturnsTheDelimiterAndKeepsTheRest(): void
    {
        fwrite($this->peer, "first\nsecond\n");

        self::assertSame("first\n", $this->port->readLine(0, "\n", 1.0));
        self::assertSame("second\n", $this->port->readLine(0, "\n", 1.0));
    }

    public function testReadLineHonoursTheMaximumLength(): void
    {
        fwrite($this->peer, 'abcdef');

        self::assertSame('abc', $this->port->readLine(3, "\n", 1.0));
        self::assertSame('def', $this->port->readLine(3, "\n", 0.2));
    }

    public function testReadLineNeverReturnsMoreThanTheMaximumLength(): void
    {
        fwrite($this->peer, "abcdef\n");

        self::assertSame('abc', $this->port->readLine(3, "\n", 1.0));
        self::assertSame('def', $this->port->readLine(3, "\n", 1.0));
        self::assertSame("\n", $this->port->readLine(3, "\n", 1.0));
    }

    public function testReadLineReturnsPartialDataWhenTheDelimiterNeverArrives(): void
    {
        fwrite($this->peer, 'no newline');

        self::assertSame('no newline', $this->port->readLine(0, "\n", 0.2));
    }

    public function testReadReturnsBytesBufferedByReadLine(): void
    {
        fwrite($this->peer, "line\nleftover");

        self::assertSame("line\n", $this->port->readLine(0, "\n", 1.0));
        self::assertSame('leftover', $this->port->read());
    }

    public function testReadReturnsBufferedDataWithoutWaiting(): void
    {
        fwrite($this->peer, "line\nleftover");

        self::assertSame("line\n", $this->port->readLine(0, "\n", 1.0));

        $startedAt = microtime(true);
        self::assertSame('leftover', $this->port->read(0, 2.0));
        self::assertLessThan(0.5, microtime(true) - $startedAt);
    }

    public function testWriteSendsTheWholePayload(): void
    {
        self::assertSame(5, $this->port->write('hello'));

        stream_set_blocking($this->peer, true);
        self::assertSame('hello', fread($this->peer, 5));
    }

    public function testWriteOfEmptyStringReturnsZero(): void
    {
        self::assertSame(0, $this->port->write(''));
    }

    public function testCloseIsIdempotentAndReported(): void
    {
        self::assertTrue($this->port->isOpen());

        $this->port->close();
        $this->port->close();

        self::assertFalse($this->port->isOpen());
        self::assertNull($this->port->resource());
    }

    public function testReadAfterCloseThrows(): void
    {
        $this->port->close();

        $this->expectException(IOException::class);
        $this->port->read();
    }

    public function testRejectsNegativeMaximumLength(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->port->read(-1);
    }

    public function testRejectsEmptyLineDelimiter(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->port->readLine(0, '');
    }
}
