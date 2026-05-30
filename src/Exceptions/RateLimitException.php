<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

/**
 * `429` — a rate limit was exceeded.
 */
final class RateLimitException extends AnypostException
{
    public function __construct(
        string $message,
        string $errorType,
        private readonly ?float $retryAfter = null,
        ?int $status = null,
        ?string $requestId = null,
        mixed $raw = null,
    ) {
        parent::__construct($message, $errorType, $status, $requestId, $raw);
    }

    /**
     * Parsed `Retry-After`, in seconds, when the server sent one.
     */
    public function getRetryAfter(): ?float
    {
        return $this->retryAfter;
    }
}
