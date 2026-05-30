<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

/**
 * `400`/`422` — the request body or query failed validation.
 */
final class ValidationException extends AnypostException
{
    /**
     * @param array<string, list<string>> $errors Field path -> list of problems.
     */
    public function __construct(
        string $message,
        string $errorType,
        private readonly array $errors = [],
        ?int $status = null,
        ?string $requestId = null,
        mixed $raw = null,
    ) {
        parent::__construct($message, $errorType, $status, $requestId, $raw);
    }

    /**
     * Field path -> list of problems. Present for `validation_error`.
     *
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
