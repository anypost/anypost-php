<?php

declare(strict_types=1);

namespace Anypost;

use Anypost\Resources\ApiKeys;
use Anypost\Resources\Domains;
use Anypost\Resources\Email;
use Anypost\Resources\Events;
use Anypost\Resources\Identity;
use Anypost\Resources\Suppressions;
use Anypost\Resources\Templates;
use Anypost\Resources\Webhooks;
use Anypost\Transport\HttpClient;
use Closure;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;

/**
 * Client for the Anypost email API.
 *
 *     use Anypost\Anypost;
 *
 *     $client = new Anypost('ap_your_api_key'); // or new Anypost() to read ANYPOST_API_KEY
 *     $email = $client->email->send([
 *         'from' => 'Acme <you@yourdomain.com>',
 *         'to' => ['someone@example.com'],
 *         'subject' => 'Hello',
 *         'html' => '<p>It worked.</p>',
 *     ]);
 *     echo $email->id;
 */
final class Anypost
{
    public const DEFAULT_BASE_URL = 'https://api.anypost.com/v1';

    public const DEFAULT_TIMEOUT = 30.0;

    public const DEFAULT_MAX_RETRIES = 2;

    /** Send operations (`/email`, `/email/batch`). */
    public readonly Email $email;

    /** Sending-domain operations (`/domains`). */
    public readonly Domains $domains;

    /** API-key operations (`/api-keys`). */
    public readonly ApiKeys $apiKeys;

    /** Template operations (`/templates`), including the draft/publish flow. */
    public readonly Templates $templates;

    /** Suppression-list operations (`/suppressions`). */
    public readonly Suppressions $suppressions;

    /** Webhook operations (`/webhooks`), including test and secret rotation. */
    public readonly Webhooks $webhooks;

    /** Read access to the event stream (`/events`). */
    public readonly Events $events;

    private readonly Identity $identity;

    /**
     * @param string|null $apiKey Defaults to the `ANYPOST_API_KEY` environment variable.
     * @param array{
     *     base_url?: string,
     *     timeout?: float,
     *     max_retries?: int,
     *     headers?: array<string, string>,
     *     http_client?: ClientInterface,
     *     sleeper?: Closure(float): void,
     *     jitter?: Closure(): float,
     * } $options
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $key = $apiKey !== null && $apiKey !== ''
            ? $apiKey
            : (getenv('ANYPOST_API_KEY') ?: null);
        if ($key === null) {
            throw new InvalidArgumentException(
                'An Anypost API key is required. Pass it to the constructor or set ANYPOST_API_KEY.',
            );
        }

        $baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');

        $http = new HttpClient(
            apiKey: $key,
            baseUrl: $baseUrl,
            timeout: $options['timeout'] ?? self::DEFAULT_TIMEOUT,
            maxRetries: $options['max_retries'] ?? self::DEFAULT_MAX_RETRIES,
            defaultHeaders: $options['headers'] ?? [],
            guzzle: $options['http_client'] ?? null,
            sleeper: $options['sleeper'] ?? null,
            jitter: $options['jitter'] ?? null,
        );

        $this->email = new Email($http);
        $this->domains = new Domains($http);
        $this->apiKeys = new ApiKeys($http);
        $this->templates = new Templates($http);
        $this->suppressions = new Suppressions($http);
        $this->webhooks = new Webhooks($http);
        $this->events = new Events($http);
        $this->identity = new Identity($http);
    }

    /**
     * Identify the team and permission level behind the current API key.
     */
    public function whoami(): Response
    {
        return $this->identity->whoami();
    }
}
