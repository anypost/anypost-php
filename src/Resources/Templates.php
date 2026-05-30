<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Page;
use Anypost\Response;

/**
 * Operations on the `/templates` endpoints, including the draft/publish flow.
 */
final class Templates extends AbstractResource
{
    /**
     * List the team's templates, newest-first.
     *
     * @param array{limit?: int, after?: string} $params
     */
    public function list(array $params = []): Page
    {
        return $this->fetchPage($params);
    }

    /**
     * Create a template. It starts unpublished — publish it before sending.
     *
     * @param array<string, mixed> $params
     */
    public function create(array $params): Response
    {
        return $this->requestObject('POST', '/templates', ['body' => $params]);
    }

    /**
     * Retrieve a template, including its published content.
     */
    public function get(string $id): Response
    {
        return $this->requestObject('GET', '/templates/' . rawurlencode($id));
    }

    /**
     * Update a template's `name`. Body content lives on the draft.
     *
     * @param array<string, mixed> $params
     */
    public function update(string $id, array $params): Response
    {
        return $this->requestObject('PATCH', '/templates/' . rawurlencode($id), ['body' => $params]);
    }

    /**
     * Permanently delete a template.
     */
    public function delete(string $id): void
    {
        $this->http->request('DELETE', '/templates/' . rawurlencode($id));
    }

    /**
     * Copy a template.
     *
     * The copy starts unpublished with a draft seeded from the source's current
     * editable content, and must be published before sending.
     *
     * @param array<string, mixed> $params
     */
    public function duplicate(string $id, array $params = []): Response
    {
        return $this->requestObject('POST', '/templates/' . rawurlencode($id) . '/duplicate', [
            'body' => $params === [] ? null : $params,
        ]);
    }

    /**
     * Retrieve the template's unpublished draft. Throws `not_found` if none exists.
     */
    public function getDraft(string $id): Response
    {
        return $this->requestObject('GET', '/templates/' . rawurlencode($id) . '/draft');
    }

    /**
     * Create or update the template's draft. Idempotent upsert; published content untouched.
     *
     * @param array<string, mixed> $params
     */
    public function updateDraft(string $id, array $params): Response
    {
        return $this->requestObject('PATCH', '/templates/' . rawurlencode($id) . '/draft', ['body' => $params]);
    }

    /**
     * Discard the template's draft without touching published content.
     */
    public function deleteDraft(string $id): void
    {
        $this->http->request('DELETE', '/templates/' . rawurlencode($id) . '/draft');
    }

    /**
     * Promote the draft into the published slot, consuming the draft.
     */
    public function publish(string $id): Response
    {
        return $this->requestObject('POST', '/templates/' . rawurlencode($id) . '/publish');
    }

    /**
     * @param array{limit?: int, after?: string} $params
     */
    private function fetchPage(array $params): Page
    {
        $response = $this->http->request('GET', '/templates', [
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
