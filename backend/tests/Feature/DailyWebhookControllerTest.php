<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_daily_webhook_rejects_invalid_signature(): void
    {
        config(['services.daily.webhook_secret' => 'daily_test_secret']);

        $payload = json_encode([
            'id' => 'evt_daily_invalid',
            'type' => 'recording.ready',
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => 'invalid-signature',
        ], $payload)->assertStatus(400);
    }

    public function test_daily_webhook_accepts_valid_signature_and_is_idempotent(): void
    {
        config(['services.daily.webhook_secret' => 'daily_test_secret']);

        $payload = json_encode([
            'id' => 'evt_daily_123',
            'type' => 'recording.ready',
            'data' => [
                'room' => 'room_1',
                'recording_id' => 'rec_123',
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'daily_test_secret');

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('event_type', 'recording.ready');

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('daily_webhook_events', [
            'event_id' => 'evt_daily_123',
            'event_type' => 'recording.ready',
        ]);

        $this->assertDatabaseCount('daily_webhook_events', 1);
    }
}
