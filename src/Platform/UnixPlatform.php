<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Platform;

use Hyperthese\PhpSerial\Exception\ConfigurationException;
use Hyperthese\PhpSerial\Exception\ConnectionException;
use Hyperthese\PhpSerial\Exception\UnsupportedConfigurationException;
use Hyperthese\PhpSerial\FlowControl;
use Hyperthese\PhpSerial\Parity;
use Hyperthese\PhpSerial\SerialSettings;
use Hyperthese\PhpSerial\StopBits;
use Hyperthese\PhpSerial\TransferMode;
use InvalidArgumentException;

/**
 * Linux and macOS implementation, backed by stty(1).
 */
final class UnixPlatform implements PlatformInterface
{
    public const LINUX = 'linux';
    public const MACOS = 'macos';

    private ?bool $sttyAvailable = null;

    public function __construct(
        private readonly string $family,
        private readonly CommandRunnerInterface $runner = new ProcessCommandRunner(),
    ) {
        if ($family !== self::LINUX && $family !== self::MACOS) {
            throw new InvalidArgumentException(sprintf('Unsupported Unix family "%s".', $family));
        }
    }

    public function name(): string
    {
        return $this->family;
    }

    /**
     * Detects stty with `command -v` instead of running `stty` itself.
     *
     * `stty` without arguments inspects stdin and fails when PHP has no TTY
     * (Apache, php-fpm, cron, systemd), which caused issue #17. `command -v`
     * is supported by POSIX shells and busybox, so it fixes #1 as well.
     */
    public function isAvailable(): bool
    {
        return $this->sttyAvailable ??= $this->runner->run('command -v stty')->isSuccessful();
    }

    public function deviceExists(string $device): bool
    {
        return file_exists($device);
    }

    public function resolveDevice(string|int $device): string
    {
        $device = is_int($device) ? 'COM' . $device : trim($device);

        if ($device === '') {
            throw new ConfigurationException('The serial device name cannot be empty.');
        }

        if (preg_match('/^COM(\d+):?$/i', $device, $matches) === 1) {
            $number = (int) $matches[1];

            if ($number < 1) {
                throw new ConfigurationException('COM port numbers start at 1.');
            }

            return '/dev/ttyS' . ($number - 1);
        }

        if (str_starts_with($device, '/')) {
            return $device;
        }

        return '/dev/' . $device;
    }

    public function configure(string $device, SerialSettings $settings): void
    {
        if (!$this->isAvailable()) {
            throw new UnsupportedConfigurationException(
                'The "stty" utility is required to configure serial ports on this platform.',
            );
        }

        $result = $this->runner->run($this->sttyCommand($device, $settings));

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
        return 'r+bn';
    }

    public function supportsNonBlockingOpen(): bool
    {
        return true;
    }

    /**
     * Builds the single stty invocation that applies every setting.
     *
     * @internal Exposed for tests; use configure() in application code.
     */
    public function sttyCommand(string $device, SerialSettings $settings): string
    {
        $deviceFlag = $this->family === self::MACOS ? '-f' : '-F';

        return sprintf(
            'stty %s %s %s',
            $deviceFlag,
            $this->quoteArgument($device),
            $this->sttyFlags($settings),
        );
    }

    /**
     * Quotes an argument for a POSIX shell.
     *
     * escapeshellarg() follows the host OS, so it produces double quotes on
     * Windows. A stty command is always POSIX, so build it the same way
     * everywhere and keep the output deterministic in tests.
     */
    private function quoteArgument(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    private function sttyFlags(SerialSettings $settings): string
    {
        $flags = [
            (string) $settings->baudRate(),
            'cs' . $settings->dataBits(),
            $this->parityFlags($settings->parity()),
            $this->stopBitsFlag($settings->stopBits()),
            $this->flowControlFlags($settings->flowControl()),
            $this->transferModeFlags($settings->transferMode()),
            $settings->dtr() ? '-hupcl' : 'hupcl',
        ];

        return implode(' ', $flags);
    }

    private function parityFlags(Parity $parity): string
    {
        return match ($parity) {
            Parity::None => '-parenb',
            Parity::Odd => 'parenb parodd',
            Parity::Even => 'parenb -parodd',
            Parity::Mark => $this->family === self::LINUX
                ? 'cmspar parenb parodd'
                : throw new UnsupportedConfigurationException(
                    'Mark parity requires cmspar, which is not available on macOS.',
                ),
            Parity::Space => $this->family === self::LINUX
                ? 'cmspar parenb -parodd'
                : throw new UnsupportedConfigurationException(
                    'Space parity requires cmspar, which is not available on macOS.',
                ),
        };
    }

    private function stopBitsFlag(StopBits $stopBits): string
    {
        return match ($stopBits) {
            StopBits::One => '-cstopb',
            StopBits::Two => 'cstopb',
            StopBits::OneAndHalf => throw new UnsupportedConfigurationException(
                '1.5 stop bits are not supported by stty; use 1 or 2 stop bits.',
            ),
        };
    }

    private function flowControlFlags(FlowControl $flowControl): string
    {
        return match ($flowControl) {
            FlowControl::None => 'clocal -crtscts -ixon -ixoff',
            FlowControl::RtsCts => '-clocal crtscts -ixon -ixoff',
            FlowControl::XonXoff => '-clocal -crtscts ixon ixoff',
        };
    }

    private function transferModeFlags(TransferMode $transferMode): string
    {
        return match ($transferMode) {
            TransferMode::Binary => '-icanon -echo -echoe -echok -iexten -isig -opost -icrnl -brkint -ignbrk',
            TransferMode::Text => 'icanon echo echoe echok iexten isig opost icrnl brkint',
        };
    }
}
