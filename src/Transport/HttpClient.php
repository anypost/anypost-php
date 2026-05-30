<?php

declare(strict_types=1);

namespace Anypost\Transport;

use Anypost\Exceptions\ApiConnectionException;
use Anypost\Exceptions\ErrorFactory;
use Anypost\Version;
use Closure;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

/**
 * Owns a Guzzle client and implements the request loop: header assembly,
 * retries with full-jitter backoff, idempotency keys, and error mapping.
 *
 * @internal
 */
final class HttpClient
{
    /** @var list<int> */
    private const RETRYABLE_STATUS = [429, 502, 503];

    private const MAX_BACKOFF_SECONDS = 8.0;

    private const BASE_BACKOFF_SECONDS = 0.5;

    private readonly ClientInterface $guzzle;

    /** @var Closure(float): void */
    private readonly Closure $sleeper;

    /** @var Closure(): float */
    private readonly Closure $jitter;

    /**
     * @param array<string, string> $defaultHeaders
     * @param Closure(float): void|null $sleeper Override the sleep between retries (tests).
     * @param Closure(): float|null     $jitter  Override the [0,1) jitter factor (tests).
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly float $timeout,
        private readonly int $maxRetries,
        private readonly array $defaultHeaders = [],
        ?ClientInterface $guzzle = null,
        ?Closure $sleeper = null,
        ?Closure $jitter = null,
    ) {
        $this->guzzle = $guzzle ?? new GuzzleClient();
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) ($seconds * 1_000_000));
            }
        };
        $this->jitter = $jitter ?? static fn (): float => mt_rand() / mt_getrandmax();
    }

    /**
     * Perform a request and return the decoded JSON body.
     *
     * @param array{
     *     body?: array<string, mixed>|null,
     *     query?: array<string, mixed>|null,
     *     idempotent?: bool,
     *     idempotency_key?: string|null,
     *     max_retries?: int|null,
     *     headers?: array<string, string>|null,
     * } $options
     */
    public function request(string $method, string $path, array $options = []): mixed
    {
        $body = $options['body'] ?? null;
        $idempotent = $options['idempotent'] ?? false;
        $idempotencyKey = $options['idempotency_key'] ?? null;
        $retries = $options['max_retries'] ?? $this->maxRetries;
        $extraHeaders = $options['headers'] ?? null;

        $guzzleOptions = [
            'headers' => $this->buildHeaders(
                hasBody: $body !== null,
                idempotent: $idempotent,
                idempotencyKey: $idempotencyKey,
                maxRetries: $retries,
                extraHeaders: $extraHeaders,
            ),
            'http_errors' => false,
            'timeout' => $this->timeout,
        ];

        $query = $this->cleanQuery($options['query'] ?? null);
        if ($query !== []) {
            $guzzleOptions['query'] = $query;
        }
        if ($body !== null) {
            $guzzleOptions['json'] = $body;
        }

        $url = $this->baseUrl . $path;
        $attempt = 0;

        while (true) {
            try {
                $response = $this->guzzle->request($method, $url, $guzzleOptions);
            } catch (ConnectException $exc) {
                if ($attempt < $retries) {
                    ($this->sleeper)($this->backoff($attempt, []));
                    $attempt++;

                    continue;
                }
                throw new ApiConnectionException($this->connectionMessage($exc), $exc);
            } catch (TransferException $exc) {
                if ($attempt < $retries) {
                    ($this->sleeper)($this->backoff($attempt, []));
                    $attempt++;

                    continue;
                }
                throw new ApiConnectionException($this->connectionMessage($exc), $exc);
            }

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return $this->decode($response);
            }

            if (in_array($status, self::RETRYABLE_STATUS, true) && $attempt < $retries) {
                ($this->sleeper)($this->backoff($attempt, $response->getHeaders()));
                $attempt++;

                continue;
            }

            throw ErrorFactory::fromResponse($status, $this->decode($response), $response->getHeaders());
        }
    }

    /**
     * @param array<string, string>|null $extraHeaders
     * @return array<string, string>
     */
    private function buildHeaders(
        bool $hasBody,
        bool $idempotent,
        ?string $idempotencyKey,
        int $maxRetries,
        ?array $extraHeaders,
    ): array {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => self::userAgent(),
        ];
        $headers = array_merge($headers, $this->defaultHeaders);

        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($idempotent) {
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $headers['Idempotency-Key'] = $idempotencyKey;
            } elseif ($maxRetries > 0) {
                // Auto-key so built-in retries of a send cannot deliver twice.
                $headers['Idempotency-Key'] = self::uuid4();
            }
        }

        if ($extraHeaders !== null) {
            $headers = array_merge($headers, $extraHeaders);
        }

        return $headers;
    }

    /**
     * @param array<string, mixed>|null $query
     * @return array<string, string>
     */
    private function cleanQuery(?array $query): array
    {
        if ($query === null) {
            return [];
        }

        $out = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            $out[$key] = match (true) {
                $value === true => 'true',
                $value === false => 'false',
                default => (string) $value,
            };
        }

        return $out;
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function backoff(int $attempt, array $headers): float
    {
        if ($headers !== []) {
            $after = ErrorFactory::retryAfterSeconds($headers);
            if ($after !== null) {
                return min($after, self::MAX_BACKOFF_SECONDS);
            }
        }

        $ceiling = min(self::BASE_BACKOFF_SECONDS * (2 ** $attempt), self::MAX_BACKOFF_SECONDS);

        return ($this->jitter)() * $ceiling; // full jitter
    }

    private function decode(ResponseInterface $response): mixed
    {
        if ($response->getStatusCode() === 204) {
            return null;
        }

        $contents = (string) $response->getBody();
        if ($contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $contents;
        }

        return $decoded;
    }

    private function connectionMessage(TransferException $exc): string
    {
        return 'Could not reach Anypost: ' . $exc->getMessage();
    }

    private static function userAgent(): string
    {
        return 'anypost-php/' . Version::VERSION . ' PHP/' . PHP_VERSION;
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
