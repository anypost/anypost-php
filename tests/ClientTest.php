<?php

declare(strict_types=1);

namespace Anypost\Tests;

use Anypost\Anypost;
use Anypost\Version;
use InvalidArgumentException;

final class ClientTest extends TestCase
{
    public function test_constructs_with_an_explicit_key(): void
    {
        $client = $this->client([$this->json(['ok' => true])]);
        $this->assertInstanceOf(Anypost::class, $client);
    }

    public function test_falls_back_to_the_environment_variable(): void
    {
        putenv('ANYPOST_API_KEY=ap_from_env');
        try {
            $client = $this->client([$this->json(['team' => null])], apiKey: null);
            $client->whoami();
            $this->assertSame('Bearer ap_from_env', $this->lastRequest()->getHeaderLine('Authorization'));
        } finally {
            putenv('ANYPOST_API_KEY');
        }
    }

    public function test_throws_without_a_key(): void
    {
        putenv('ANYPOST_API_KEY');
        $this->expectException(InvalidArgumentException::class);
        $this->client([], apiKey: null);
    }

    public function test_sends_the_expected_default_headers(): void
    {
        $client = $this->client([$this->json(['id' => 'email_1', 'created_at' => 'now'])]);
        $client->email->send([
            'from' => 'a@b.com',
            'to' => ['c@d.com'],
            'subject' => 'Hi',
            'text' => 'Yo',
        ]);

        $request = $this->lastRequest();
        $this->assertSame('Bearer ap_test', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('anypost-php/' . Version::VERSION, $request->getHeaderLine('User-Agent'));
    }

    public function test_merges_custom_default_headers(): void
    {
        $client = $this->client(
            [$this->json(['team' => null])],
            ['headers' => ['X-Trace' => 'abc123']],
        );
        $client->whoami();
        $this->assertSame('abc123', $this->lastRequest()->getHeaderLine('X-Trace'));
    }

    public function test_trims_a_trailing_slash_from_the_base_url(): void
    {
        $client = $this->client(
            [$this->json(['team' => null])],
            ['base_url' => 'https://api.example.test/v1/'],
        );
        $client->whoami();
        $this->assertSame(
            'https://api.example.test/v1/whoami',
            (string) $this->lastRequest()->getUri(),
        );
    }
}
