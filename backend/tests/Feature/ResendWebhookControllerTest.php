<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResendWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_resend_webhook_rejects_invalid_signature(): void
    {
        config(['services.resend.webhook_secret' => 'resend_webhook_secret_test']);

        $payload = json_encode([
            'id' => 'evt_resend_bad',
            'type' => 'email.delivered',
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/resend', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_RESEND_SIGNATURE' => 'sha256=invalid',
        ], $payload)->assertStatus(400);
    }

    public function test_resend_webhook_accepts_svix_signature_and_is_idempotent(): void
    {
        config(['services.resend.webhook_secret' => 'resend_webhook_secret_test']);

        $payload = json_encode([
            'id' => 'evt_resend_123',
            'type' => 'email.delivered',
            'data' => [
                'email_id' => 'email_123',
                'to' => ['cliente@example.com'],
            ],
        ], JSON_THROW_ON_ERROR);

        $svixId = 'msg_resend_123';
        $svixTimestamp = (string) time();
        $svixSignature = $this->makeSvixSignature($svixId, $svixTimestamp, $payload, 'resend_webhook_secret_test');

        $this->call('POST', '/api/webhooks/resend', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $svixId,
            'HTTP_SVIX_TIMESTAMP' => $svixTimestamp,
            'HTTP_SVIX_SIGNATURE' => $svixSignature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('event_type', 'email.delivered');

        $this->call('POST', '/api/webhooks/resend', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $svixId,
            'HTTP_SVIX_TIMESTAMP' => $svixTimestamp,
            'HTTP_SVIX_SIGNATURE' => $svixSignature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('resend_webhook_events', [
            'event_id' => 'evt_resend_123',
            'event_type' => 'email.delivered',
        ]);

        $this->assertDatabaseCount('resend_webhook_events', 1);
    }

    private function makeSvixSignature(string $id, string $timestamp, string $payload, string $secret): string
    {
        $signedContent = $id.'.'.$timestamp.'.'.$payload;
        $signature = base64_encode(hash_hmac('sha256', $signedContent, $secret, true));

        return 'v1,'.$signature;
    }
}
