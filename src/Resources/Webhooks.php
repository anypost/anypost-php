<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Page;
use Anypost\Response;

/**
 * Operations on the `/webhooks` endpoints.
 */
final class Webhooks extends AbstractResource
{
    /**
     * List the team's webhooks, newest-first.
     *
     * @param array{limit?: int, after?: string} $params
     */
    public function list(array $params = []): Page
    {
        return $this->fetchPage($params);
    }

    /**
     * Create a webhook.
     *
     * The full `signing_secret` is on the response to this call only — store it
     * now to verify future deliveries; later reads return only the prefix.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): Response
    {
        return $this->requestObject('POST', '/webhooks', ['body' => $params]);
    }

    /**
     * Retrieve a webhook. The signing secret is never returned — only its prefix.
     */
    public function get(string $id): Response
    {
        return $this->requestObject('GET', '/webhooks/' . rawurlencode($id));
    }

    /**
     * Update a webhook's name, URL, subscribed events, and status.
     *
     * This does not rotate the signing secret — use {@see rotateSecret()}.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): Response
    {
        return $this->requestObject('PATCH', '/webhooks/' . rawurlencode($id), ['body' => $params]);
    }

    /**
     * Permanently delete a webhook.
     */
    public function delete(string $id): void
    {
        $this->http->request('DELETE', '/webhooks/' . rawurlencode($id));
    }

    /**
     * Send one synthetic `webhook.test` event and report the outcome.
     *
     * One-shot, not retried, and absent from delivery history. Returns the
     * result even when the endpoint fails — read `delivered` and `status_code`.
     * Works on a `disabled` webhook too.
     */
    public function test(string $id): Response
    {
        return $this->requestObject('POST', '/webhooks/' . rawurlencode($id) . '/test');
    }

    /**
     * Rotate the signing secret.
     *
     * The new secret is on this response only. The previous secret stays valid
     * for a 24h grace window, during which deliveries carry a `v1=` component
     * for each. Rotating again before the window ends throws
     * `webhook_rotation_in_progress` (a {@see \Anypost\Exceptions\ConflictException}).
     */
    public function rotateSecret(string $id): Response
    {
        return $this->requestObject('POST', '/webhooks/' . rawurlencode($id) . '/rotate-secret');
    }

    /**
     * @param array{limit?: int, after?: string} $params
     */
    private function fetchPage(array $params): Page
    {
        $response = $this->http->request('GET', '/webhooks', [
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
