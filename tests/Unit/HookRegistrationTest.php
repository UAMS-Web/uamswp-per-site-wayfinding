<?php

/**
 * What the plugin registers on WordPress hooks when its file loads.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Tests\Support\PluginHooks;

it('registers exactly these hooks, in this order, with these priorities and argument counts', function (): void {
    $registered = array_map(
        static fn (array $entry): array => [$entry['type'], $entry['hook'], $entry['priority'], $entry['accepted_args']],
        PluginHooks::registered(),
    );

    expect($registered)->toBe([
        ['filter', 'robots_txt', 10, 2],
        ['action', 'init', 10, 1],
        ['filter', 'query_vars', 10, 1],
        ['action', 'template_redirect', 10, 1],
        ['filter', 'redirect_canonical', 10, 2],
        ['action', 'admin_menu', 10, 1],
        ['action', 'network_admin_menu', 10, 1],
    ]);
});

it('registers one activation hook, for the plugin file itself', function (): void {
    $activation = PluginHooks::activation();

    expect($activation)->toHaveCount(1)
        ->and($activation[0]['file'])->toBe(realpath(__DIR__.'/../../uamswp-per-site-wayfinding.php'));
});
