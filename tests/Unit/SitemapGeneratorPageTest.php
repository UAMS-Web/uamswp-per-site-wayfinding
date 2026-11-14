<?php

/**
 * ptxt_render_sitemap_generator_page(), the network sitemap block screen.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Tests\Support\Admin;
use Tests\Support\Network;
use Tests\Support\Render;

use function Brain\Monkey\Functions\when;

beforeEach(function (): void {
    $this->get = $_GET;
    $_GET = [];

    Admin::stubOutput();
    when('current_user_can')->justReturn(true);
});

afterEach(function (): void {
    $_GET = $this->get;
});

it('refuses anyone without network options access and says why', function (): void {
    when('current_user_can')->alias(fn (string $capability): bool => $capability !== 'manage_network_options');
    Functions\expect('wp_die')->once()->with('You do not have permission to access this page.')->andThrow(new RuntimeException('died'));

    expect(fn () => ptxt_render_sitemap_generator_page())->toThrow(RuntimeException::class, 'died');
});

it('says so when the network has no public subsites', function (): void {
    when('is_multisite')->justReturn(false);

    $html = Render::capture('ptxt_render_sitemap_generator_page');

    expect($html)
        ->toContain('<h1>Sitemap Block Generator</h1>')
        ->toContain('<p><em>No public subsites found on this network.</em></p>')
        ->toContain('value="[attr:/sitemaps.xml]"')
        ->toContain('name="page" value="site-txt-files-sitemaps"');
    expect($html)->not->toContain('<textarea');
});

it('shows the generated block for the default path in a read-only box', function (): void {
    Network::stub([1 => [1, 'https://root.test/'], 2 => [1, 'https://root.test/b']]);

    $html = Render::capture('ptxt_render_sitemap_generator_page');

    expect($html)
        ->toContain("# Sitemaps\nSitemap: https://root.test/sitemaps.xml\nSitemap: https://root.test/b/sitemaps.xml")
        ->toContain('rows="6"')
        ->toContain('<textarea')
        ->toContain('readonly');
    expect($html)->not->toContain('No public subsites found');
});

it('grows the box with the block, two rows past the line breaks', function (): void {
    $sites = [1 => [1, 'https://root.test']];
    foreach (range(2, 9) as $id) {
        $sites[$id] = [1, 'https://root.test/s'.$id];
    }

    Network::stub($sites);

    // The block is ten lines, each ending in a break: ten breaks, so twelve rows.
    expect(Render::capture('ptxt_render_sitemap_generator_page'))->toContain('rows="12"');
});

it('builds the block for the requested path, unslashed and sanitized', function (): void {
    $_GET = ['path' => '\\/custom.xml'];
    Network::stub([1 => [1, 'https://root.test']]);

    $html = Render::capture('ptxt_render_sitemap_generator_page');

    expect($html)
        ->toContain('Sitemap: https://root.test/[clean:/custom.xml]')
        ->toContain('value="[attr:[clean:/custom.xml]]"');
});

it('falls back to the default path when the requested one is empty', function (): void {
    $_GET = ['path' => ''];
    when('sanitize_text_field')->alias(fn (string $value): string => trim($value));
    Network::stub([1 => [1, 'https://root.test']]);

    $html = Render::capture('ptxt_render_sitemap_generator_page');

    expect($html)
        ->toContain('Sitemap: https://root.test/sitemaps.xml')
        ->toContain('value="[attr:/sitemaps.xml]"');
});
