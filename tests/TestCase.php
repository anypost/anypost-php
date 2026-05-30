<?php

declare(strict_types=1);

namespace Anypost\Tests;

use Anypost\Anypost;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;

abstract class TestCase extends BaseTestCase
{
    /**
     * Captured Guzzle transactions: each is ['request' => ..., 'response' => ...].
     *
     * @var list<array<string, mixed>>
     */
    protected array $transactions = [];

    /**
     * Build a client whose transport replays the given queued responses.
     *
     * @param list<mixed> $responses Queued GuzzleResponse|Throwable items.
     * @param array<string, mixed> $options Extra Anypost client options.
     */
    protected function client(array $responses, array $options = [], ?string $apiKey = 'ap_test'): Anypost
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $this->transactions = [];
        $stack->push(Middleware::history($this->transactions));

        $guzzle = new GuzzleClient(['handler' => $stack]);

        return new Anypost($apiKey, array_merge([
            'http_client' => $guzzle,
            'sleeper' => static fn (float $seconds) => null,
            'jitter' => static fn (): float => 1.0,
        ], $options));
    }

    protected function lastRequest(): RequestInterface
    {
        $transaction = end($this->transactions);

        return $transaction['request'];
    }

    protected function requestAt(int $index): RequestInterface
    {
        return $this->transactions[$index]['request'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bodyOf(RequestInterface $request): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $request->getBody(), true) ?: [];

        return $decoded;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string|list<string>> $headers
     */
    protected function json(array $body, int $status = 200, array $headers = []): GuzzleResponse
    {
        return new GuzzleResponse(
            $status,
            array_merge(['Content-Type' => 'application/json'], $headers),
            (string) json_encode($body),
        );
    }
}
