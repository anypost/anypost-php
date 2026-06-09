<?php

declare(strict_types=1);

namespace Anypost;

/**
 * The single source of truth for the SDK version.
 *
 * Bump this constant, tag the commit `vX.Y.Z`, and push — Packagist syncs the
 * new release from the tag.
 */
final class Version
{
    public const VERSION = '1.0.0';
}
