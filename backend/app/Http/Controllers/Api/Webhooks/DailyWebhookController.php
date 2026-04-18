<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\DailyWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DailyWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) ($request->header('X-Daily-Signature')
            ?? $request->header('Daily-Signature')
            ?? '');
        $secret = (string) config('services.daily.webhook_secret', '');

        if ($secret === '' || ! $this->isValidSignature($payload, $signature, $secret)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 400);
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true) ?? [];
        $eventId = (string) Arr::get($event, 'id', Arr::get($event, 'event_id', ''));
        $eventType = (string) Arr::get($event, 'type', Arr::get($event, 'event', ''));

        if ($eventId === '') {
            return response()->json([
                'message' => 'Invalid Daily event id.',
            ], 400);
        }

        $storedEvent = DailyWebhookEvent::query()->firstOrCreate(
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

    private function isValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        $header = trim($signatureHeader);

        if (str_starts_with($header, 'sha256=')) {
            $header = substr($header, 7);
        }

        return hash_equals($expected, $header);
    }
}
