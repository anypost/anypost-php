<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Page;
use Anypost\Response;

/**
 * Operations on the `/domains` endpoints.
 */
final class Domains extends AbstractResource
{
    /**
     * List the team's domains, newest-first.
     *
     * Returns one {@see Page}; iterate it to walk every page, or follow
     * `$page->nextCursor` yourself.
     *
     * @param array{limit?: int, after?: string} $params
     */
    public function list(array $params = []): Page
    {
        return $this->fetchPage($params);
    }

    /**
     * Add a sending domain. The returned domain is `pending` until verified.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): Response
    {
        return $this->requestObject('POST', '/domains', ['body' => $params]);
    }

    /**
     * Retrieve a single domain by id.
     */
    public function get(string $id): Response
    {
        return $this->requestObject('GET', '/domains/' . rawurlencode($id));
    }

    /**
     * Update a domain's tracking configuration. The domain `name` is immutable.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): Response
    {
        return $this->requestObject('PATCH', '/domains/' . rawurlencode($id), ['body' => $params]);
    }

    /**
     * Permanently delete a domain and its DKIM keys.
     */
    public function delete(string $id): void
    {
        $this->http->request('DELETE', '/domains/' . rawurlencode($id));
    }

    /**
     * Trigger a verification check.
     *
     * Always returns the current domain — read `status` and
     * `verification_failure` to learn the outcome; a still-`pending` domain does
     * not throw. Safe to poll while DNS propagates.
     */
    public function verify(string $id): Response
    {
        return $this->requestObject('POST', '/domains/' . rawurlencode($id) . '/verify');
    }

    /**
     * @param array{limit?: int, after?: string} $params
     */
    private function fetchPage(array $params): Page
    {
        $response = $this->http->request('GET', '/domains', [
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
