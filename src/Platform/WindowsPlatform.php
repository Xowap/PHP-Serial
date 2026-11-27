<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Platform;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\Exception\ConnectionException;
use Hyperthese\PhpSerial\FlowControl;
use Hyperthese\PhpSerial\Parity;
use Hyperthese\PhpSerial\SerialSettings;
use Hyperthese\PhpSerial\StopBits;

/**
 * Windows implementation, backed by the `mode` command.
 *
 * Device handles are opened through the `\\.\COM<n>` namespace, which works
 * for every port number. `mode`, on the other hand, expects `COM<n>:`.
 */
final class WindowsPlatform implements PlatformInterface
{
    private const DEVICE_PREFIX = '\\\\.\\COM';

    public function __construct(
        private readonly CommandRunnerInterface $runner = new ProcessCommandRunner(),
    ) {
    }

    public function name(): string
    {
        return 'windows';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function deviceExists(string $device): bool
    {
        // COM ports cannot be stat'ed reliably; fopen() reports whether they exist.
        return true;
    }

    public function resolveDevice(string|int $device): string
    {
        if (is_int($device)) {
            $device = 'COM' . $device;
        } else {
            $device = strtoupper(trim($device));

            if (str_starts_with($device, "\\\\.\\")) {
                $device = substr($device, 4);
            }
        }

        if (preg_match('/^COM(\d{1,3}):?$/', $device, $matches) !== 1) {
            throw new ConfigurationException(sprintf(
                'Invalid Windows serial device "%s". Expected COM1, COM3: or 3.',
                $device,
            ));
        }

        $number = (int) $matches[1];

        if ($number < 1 || $number > 256) {
            throw new ConfigurationException('Windows COM port numbers must be between 1 and 256.');
        }

        return self::DEVICE_PREFIX . $number;
    }

    public function configure(string $device, SerialSettings $settings): void
    {
        $result = $this->runner->run($this->modeCommand($device, $settings));

        if (!$result->isSuccessful()) {
            throw new ConnectionException(sprintf(
                'Unable to configure "%s": %s',
                $device,
                $result->errorMessage(),
            ));
        }
    }

    public function defaultOpenMode(): string
    {
        return 'r+b';
    }

    public function supportsNonBlockingOpen(): bool
    {
        return false;
    }

    /**
     * Builds one complete `mode` invocation.
     *
     * A full command is sent every time on purpose: `mode` resets options it
     * was not given, which is what made successive conf*() calls undo each
     * other in 1.x (PR #10).
     *
     * @internal Exposed for tests; use configure() in application code.
     */
    public function modeCommand(string $device, SerialSettings $settings): string
    {
        return sprintf(
            'mode %s BAUD=%d PARITY=%s DATA=%d STOP=%s %s DTR=%s',
            $this->modeDeviceName($device),
            $settings->baudRate(),
            $this->parityCode($settings->parity()),
            $settings->dataBits(),
            $this->stopBitsCode($settings->stopBits()),
            $this->flowControlFlags($settings->flowControl()),
            $settings->dtr() ? 'ON' : 'OFF',
        );
    }

    private function modeDeviceName(string $device): string
    {
        if (!str_starts_with($device, self::DEVICE_PREFIX)) {
            throw new ConfigurationException(sprintf('Invalid Windows device path "%s".', $device));
        }

        return 'COM' . substr($device, strlen(self::DEVICE_PREFIX)) . ':';
    }

    private function parityCode(Parity $parity): string
    {
        return match ($parity) {
            Parity::None => 'N',
            Parity::Odd => 'O',
            Parity::Even => 'E',
            Parity::Mark => 'M',
            Parity::Space => 'S',
        };
    }

    private function stopBitsCode(StopBits $stopBits): string
    {
        return match ($stopBits) {
            StopBits::One => '1',
            StopBits::OneAndHalf => '15',
            StopBits::Two => '2',
        };
    }

    private function flowControlFlags(FlowControl $flowControl): string
    {
        return match ($flowControl) {
            FlowControl::None => 'XON=OFF OCTS=OFF RTS=ON',
            FlowControl::RtsCts => 'XON=OFF OCTS=ON RTS=HS',
            FlowControl::XonXoff => 'XON=ON OCTS=OFF RTS=ON',
        };
    }
}
