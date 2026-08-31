<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Response;
use Anypost\Util\Base64;
use InvalidArgumentException;

/**
 * Operations on the `/email` endpoints.
 *
 * Give each attachment its content exactly one of two ways: `content` for raw
 * file bytes (e.g. from `file_get_contents`), which the SDK base64-encodes for
 * transport, or `content_base64` for content that is already base64 at rest,
 * which is sent through verbatim so nothing is decoded just to be re-encoded.
 * PHP has no type-level distinction between the two — both are strings — so the
 * key you choose is the signal.
 */
final class Email extends AbstractResource
{
    /**
     * Send a single message.
     *
     * All addresses in `to`/`cc`/`bcc` share one envelope. Returns the queued
     * message id; throws an {@see \Anypost\Exceptions\AnypostException} subclass
     * on failure.
     *
     * @param array<string, mixed> $email
     */
    public function send(array $email, ?string $idempotencyKey = null): Response
    {
        return $this->requestObject('POST', '/email', [
            'body' => $this->encodeAttachments($email),
            'idempotent' => true,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Send 1-100 independent messages in one request.
     *
     * A mixed-outcome batch (HTTP `207`) returns normally — inspect each entry's
     * `status` in `data`; it does not throw.
     *
     * @param array<string, mixed> $batch
     */
    public function sendBatch(array $batch, ?string $idempotencyKey = null): Response
    {
        $body = $batch;
        if (! empty($batch['defaults']) && is_array($batch['defaults'])) {
            $body['defaults'] = $this->encodeAttachments($batch['defaults']);
        }

        $emails = $batch['emails'] ?? [];
        $body['emails'] = array_map(
            fn (array $email): array => $this->encodeAttachments($email),
            is_array($emails) ? $emails : [],
        );

        return $this->requestObject('POST', '/email/batch', [
            'body' => $body,
            'idempotent' => true,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Return a copy of a message with each attachment's content collapsed onto
     * the single base64 `content` field the API takes.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function encodeAttachments(array $message): array
    {
        if (empty($message['attachments']) || ! is_array($message['attachments'])) {
            return $message;
        }

        $message['attachments'] = array_map(
            fn (array $attachment): array => $this->encodeAttachment($attachment),
            $message['attachments'],
        );

        return $message;
    }

    /**
     * @param array<string, mixed> $attachment
     * @return array<string, mixed>
     */
    private function encodeAttachment(array $attachment): array
    {
        $raw = $attachment['content'] ?? null;
        $encoded = $attachment['content_base64'] ?? null;
        $name = var_export($attachment['filename'] ?? null, true);

        if ($raw !== null && $encoded !== null) {
            throw new InvalidArgumentException(
                "Attachment {$name} sets both 'content' and 'content_base64'; set exactly one.",
            );
        }

        if (is_string($encoded)) {
            unset($attachment['content_base64']);
            $attachment['content'] = $encoded;

            return $attachment;
        }

        if (is_string($raw)) {
            $attachment['content'] = Base64::encode($raw);

            return $attachment;
        }

        throw new InvalidArgumentException(
            "Attachment {$name} has no content; set 'content' (raw bytes) or 'content_base64'.",
        );
    }
}
