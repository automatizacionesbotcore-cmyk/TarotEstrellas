<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Jobs\ProcesarTranscripcionJob;
use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\DailyWebhookEvent;
use App\Models\Grabacion;
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

        if ($eventType === 'recording.ready') {
            $this->handleRecordingReady($storedEvent, $event);
        }

        if ($eventType === 'recording.started') {
            $this->handleRecordingStarted($storedEvent, $event);
        }

        if ($eventType === 'recording.error') {
            $this->handleRecordingError($storedEvent, $event);
        }

        if ($eventType === 'meeting.started') {
            $this->handleMeetingEvent($storedEvent, $event, 'sesion_iniciada');
        }

        if ($eventType === 'meeting.ended') {
            $this->handleMeetingEvent($storedEvent, $event, 'sesion_finalizada');
        }

        return response()->json([
            'received' => true,
            'event_type' => $eventType,
        ]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleRecordingReady(DailyWebhookEvent $storedEvent, array $event): void
    {
        $data = (array) Arr::get($event, 'data', []);

        $citaUuid = (string) Arr::get(
            $event,
            'metadata.cita_uuid',
            Arr::get($data, 'metadata.cita_uuid', Arr::get($data, 'cita_uuid', ''))
        );

        $recordingId = (string) Arr::get($data, 'id', Arr::get($data, 'recording_id', ''));
        $roomName = (string) Arr::get($data, 'room', Arr::get($event, 'room', ''));
        $recordingUrl = (string) Arr::get(
            $data,
            'url',
            Arr::get($data, 'download_url', Arr::get($data, 'recording_url', ''))
        );

        $citaId = $this->resolveCitaId($citaUuid);

        $grabacion = Grabacion::query()
            ->when($recordingId !== '', fn ($query) => $query->where('daily_recording_id', $recordingId))
            ->when($recordingId === '' && $roomName !== '', fn ($query) => $query->where('daily_room_name', $roomName))
            ->when($citaId !== null, fn ($query) => $query->where('cita_id', $citaId))
            ->latest('id')
            ->first();

        if (! $grabacion) {
            $grabacion = new Grabacion();
        }

        $grabacion->fill([
            'cita_id' => $citaId,
            'daily_webhook_event_id' => $storedEvent->id,
            'daily_recording_id' => $recordingId !== '' ? $recordingId : $grabacion->daily_recording_id,
            'daily_room_name' => $roomName !== '' ? $roomName : $grabacion->daily_room_name,
            'url_grabacion' => $recordingUrl !== '' ? $recordingUrl : $grabacion->url_grabacion,
            'estado' => 'pendiente_transcripcion',
            'metadata' => $event,
        ]);
        $grabacion->save();

        ProcesarTranscripcionJob::dispatch($grabacion->id);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleRecordingStarted(DailyWebhookEvent $storedEvent, array $event): void
    {
        [$citaId, $recordingId, $roomName] = $this->extractRecordingContext($event);

        $grabacion = Grabacion::query()
            ->when($recordingId !== '', fn ($query) => $query->where('daily_recording_id', $recordingId))
            ->when($recordingId === '' && $roomName !== '', fn ($query) => $query->where('daily_room_name', $roomName))
            ->when($citaId !== null, fn ($query) => $query->where('cita_id', $citaId))
            ->latest('id')
            ->first();

        if (! $grabacion) {
            $grabacion = new Grabacion();
        }

        $grabacion->fill([
            'cita_id' => $citaId,
            'daily_webhook_event_id' => $storedEvent->id,
            'daily_recording_id' => $recordingId !== '' ? $recordingId : $grabacion->daily_recording_id,
            'daily_room_name' => $roomName !== '' ? $roomName : $grabacion->daily_room_name,
            'estado' => 'grabando',
            'metadata' => $event,
        ]);
        $grabacion->save();
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleRecordingError(DailyWebhookEvent $storedEvent, array $event): void
    {
        [$citaId, $recordingId, $roomName] = $this->extractRecordingContext($event);

        $grabacion = Grabacion::query()
            ->when($recordingId !== '', fn ($query) => $query->where('daily_recording_id', $recordingId))
            ->when($recordingId === '' && $roomName !== '', fn ($query) => $query->where('daily_room_name', $roomName))
            ->when($citaId !== null, fn ($query) => $query->where('cita_id', $citaId))
            ->latest('id')
            ->first();

        if (! $grabacion) {
            $grabacion = new Grabacion();
        }

        $grabacion->fill([
            'cita_id' => $citaId,
            'daily_webhook_event_id' => $storedEvent->id,
            'daily_recording_id' => $recordingId !== '' ? $recordingId : $grabacion->daily_recording_id,
            'daily_room_name' => $roomName !== '' ? $roomName : $grabacion->daily_room_name,
            'estado' => 'error_grabacion',
            'metadata' => $event,
        ]);
        $grabacion->save();
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleMeetingEvent(DailyWebhookEvent $storedEvent, array $event, string $estado): void
    {
        $data = (array) Arr::get($event, 'data', []);

        $citaUuid = (string) Arr::get(
            $event,
            'metadata.cita_uuid',
            Arr::get($data, 'metadata.cita_uuid', Arr::get($data, 'cita_uuid', ''))
        );
        $roomName = (string) Arr::get($data, 'room', Arr::get($event, 'room', ''));

        $citaId = $this->resolveCitaId($citaUuid);

        $grabacion = Grabacion::query()
            ->when($roomName !== '', fn ($query) => $query->where('daily_room_name', $roomName))
            ->when($citaId !== null, fn ($query) => $query->where('cita_id', $citaId))
            ->latest('id')
            ->first();

        if (! $grabacion) {
            $grabacion = new Grabacion();
        }

        $grabacion->fill([
            'cita_id' => $citaId,
            'daily_webhook_event_id' => $storedEvent->id,
            'daily_room_name' => $roomName !== '' ? $roomName : $grabacion->daily_room_name,
            'estado' => $estado,
            'metadata' => $event,
        ]);
        $grabacion->save();
    }

    private function resolveCitaId(string $citaUuid): ?int
    {
        if ($citaUuid === '') {
            return null;
        }

        /** @var int|null $citaId */
        $citaId = Cita::query()->where('uuid', $citaUuid)->value('id');

        return $citaId;
    }

    /**
     * @param array<string, mixed> $event
     * @return array{0: int|null, 1: string, 2: string}
     */
    private function extractRecordingContext(array $event): array
    {
        $data = (array) Arr::get($event, 'data', []);

        $citaUuid = (string) Arr::get(
            $event,
            'metadata.cita_uuid',
            Arr::get($data, 'metadata.cita_uuid', Arr::get($data, 'cita_uuid', ''))
        );

        $recordingId = (string) Arr::get($data, 'id', Arr::get($data, 'recording_id', ''));
        $roomName = (string) Arr::get($data, 'room', Arr::get($event, 'room', ''));

        return [$this->resolveCitaId($citaUuid), $recordingId, $roomName];
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
