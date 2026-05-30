<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Response;
use Anypost\Transport\HttpClient;

/**
 * Shared base for the API resources: holds the transport and wraps decoded
 * object responses as {@see Response} instances.
 *
 * @internal
 */
abstract class AbstractResource
{
    public function __construct(protected readonly HttpClient $http)
    {
    }

    /**
     * Perform a request and wrap the decoded object body as a {@see Response}.
     *
     * @param array<string, mixed> $options
     */
    protected function requestObject(string $method, string $path, array $options = []): Response
    {
        $decoded = $this->http->request($method, $path, $options);

        return new Response(is_array($decoded) ? $decoded : []);
    }
}
