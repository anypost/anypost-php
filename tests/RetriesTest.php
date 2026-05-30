<?php

declare(strict_types=1);

namespace Anypost\Tests;

use Anypost\Exceptions\ApiConnectionException;
use Anypost\Exceptions\ValidationException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

final class RetriesTest extends TestCase
{
    public function test_retries_on_429_then_succeeds(): void
    {
        $client = $this->client([
            $this->json(['error' => ['type' => 'rate_limit_exceeded']], 429),
            $this->json(['id' => 'email_ok', 'created_at' => 'now'], 202),
        ]);

        $email = $client->email->send(['from' => 'a@b.com', 'to' => ['c@d.com'], 'text' => 'x']);

        $this->assertSame('email_ok', $email->id);
        $this->assertCount(2, $this->transactions);
    }

    public function test_reuses_one_idempotency_key_across_send_retries(): void
    {
        $client = $this->client([
            $this->json(['error' => ['type' => 'rate_limit_exceeded']], 503),
            $this->json(['id' => 'email_ok', 'created_at' => 'now'], 202),
        ]);

        $client->email->send(['from' => 'a@b.com', 'to' => ['c@d.com'], 'text' => 'x']);

        $first = $this->requestAt(0)->getHeaderLine('Idempotency-Key');
        $second = $this->requestAt(1)->getHeaderLine('Idempotency-Key');
        $this->assertNotSame('', $first);
        $this->assertSame($first, $second);
    }

    public function test_retries_on_a_network_error_then_gives_up(): void
    {
        $client = $this->client([
            new ConnectException('boom', new Request('GET', 'whoami')),
            new ConnectException('boom', new Request('GET', 'whoami')),
            new ConnectException('boom', new Request('GET', 'whoami')),
        ]);

        $this->expectException(ApiConnectionException::class);
        $client->whoami();
    }

    public function test_recovers_from_a_transient_network_error(): void
    {
        $client = $this->client([
            new ConnectException('boom', new Request('GET', 'whoami')),
            $this->json(['team' => null]),
        ]);

        $result = $client->whoami();
        $this->assertNull($result->team);
        $this->assertCount(2, $this->transactions);
    }

    public function test_does_not_retry_on_a_4xx(): void
    {
        $client = $this->client([
            $this->json(['error' => ['type' => 'validation_error', 'message' => 'bad']], 422),
        ]);

        try {
            $client->email->send(['from' => 'a@b.com', 'to' => [], 'text' => 'x']);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException) {
            $this->assertCount(1, $this->transactions);
        }
    }

    public function test_respects_max_retries_zero(): void
    {
        $client = $this->client(
            [$this->json(['error' => ['type' => 'rate_limit_exceeded']], 429)],
            ['max_retries' => 0],
        );

        try {
            $client->whoami();
            $this->fail('Expected a RateLimitException.');
        } catch (\Anypost\Exceptions\RateLimitException) {
            $this->assertCount(1, $this->transactions);
        }
    }
}
