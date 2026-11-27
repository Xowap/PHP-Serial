<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Platform;

/**
 * Result of a shell command executed by a {@see CommandRunnerInterface}.
 */
final class CommandResult
{
    public function __construct(
        public readonly string $command,
        public readonly int $exitCode,
        public readonly string $stdout = '',
        public readonly string $stderr = '',
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Human readable output for exception messages.
     */
    public function errorMessage(): string
    {
        $message = trim($this->stderr);

        if ($message === '') {
            $message = trim($this->stdout);
        }

        if ($message === '') {
            return sprintf('the command exited with code %d', $this->exitCode);
        }

        return $message;
    }
}
