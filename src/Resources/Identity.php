<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Response;

/**
 * Identity operations (`/whoami`).
 */
final class Identity extends AbstractResource
{
    /**
     * Identify the team and permission level behind the current API key.
     */
    public function whoami(): Response
    {
        return $this->requestObject('GET', '/whoami');
    }
}
