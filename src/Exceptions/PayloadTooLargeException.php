<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

/**
 * `413` — the request body exceeded the 5 MB gateway limit.
 */
final class PayloadTooLargeException extends AnypostException
{
}
