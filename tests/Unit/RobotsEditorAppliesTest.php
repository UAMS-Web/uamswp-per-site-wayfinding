<?php

/**
 * ptxt_robots_editor_applies(), which sites get a per-site robots.txt editor.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

it('applies on a single site without asking about the network', function (): void {
    when('is_multisite')->justReturn(false);
    Functions\expect('is_subdomain_install')->never();
    Functions\expect('is_main_site')->never();

    expect(ptxt_robots_editor_applies())->toBeTrue();
});

it('applies on every site of a subdomain network without asking which site it is', function (): void {
    when('is_multisite')->justReturn(true);
    when('is_subdomain_install')->justReturn(true);

    Functions\expect('is_main_site')->never();

    expect(ptxt_robots_editor_applies())->toBeTrue();
});

it('applies on a subdirectory network only for the main site', function (bool $isMain): void {
    when('is_multisite')->justReturn(true);
    when('is_subdomain_install')->justReturn(false);
    when('is_main_site')->justReturn($isMain);

    expect(ptxt_robots_editor_applies())->toBe($isMain);
})->with([
    'main site' => [true],
    'child site' => [false],
]);
