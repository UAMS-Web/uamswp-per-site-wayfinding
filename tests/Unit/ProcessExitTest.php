<?php

/**
 * The three `exit;` statements in the plugin, run in a separate PHP process.
 *
 * A test process cannot survive an `exit;`, so the other tests stop short of it
 * with a stand-in. This file runs the real statement and reads the output of the
 * process that ran it. Each ending scenario has a control that differs only in
 * the condition under test and must carry on to the final marker.
 *
 * @see tests/Fixtures/exit-probe.php
 */

declare(strict_types=1);

use Tests\Support\ExitProbe;

it('ends the process, printing nothing, when the plugin file is loaded outside WordPress', function (): void {
    expect(ExitProbe::run('guard-without-abspath'))->toBe(['output' => '', 'status' => 0]);
});

it('carries on past the guard when ABSPATH is defined', function (): void {
    expect(ExitProbe::run('guard-with-abspath'))->toBe(['output' => 'AFTER', 'status' => 0]);
});

it('ends the request after serving llms.txt', function (): void {
    expect(ExitProbe::run('llms'))->toBe([
        'output' => "# llms.txt not yet configured for https://probe.test\n",
        'status' => 0,
    ]);
});

it('ends the request after serving llms-full.txt', function (): void {
    expect(ExitProbe::run('llms-full'))->toBe([
        'output' => "# llms-full.txt not yet configured for https://probe.test\n",
        'status' => 0,
    ]);
});

it('does not end a request for any other path', function (): void {
    expect(ExitProbe::run('neither'))->toBe(['output' => 'AFTER', 'status' => 0]);
});
