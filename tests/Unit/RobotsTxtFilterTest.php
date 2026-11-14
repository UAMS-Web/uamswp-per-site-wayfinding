<?php

/**
 * The robots_txt filter: a subsite's saved robots.txt replaces WordPress' own.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Tests\Support\PluginHooks;

use function Brain\Monkey\Functions\when;

it('leaves the output alone where the editor does not apply, without reading the option', function (): void {
    when('is_multisite')->justReturn(true);
    when('is_subdomain_install')->justReturn(false);
    when('is_main_site')->justReturn(false);

    Functions\expect('get_option')->never();

    expect(PluginHooks::callback('filter', 'robots_txt')('wordpress default', true))->toBe('wordpress default');
});

it('serves the saved content where the editor applies', function (): void {
    when('is_multisite')->justReturn(false);
    Functions\expect('get_option')->once()->with('custom_robots_txt', '')->andReturn("User-agent: *\nDisallow: /private/\n");

    expect(PluginHooks::callback('filter', 'robots_txt')('wordpress default', true))
        ->toBe("User-agent: *\nDisallow: /private/\n");
});

it('keeps the default when nothing is saved or the saved content is empty', function (mixed $saved): void {
    when('is_multisite')->justReturn(false);
    when('get_option')->justReturn($saved);

    expect(PluginHooks::callback('filter', 'robots_txt')('wordpress default', true))->toBe('wordpress default');
})->with([
    'never saved' => [false],
    'saved empty' => [''],
    'saved as something other than text' => [['not', 'text']],
]);

it('serves saved content that is the string zero', function (): void {
    when('is_multisite')->justReturn(false);
    when('get_option')->justReturn('0');

    expect(PluginHooks::callback('filter', 'robots_txt')('wordpress default', true))->toBe('0');
});
