<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Page;

/**
 * Read access to the `/events` stream. List-only — events are not addressable by id.
 */
final class Events extends AbstractResource
{
    /**
     * Page through the team's events, newest-first.
     *
     * The window defaults to the last 24 hours and is clamped to the plan's
     * retention. Filter with `start`, `end`, `event_type`, `recipient`,
     * `email_id`, `message_id`, `domain`, `topic`, `campaign`, `template_id`,
     * and `tags` (a list, matched with hasAny).
     *
     * @param array<string, mixed> $params
     */
    public function list(array $params = []): Page
    {
        return $this->fetchPage($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fetchPage(array $params): Page
    {
        $tags = $params['tags'] ?? null;

        $response = $this->http->request('GET', '/events', [
            'query' => [
                'limit' => $params['limit'] ?? null,
                'after' => $params['after'] ?? null,
                'start' => $params['start'] ?? null,
                'end' => $params['end'] ?? null,
                'event_type' => $params['event_type'] ?? null,
                'recipient' => $params['recipient'] ?? null,
                'email_id' => $params['email_id'] ?? null,
                'message_id' => $params['message_id'] ?? null,
                'domain' => $params['domain'] ?? null,
                'topic' => $params['topic'] ?? null,
                'campaign' => $params['campaign'] ?? null,
                'template_id' => $params['template_id'] ?? null,
                // Sent comma-separated (tags=a,b); the API matches with hasAny.
                'tags' => is_array($tags) && $tags !== [] ? implode(',', $tags) : null,
            ],
        ]);

        return new Page(
            is_array($response) ? $response : [],
            fn (string $after): Page => $this->fetchPage([...$params, 'after' => $after]),
        );
    }
}
