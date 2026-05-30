<?php

declare(strict_types=1);

namespace Anypost\Webhook;

use Anypost\Response;

/**
 * Verify the signature on an Anypost webhook delivery.
 */
final class WebhookSignature
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * Verify an Anypost webhook signature.
     *
     * Pass the **raw** request body (the exact bytes received, before JSON
     * parsing), the `Anypost-Signature` header value, and the webhook's signing
     * secret. Returns on success; throws {@see WebhookVerificationException}
     * otherwise.
     *
     * The header may carry more than one `v1=` component during a secret
     * rotation; a match on any one passes, so deliveries keep verifying across a
     * rotation. Set `$toleranceSeconds` to 0 to disable the freshness check.
     *
     * @throws WebhookVerificationException
     */
    public static function verify(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): void {
        [$timestamp, $signatures] = self::parseHeader($signatureHeader);

        if ($toleranceSeconds > 0) {
            $current = $now ?? time();
            if ($current - $timestamp > $toleranceSeconds) {
                throw new WebhookVerificationException(
                    "Timestamp {$timestamp} is older than the {$toleranceSeconds}s tolerance.",
                    WebhookVerificationFailure::TimestampOutOfTolerance,
                );
            }
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        // Constant-time over every candidate: accumulate without early exit.
        $matched = false;
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                $matched = true;
            }
        }

        if (! $matched) {
            throw new WebhookVerificationException(
                'No signature in the header matched the computed signature.',
                WebhookVerificationFailure::NoMatch,
            );
        }
    }

    /**
     * Verify a delivery and return its parsed body as a {@see Response}.
     *
     * A thin wrapper over {@see verify()} that decodes the JSON only after the
     * signature checks out.
     *
     * @throws WebhookVerificationException
     */
    public static function unwrap(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): Response {
        self::verify($payload, $signatureHeader, $secret, $toleranceSeconds, $now);

        $decoded = json_decode($payload, true);

        return new Response(is_array($decoded) ? $decoded : []);
    }

    /**
     * @return array{0: int, 1: list<string>}
     *
     * @throws WebhookVerificationException
     */
    private static function parseHeader(string $header): array
    {
        if ($header === '') {
            throw new WebhookVerificationException(
                'The Anypost-Signature header is empty.',
                WebhookVerificationFailure::MalformedHeader,
            );
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pos = strpos($part, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($part, 0, $pos));
            $value = trim(substr($part, $pos + 1));

            if ($key === 't') {
                if (preg_match('/^\d+$/', $value) === 1) {
                    $timestamp = (int) $value;
                }
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null) {
            throw new WebhookVerificationException(
                'The Anypost-Signature header has no timestamp (t=).',
                WebhookVerificationFailure::NoTimestamp,
            );
        }
        if ($signatures === []) {
            throw new WebhookVerificationException(
                'The Anypost-Signature header has no v1= signature.',
                WebhookVerificationFailure::NoSignatures,
            );
        }

        return [$timestamp, $signatures];
    }
}
