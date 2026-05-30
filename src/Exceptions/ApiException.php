<?php

declare(strict_types=1);

namespace Anypost\Exceptions;

/**
 * A server error (`5xx`), including `internal_error` and `provisioning_error`.
 */
final class ApiException extends AnypostException
{
}
