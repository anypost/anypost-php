<?php

declare(strict_types=1);

namespace Anypost\Tests;

use Anypost\Response;

final class PaginationTest extends TestCase
{
    public function test_returns_a_single_page_with_wrapped_items(): void
    {
        $client = $this->client([
            $this->json([
                'data' => [['id' => 'dom_1'], ['id' => 'dom_2']],
                'has_more' => false,
                'next_cursor' => null,
            ]),
        ]);

        $page = $client->domains->list();

        $this->assertCount(2, $page->data);
        $this->assertInstanceOf(Response::class, $page->data[0]);
        $this->assertSame('dom_1', $page->data[0]->id);
        $this->assertFalse($page->hasMore);
        $this->assertNull($page->getNextPage());
    }

    public function test_iterating_walks_every_page(): void
    {
        $client = $this->client([
            $this->json([
                'data' => [['id' => 'dom_1'], ['id' => 'dom_2']],
                'has_more' => true,
                'next_cursor' => 'cursor_2',
            ]),
            $this->json([
                'data' => [['id' => 'dom_3']],
                'has_more' => false,
                'next_cursor' => null,
            ]),
        ]);

        $ids = [];
        foreach ($client->domains->list(['limit' => 2]) as $domain) {
            $ids[] = $domain->id;
        }

        $this->assertSame(['dom_1', 'dom_2', 'dom_3'], $ids);
        $this->assertCount(2, $this->transactions);

        parse_str($this->requestAt(1)->getUri()->getQuery(), $secondQuery);
        $this->assertSame('cursor_2', $secondQuery['after']);
        $this->assertSame('2', $secondQuery['limit']);
    }

    public function test_first_page_query_omits_null_params(): void
    {
        $client = $this->client([
            $this->json(['data' => [], 'has_more' => false, 'next_cursor' => null]),
        ]);

        $client->domains->list();

        $this->assertSame('', $this->lastRequest()->getUri()->getQuery());
    }
}
