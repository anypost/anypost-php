<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

/**
 * `409` — `conflict`, `idempotency_concurrent`, or `webhook_rotation_in_progress`.
 */
final class ConflictException extends AnypostException
{
}
