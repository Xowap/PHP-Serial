<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Platform;

use Hyperthese\PhpSerial\Exception\IOException;

/**
 * Runs commands through proc_open(), capturing stdout, stderr and the exit code.
 *
 * On Windows PHP passes a string command to cmd.exe /c, which is what the
 * `mode` utility requires.
 */
final class ProcessCommandRunner implements CommandRunnerInterface
{
    public function run(string $command): CommandResult
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new IOException(sprintf('Unable to start the command "%s".', $command));
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new CommandResult(
            $command,
            $exitCode,
            $stdout === false ? '' : $stdout,
            $stderr === false ? '' : $stderr,
        );
    }
}
