<?php

declare(strict_types=1);

namespace Anypost;

use Closure;
use IteratorAggregate;
use Traversable;

/**
 * One page of a list result.
 *
 * Mirrors the wire envelope (`data`, `has_more`, `next_cursor`) and is
 * iterable: iterating walks every remaining page automatically, re-fetching
 * with `after = next_cursor`.
 *
 *     $page = $client->domains->list();        // one page
 *     foreach ($page->data as $domain) { ... } // just this page
 *
 *     foreach ($client->domains->list() as $domain) { ... } // every domain, all pages
 *
 * @implements IteratorAggregate<int, Response>
 */
final class Page implements IteratorAggregate
{
    /**
     * The items on this page, each wrapped as a {@see Response}.
     *
     * @var list<Response>
     */
    public readonly array $data;

    public readonly bool $hasMore;

    public readonly ?string $nextCursor;

    /** @var Closure(string): Page */
    private readonly Closure $fetchNext;

    /**
     * @param array<string, mixed> $response
     * @param Closure(string): Page $fetchNext
     */
    public function __construct(array $response, Closure $fetchNext)
    {
        $rawData = $response['data'] ?? [];
        /** @var list<Response> $wrapped */
        $wrapped = array_map([Response::class, 'wrap'], is_array($rawData) ? $rawData : []);
        $this->data = $wrapped;
        $this->hasMore = (bool) ($response['has_more'] ?? false);
        $nextCursor = $response['next_cursor'] ?? null;
        $this->nextCursor = is_string($nextCursor) ? $nextCursor : null;
        $this->fetchNext = $fetchNext;
    }

    /**
     * Fetch the next page, or null when there are no more.
     */
    public function getNextPage(): ?Page
    {
        if (! $this->hasMore || $this->nextCursor === null) {
            return null;
        }

        return ($this->fetchNext)($this->nextCursor);
    }

    public function getIterator(): Traversable
    {
        $page = $this;
        while ($page !== null) {
            yield from $page->data;
            $page = $page->getNextPage();
        }
    }
}
