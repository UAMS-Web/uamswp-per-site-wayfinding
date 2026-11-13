<?php

/**
 * ptxt_wildcard_disallows_root(), the guard behind the "block all crawlers" confirmation.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

beforeEach(function (): void {
    // The plugin registers hooks and an activation hook at file scope. Brain
    // Monkey supplies add_action() and add_filter(); the activation hook is
    // stubbed here. require_once loads the file once per process, so the
    // functions stay defined for later tests.
    Functions\when('register_activation_hook')->justReturn(null);

    require_once __DIR__.'/../../uamswp-per-site-wayfinding.php';
});

it('flags a wildcard group that disallows the whole site', function (string $robots): void {
    expect(ptxt_wildcard_disallows_root($robots))->toBeTrue();
})->with([
    'bare block' => ["User-agent: *\nDisallow: /\n"],
    'CRLF line endings' => ["User-agent: *\r\nDisallow: /\r\n"],
    'inline comment' => ["User-agent: *\nDisallow: / # staging\n"],
    'later group allows' => ["User-agent: *\nDisallow: /\n\nUser-agent: Googlebot\nAllow: /\n"],
]);

it('does not flag a robots.txt that leaves the site crawlable', function (mixed $robots): void {
    expect(ptxt_wildcard_disallows_root($robots))->toBeFalse();
})->with([
    'empty string' => [''],
    'not a string' => [null],
    'path-only disallow' => ["User-agent: *\nDisallow: /wp-admin/\n"],
    'allow overrides the block' => ["User-agent: *\nDisallow: /\nAllow: /\n"],
    'specific bot blocked, wildcard open' => ["User-agent: SemrushBot\nDisallow: /\n\nUser-agent: *\nAllow: /\n"],
]);
