<?php

/**
 * The init rewrite rules and the query variables that route /llms.txt and /llms-full.txt.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Tests\Support\PluginHooks;

it('adds a top-priority rewrite rule for each of the two files on init', function (): void {
    Functions\expect('add_rewrite_rule')->once()->with('^llms\.txt$', 'index.php?llms_txt=1', 'top');
    Functions\expect('add_rewrite_rule')->once()->with('^llms-full\.txt$', 'index.php?llms_full_txt=1', 'top');

    PluginHooks::callback('action', 'init')();
});

it('appends the two query variables after the existing ones', function (): void {
    expect(PluginHooks::callback('filter', 'query_vars')(['p', 'page']))
        ->toBe(['p', 'page', 'llms_txt', 'llms_full_txt']);
});

it('adds the same two rules, then flushes without a hard rewrite, on activation', function (): void {
    Functions\expect('add_rewrite_rule')->once()->with('^llms\.txt$', 'index.php?llms_txt=1', 'top');
    Functions\expect('add_rewrite_rule')->once()->with('^llms-full\.txt$', 'index.php?llms_full_txt=1', 'top');
    Functions\expect('flush_rewrite_rules')->once()->with(false);

    PluginHooks::activation()[0]['callback']();
});
