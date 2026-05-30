<?php

declare(strict_types=1);

namespace Anypost\Tests;

final class EmailTest extends TestCase
{
    public function test_send_posts_to_email_and_parses_the_response(): void
    {
        $client = $this->client([
            $this->json(['id' => 'email_abc', 'created_at' => '2026-05-29T00:00:00Z'], 202),
        ]);

        $email = $client->email->send([
            'from' => 'Acme <you@yourdomain.com>',
            'to' => ['someone@example.com'],
            'subject' => 'Hello',
            'html' => '<p>It worked.</p>',
        ]);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/email', $request->getUri()->getPath());
        $this->assertSame('email_abc', $email->id);
        $this->assertSame('2026-05-29T00:00:00Z', $email['created_at']);

        $body = $this->bodyOf($request);
        $this->assertSame('Acme <you@yourdomain.com>', $body['from']);
        $this->assertSame(['someone@example.com'], $body['to']);
    }

    public function test_send_auto_generates_an_idempotency_key(): void
    {
        $client = $this->client([$this->json(['id' => 'email_1', 'created_at' => 'now'], 202)]);
        $client->email->send(['from' => 'a@b.com', 'to' => ['c@d.com'], 'text' => 'x']);

        $key = $this->lastRequest()->getHeaderLine('Idempotency-Key');
        $this->assertNotSame('', $key);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $key,
        );
    }

    public function test_send_respects_an_explicit_idempotency_key(): void
    {
        $client = $this->client([$this->json(['id' => 'email_1', 'created_at' => 'now'], 202)]);
        $client->email->send(['from' => 'a@b.com', 'to' => ['c@d.com'], 'text' => 'x'], 'my-key-123');

        $this->assertSame('my-key-123', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function test_send_does_not_set_idempotency_key_when_retries_disabled(): void
    {
        $client = $this->client(
            [$this->json(['id' => 'email_1', 'created_at' => 'now'], 202)],
            ['max_retries' => 0],
        );
        $client->email->send(['from' => 'a@b.com', 'to' => ['c@d.com'], 'text' => 'x']);

        $this->assertSame('', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function test_send_base64_encodes_raw_attachment_content(): void
    {
        $client = $this->client([$this->json(['id' => 'email_1', 'created_at' => 'now'], 202)]);
        $client->email->send([
            'from' => 'a@b.com',
            'to' => ['c@d.com'],
            'subject' => 'With file',
            'text' => 'see attached',
            'attachments' => [
                ['filename' => 'hello.txt', 'content' => 'hello world'],
            ],
        ]);

        $body = $this->bodyOf($this->lastRequest());
        $this->assertSame(base64_encode('hello world'), $body['attachments'][0]['content']);
        $this->assertSame('hello.txt', $body['attachments'][0]['filename']);
    }

    public function test_send_batch_posts_all_emails_and_does_not_throw_on_207(): void
    {
        $client = $this->client([
            $this->json([
                'summary' => ['total' => 2, 'queued' => 1, 'failed' => 1],
                'data' => [
                    ['status' => 'queued', 'index' => 0, 'id' => 'email_1', 'created_at' => 'now'],
                    ['status' => 'failed', 'index' => 1, 'error' => ['type' => 'validation_error', 'message' => 'bad']],
                ],
            ], 207),
        ]);

        $batch = $client->email->sendBatch([
            'emails' => [
                ['from' => 'a@b.com', 'to' => ['ok@example.com'], 'text' => 'x'],
                ['from' => 'a@b.com', 'to' => ['bad'], 'text' => 'x'],
            ],
        ]);

        $request = $this->lastRequest();
        $this->assertStringEndsWith('/email/batch', $request->getUri()->getPath());
        $this->assertSame(1, $batch->summary->queued);
        $this->assertCount(2, $batch->data);
        $this->assertSame('failed', $batch->data[1]->status);
    }

    public function test_send_batch_encodes_attachments_in_defaults_and_emails(): void
    {
        $client = $this->client([$this->json(['summary' => [], 'data' => []], 202)]);
        $client->email->sendBatch([
            'defaults' => [
                'attachments' => [['filename' => 'd.txt', 'content' => 'default-bytes']],
            ],
            'emails' => [
                [
                    'from' => 'a@b.com',
                    'to' => ['c@d.com'],
                    'text' => 'x',
                    'attachments' => [['filename' => 'e.txt', 'content' => 'email-bytes']],
                ],
            ],
        ]);

        $body = $this->bodyOf($this->lastRequest());
        $this->assertSame(base64_encode('default-bytes'), $body['defaults']['attachments'][0]['content']);
        $this->assertSame(base64_encode('email-bytes'), $body['emails'][0]['attachments'][0]['content']);
    }
}
