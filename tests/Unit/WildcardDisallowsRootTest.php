<?php

/**
 * ptxt_wildcard_disallows_root(), the guard behind the "block all crawlers" confirmation.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

it('flags a wildcard group that disallows the whole site', function (string $robots): void {
    expect(ptxt_wildcard_disallows_root($robots))->toBeTrue();
})->with([
    'bare block' => ["User-agent: *\nDisallow: /\n"],
    'CRLF line endings' => ["User-agent: *\r\nDisallow: /\r\n"],
    'inline comment' => ["User-agent: *\nDisallow: / # staging\n"],
    'comment on the agent line' => ["User-agent: * # everyone\nDisallow: /\n"],
    'CR-only line endings' => ["User-agent: *\rDisallow: /\r"],
    'later group allows' => ["User-agent: *\nDisallow: /\n\nUser-agent: Googlebot\nAllow: /\n"],
]);

it('does not flag a robots.txt that leaves the site crawlable', function (mixed $robots): void {
    expect(ptxt_wildcard_disallows_root($robots))->toBeFalse();
})->with([
    'empty string' => [''],
    'not a string' => [null],
    'path-only disallow' => ["User-agent: *\nDisallow: /wp-admin/\n"],
    'commented-out disallow' => ["User-agent: *\n# Disallow: /\n"],
    'comment-only robots.txt' => ["# User-agent: *\n# Disallow: /\n"],
    'allow overrides the block' => ["User-agent: *\nDisallow: /\nAllow: /\n"],
    'specific bot blocked, wildcard open' => ["User-agent: SemrushBot\nDisallow: /\n\nUser-agent: *\nAllow: /\n"],
]);
