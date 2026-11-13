<?php

declare(strict_types=1);

namespace Tests\Support;

use function Brain\Monkey\Functions\when;

/**
 * Runs the redirect_canonical filter for a request.
 */
final class RedirectCanonical
{
    /**
     * Run the filter for a request, on a site whose home URL is $home.
     */
    public static function run(string $requested, string $home = 'https://example.test/'): mixed
    {
        when('home_url')->justReturn($home);

        return PluginHooks::callback('filter', 'redirect_canonical')('https://example.test/redirected/', $requested);
    }
}
