<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Output capture for the functions that echo a page.
 */
final class Render
{
    /**
     * What the callable echoed.
     */
    public static function capture(callable $render): string
    {
        ob_start();

        try {
            $render();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    /**
     * What the callable echoed before it threw ExitReached, and whether it did.
     *
     * @return array{output: string, reachedExit: bool}
     */
    public static function captureUntilExit(callable $render): array
    {
        $reached = false;

        $output = self::capture(static function () use ($render, &$reached): void {
            try {
                $render();
            } catch (ExitReached) {
                $reached = true;
            }
        });

        return ['output' => $output, 'reachedExit' => $reached];
    }
}
