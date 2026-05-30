<?php

declare(strict_types=1);

namespace Anypost\Tests;

use Anypost\Response;
use Anypost\Webhook\WebhookSignature;
use Anypost\Webhook\WebhookVerificationException;
use Anypost\Webhook\WebhookVerificationFailure;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    private const PAYLOAD = '{"batch_id":"wb_1","timestamp":1000,"events":[{"id":"evt_1","type":"email.delivered"}]}';

    private function sign(string $payload, int $timestamp, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    public function test_accepts_a_valid_signature(): void
    {
        $ts = 1000;
        $header = "t={$ts},v1=" . $this->sign(self::PAYLOAD, $ts);

        WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, now: $ts + 5);
        $this->expectNotToPerformAssertions();
    }

    public function test_rejects_a_tampered_payload(): void
    {
        $ts = 1000;
        $header = "t={$ts},v1=" . $this->sign(self::PAYLOAD, $ts);

        try {
            WebhookSignature::verify('{"batch_id":"tampered"}', $header, self::SECRET, now: $ts);
            $this->fail('Expected a verification failure.');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationFailure::NoMatch, $e->getReason());
        }
    }

    public function test_passes_when_any_v1_matches_during_rotation(): void
    {
        $ts = 1000;
        $good = $this->sign(self::PAYLOAD, $ts);
        $header = "t={$ts},v1=" . str_repeat('0', 64) . ",v1={$good}";

        WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, now: $ts);
        $this->expectNotToPerformAssertions();
    }

    public function test_rejects_a_stale_timestamp(): void
    {
        $ts = 1000;
        $header = "t={$ts},v1=" . $this->sign(self::PAYLOAD, $ts);

        try {
            WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, 300, now: $ts + 301);
            $this->fail('Expected a verification failure.');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationFailure::TimestampOutOfTolerance, $e->getReason());
        }
    }

    public function test_tolerance_zero_disables_the_freshness_check(): void
    {
        $ts = 1000;
        $header = "t={$ts},v1=" . $this->sign(self::PAYLOAD, $ts);

        WebhookSignature::verify(self::PAYLOAD, $header, self::SECRET, 0, now: $ts + 999999);
        $this->expectNotToPerformAssertions();
    }

    public function test_rejects_a_malformed_header(): void
    {
        try {
            WebhookSignature::verify(self::PAYLOAD, '', self::SECRET);
            $this->fail('Expected a verification failure.');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationFailure::MalformedHeader, $e->getReason());
        }
    }

    public function test_rejects_a_header_without_a_timestamp(): void
    {
        try {
            WebhookSignature::verify(self::PAYLOAD, 'v1=abc', self::SECRET);
            $this->fail('Expected a verification failure.');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationFailure::NoTimestamp, $e->getReason());
        }
    }

    public function test_rejects_a_header_without_a_signature(): void
    {
        try {
            WebhookSignature::verify(self::PAYLOAD, 't=1000', self::SECRET);
            $this->fail('Expected a verification failure.');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationFailure::NoSignatures, $e->getReason());
        }
    }

    public function test_unwrap_returns_the_parsed_delivery(): void
    {
        $ts = 1000;
        $header = "t={$ts},v1=" . $this->sign(self::PAYLOAD, $ts);

        $delivery = WebhookSignature::unwrap(self::PAYLOAD, $header, self::SECRET, now: $ts);

        $this->assertInstanceOf(Response::class, $delivery);
        $this->assertSame('wb_1', $delivery->batch_id);
        $this->assertSame('evt_1', $delivery->events[0]->id);
    }
}
