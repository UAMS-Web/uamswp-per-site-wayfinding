<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Thrown by a stand-in for the call that sits just before `exit;` in the plugin,
 * so a test can stop where the plugin would end the request.
 */
final class ExitReached extends RuntimeException {}
