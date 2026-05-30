<?php

declare(strict_types=1);

namespace Anypost\Webhook;

/**
 * Why a signature failed to verify. Branch on this rather than the message.
 */
enum WebhookVerificationFailure: string
{
    /** The `Anypost-Signature` header could not be parsed. */
    case MalformedHeader = 'malformed_header';

    /** The header carried no `t=` component. */
    case NoTimestamp = 'no_timestamp';

    /** The header carried no `v1=` component. */
    case NoSignatures = 'no_signatures';

    /** The delivery is older than the tolerance. */
    case TimestampOutOfTolerance = 'timestamp_out_of_tolerance';

    /** No `v1=` component matched the computed signature. */
    case NoMatch = 'no_match';
}
