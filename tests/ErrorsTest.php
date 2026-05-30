<?php

declare(strict_types=1);

namespace Anypost\Tests;

use Anypost\Exceptions\ApiException;
use Anypost\Exceptions\AuthenticationException;
use Anypost\Exceptions\ConflictException;
use Anypost\Exceptions\IdempotencyMismatchException;
use Anypost\Exceptions\NotFoundException;
use Anypost\Exceptions\PayloadTooLargeException;
use Anypost\Exceptions\PermissionException;
use Anypost\Exceptions\RateLimitException;
use Anypost\Exceptions\ValidationException;

final class ErrorsTest extends TestCase
{
    public function test_validation_error_exposes_field_errors(): void
    {
        $client = $this->client([
            $this->json([
                'error' => [
                    'type' => 'validation_error',
                    'message' => 'Invalid request.',
                    'errors' => ['to' => ['must not be empty']],
                ],
            ], 422),
        ]);

        try {
            $client->email->send(['from' => 'a@b.com', 'to' => [], 'text' => 'x']);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('validation_error', $e->getErrorType());
            $this->assertSame(422, $e->getStatus());
            $this->assertSame(['to' => ['must not be empty']], $e->getErrors());
            $this->assertSame('Invalid request.', $e->getMessage());
        }
    }

    public function test_maps_each_error_type_to_its_class(): void
    {
        $cases = [
            [401, 'authentication_error', AuthenticationException::class],
            [403, 'permission_error', PermissionException::class],
            [404, 'not_found', NotFoundException::class],
            [409, 'idempotency_concurrent', ConflictException::class],
            [422, 'idempotency_mismatch', IdempotencyMismatchException::class],
            [500, 'internal_error', ApiException::class],
        ];

        foreach ($cases as [$status, $type, $class]) {
            $client = $this->client([
                $this->json(['error' => ['type' => $type, 'message' => 'nope']], $status),
            ]);

            try {
                $client->whoami();
                $this->fail("Expected {$class} for {$type}.");
            } catch (\Anypost\Exceptions\AnypostException $e) {
                $this->assertInstanceOf($class, $e);
                $this->assertSame($type, $e->getErrorType());
            }
        }
    }

    public function test_rate_limit_error_parses_retry_after(): void
    {
        $client = $this->client([
            $this->json(
                ['error' => ['type' => 'rate_limit_exceeded', 'message' => 'slow down']],
                429,
                ['Retry-After' => '7'],
            ),
        ], ['max_retries' => 0]);

        try {
            $client->whoami();
            $this->fail('Expected RateLimitException.');
        } catch (RateLimitException $e) {
            $this->assertSame(7.0, $e->getRetryAfter());
        }
    }

    public function test_handles_the_flat_413_envelope(): void
    {
        $client = $this->client([$this->json(['error' => 'payload_too_large'], 413)]);

        try {
            $client->email->send(['from' => 'a@b.com', 'to' => ['c@d.com'], 'text' => 'x']);
            $this->fail('Expected PayloadTooLargeException.');
        } catch (PayloadTooLargeException $e) {
            $this->assertSame('payload_too_large', $e->getErrorType());
            $this->assertSame(413, $e->getStatus());
        }
    }

    public function test_captures_the_request_id_header(): void
    {
        $client = $this->client([
            $this->json(
                ['error' => ['type' => 'not_found', 'message' => 'gone']],
                404,
                ['Anypost-Request-Id' => 'req_123'],
            ),
        ]);

        try {
            $client->domains->get('dom_1');
            $this->fail('Expected NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('req_123', $e->getRequestId());
        }
    }

    public function test_falls_back_to_status_when_body_has_no_type(): void
    {
        $client = $this->client([$this->json([], 403)]);

        $this->expectException(PermissionException::class);
        $client->whoami();
    }
}
