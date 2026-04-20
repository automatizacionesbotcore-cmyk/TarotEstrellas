<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_whatsapp_meta_verification_challenge_is_resolved(): void
    {
        config([
            'services.whatsapp.provider' => 'meta',
            'services.whatsapp.meta_verify_token' => 'meta_verify_token_test',
        ]);

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=meta_verify_token_test&hub_challenge=12345')
            ->assertOk()
            ->assertSeeText('12345');
    }

    public function test_whatsapp_webhook_rejects_invalid_signature(): void
    {
        config([
            'services.whatsapp.provider' => 'meta',
            'services.whatsapp.meta_app_secret' => 'meta_app_secret_test',
        ]);

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'waba_123',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'statuses' => [
                                    ['id' => 'wamid.bad', 'status' => 'sent'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $payload)->assertStatus(400);
    }

    public function test_whatsapp_webhook_accepts_meta_signature_and_is_idempotent(): void
    {
        config([
            'services.whatsapp.provider' => 'meta',
            'services.whatsapp.meta_app_secret' => 'meta_app_secret_test',
        ]);

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'waba_123',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'statuses' => [
                                    [
                                        'id' => 'wamid.HBgLNzc3Nzc3Nzc3FQIAERgS',
                                        'status' => 'delivered',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = 'sha256='.hash_hmac('sha256', $payload, 'meta_app_secret_test');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('event_type', 'messages');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('whatsapp_webhook_events', [
            'event_id' => 'waba_123:wamid.HBgLNzc3Nzc3Nzc3FQIAERgS',
            'event_type' => 'messages',
        ]);

        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
    }
}
