<?php

/**
 * The admin_menu and network_admin_menu actions that register the plugin's pages.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Tests\Support\PluginHooks;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

it('adds nothing to a site admin menu for anyone but a super admin', function (): void {
    when('is_super_admin')->justReturn(false);
    expect('add_options_page')->never();

    PluginHooks::callback('action', 'admin_menu')();
});

it('adds the Site Files page under Settings for a super admin', function (): void {
    when('is_super_admin')->justReturn(true);
    expect('add_options_page')->once()->with(
        'Site Files',
        'Site Files',
        'manage_network_options',
        'site-txt-files',
        'ptxt_render_settings_page',
    );

    PluginHooks::callback('action', 'admin_menu')();
});

it('adds the tracking and sitemap pages under Network Settings', function (): void {
    expect('add_submenu_page')->once()->with(
        'settings.php',
        'Site Files Tracking',
        'Site Files Tracking',
        'manage_network_options',
        'site-txt-files-tracking',
        'ptxt_render_network_tracking_page',
    );
    expect('add_submenu_page')->once()->with(
        'settings.php',
        'Sitemap Block Generator',
        'Sitemap Block Generator',
        'manage_network_options',
        'site-txt-files-sitemaps',
        'ptxt_render_sitemap_generator_page',
    );

    PluginHooks::callback('action', 'network_admin_menu')();
});
