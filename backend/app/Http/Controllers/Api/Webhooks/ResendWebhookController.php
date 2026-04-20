<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ResendWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ResendWebhookController extends Controller
{
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

        $eventId = (string) Arr::get(
            $event,
            'id',
            Arr::get($event, 'event_id', Arr::get($event, 'data.id', (string) $request->header('svix-id', '')))
        );

        if ($eventId === '') {
            $eventId = sha1($payload);
        }

        $eventType = (string) Arr::get($event, 'type', Arr::get($event, 'event', ''));

        $storedEvent = ResendWebhookEvent::query()->firstOrCreate(
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

    private function isValidSignature(Request $request, string $payload): bool
    {
        $secret = (string) config('services.resend.webhook_secret', '');

        if ($secret === '') {
            return false;
        }

        $svixId = (string) $request->header('svix-id', '');
        $svixTimestamp = (string) $request->header('svix-timestamp', '');
        $svixSignature = (string) $request->header('svix-signature', '');

        if ($svixId !== '' && $svixTimestamp !== '' && $svixSignature !== '') {
            return $this->isValidSvixSignature($payload, $secret, $svixId, $svixTimestamp, $svixSignature);
        }

        $signature = (string) ($request->header('Resend-Signature')
            ?? $request->header('X-Resend-Signature')
            ?? '');

        return $this->isValidHmacSignature($payload, $secret, $signature);
    }

    private function isValidHmacSignature(string $payload, string $secret, string $signatureHeader): bool
    {
        if ($signatureHeader === '') {
            return false;
        }

        $signature = trim($signatureHeader);
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function isValidSvixSignature(
        string $payload,
        string $secret,
        string $svixId,
        string $svixTimestamp,
        string $svixSignatureHeader
    ): bool {
        if (! ctype_digit($svixTimestamp)) {
            return false;
        }

        if (abs(time() - (int) $svixTimestamp) > 300) {
            return false;
        }

        $signedContent = $svixId.'.'.$svixTimestamp.'.'.$payload;
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $secret, true));

        $parts = preg_split('/\s+/', trim($svixSignatureHeader)) ?: [];
        foreach ($parts as $part) {
            $chunks = explode(',', $part, 2);
            if (count($chunks) !== 2) {
                continue;
            }

            [$version, $signature] = $chunks;
            if (trim($version) !== 'v1') {
                continue;
            }

            if (hash_equals($expected, trim($signature))) {
                return true;
            }
        }

        return false;
    }
}
