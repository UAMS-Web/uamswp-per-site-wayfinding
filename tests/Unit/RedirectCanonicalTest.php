<?php

/**
 * The redirect_canonical filter that stops WordPress adding a slash to /llms.txt and /llms-full.txt.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

use Tests\Support\RedirectCanonical;

it('stops the redirect for the two files on a root site', function (string $path): void {
    expect(RedirectCanonical::run('https://example.test'.$path))->toBeFalse();
})->with([
    'llms.txt' => ['/llms.txt'],
    'llms-full.txt' => ['/llms-full.txt'],
]);

it('lets the redirect through for any other path', function (string $path): void {
    expect(RedirectCanonical::run('https://example.test'.$path))->toBe('https://example.test/redirected/');
})->with([
    'a page' => ['/about'],
    'a longer name' => ['/llms.txt.bak'],
    'a similar name' => ['/llms-full.txts'],
    'the file in a folder' => ['/docs/llms.txt'],
    'the folder root' => ['/'],
]);

it('lets the redirect through when the request has no path', function (string $requested): void {
    expect(RedirectCanonical::run($requested))->toBe('https://example.test/redirected/');
})->with([
    'host only' => ['https://example.test'],
    'empty' => [''],
]);

it('strips the subsite folder before matching on a subdirectory network', function (string $home): void {
    expect(RedirectCanonical::run('https://example.test/pharmacy/llms.txt', $home))->toBeFalse()
        ->and(RedirectCanonical::run('https://example.test/pharmacy/llms-full.txt', $home))->toBeFalse();
})->with([
    'home URL with a trailing slash' => ['https://example.test/pharmacy/'],
    'home URL without one' => ['https://example.test/pharmacy'],
]);

it('does not strip a folder that is not the subsite folder', function (): void {
    expect(RedirectCanonical::run('https://example.test/other/llms.txt', 'https://example.test/pharmacy/'))
        ->toBe('https://example.test/redirected/');
});

it('treats the bare subsite folder as the site root', function (): void {
    expect(RedirectCanonical::run('https://example.test/pharmacy/', 'https://example.test/pharmacy/'))
        ->toBe('https://example.test/redirected/');
});

it('does not match a folder that only starts like the subsite folder', function (): void {
    expect(RedirectCanonical::run('https://example.test/pharmacyx/llms.txt', 'https://example.test/pharmacy/'))
        ->toBe('https://example.test/redirected/');
});

it('still matches the root path when the home URL has no path', function (): void {
    expect(RedirectCanonical::run('https://example.test/llms.txt', 'https://example.test'))->toBeFalse();
});
