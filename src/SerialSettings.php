<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial;

use Hyperthese\PhpSerial\Exception\ConfigurationException;

/**
 * Immutable serial port configuration.
 *
 * Every "with*" method returns a new instance, so a settings object can be
 * safely shared and applied atomically when the device is opened.
 */
final class SerialSettings
{
    public const DEFAULT_BAUD_RATE = 9600;
    public const DEFAULT_DATA_BITS = 8;

    public function __construct(
        private readonly int $baudRate = self::DEFAULT_BAUD_RATE,
        private readonly Parity $parity = Parity::None,
        private readonly int $dataBits = self::DEFAULT_DATA_BITS,
        private readonly StopBits $stopBits = StopBits::One,
        private readonly FlowControl $flowControl = FlowControl::None,
        private readonly TransferMode $transferMode = TransferMode::Binary,
        private readonly bool $dtr = true,
    ) {
        if ($baudRate <= 0) {
            throw new ConfigurationException('The baud rate must be greater than zero.');
        }

        if ($dataBits < 5 || $dataBits > 8) {
            throw new ConfigurationException('The number of data bits must be between 5 and 8.');
        }
    }

    public function baudRate(): int
    {
        return $this->baudRate;
    }

    public function parity(): Parity
    {
        return $this->parity;
    }

    public function dataBits(): int
    {
        return $this->dataBits;
    }

    public function stopBits(): StopBits
    {
        return $this->stopBits;
    }

    public function flowControl(): FlowControl
    {
        return $this->flowControl;
    }

    public function transferMode(): TransferMode
    {
        return $this->transferMode;
    }

    public function dtr(): bool
    {
        return $this->dtr;
    }

    public function withBaudRate(int $baudRate): self
    {
        return new self(
            $baudRate,
            $this->parity,
            $this->dataBits,
            $this->stopBits,
            $this->flowControl,
            $this->transferMode,
            $this->dtr,
        );
    }

    public function withParity(Parity $parity): self
    {
        return new self(
            $this->baudRate,
            $parity,
            $this->dataBits,
            $this->stopBits,
            $this->flowControl,
            $this->transferMode,
            $this->dtr,
        );
    }

    public function withDataBits(int $dataBits): self
    {
        return new self(
            $this->baudRate,
            $this->parity,
            $dataBits,
            $this->stopBits,
            $this->flowControl,
            $this->transferMode,
            $this->dtr,
        );
    }

    public function withStopBits(StopBits $stopBits): self
    {
        return new self(
            $this->baudRate,
            $this->parity,
            $this->dataBits,
            $stopBits,
            $this->flowControl,
            $this->transferMode,
            $this->dtr,
        );
    }

    public function withFlowControl(FlowControl $flowControl): self
    {
        return new self(
            $this->baudRate,
            $this->parity,
            $this->dataBits,
            $this->stopBits,
            $flowControl,
            $this->transferMode,
            $this->dtr,
        );
    }

    public function withTransferMode(TransferMode $transferMode): self
    {
        return new self(
            $this->baudRate,
            $this->parity,
            $this->dataBits,
            $this->stopBits,
            $this->flowControl,
            $transferMode,
            $this->dtr,
        );
    }

    public function withDtr(bool $dtr): self
    {
        return new self(
            $this->baudRate,
            $this->parity,
            $this->dataBits,
            $this->stopBits,
            $this->flowControl,
            $this->transferMode,
            $dtr,
        );
    }
}
