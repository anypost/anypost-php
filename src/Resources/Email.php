<?php

declare(strict_types=1);

namespace Anypost\Resources;

use Anypost\Response;
use Anypost\Util\Base64;

/**
 * Operations on the `/email` endpoints.
 *
 * Attachment `content` is the raw file bytes (e.g. from `file_get_contents`);
 * the SDK base64-encodes it for transport. Do not pre-encode it.
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
     * Return a copy of a message with each attachment's raw `content`
     * base64-encoded for transport.
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
            function (array $attachment): array {
                if (isset($attachment['content']) && is_string($attachment['content'])) {
                    $attachment['content'] = Base64::encode($attachment['content']);
                }

                return $attachment;
            },
            $message['attachments'],
        );

        return $message;
    }
}
