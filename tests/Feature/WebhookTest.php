<?php

namespace Tests\Feature;

use App\Jobs\ProcessProviderWebhook;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.provider.webhook_secret' => 'test-webhook-secret']);
    }

    /** @return array<string,mixed> */
    private function payload(string $type = 'operation.updated'): array
    {
        return ['id' => 'evt-1', 'type' => $type, 'data' => ['external_id' => 'op-1', 'amount_cents' => 100, 'status' => 'posted']];
    }

    private function signature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'test-webhook-secret');
    }

    public function test_accepts_signed_webhook_and_dispatches_processing(): void
    {
        Queue::fake();
        $p = $this->payload();
        $this->withHeader('X-Provider-Signature', $this->signature($p))->postJson('/api/webhooks/provider', $p)->assertAccepted();
        Queue::assertPushed(ProcessProviderWebhook::class);
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->withHeader('X-Provider-Signature', 'invalid')->postJson('/api/webhooks/provider', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_duplicate_event_is_acknowledged_without_another_job(): void
    {
        Queue::fake();
        $p = $this->payload();
        $this->withHeader('X-Provider-Signature', $this->signature($p))->postJson('/api/webhooks/provider', $p)->assertAccepted();
        $this->withHeader('X-Provider-Signature', $this->signature($p))->postJson('/api/webhooks/provider', $p)->assertOk()->assertJsonPath('status', 'duplicate');
        Queue::assertPushed(ProcessProviderWebhook::class, 1);
    }

    public function test_unexpected_event_retries_without_marking_processed(): void
    {
        $p = $this->payload('unknown');
        $this->withHeader('X-Provider-Signature', $this->signature($p))->postJson('/api/webhooks/provider', $p);
        $event = WebhookEvent::query()->firstOrFail();
        $this->expectException(\UnexpectedValueException::class);
        (new ProcessProviderWebhook($event->id))->handle();
        $this->assertNull($event->fresh()->processed_at);
    }
}
