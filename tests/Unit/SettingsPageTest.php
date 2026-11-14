<?php

/**
 * ptxt_render_settings_page(), the per-site Site Files screen.
 *
 * Options are plain arrays on the test, and every read and write is recorded, so
 * a test can say exactly which options the page read (with which default) and
 * wrote.
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
    when('is_super_admin')->justReturn(true);
    when('is_multisite')->justReturn(false);
    when('home_url')->justReturn('https://site.test');
    when('admin_url')->alias(fn (string $path): string => 'https://site.test/wp-admin/'.$path);
    when('wp_verify_nonce')->alias(fn ($nonce, string $action): bool => $nonce === 'good' && $action === 'save_site_txt_files');
});

afterEach(function (): void {
    $_POST = $this->post;
});

it('refuses anyone but a super admin and says why', function (): void {
    when('is_super_admin')->justReturn(false);
    Functions\expect('wp_die')->once()->with('You do not have permission to access this page.')->andThrow(new RuntimeException('died'));

    expect(fn () => ptxt_render_settings_page())->toThrow(RuntimeException::class, 'died');
});

it('shows the saved content, the site address and the form for a request that saves nothing', function (): void {
    OptionStore::$options = [
        'custom_robots_txt' => 'saved robots',
        'custom_llms_txt' => 'saved llms',
        'custom_llms_full_txt' => 'saved full',
    ];

    $html = Render::capture('ptxt_render_settings_page');

    expect($html)
        ->toContain('<h1>Site Files</h1>')
        ->toContain('<code>[html:https://site.test]/robots.txt</code>')
        ->toContain('<code>[html:https://site.test]/llms.txt</code>')
        ->toContain('<code>[html:https://site.test]/llms-full.txt</code>')
        ->toContain('<nonce:save_site_txt_files:site_txt_files_nonce>')
        ->toContain('<submit:Save Site Files>')
        ->toContain('name="custom_robots_txt"')
        ->toContain('>[textarea:saved robots]</textarea>')
        ->toContain('>[textarea:saved llms]</textarea>')
        ->toContain('>[textarea:saved full]</textarea>')
        ->not->toContain('Settings saved.')
        ->not->toContain('Warning:')
        ->not->toContain('confirm_disallow_all')
        ->and(OptionStore::$updates)->toBe([]);
});

it('starts the robots.txt editor from the default content, and the other two empty', function (): void {
    when('home_url')->alias(fn (string $path = ''): string => 'https://site.test'.$path);
    $default = "User-agent: *\nAllow: /\n\nSitemap: https://site.test/sitemap.xml\n";

    $html = Render::capture('ptxt_render_settings_page');

    expect(OptionStore::$reads)->toBe([
        ['custom_robots_txt', $default],
        ['custom_llms_txt', ''],
        ['custom_llms_full_txt', ''],
    ])->and($html)->toContain('>[textarea:'.$default.']</textarea>');
});

it('saves all three files, unslashed, and says so', function (): void {
    $_POST = [
        'site_txt_files_nonce' => 'good',
        'custom_robots_txt' => 'User-agent: *\\\\nAllow: /',
        'custom_llms_txt' => 'it\\\'s llms',
        'custom_llms_full_txt' => 'full',
    ];

    $html = Render::capture('ptxt_render_settings_page');

    expect(OptionStore::$updates)->toBe([
        ['custom_robots_txt', 'User-agent: *\\nAllow: /'],
        ['custom_llms_txt', "it's llms"],
        ['custom_llms_full_txt', 'full'],
    ])->and($html)->toContain('<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>');
});

it('saves empty content when a field is missing from the post', function (): void {
    $_POST = ['site_txt_files_nonce' => 'good'];

    Render::capture('ptxt_render_settings_page');

    expect(OptionStore::$updates)->toBe([
        ['custom_robots_txt', ''],
        ['custom_llms_txt', ''],
        ['custom_llms_full_txt', ''],
    ]);
});

it('saves nothing when the nonce does not verify', function (): void {
    $_POST = ['site_txt_files_nonce' => 'bad', 'custom_robots_txt' => 'x'];

    expect(Render::capture('ptxt_render_settings_page'))->not->toContain('Settings saved.')
        ->and(OptionStore::$updates)->toBe([]);
});

it('saves nothing when there is no nonce', function (): void {
    $_POST = ['custom_robots_txt' => 'x'];
    Functions\expect('wp_verify_nonce')->never();

    expect(Render::capture('ptxt_render_settings_page'))->not->toContain('Settings saved.')
        ->and(OptionStore::$updates)->toBe([]);
});

it('holds back a robots.txt that blocks every crawler until it is confirmed', function (): void {
    $_POST = [
        'site_txt_files_nonce' => 'good',
        'custom_robots_txt' => "User-agent: *\nDisallow: /",
        'custom_llms_txt' => 'my llms',
        'custom_llms_full_txt' => 'my full',
    ];

    $html = Render::capture('ptxt_render_settings_page');

    expect($html)
        ->toContain('<div class="notice notice-error">')
        ->toContain('<strong>Warning:</strong>')
        ->toContain(">[textarea:User-agent: *\nDisallow: /]</textarea>")
        ->toContain('>[textarea:my llms]</textarea>')
        ->toContain('>[textarea:my full]</textarea>')
        ->toContain('name="confirm_disallow_all" value="1"')
        ->toContain('Yes, I intend to block all crawlers on [html:https://site.test].')
        ->not->toContain('Settings saved.')
        ->and(OptionStore::$updates)->toBe([])
        ->and(OptionStore::$reads)->toBe([]);
});

it('saves a robots.txt that blocks every crawler once it is confirmed', function (): void {
    $_POST = [
        'site_txt_files_nonce' => 'good',
        'custom_robots_txt' => "User-agent: *\nDisallow: /",
        'confirm_disallow_all' => '1',
    ];

    $html = Render::capture('ptxt_render_settings_page');

    expect(OptionStore::$updates)->toBe([
        ['custom_robots_txt', "User-agent: *\nDisallow: /"],
        ['custom_llms_txt', ''],
        ['custom_llms_full_txt', ''],
    ])->and($html)->toContain('Settings saved.')
        ->not->toContain('<strong>Warning:</strong>');
});

it('does not treat an empty confirmation as confirmed', function (): void {
    $_POST = [
        'site_txt_files_nonce' => 'good',
        'custom_robots_txt' => "User-agent: *\nDisallow: /",
        'confirm_disallow_all' => '',
    ];

    expect(Render::capture('ptxt_render_settings_page'))->toContain('<strong>Warning:</strong>')
        ->and(OptionStore::$updates)->toBe([]);
});

it('points to the editor on the network root, and shows none, on a child site of a subdirectory network', function (): void {
    Admin::asChildSite();

    $html = Render::capture('ptxt_render_settings_page');

    expect($html)
        ->toContain('<strong>Host-scoped.</strong>')
        ->toContain('<a href="[url:https://root.test/1/wp-admin/options-general.php?page=site-txt-files]">Site Files (network root)</a>')
        ->not->toContain('name="custom_robots_txt"')
        ->not->toContain('/robots.txt</code>,');
});

it('saves the two llms files and never the robots.txt on a child site, even one that blocks everything', function (): void {
    Admin::asChildSite();
    $_POST = [
        'site_txt_files_nonce' => 'good',
        'custom_robots_txt' => "User-agent: *\nDisallow: /",
        'custom_llms_txt' => 'a',
        'custom_llms_full_txt' => 'b',
    ];

    $html = Render::capture('ptxt_render_settings_page');

    expect(OptionStore::$updates)->toBe([
        ['custom_llms_txt', 'a'],
        ['custom_llms_full_txt', 'b'],
    ])->and($html)->toContain('Settings saved.')
        ->not->toContain('<strong>Warning:</strong>');
});

it('builds the network root link from the main site on a network', function (): void {
    Admin::asChildSite();
    when('get_main_site_id')->justReturn(4);

    expect(Render::capture('ptxt_render_settings_page'))
        ->toContain('[url:https://root.test/4/wp-admin/options-general.php?page=site-txt-files]');
});

it('shows the physical robots.txt warning only where a file exists and the editor applies', function (): void {
    if (! is_dir(ABSPATH)) {
        mkdir(ABSPATH, 0777, true);
    }

    file_put_contents(ABSPATH.'robots.txt', 'x');

    try {
        $html = Render::capture('ptxt_render_settings_page');
    } finally {
        unlink(ABSPATH.'robots.txt');
    }

    expect($html)
        ->toContain('<strong>A physical <code>robots.txt</code> file exists at the web root.</strong>')
        ->toContain('<code>[html:'.ABSPATH.']robots.txt</code>');
});

it('shows no physical robots.txt warning when there is no file', function (): void {
    expect(Render::capture('ptxt_render_settings_page'))->not->toContain('A physical');
});

it('shows no physical robots.txt warning on a site without the editor, whatever is on disk', function (): void {
    Admin::asChildSite();
    if (! is_dir(ABSPATH)) {
        mkdir(ABSPATH, 0777, true);
    }

    file_put_contents(ABSPATH.'robots.txt', 'x');

    try {
        $html = Render::capture('ptxt_render_settings_page');
    } finally {
        unlink(ABSPATH.'robots.txt');
    }

    expect($html)->not->toContain('A physical');
});

it('reports GA4 tracking as enabled only with both an id and a secret', function (string $id, string $secret, bool $enabled): void {
    OptionStore::$siteOptions = ['ptxt_ga4_measurement_id' => $id, 'ptxt_ga4_api_secret' => $secret];

    $html = Render::capture('ptxt_render_settings_page');

    if ($enabled) {
        expect($html)->toContain('Enabled</span>')->not->toContain('Disabled</span>');
    } else {
        expect($html)->toContain('Disabled</span>')->not->toContain('Enabled</span>');
    }
})->with([
    'both set' => ['G-1', 's', true],
    'no id' => ['', 's', false],
    'no secret' => ['G-1', '', false],
    'neither' => ['', '', false],
]);

it('reads the tracking credentials from the network options, defaulting to empty', function (): void {
    Render::capture('ptxt_render_settings_page');

    expect(OptionStore::$siteReads)->toBe([
        ['ptxt_ga4_measurement_id', ''],
        ['ptxt_ga4_api_secret', ''],
    ]);
});

it('starts from the default content when the saved robots.txt is not text', function (): void {
    OptionStore::$options = ['custom_robots_txt' => ['not', 'text']];
    when('home_url')->alias(fn (string $path = ''): string => 'https://site.test'.$path);

    expect(Render::capture('ptxt_render_settings_page'))
        ->toContain('>[textarea:User-agent: *'."\n".'Allow: /');
});
