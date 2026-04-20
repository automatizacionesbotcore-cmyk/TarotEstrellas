<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class WhatsappWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode = (string) $request->query('hub_mode', '');
        $verifyToken = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');
        $expectedToken = (string) config('services.whatsapp.meta_verify_token', '');

        if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, $verifyToken)) {
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response()->json([
            'message' => 'Webhook verification failed.',
        ], 403);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->isValidSignature($request, $payload)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 400);
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true) ?? [];
        $eventId = $this->extractEventId($event, $payload);
        $eventType = $this->extractEventType($event);

        $storedEvent = WhatsappWebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'event_type' => $eventType,
                'payload' => $event,
                'processed_at' => now(),
            ]
        );

        if (! $storedEvent->wasRecentlyCreated) {
            return response()->json([
                'received' => true,
                'duplicate' => true,
            ]);
        }

        return response()->json([
            'received' => true,
            'event_type' => $eventType,
        ]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function extractEventId(array $event, string $payload): string
    {
        $eventId = (string) Arr::get($event, 'id', Arr::get($event, 'event_id', ''));
        if ($eventId !== '') {
            return $eventId;
        }

        $entryId = (string) Arr::get($event, 'entry.0.id', '');
        $statusId = (string) Arr::get($event, 'entry.0.changes.0.value.statuses.0.id', '');
        $messageId = (string) Arr::get($event, 'entry.0.changes.0.value.messages.0.id', '');

        if ($entryId !== '' && $statusId !== '') {
            return $entryId.':'.$statusId;
        }

        if ($entryId !== '' && $messageId !== '') {
            return $entryId.':'.$messageId;
        }

        return sha1($payload);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function extractEventType(array $event): string
    {
        $eventType = (string) Arr::get($event, 'type', Arr::get($event, 'event', ''));
        if ($eventType !== '') {
            return $eventType;
        }

        $field = (string) Arr::get($event, 'entry.0.changes.0.field', '');
        if ($field !== '') {
            return $field;
        }

        $object = (string) Arr::get($event, 'object', 'whatsapp');

        return $object;
    }

    private function isValidSignature(Request $request, string $payload): bool
    {
        $provider = strtolower((string) config('services.whatsapp.provider', 'meta'));

        if ($provider === 'ycloud') {
            $secret = (string) config('services.whatsapp.ycloud_webhook_secret', '');
            $signature = (string) ($request->header('X-YCloud-Signature')
                ?? $request->header('X-YC-Signature')
                ?? $request->header('X-Signature')
                ?? '');

            return $this->validateSha256Hmac($payload, $signature, $secret);
        }

        $secret = (string) config('services.whatsapp.meta_app_secret', '');
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        return $this->validateSha256Hmac($payload, $signature, $secret);
    }

    private function validateSha256Hmac(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || $signatureHeader === '') {
            return false;
        }

        $signature = trim($signatureHeader);
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
