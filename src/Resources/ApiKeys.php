<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Page;
use Anypost\Response;

/**
 * Operations on the `/api-keys` endpoints.
 */
final class ApiKeys extends AbstractResource
{
    /**
     * List the team's API keys, newest-first.
     *
     * @param array{limit?: int, after?: string} $params
     */
    public function list(array $params = []): Page
    {
        return $this->fetchPage($params);
    }

    /**
     * Issue a new API key.
     *
     * The plaintext secret is returned only in this response, as `key` — store
     * it securely; it cannot be retrieved later.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): Response
    {
        return $this->requestObject('POST', '/api-keys', ['body' => $params]);
    }

    /**
     * Retrieve a single API key's metadata. The secret is never returned.
     */
    public function get(string $id): Response
    {
        return $this->requestObject('GET', '/api-keys/' . rawurlencode($id));
    }

    /**
     * Update a key's name, permissions, and restrictions.
     *
     * The secret is not rotated here. Changes may take up to 5 minutes to
     * propagate through the gateway cache.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): Response
    {
        return $this->requestObject('PATCH', '/api-keys/' . rawurlencode($id), ['body' => $params]);
    }

    /**
     * Delete a key. It may keep authenticating for up to 5 minutes (gateway cache).
     */
    public function delete(string $id): void
    {
        $this->http->request('DELETE', '/api-keys/' . rawurlencode($id));
    }

    /**
     * @param array{limit?: int, after?: string} $params
     */
    private function fetchPage(array $params): Page
    {
        $response = $this->http->request('GET', '/api-keys', [
            'query' => [
                'limit' => $params['limit'] ?? null,
                'after' => $params['after'] ?? null,
            ],
        ]);

        return new Page(
            is_array($response) ? $response : [],
            fn (string $after): Page => $this->fetchPage([...$params, 'after' => $after]),
        );
    }
}
