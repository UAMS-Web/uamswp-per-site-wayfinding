<?php

/**
 * ptxt_render_network_tracking_page(), the network GA4 credentials screen.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Tests\Support\Admin;
use Tests\Support\OptionStore;
use Tests\Support\Render;

use function Brain\Monkey\Functions\when;

beforeEach(function (): void {
    $this->post = $_POST;
    $_POST = [];

    OptionStore::install();

    Admin::stubOutput();
    when('current_user_can')->justReturn(true);
    when('wp_verify_nonce')->alias(fn ($nonce, string $action): bool => $nonce === 'good' && $action === 'save_site_txt_tracking');
});

afterEach(function (): void {
    $_POST = $this->post;
});

it('refuses anyone without network options access and says why', function (): void {
    when('current_user_can')->alias(fn (string $capability): bool => $capability !== 'manage_network_options');
    Functions\expect('wp_die')->once()->with('You do not have permission to access this page.')->andThrow(new RuntimeException('died'));

    expect(fn () => ptxt_render_network_tracking_page())->toThrow(RuntimeException::class, 'died');
});

it('checks the network options capability and no other', function (): void {
    $asked = [];
    when('current_user_can')->alias(function (string $capability) use (&$asked): bool {
        $asked[] = $capability;

        return true;
    });

    Render::capture('ptxt_render_network_tracking_page');

    expect($asked)->toBe(['manage_network_options']);
});

it('shows the saved credentials escaped in the form, and no saved notice', function (): void {
    OptionStore::$siteOptions = ['ptxt_ga4_measurement_id' => 'G-1', 'ptxt_ga4_api_secret' => 'sec'];

    $html = Render::capture('ptxt_render_network_tracking_page');

    expect($html)
        ->toContain('<h1>Site Files Tracking</h1>')
        ->toContain('value="[attr:G-1]"')
        ->toContain('value="[attr:sec]"')
        ->toContain('<nonce:save_site_txt_tracking:site_txt_tracking_nonce>')
        ->toContain('<submit:Save Tracking Settings>')
        ->not->toContain('Tracking settings saved.')
        ->and(OptionStore::$siteReads)->toBe([
            ['ptxt_ga4_measurement_id', ''],
            ['ptxt_ga4_api_secret', ''],
        ])
        ->and(OptionStore::$siteUpdates)->toBe([]);
});

it('saves both credentials, unslashed, trimmed and sanitized, and says so', function (): void {
    $_POST = [
        'site_tracking_unused' => 'x',
        'site_txt_tracking_nonce' => 'good',
        'ptxt_ga4_measurement_id' => '  G-\\1  ',
        'ptxt_ga4_api_secret' => ' s3 ',
    ];

    $html = Render::capture('ptxt_render_network_tracking_page');

    expect(OptionStore::$siteUpdates)->toBe([
        ['ptxt_ga4_measurement_id', '[clean:G-1]'],
        ['ptxt_ga4_api_secret', '[clean:s3]'],
    ])->and($html)->toContain('<div class="notice notice-success is-dismissible"><p>Tracking settings saved.</p></div>');
});

it('saves empty credentials when the fields are missing from the post', function (): void {
    $_POST = ['site_txt_tracking_nonce' => 'good'];

    $html = Render::capture('ptxt_render_network_tracking_page');

    expect(OptionStore::$siteUpdates)->toBe([
        ['ptxt_ga4_measurement_id', '[clean:]'],
        ['ptxt_ga4_api_secret', '[clean:]'],
    ])->and($html)->toContain('Tracking settings saved.');
});

it('saves nothing when the nonce does not verify', function (): void {
    $_POST = ['site_txt_tracking_nonce' => 'bad', 'ptxt_ga4_measurement_id' => 'G-1'];

    expect(Render::capture('ptxt_render_network_tracking_page'))->not->toContain('Tracking settings saved.')
        ->and(OptionStore::$siteUpdates)->toBe([]);
});

it('saves nothing when there is no nonce', function (): void {
    $_POST = ['ptxt_ga4_measurement_id' => 'G-1'];

    expect(Render::capture('ptxt_render_network_tracking_page'))->not->toContain('Tracking settings saved.')
        ->and(OptionStore::$siteUpdates)->toBe([]);
});

it('shows the credentials it has just saved', function (): void {
    $_POST = ['site_txt_tracking_nonce' => 'good', 'ptxt_ga4_measurement_id' => 'G-9', 'ptxt_ga4_api_secret' => 'k'];
    when('update_site_option')->alias(function (string $name, $value): void {
        OptionStore::$siteOptions[$name] = $value;
    });

    $html = Render::capture('ptxt_render_network_tracking_page');

    expect($html)->toContain('value="[attr:[clean:G-9]]"')->toContain('value="[attr:[clean:k]]"');
});
