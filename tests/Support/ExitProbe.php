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
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../Fixtures/exit-probe.php').' '.escapeshellarg($scenario);
        $raw = (string) shell_exec($command.' 2>&1; printf "\\nstatus=%s" "$?"');
        $position = (int) strrpos($raw, "\nstatus=");

        return [
            'output' => substr($raw, 0, $position),
            'status' => (int) substr($raw, $position + strlen("\nstatus=")),
        ];
    }
}
