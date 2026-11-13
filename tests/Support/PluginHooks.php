<?php

declare(strict_types=1);

namespace Tests\Support;

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;
use function Patchwork\redefine;

/**
 * Loads the plugin file once per process and keeps the callbacks it registers.
 *
 * The plugin registers every hook at file scope, with closures. Brain Monkey's
 * hook functions record a callback only as a string form, so a closure cannot be
 * recovered from them. This captures each registration as it happens, so a test
 * can call the callback directly.
 */
final class PluginHooks
{
    /**
     * @var list<array{type: string, hook: string, callback: callable, priority: int, accepted_args: int}>
     */
    private static array $registered = [];

    /**
     * @var list<array{file: string, callback: callable}>
     */
    private static array $activation = [];

    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        redefine('add_filter', function (string $hook, $callback, int $priority = 10, int $accepted = 1): bool {
            self::$registered[] = [
                'type' => 'filter',
                'hook' => $hook,
                'callback' => $callback,
                'priority' => $priority,
                'accepted_args' => $accepted,
            ];

            return true;
        });
        redefine('add_action', function (string $hook, $callback, int $priority = 10, int $accepted = 1): bool {
            self::$registered[] = [
                'type' => 'action',
                'hook' => $hook,
                'callback' => $callback,
                'priority' => $priority,
                'accepted_args' => $accepted,
            ];

            return true;
        });
        // WordPress core defines this one and Brain Monkey does not, so there is
        // nothing for Patchwork to redefine: Brain Monkey creates it.
        when('register_activation_hook')->alias(function (string $file, $callback): void {
            self::$activation[] = ['file' => $file, 'callback' => $callback];
        });

        require_once __DIR__.'/../../uamswp-per-site-wayfinding.php';

        self::$loaded = true;
    }

    /**
     * The one callback registered on a hook of a type.
     */
    public static function callback(string $type, string $hook): callable
    {
        self::load();

        $matches = array_values(array_filter(
            self::$registered,
            static fn (array $entry): bool => $entry['type'] === $type && $entry['hook'] === $hook,
        ));

        if (count($matches) !== 1) {
            throw new \RuntimeException(sprintf('Expected one %s on "%s", found %d.', $type, $hook, count($matches)));
        }

        return $matches[0]['callback'];
    }

    /**
     * Every registration, for tests that pin priority and accepted arguments.
     *
     * @return list<array{type: string, hook: string, callback: callable, priority: int, accepted_args: int}>
     */
    public static function registered(): array
    {
        self::load();

        return self::$registered;
    }

    /**
     * @return list<array{file: string, callback: callable}>
     */
    public static function activation(): array
    {
        self::load();

        return self::$activation;
    }
}
