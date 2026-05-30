<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

/**
 * `422` `idempotency_mismatch` — a key was reused with a different body.
 */
final class IdempotencyMismatchException extends AnypostException
{
}
