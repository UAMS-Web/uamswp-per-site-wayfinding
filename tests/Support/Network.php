<?php

declare(strict_types=1);

namespace Tests\Support;

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * A stubbed multisite network for the sitemap block.
 */
final class Network
{
    /**
     * Stub the network functions for a list of sites.
     *
     * Each site is checked for the calls the plugin must make: one switch and one
     * restore per site, and one home URL read per site that is public.
     *
     * @param  array<int, array{0: scalar, 1: string}>  $sites  Blog id to [blog_public option, home URL].
     * @param  int|string  $mainSiteId
     */
    public static function stub(array $sites, $mainSiteId = 1): void
    {
        when('is_multisite')->justReturn(true);
        when('get_main_site_id')->justReturn($mainSiteId);

        $objects = [];
        foreach (array_keys($sites) as $blogId) {
            $objects[] = (object) ['blog_id' => (string) $blogId];
        }

        expect('get_sites')->once()->with(['public' => 1, 'number' => 500])->andReturn($objects);

        $current = 0;
        expect('switch_to_blog')->times(count($sites))->andReturnUsing(function ($id) use (&$current): void {
            $current = (int) $id;
        });
        expect('get_option')->times(count($sites))->with('blog_public', '')->andReturnUsing(
            function () use (&$current, $sites) {
                return $sites[$current][0];
            }
        );
        expect('restore_current_blog')->times(count($sites));

        foreach ($sites as $blogId => [$public, $home]) {
            if ((string) $public === '1') {
                expect('get_home_url')->once()->with($blogId)->andReturn($home);
            }
        }
    }
}
