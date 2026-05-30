<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for every error raised by the SDK.
 *
 * Branch on {@see getErrorType()} (the stable, machine-readable code) rather
 * than on the HTTP status or the message text.
 */
class AnypostException extends RuntimeException
{
    /**
     * @param string      $message   Human-readable message.
     * @param string      $errorType Stable, machine-readable error type.
     * @param int|null    $status    HTTP status, or null when no response was received.
     * @param string|null $requestId Request id from the response, when present.
     * @param mixed       $raw       The decoded response body (or underlying cause).
     */
    public function __construct(
        string $message,
        protected readonly string $errorType,
        protected readonly ?int $status = null,
        protected readonly ?string $requestId = null,
        protected readonly mixed $raw = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Stable, machine-readable error type. Branch on this.
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * HTTP status, or null when no response was received.
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * Request id from the response, when present.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * The decoded response body, or the underlying cause for connection errors.
     */
    public function getRaw(): mixed
    {
        return $this->raw;
    }
}
