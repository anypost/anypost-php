<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

/**
 * Maps an HTTP response into the right {@see AnypostException} subclass.
 *
 * Mapping keys primarily on the canonical `error.type`, falling back to the
 * HTTP status when the body carries no recognizable type.
 *
 * @internal
 */
final class ErrorFactory
{
    private const REQUEST_ID_HEADERS = [
        'anypost-request-id',
        'x-anypost-request-id',
        'x-request-id',
    ];

    /**
     * @param array<string, list<string>> $headers
     */
    public static function fromResponse(int $status, mixed $body, array $headers): AnypostException
    {
        $requestId = self::readRequestId($headers);
        $envelope = is_array($body) ? $body : [];
        $error = $envelope['error'] ?? null;

        $errors = [];
        if (is_array($error)) {
            // Canonical envelope: { error: { type, message, errors? } }.
            $type = is_string($error['type'] ?? null) ? $error['type'] : self::typeFromStatus($status);
            $message = is_string($error['message'] ?? null) ? $error['message'] : self::defaultMessage($status);
            if (isset($error['errors']) && is_array($error['errors'])) {
                /** @var array<string, list<string>> $errors */
                $errors = $error['errors'];
            }
        } elseif (is_string($error)) {
            // Flat envelope: { error: "<code>", message? }.
            $type = $error;
            $message = is_string($envelope['message'] ?? null)
                ? $envelope['message']
                : str_replace('_', ' ', $error);
        } else {
            $type = self::typeFromStatus($status);
            $message = self::defaultMessage($status);
        }

        return self::build($status, $type, $message, $errors, $requestId, $body, $headers);
    }

    /**
     * Parse a `Retry-After` header (delta-seconds or HTTP-date) into seconds.
     *
     * @param array<string, list<string>> $headers
     */
    public static function retryAfterSeconds(array $headers, ?int $now = null): ?float
    {
        $value = self::header($headers, 'retry-after');
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $now ??= time();

        return max(0.0, (float) ($timestamp - $now));
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, list<string>> $allHeaders
     */
    private static function build(
        int $status,
        string $type,
        string $message,
        array $errors,
        ?string $requestId,
        mixed $raw,
        array $allHeaders,
    ): AnypostException {
        switch ($type) {
            case 'validation_error':
                return new ValidationException($message, $type, $errors, $status, $requestId, $raw);
            case 'authentication_error':
                return new AuthenticationException($message, $type, $status, $requestId, $raw);
            case 'permission_error':
                return new PermissionException($message, $type, $status, $requestId, $raw);
            case 'not_found':
                return new NotFoundException($message, $type, $status, $requestId, $raw);
            case 'conflict':
            case 'idempotency_concurrent':
            case 'webhook_rotation_in_progress':
                return new ConflictException($message, $type, $status, $requestId, $raw);
            case 'idempotency_mismatch':
                return new IdempotencyMismatchException($message, $type, $status, $requestId, $raw);
            case 'rate_limit_exceeded':
                return new RateLimitException(
                    $message,
                    $type,
                    self::retryAfterSeconds($allHeaders),
                    $status,
                    $requestId,
                    $raw,
                );
            case 'payload_too_large':
                return new PayloadTooLargeException($message, $type, $status, $requestId, $raw);
            case 'provisioning_error':
            case 'internal_error':
                return new ApiException($message, $type, $status, $requestId, $raw);
        }

        return self::byStatus($status, $type, $message, $errors, $requestId, $raw, $allHeaders);
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, list<string>> $allHeaders
     */
    private static function byStatus(
        int $status,
        string $type,
        string $message,
        array $errors,
        ?string $requestId,
        mixed $raw,
        array $allHeaders,
    ): AnypostException {
        return match (true) {
            $status === 401 => new AuthenticationException($message, $type, $status, $requestId, $raw),
            $status === 403 => new PermissionException($message, $type, $status, $requestId, $raw),
            $status === 404 => new NotFoundException($message, $type, $status, $requestId, $raw),
            $status === 409 => new ConflictException($message, $type, $status, $requestId, $raw),
            $status === 413 => new PayloadTooLargeException($message, $type, $status, $requestId, $raw),
            $status === 429 => new RateLimitException(
                $message,
                $type,
                self::retryAfterSeconds($allHeaders),
                $status,
                $requestId,
                $raw,
            ),
            $status === 400 || $status === 422 => new ValidationException(
                $message,
                $type,
                $errors,
                $status,
                $requestId,
                $raw,
            ),
            $status >= 500 => new ApiException($message, $type, $status, $requestId, $raw),
            default => new AnypostException($message, $type, $status, $requestId, $raw),
        };
    }

    private static function typeFromStatus(int $status): string
    {
        return match ($status) {
            400, 422 => 'validation_error',
            401 => 'authentication_error',
            403 => 'permission_error',
            404 => 'not_found',
            409 => 'conflict',
            413 => 'payload_too_large',
            429 => 'rate_limit_exceeded',
            default => $status >= 500 ? 'internal_error' : 'api_error',
        };
    }

    private static function defaultMessage(int $status): string
    {
        return "Anypost request failed with status {$status}.";
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private static function readRequestId(array $headers): ?string
    {
        foreach (self::REQUEST_ID_HEADERS as $name) {
            $value = self::header($headers, $name);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Case-insensitive single-value header lookup.
     *
     * @param array<string, list<string>> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $name = strtolower($name);
        foreach ($headers as $key => $values) {
            if (strtolower((string) $key) === $name) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
