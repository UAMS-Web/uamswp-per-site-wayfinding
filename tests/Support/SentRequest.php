<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The request a stubbed wp_remote_post() received, typed so a test can read it.
 */
final class SentRequest
{
    public ?string $endpoint = null;

    /**
     * @var array<mixed>|null
     */
    public ?array $args = null;

    /**
     * One argument of the request, or null when it is absent.
     */
    public function arg(string $key): mixed
    {
        return $this->args[$key] ?? null;
    }

    /**
     * The JSON body of the request, or an empty string when there is none or it is not text.
     */
    public function body(): string
    {
        $body = $this->arg('body');

        return is_string($body) ? $body : '';
    }
}
