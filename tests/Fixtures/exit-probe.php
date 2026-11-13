<?php

/**
 * Runs the plugin in its own PHP process so that its `exit;` statements really end the process.
 *
 * Usage: php exit-probe.php <scenario>
 *
 * Scenarios:
 *   guard-without-abspath  Load the plugin file with no ABSPATH defined.
 *   guard-with-abspath     Load it with ABSPATH defined (the control: the process carries on).
 *   llms                   Run the template_redirect callback for /llms.txt.
 *   llms-full              Run it for /llms-full.txt.
 *   neither                Run it for a request that is neither (the control: the process carries on).
 *
 * Prints AFTER when the process was still running once the scenario ended.
 */

declare(strict_types=1);

$scenario = $argv[1] ?? '';
$plugin = dirname(__DIR__, 2).'/uamswp-per-site-wayfinding.php';

if ($scenario !== 'guard-without-abspath') {
    define('ABSPATH', sys_get_temp_dir().'/');
}

$callbacks = [];

function add_filter(string $hook, callable $callback, int $priority = 10, int $args = 1): bool
{
    return true;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $args = 1): bool
{
    $GLOBALS['callbacks'][$hook][] = $callback;

    return true;
}

function register_activation_hook(string $file, callable $callback): void {}

function get_query_var(string $name): string
{
    $wanted = ['llms' => 'llms_txt', 'llms-full' => 'llms_full_txt'][$GLOBALS['scenario']] ?? '';

    return $name === $wanted ? '1' : '';
}

function get_option(string $name, $default = false)
{
    return $default;
}

function get_site_option(string $name, $default = false)
{
    return $default;
}

function home_url(string $path = ''): string
{
    return 'https://probe.test'.$path;
}

require $plugin;

if (in_array($scenario, ['llms', 'llms-full', 'neither'], true)) {
    foreach ($callbacks['template_redirect'] as $callback) {
        $callback();
    }
}

echo 'AFTER';
