<?php

/**
 * ptxt_physical_robots_exists(), which detects a robots.txt file that shadows the virtual one.
 *
 * ABSPATH is a directory the bootstrap picked, so a test makes the file itself.
 *
 * @see uamswp-per-site-wayfinding.php
 */

declare(strict_types=1);

afterEach(function (): void {
    $file = ABSPATH.'robots.txt';
    if (is_file($file)) {
        unlink($file);
    }
});

it('reports a robots.txt file at the web root', function (): void {
    if (! is_dir(ABSPATH)) {
        mkdir(ABSPATH, 0777, true);
    }

    file_put_contents(ABSPATH.'robots.txt', "User-agent: *\n");

    expect(ptxt_physical_robots_exists())->toBeTrue();
});

it('reports no file when the web root holds no robots.txt', function (): void {
    if (! is_dir(ABSPATH)) {
        mkdir(ABSPATH, 0777, true);
    }

    expect(ptxt_physical_robots_exists())->toBeFalse();
});

it('does not take a robots.txt in another directory for the web root one', function (): void {
    $elsewhere = dirname(rtrim(ABSPATH, '/')).'/robots.txt';
    $created = ! file_exists($elsewhere);
    if ($created) {
        file_put_contents($elsewhere, "User-agent: *\n");
    }

    try {
        expect(ptxt_physical_robots_exists())->toBeFalse();
    } finally {
        if ($created) {
            unlink($elsewhere);
        }
    }
});
