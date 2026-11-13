<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Runs a scenario of tests/Fixtures/exit-probe.php in its own PHP process.
 */
final class ExitProbe
{
    /**
     * What the process printed, and the status it ended with.
     *
     * @return array{output: string, status: int}
     */
    public static function run(string $scenario): array
    {
        // An argument array starts the process without a shell, so the same call works on Windows.
        $process = proc_open(
            [PHP_BINARY, __DIR__.'/../Fixtures/exit-probe.php', $scenario],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (! is_resource($process)) {
            return ['output' => 'could not start PHP', 'status' => -1];
        }

        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['output' => $output, 'status' => proc_close($process)];
    }
}
