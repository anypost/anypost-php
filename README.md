# Anypost PHP SDK

The official PHP client for the [Anypost](https://anypost.com) email API.

Requires PHP 8.1+. Built on [Guzzle](https://docs.guzzlephp.org/).

This README covers the SDK itself: installation, idioms, and configuration. For platform concepts and the full field-level API reference, see the [Anypost documentation](https://anypost.com/docs).

## Install

```bash
composer require anypost/anypost-php
```

## Quickstart

```php
use Anypost\Anypost;

$client = new Anypost('ap_your_api_key');

$email = $client->email->send([
    'from' => 'YourCo <you@yourdomain.com>',
    'to' => ['you@example.com'],
    'subject' => 'Welcome to Anypost',
    'html' => '<p>Hello, inbox!</p>',
]);

echo $email->id;
```

The constructor also reads `ANYPOST_API_KEY` from the environment:

```php
$client = new Anypost();
```

Keep the key server-side. It is a bearer credential; never ship it to a browser or mobile app.

Request bodies are plain associative arrays that match the API one-to-one. Responses come back as `Anypost\Response` objects: read fields with property or array syntax (`$email->id` or `$email['id']`), and nested objects are themselves `Response` instances. Call `$email->toArray()` for the raw decoded structure.

## Sending

One of `text`, `html`, or `template_id` is required. All recipients in `to`, `cc`, and `bcc` share one envelope and count against a combined limit of 50.

```php
$client->email->send([
    'from' => 'YourCo <you@yourdomain.com>',
    'to' => ['a@example.com', 'b@example.com'],
    'cc' => ['team@example.com'],
    'reply_to' => 'support@yourdomain.com',
    'subject' => 'Receipt #4823',
    'html' => '<p>Thanks for your order.</p>',
    'text' => 'Thanks for your order.',
    'tags' => ['receipt'],
]);
```

Attachment `content` is the raw file bytes: pass what `file_get_contents` returns and the client base64-encodes it. Do not pre-encode it. The request body is capped at 5 MB.

```php
$client->email->send([
    'from' => 'YourCo <you@yourdomain.com>',
    'to' => ['someone@example.com'],
    'subject' => 'Your report',
    'text' => 'Attached.',
    'attachments' => [
        ['filename' => 'report.pdf', 'content' => file_get_contents('report.pdf')],
    ],
]);
```

Send with a published template and per-recipient variables:

```php
$client->email->send([
    'from' => 'YourCo <you@yourdomain.com>',
    'to' => ['someone@example.com'],
    'template_id' => 'template_018f2c5e-3a40-7a91-9c25-3a0b1d5e6f78',
    'variables' => ['name' => 'Ada', 'plan' => 'pro'],
]);
```

See the [send reference](https://anypost.com/docs/reference/emails) for the complete field list.

## Batch

Send 1 to 100 independent messages in one request. `defaults` fills any field an entry omits.

```php
$result = $client->email->sendBatch([
    'defaults' => ['from' => 'YourCo <you@yourdomain.com>'],
    'emails' => [
        ['to' => ['a@example.com'], 'subject' => 'Hi A', 'text' => '...'],
        ['to' => ['b@example.com'], 'subject' => 'Hi B', 'text' => '...'],
    ],
]);
```

A batch with mixed outcomes returns HTTP `207` and resolves normally. Inspect each entry rather than relying on a thrown error:

```php
$result->summary; // { total, queued, failed }

foreach ($result->data as $entry) {
    if ($entry->status === 'queued') {
        echo "{$entry->index} {$entry->id}\n";
    } else {
        echo "{$entry->index} {$entry->error->type} {$entry->error->message}\n";
    }
}
```

## Domains

Manage sending domains under `$client->domains`. Add a domain, publish the DNS records it returns, then verify.

```php
$domain = $client->domains->create(['name' => 'example.com']);

foreach ($domain->dns_records as $record) {
    echo "{$record->type} {$record->name} -> {$record->value}\n";
}

$checked = $client->domains->verify($domain->id);
if ($checked->status !== 'verified') {
    // verify returns the current domain even while pending; it does not throw
    echo $checked->verification_failure;
}
```

`get`, `update` (tracking config only), and `delete` round out the resource. See [Domains](https://anypost.com/docs/reference/domains) for the verification lifecycle and field reference.

## API keys

Manage keys under `$client->apiKeys`. The plaintext secret comes back only once, on `create`, as `key`, so store it then.

```php
$created = $client->apiKeys->create([
    'name' => 'Production server',
    'permissions' => 'send_only',
    'allowed_domains' => ['example.com'],
]);
echo $created->key; // never retrievable again
```

`get` returns metadata only (`key_prefix`, never the secret); `update` and `delete` round out the resource. See [API keys](https://anypost.com/docs/reference/api-keys) for the permission model and cache propagation.

## Templates

Templates use a draft/published model: edits land in a draft, and `publish` promotes it. A template can't be used for sending until it's published.

```php
$template = $client->templates->create([
    'name' => 'Welcome email',
    'kind' => 'html',
    'html' => '<h1>Welcome, {{ name }}</h1>',
]);

$client->templates->publish($template->id);
```

`kind` (`html` or `markdown`) is immutable once set; the plain-text body is always derived server-side. `getDraft`, `updateDraft`, `deleteDraft`, `duplicate`, `get`, `update` (name only), and `delete` round out the resource. Send a published template with `template_id` (see [Sending](#sending)). See [Templates](https://anypost.com/docs/reference/templates) for the full model.

## Suppressions

A suppression blocks sends to an address, scoped to a `topic`. The wildcard `*` blocks every topic; a named topic (e.g. `marketing`) leaves transactional traffic untouched.

```php
$client->suppressions->create([
    'email' => 'alice@example.com',
    'topic' => 'marketing',
    'note' => 'Customer requested removal',
]);

$client->suppressions->delete('alice@example.com', 'marketing');
```

`get`, `list` (with `email_contains`, `topic`, `reason`, and `origin` filters), `listForEmail`, and `deleteForEmail` round out the resource. See [Suppressions](https://anypost.com/docs/reference/suppressions) for scoping and the automatic-suppression rules for bounces and complaints.

## Webhooks

Manage webhook subscriptions under `$client->webhooks`. The `signing_secret` comes back only once, on `create`; later reads return only `signing_secret_prefix`.

```php
$webhook = $client->webhooks->create([
    'name' => 'Production events',
    'url' => 'https://hooks.example.com/anypost',
    'events' => ['email.delivered', 'email.bounced', 'email.complained'],
]);
echo $webhook->signing_secret; // store now; never retrievable again
```

`update`, `test`, `rotateSecret`, `get`, `list`, and `delete` round out the resource. See [Webhooks](https://anypost.com/docs/reference/webhooks) for the event catalog, status transitions, and the secret-rotation grace window.

### Verifying deliveries

`WebhookSignature::verify` is static: it needs the signing secret, not an API key, so call it in your handler without a client. Pass the **raw** request body (the exact bytes, before JSON parsing), the `Anypost-Signature` header, and the secret. It returns on success and throws `WebhookVerificationException` otherwise. `WebhookSignature::unwrap` does the same and returns the parsed delivery as a `Response`.

```php
use Anypost\Webhook\WebhookSignature;
use Anypost\Webhook\WebhookVerificationException;

try {
    $delivery = WebhookSignature::unwrap($rawBody, $signatureHeader, $secret);
    foreach ($delivery->events as $event) {
        echo "{$event->type} {$event->data->email_id}\n";
    }
} catch (WebhookVerificationException $e) {
    // $e->getReason(): WebhookVerificationFailure::NoMatch | ::TimestampOutOfTolerance | ...
    http_response_code(400);
}
```

Reach for `verify` when something else has already parsed the body. Keep the raw bytes for the verify step, then use your parsed object once it passes:

```php
use Anypost\Webhook\WebhookSignature;
use Anypost\Webhook\WebhookVerificationException;

$raw = file_get_contents('php://input');
try {
    WebhookSignature::verify($raw, $_SERVER['HTTP_ANYPOST_SIGNATURE'] ?? '', $secret);
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    return;
}

foreach (json_decode($raw, true)['events'] as $event) {
    handle($event);
}
```

Deliveries older than five minutes are rejected by default to bound replay; pass a fourth argument to widen, narrow, or disable (`0`) that check. During a secret rotation the header carries a `v1=` component per active secret, and a match on any one passes, so deliveries keep verifying while you redeploy.

## Events

`$client->events->list` pages the team's event stream, newest-first. The window defaults to the last 24 hours and is clamped to your plan's retention. Events are read-only and not addressable by id, so there is no `get`.

```php
foreach ($client->events->list(['event_type' => 'email.bounced']) as $event) {
    echo "{$event->occurred_at} {$event->recipient} {$event->bounce_classification}\n";
}
```

Filter by `start`, `end`, `event_type`, `recipient`, `email_id`, `message_id`, `domain`, `topic`, `campaign`, `template_id`, and `tags`, an array that matches an event carrying *any* of the given tags. Every other filter is exact-match. This is also how you backfill the gap after a webhook endpoint was disabled: page the events that occurred during the outage once it's healthy. See [Events](https://anypost.com/docs/reference/events) for the field reference.

## Pagination

List endpoints return a `Page`. Read one page directly, or iterate it to walk every page; the client fetches each one as needed.

```php
$page = $client->domains->list(['limit' => 50]);
$page->data;       // this page's items
$page->hasMore;    // whether another page exists
$page->nextCursor; // pass as "after" to fetch it yourself

foreach ($client->domains->list() as $domain) {
    echo $domain->name; // every domain, across all pages
}
```

## Errors

A failed request throws an `AnypostException` subclass. Branch on `getErrorType()`, the stable machine-readable code, not on the HTTP status.

```php
use Anypost\Exceptions\AnypostException;
use Anypost\Exceptions\RateLimitException;
use Anypost\Exceptions\ValidationException;

try {
    $client->email->send($message);
} catch (ValidationException $e) {
    print_r($e->getErrors()); // ['from' => ['The from field is required.']]
} catch (RateLimitException $e) {
    echo $e->getRetryAfter(); // seconds, or null
} catch (AnypostException $e) {
    echo $e->getErrorType() . ' ' . $e->getStatus() . ' ' . $e->getMessage();
}
```

| Class | `errorType` | Status |
|---|---|---|
| `ValidationException` | `validation_error` | `400`, `422` |
| `AuthenticationException` | `authentication_error` | `401` |
| `PermissionException` | `permission_error` | `403` |
| `NotFoundException` | `not_found` | `404` |
| `ConflictException` | `conflict`, `idempotency_concurrent`, `webhook_rotation_in_progress` | `409` |
| `IdempotencyMismatchException` | `idempotency_mismatch` | `422` |
| `RateLimitException` | `rate_limit_exceeded` | `429` |
| `PayloadTooLargeException` | `payload_too_large` | `413` |
| `ApiException` | `internal_error`, `provisioning_error` | `5xx` |
| `ApiConnectionException` | `connection_error` | none |

Every error carries `getErrorType()`, `getStatus()`, `getMessage()`, `getRequestId()`, and `getRaw()` (the parsed body).

## Retries and idempotency

The client retries `429`, `502`, `503`, and network failures up to `max_retries` times (default 2), with exponential backoff and full jitter. It honors `Retry-After`.

Sends are made safe to retry automatically: when retries are enabled and you do not pass an idempotency key, the client generates one and reuses it across attempts, so a retried send cannot deliver twice. Pass your own key to dedupe across process restarts:

```php
$client->email->send($message, $orderId);
$client->email->sendBatch($batch, $idempotencyKey);
```

## Configuration

```php
new Anypost('ap_your_api_key', [
    'base_url' => 'https://api.anypost.com/v1',
    'timeout' => 30.0,
    'max_retries' => 2,
    'headers' => ['X-My-Header' => 'value'],
]);
```

| Option | Default | Description |
|---|---|---|
| `base_url` | `https://api.anypost.com/v1` | API base URL. |
| `timeout` | `30.0` | Per-request timeout, in seconds. |
| `max_retries` | `2` | Automatic retries for transient failures. |
| `headers` | `[]` | Extra headers sent on every request. |
| `http_client` | a new one | Bring your own Guzzle `ClientInterface`. |

The first constructor argument is the API key (`ap_...`); omit it to read `ANYPOST_API_KEY`. `send` and `sendBatch` accept a per-call idempotency key as their second argument.

## License

MIT
