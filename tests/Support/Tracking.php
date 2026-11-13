<?php

declare(strict_types=1);

namespace Tests\Support;

use function Brain\Monkey\Functions\when;

/**
 * A GA4 send with its WordPress dependencies stubbed.
 */
final class Tracking
{
    /**
     * Stub the options and the helpers a send needs, and capture the request.
     */
    public static function capture(string $id = 'G-TEST', string $secret = 'topsecret'): \stdClass
    {
        $sent = (object) ['endpoint' => null, 'args' => null];

        when('get_site_option')->alias(fn (string $name, $default = false) => match ($name) {
            'ptxt_ga4_measurement_id' => $id,
            'ptxt_ga4_api_secret' => $secret,
            default => $default,
        });
        when('home_url')->justReturn('https://site.example.test/sub');
        when('add_query_arg')->alias(
            fn (array $args, string $url): string => $url.'?'.http_build_query($args)
        );
        when('wp_remote_post')->alias(function (string $endpoint, array $args) use ($sent): void {
            $sent->endpoint = $endpoint;
            $sent->args = $args;
        });

        return $sent;
    }
}
