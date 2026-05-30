<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

use Throwable;

/**
 * No HTTP response was received (network failure, timeout, or abort).
 */
final class ApiConnectionException extends AnypostException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(
            $message,
            errorType: 'connection_error',
            status: null,
            requestId: null,
            raw: $previous,
            previous: $previous,
        );
    }
}
