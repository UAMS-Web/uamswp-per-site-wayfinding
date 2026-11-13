<?php

/**
 * ptxt_default_robots(), the robots.txt a newly configured site starts from.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

it('allows every crawler and names the site sitemap', function (): void {
    Functions\expect('home_url')->once()->with('/sitemap.xml')->andReturn('https://example.test/sitemap.xml');

    expect(ptxt_default_robots())->toBe("User-agent: *\nAllow: /\n\nSitemap: https://example.test/sitemap.xml\n");
});
