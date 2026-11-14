<?php

/**
 * ptxt_track_request(), the fire-and-forget GA4 Measurement Protocol event.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Tests\Support\Tracking;

use function Brain\Monkey\Functions\when;

beforeEach(function (): void {
    $this->server = $_SERVER;
    unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
});

afterEach(function (): void {
    $_SERVER = $this->server;
});

it('sends nothing unless both the measurement id and the secret are set', function (string $id, string $secret): void {
    when('get_site_option')->alias(fn (string $name): string => match ($name) {
        'ptxt_ga4_measurement_id' => $id,
        'ptxt_ga4_api_secret' => $secret,
        default => '',
    });
    Functions\expect('wp_remote_post')->never();

    ptxt_track_request('llms.txt');
})->with([
    'both empty' => ['', ''],
    'no id' => ['', 'topsecret'],
    'no secret' => ['G-TEST', ''],
]);

it('reads both credentials from the network options, defaulting to empty', function (): void {
    Functions\expect('get_site_option')->once()->with('ptxt_ga4_measurement_id', '')->andReturn('');
    Functions\expect('get_site_option')->once()->with('ptxt_ga4_api_secret', '')->andReturn('');
    Functions\expect('wp_remote_post')->never();

    ptxt_track_request('llms.txt');
});

it('posts one non-blocking event with the request details', function (): void {
    $sent = Tracking::capture();
    $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

    ptxt_track_request('llms-full.txt');

    expect($sent->endpoint)->toBe('https://www.google-analytics.com/mp/collect?measurement_id=G-TEST&api_secret=topsecret')
        ->and($sent->args)->toHaveKeys(['timeout', 'blocking', 'headers', 'body'])
        ->and($sent->args['timeout'])->toBe(1)
        ->and($sent->args['blocking'])->toBeFalse()
        ->and($sent->args['headers'])->toBe(['Content-Type' => 'application/json']);

    expect(json_decode($sent->args['body'], true))->toBe([
        'client_id' => 'bot.'.substr(md5('GPTBot/1.0'), 0, 16),
        'events' => [[
            'name' => 'wayfinding_request',
            'params' => [
                'file_name' => 'llms-full.txt',
                'user_agent' => 'GPTBot/1.0',
                'requester_ip' => '203.0.113.9',
                'site_host' => 'site.example.test',
                'engagement_time_msec' => 1,
            ],
        ]],
    ]);
});

it('sends nothing when the payload cannot be encoded', function (): void {
    $sent = Tracking::capture();
    when('wp_json_encode')->justReturn(false);

    ptxt_track_request('llms.txt');

    expect($sent->endpoint)->toBeNull()
        ->and($sent->args)->toBeNull();
});

it('falls back to an unknown agent and a zero address when the request carries neither', function (): void {
    $sent = Tracking::capture();

    ptxt_track_request('llms.txt');

    $event = json_decode($sent->args['body'], true);

    expect($event['client_id'])->toBe('bot.'.substr(md5('unknown'), 0, 16))
        ->and($event['events'][0]['params']['user_agent'])->toBe('unknown')
        ->and($event['events'][0]['params']['requester_ip'])->toBe('0.0.0.0');
});

it('keeps at most the first 500 characters of the user agent', function (): void {
    $sent = Tracking::capture();
    $_SERVER['HTTP_USER_AGENT'] = str_repeat('a', 499).'bc';

    ptxt_track_request('llms.txt');

    $event = json_decode($sent->args['body'], true);

    expect($event['events'][0]['params']['user_agent'])->toBe(str_repeat('a', 499).'b')
        ->and($event['client_id'])->toBe('bot.'.substr(md5(str_repeat('a', 499).'b'), 0, 16));
});

it('reads the user agent and address as strings', function (): void {
    $sent = Tracking::capture();
    $_SERVER['HTTP_USER_AGENT'] = 12345;
    $_SERVER['REMOTE_ADDR'] = 42;

    ptxt_track_request('llms.txt');

    $params = json_decode($sent->args['body'], true)['events'][0]['params'];

    expect($params['user_agent'])->toBe('12345')
        ->and($params['requester_ip'])->toBe('42');
});
