<?php

declare(strict_types=1);

namespace Anypost\Util;

/**
 * @internal
 */
final class Base64
{
    /**
     * Base64-encode raw attachment bytes for transport.
     */
    public static function encode(string $data): string
    {
        return base64_encode($data);
    }
}
