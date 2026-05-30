<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Page;
use Anypost\Response;

/**
 * Operations on the `/suppressions` endpoints. Entries key on `(email, topic)`.
 */
final class Suppressions extends AbstractResource
{
    /**
     * List the team's suppressions, newest-first. Expired rows are filtered out.
     *
     * Filter with `email_contains`, `topic`, `reason`, and `origin`.
     *
     * @param array{limit?: int, after?: string, email_contains?: string, topic?: string, reason?: string, origin?: string} $params
     */
    public function list(array $params = []): Page
    {
        return $this->fetchPage($params);
    }

    /**
     * Add a manual suppression.
     *
     * Defaults to topic `*` (every topic). Throws `validation_error` if an
     * active entry for the same `(email, topic)` exists.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): Response
    {
        return $this->requestObject('POST', '/suppressions', ['body' => $params]);
    }

    /**
     * Retrieve the suppression for an `(email, topic)` pair.
     *
     * Use `*` as the topic for the global row. Throws `not_found` if the pair
     * isn't suppressed.
     */
    public function get(string $email, string $topic): Response
    {
        return $this->requestObject(
            'GET',
            '/suppressions/' . rawurlencode($email) . '/' . rawurlencode($topic),
        );
    }

    /**
     * Remove the single `(email, topic)` row. Other topics are untouched.
     */
    public function delete(string $email, string $topic): void
    {
        $this->http->request(
            'DELETE',
            '/suppressions/' . rawurlencode($email) . '/' . rawurlencode($topic),
        );
    }

    /**
     * List every suppression on file for an address, across all topics.
     *
     * Throws `not_found` if the address has no active suppressions.
     *
     * @return list<Response>
     */
    public function listForEmail(string $email): array
    {
        $response = $this->http->request('GET', '/suppressions/' . rawurlencode($email));
        $data = is_array($response) ? ($response['data'] ?? []) : [];

        /** @var list<Response> $wrapped */
        $wrapped = array_map([Response::class, 'wrap'], is_array($data) ? $data : []);

        return $wrapped;
    }

    /**
     * Remove an address from the suppression list across every topic.
     */
    public function deleteForEmail(string $email): void
    {
        $this->http->request('DELETE', '/suppressions/' . rawurlencode($email));
    }

    /**
     * @param array{limit?: int, after?: string, email_contains?: string, topic?: string, reason?: string, origin?: string} $params
     */
    private function fetchPage(array $params): Page
    {
        $response = $this->http->request('GET', '/suppressions', [
            'query' => [
                'limit' => $params['limit'] ?? null,
                'after' => $params['after'] ?? null,
                'email_contains' => $params['email_contains'] ?? null,
                'topic' => $params['topic'] ?? null,
                'reason' => $params['reason'] ?? null,
                'origin' => $params['origin'] ?? null,
            ],
        ]);

        return new Page(
            is_array($response) ? $response : [],
            fn (string $after): Page => $this->fetchPage([...$params, 'after' => $after]),
        );
    }
}
