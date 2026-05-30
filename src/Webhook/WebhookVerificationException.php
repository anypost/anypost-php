<?php

declare(strict_types=1);

namespace Anypost\Webhook;

use RuntimeException;

/**
 * Raised when a webhook delivery's signature cannot be verified.
 */
final class WebhookVerificationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly WebhookVerificationFailure $reason,
    ) {
        parent::__construct($message);
    }

    /**
     * The machine-readable reason. Branch on this.
     */
    public function getReason(): WebhookVerificationFailure
    {
        return $this->reason;
    }
}
