<?php

/**
 * ptxt_build_multisite_sitemap_block(), the "# Sitemaps" block of the network-admin generator.
 *
 * This file sorts first on purpose. The first test needs WordPress' get_sites()
 * and get_main_site_id() to be undefined, and Brain Monkey leaves a function it
 * has stubbed defined for the rest of the process, so no other test may stub
 * either one before it. The test asserts that precondition and fails loudly if
 * the order is ever broken.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Tests\Support\Network;

use function Brain\Monkey\Functions\when;

it('returns an empty block when WordPress cannot list sites, and finds the main site when it can', function (): void {
    expect(function_exists('get_sites'))->toBeFalse('get_sites() was stubbed by an earlier test: this file must sort first')
        ->and(function_exists('get_main_site_id'))->toBeFalse('get_main_site_id() was stubbed by an earlier test: this file must sort first');

    when('is_multisite')->justReturn(true);

    expect(ptxt_build_multisite_sitemap_block())->toBe('');

    // get_sites() exists, get_main_site_id() still does not: the root site is blog 1.
    when('get_sites')->justReturn([(object) ['blog_id' => '1'], (object) ['blog_id' => '2']]);
    when('switch_to_blog')->justReturn();
    when('get_option')->justReturn('1');
    when('get_home_url')->alias(fn ($id): string => 'https://site'.$id.'.test/');
    when('restore_current_blog')->justReturn();

    expect(ptxt_build_multisite_sitemap_block('/map.xml'))
        ->toBe("# Sitemaps\nSitemap: https://site1.test/map.xml\nSitemap: https://site2.test/map.xml\n");

    // Once get_main_site_id() exists its answer wins over the default of 1.
    when('get_main_site_id')->justReturn(2);

    expect(ptxt_build_multisite_sitemap_block('/map.xml'))
        ->toBe("# Sitemaps\nSitemap: https://site2.test/map.xml\nSitemap: https://site1.test/map.xml\n");
});

it('returns an empty block on a single site without asking for sites', function (): void {
    when('is_multisite')->justReturn(false);
    Functions\expect('get_sites')->never();

    expect(ptxt_build_multisite_sitemap_block())->toBe('');
});

it('returns an empty block when the network has no public sites, asking for public sites up to 500', function (): void {
    when('is_multisite')->justReturn(true);
    Functions\expect('get_sites')->once()->with(['public' => 1, 'number' => 500])->andReturn([]);

    expect(ptxt_build_multisite_sitemap_block())->toBe('');
});

it('lists the root site first and the child sites alphabetically, ignoring case', function (): void {
    Network::stub([
        3 => ['1', 'https://zeta.test/'],
        1 => ['1', 'https://root.test/'],
        4 => ['1', 'https://Alpha.test/'],
        2 => ['1', 'https://beta.test/'],
    ]);

    expect(ptxt_build_multisite_sitemap_block())->toBe(
        "# Sitemaps\n"
        ."Sitemap: https://root.test/sitemaps.xml\n"
        ."Sitemap: https://Alpha.test/sitemaps.xml\n"
        ."Sitemap: https://beta.test/sitemaps.xml\n"
        ."Sitemap: https://zeta.test/sitemaps.xml\n"
    );
});

it('leaves out a site that is hidden from search engines, and still restores the blog', function (): void {
    Network::stub([
        1 => ['1', 'https://root.test/'],
        2 => ['0', 'https://hidden.test/'],
        3 => ['1', 'https://shown.test/'],
    ]);
    expect(ptxt_build_multisite_sitemap_block())->toBe(
        "# Sitemaps\nSitemap: https://root.test/sitemaps.xml\nSitemap: https://shown.test/sitemaps.xml\n"
    );
});

it('reads the search-engine setting as a string, so an integer 1 still counts as public', function (): void {
    Network::stub([
        1 => [1, 'https://root.test/'],
        2 => [true, 'https://truthy.test/'],
    ]);

    expect(ptxt_build_multisite_sitemap_block())->toBe("# Sitemaps\nSitemap: https://root.test/sitemaps.xml\nSitemap: https://truthy.test/sitemaps.xml\n");
});

it('switches to each site, reads its home URL by blog id, and trims trailing slashes', function (): void {
    Network::stub([
        1 => ['1', 'https://root.test///'],
        5 => ['1', 'https://child.test/path/'],
    ]);
    expect(ptxt_build_multisite_sitemap_block())->toBe(
        "# Sitemaps\nSitemap: https://root.test/sitemaps.xml\nSitemap: https://child.test/path/sitemaps.xml\n"
    );
});

it('treats the main site id and the blog id as integers when comparing them', function (): void {
    Network::stub([
        7 => ['1', 'https://main.test/'],
        8 => ['1', 'https://other.test/'],
    ], '7');
    expect(ptxt_build_multisite_sitemap_block())->toBe(
        "# Sitemaps\nSitemap: https://main.test/sitemaps.xml\nSitemap: https://other.test/sitemaps.xml\n"
    );
});

it('writes only the heading when no listed site has search visibility on', function (): void {
    Network::stub([
        1 => ['0', 'https://root.test/'],
    ]);

    expect(ptxt_build_multisite_sitemap_block())->toBe("# Sitemaps\n");
});

it('lists child sites with no main site among them', function (): void {
    Network::stub([
        2 => ['1', 'https://b.test/'],
        3 => ['1', 'https://a.test/'],
    ]);

    expect(ptxt_build_multisite_sitemap_block())->toBe(
        "# Sitemaps\nSitemap: https://a.test/sitemaps.xml\nSitemap: https://b.test/sitemaps.xml\n"
    );
});

it('guarantees exactly one leading slash on the sitemap path', function (string $given, string $expected): void {
    Network::stub([1 => ['1', 'https://root.test/']]);

    expect(ptxt_build_multisite_sitemap_block($given))->toBe("# Sitemaps\nSitemap: https://root.test{$expected}\n");
})->with([
    'no slash' => ['sitemap.xml', '/sitemap.xml'],
    'one slash' => ['/sitemap.xml', '/sitemap.xml'],
    'several slashes' => ['///sitemap.xml', '/sitemap.xml'],
    'nested' => ['maps/all.xml', '/maps/all.xml'],
]);
