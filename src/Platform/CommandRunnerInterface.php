<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Platform;

/**
 * Runs the platform utilities used to configure a serial port.
 */
interface CommandRunnerInterface
{
    public function run(string $command): CommandResult;
}
