<?php

declare(strict_types=1);

namespace Tests\Support;

use function Brain\Monkey\Functions\when;

/**
 * An in-memory stand-in for WordPress' options, recording every read and write.
 *
 * A test sets `$options` or `$siteOptions`, runs the page, and asserts on what the page read (with
 * which default) and wrote. The state lives here, not on the test case, so a static analyzer can
 * type it.
 */
final class OptionStore
{
    /** @var array<string, mixed> */
    public static array $options = [];

    /** @var array<string, mixed> */
    public static array $siteOptions = [];

    /** @var list<array{0: string, 1: mixed}> */
    public static array $reads = [];

    /** @var list<array{0: string, 1: mixed}> */
    public static array $siteReads = [];

    /** @var list<array{0: string, 1: mixed}> */
    public static array $updates = [];

    /** @var list<array{0: string, 1: mixed}> */
    public static array $siteUpdates = [];

    /**
     * Empty the store and stub the four option functions to use it.
     */
    public static function install(): void
    {
        self::$options = [];
        self::$siteOptions = [];
        self::$reads = [];
        self::$siteReads = [];
        self::$updates = [];
        self::$siteUpdates = [];

        when('get_option')->alias(function (string $name, mixed $default = false): mixed {
            self::$reads[] = [$name, $default];

            return array_key_exists($name, self::$options) ? self::$options[$name] : $default;
        });
        when('get_site_option')->alias(function (string $name, mixed $default = false): mixed {
            self::$siteReads[] = [$name, $default];

            return array_key_exists($name, self::$siteOptions) ? self::$siteOptions[$name] : $default;
        });
        when('update_option')->alias(function (string $name, mixed $value): void {
            self::$updates[] = [$name, $value];
        });
        when('update_site_option')->alias(function (string $name, mixed $value): void {
            self::$siteUpdates[] = [$name, $value];
        });
    }
}
