<?php

declare(strict_types=1);

namespace Anypost\Tests;

final class ResourcesTest extends TestCase
{
    public function test_whoami_reads_identity(): void
    {
        $client = $this->client([
            $this->json(['team' => ['id' => 'team_1', 'name' => 'Acme'], 'api_key' => ['id' => 'key_1', 'permissions' => 'full']]),
        ]);

        $me = $client->whoami();

        $this->assertStringEndsWith('/whoami', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('Acme', $me->team->name);
        $this->assertSame('full', $me->api_key->permissions);
    }

    public function test_domains_crud_request_shapes(): void
    {
        $client = $this->client([
            $this->json(['id' => 'dom_1']),
            $this->json(['id' => 'dom_1']),
            $this->json(['id' => 'dom_1']),
            $this->json([], 204),
            $this->json(['id' => 'dom_1', 'status' => 'pending']),
        ]);

        $client->domains->create(['name' => 'mail.acme.com']);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('mail.acme.com', $this->bodyOf($this->lastRequest())['name']);

        $client->domains->get('dom_1');
        $this->assertSame('GET', $this->lastRequest()->getMethod());

        $client->domains->update('dom_1', ['tracking' => ['opens' => true]]);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());

        $client->domains->delete('dom_1');
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());

        $domain = $client->domains->verify('dom_1');
        $this->assertStringEndsWith('/domains/dom_1/verify', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('pending', $domain->status);
    }

    public function test_templates_draft_and_publish_endpoints(): void
    {
        $client = $this->client([
            $this->json(['id' => 'tpl_1']),
            $this->json(['template_id' => 'tpl_1']),
            $this->json([], 204),
            $this->json(['id' => 'tpl_1', 'published' => true]),
            $this->json(['id' => 'tpl_2']),
        ]);

        $client->templates->getDraft('tpl_1');
        $this->assertStringEndsWith('/templates/tpl_1/draft', $this->lastRequest()->getUri()->getPath());

        $client->templates->updateDraft('tpl_1', ['subject' => 'Hi {{name}}']);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());
        $this->assertSame('Hi {{name}}', $this->bodyOf($this->lastRequest())['subject']);

        $client->templates->deleteDraft('tpl_1');
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());

        $client->templates->publish('tpl_1');
        $this->assertStringEndsWith('/templates/tpl_1/publish', $this->lastRequest()->getUri()->getPath());

        $client->templates->duplicate('tpl_1', ['name' => 'Copy']);
        $this->assertStringEndsWith('/templates/tpl_1/duplicate', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('Copy', $this->bodyOf($this->lastRequest())['name']);
    }

    public function test_webhooks_test_and_rotate_secret(): void
    {
        $client = $this->client([
            $this->json(['delivered' => true, 'status_code' => 200]),
            $this->json(['id' => 'wh_1', 'signing_secret' => 'whsec_new']),
        ]);

        $result = $client->webhooks->test('wh_1');
        $this->assertStringEndsWith('/webhooks/wh_1/test', $this->lastRequest()->getUri()->getPath());
        $this->assertTrue($result->delivered);

        $rotated = $client->webhooks->rotateSecret('wh_1');
        $this->assertStringEndsWith('/webhooks/wh_1/rotate-secret', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('whsec_new', $rotated->signing_secret);
    }

    public function test_api_keys_create_returns_secret(): void
    {
        $client = $this->client([$this->json(['id' => 'key_1', 'key' => 'ap_secret'])]);

        $key = $client->apiKeys->create(['name' => 'CI', 'permissions' => 'send_only']);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringEndsWith('/api-keys', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('ap_secret', $key->key);
    }

    public function test_suppressions_encode_email_and_topic_in_the_path(): void
    {
        $client = $this->client([
            $this->json(['email' => 'a+b@c.com', 'topic' => '*']),
            $this->json([], 204),
        ]);

        $client->suppressions->get('a+b@c.com', '*');
        $path = $this->lastRequest()->getUri()->getPath();
        $this->assertStringContainsString('a%2Bb%40c.com', $path);
        $this->assertStringContainsString('%2A', $path);

        $client->suppressions->delete('a+b@c.com', '*');
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
    }

    public function test_suppressions_list_for_email_returns_a_list(): void
    {
        $client = $this->client([
            $this->json(['data' => [['email' => 'a@b.com', 'topic' => '*'], ['email' => 'a@b.com', 'topic' => 'news']]]),
        ]);

        $rows = $client->suppressions->listForEmail('a@b.com');
        $this->assertCount(2, $rows);
        $this->assertSame('news', $rows[1]->topic);
    }

    public function test_events_join_tags_as_csv(): void
    {
        $client = $this->client([
            $this->json(['data' => [], 'has_more' => false, 'next_cursor' => null]),
        ]);

        $client->events->list(['event_type' => 'email.delivered', 'tags' => ['welcome', 'onboarding']]);

        parse_str($this->lastRequest()->getUri()->getQuery(), $query);
        $this->assertSame('email.delivered', $query['event_type']);
        $this->assertSame('welcome,onboarding', $query['tags']);
    }

    public function test_events_expose_bot_on_proxied_open(): void
    {
        $client = $this->client([
            $this->json(['data' => [
                ['id' => 'evt_bot', 'type' => 'email.opened', 'tracking' => ['bot' => ['source' => 'google', 'kind' => 'proxy']]],
                ['id' => 'evt_human', 'type' => 'email.opened', 'tracking' => null],
            ], 'has_more' => false, 'next_cursor' => null]),
        ]);

        $page = $client->events->list(['event_type' => 'email.opened']);

        // The nested bot object is wrapped as a Response and reachable via both
        // property and array access.
        $this->assertSame('google', $page->data[0]->tracking->bot->source);
        $this->assertSame('proxy', $page->data[0]['tracking']['bot']['kind']);
        // A human open carries no bot classification.
        $this->assertNull($page->data[1]->tracking);
    }
}
