<?php

/**
 * The template_redirect action that serves /llms.txt and /llms-full.txt.
 *
 * Each branch ends in exit, which a test cannot survive, so ptxt_track_request()
 * (the last call before it) is replaced with one that throws ExitReached.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Tests\Support\ExitReached;
use Tests\Support\PluginHooks;
use Tests\Support\Render;

use function Brain\Monkey\Functions\when;

it('serves the saved llms.txt as plain text, tracks it, and ends the request', function (): void {
    when('get_query_var')->alias(fn (string $name): string => $name === 'llms_txt' ? '1' : '');
    Functions\expect('header')->once()->with('Content-Type: text/plain; charset=utf-8');
    when('home_url')->justReturn('https://example.test');
    Functions\expect('get_option')->once()
        ->with('custom_llms_txt', "# llms.txt not yet configured for https://example.test\n")->andReturn("# Site\n");
    Functions\expect('ptxt_track_request')->once()->with('llms.txt')->andThrow(new ExitReached);

    $result = Render::captureUntilExit(PluginHooks::callback('action', 'template_redirect'));

    expect($result)->toBe(['output' => "# Site\n", 'reachedExit' => true]);
});

it('serves the saved llms-full.txt as plain text, tracks it, and ends the request', function (): void {
    when('get_query_var')->alias(fn (string $name): string => $name === 'llms_full_txt' ? '1' : '');
    Functions\expect('header')->once()->with('Content-Type: text/plain; charset=utf-8');
    when('home_url')->justReturn('https://example.test');
    Functions\expect('get_option')->once()
        ->with('custom_llms_full_txt', "# llms-full.txt not yet configured for https://example.test\n")->andReturn("# Full\n");
    Functions\expect('ptxt_track_request')->once()->with('llms-full.txt')->andThrow(new ExitReached);

    $result = Render::captureUntilExit(PluginHooks::callback('action', 'template_redirect'));

    expect($result)->toBe(['output' => "# Full\n", 'reachedExit' => true]);
});

it('falls back to a placeholder naming the site when llms.txt was never saved', function (): void {
    when('get_query_var')->alias(fn (string $name): string => $name === 'llms_txt' ? '1' : '');
    when('header')->justReturn();
    when('home_url')->justReturn('https://example.test');
    when('get_option')->alias(fn (string $name, $default = false) => $default);
    when('ptxt_track_request')->alias(function (): void {
        throw new ExitReached;
    });

    $result = Render::captureUntilExit(PluginHooks::callback('action', 'template_redirect'));

    expect($result['output'])->toBe("# llms.txt not yet configured for https://example.test\n");
});

it('falls back to a placeholder naming the site when llms-full.txt was never saved', function (): void {
    when('get_query_var')->alias(fn (string $name): string => $name === 'llms_full_txt' ? '1' : '');
    when('header')->justReturn();
    when('home_url')->justReturn('https://example.test');
    when('get_option')->alias(fn (string $name, $default = false) => $default);
    when('ptxt_track_request')->alias(function (): void {
        throw new ExitReached;
    });

    $result = Render::captureUntilExit(PluginHooks::callback('action', 'template_redirect'));

    expect($result['output'])->toBe("# llms-full.txt not yet configured for https://example.test\n");
});

it('serves llms.txt when both variables are set', function (): void {
    when('get_query_var')->justReturn('1');
    when('header')->justReturn();
    when('home_url')->justReturn('https://example.test');
    when('get_option')->justReturn('first');
    Functions\expect('ptxt_track_request')->once()->with('llms.txt')->andThrow(new ExitReached);

    expect(Render::captureUntilExit(PluginHooks::callback('action', 'template_redirect'))['output'])->toBe('first');
});

it('does nothing on any other request', function (): void {
    when('get_query_var')->justReturn('');
    Functions\expect('header')->never();
    Functions\expect('get_option')->never();
    Functions\expect('ptxt_track_request')->never();

    expect(Render::captureUntilExit(PluginHooks::callback('action', 'template_redirect')))
        ->toBe(['output' => '', 'reachedExit' => false]);
});
