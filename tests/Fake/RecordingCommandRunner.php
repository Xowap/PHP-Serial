<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Tests\Fake;

use Hyperthese\PhpSerial\Platform\CommandResult;
use Hyperthese\PhpSerial\Platform\CommandRunnerInterface;

/**
 * Records every command it is asked to run and replays queued results.
 */
final class RecordingCommandRunner implements CommandRunnerInterface
{
    /** @var array<int, string> */
    public array $commands = [];

    /** @var array<int, CommandResult> */
    private array $results = [];

    public function queueResult(int $exitCode, string $stdout = '', string $stderr = ''): void
    {
        $this->results[] = new CommandResult('', $exitCode, $stdout, $stderr);
    }

    public function run(string $command): CommandResult
    {
        $this->commands[] = $command;

        $result = array_shift($this->results);

        if ($result === null) {
            return new CommandResult($command, 0);
        }

        return new CommandResult($command, $result->exitCode, $result->stdout, $result->stderr);
    }
}
