<?php

namespace App\Services;

use App\Jobs\ProcesarTranscripcionJob;
use App\Models\Cita;
use App\Models\Grabacion;
use Illuminate\Support\Arr;

class DailyRecordingSyncService
{
    public function __construct(private readonly DailyRoomService $daily)
    {
    }

    /**
     * @return array{checked:int, imported:int, processed:int, skipped:int}
     */
    public function sync(int $limit = 25, bool $processNow = false): array
    {
        $stats = [
            'checked' => 0,
            'imported' => 0,
            'processed' => 0,
            'skipped' => 0,
        ];

        foreach ($this->daily->listarGrabaciones($limit) as $recording) {
            $stats['checked']++;

            $recordingId = (string) Arr::get($recording, 'id', '');
            $roomName = (string) Arr::get($recording, 'room_name', Arr::get($recording, 'room', ''));
            $status = (string) Arr::get($recording, 'status', '');

            if ($recordingId === '' || $roomName === '' || $status !== 'finished') {
                $stats['skipped']++;
                continue;
            }

            $cita = Cita::query()->where('daily_room_name', $roomName)->first();
            if (! $cita) {
                $stats['skipped']++;
                continue;
            }

            $grabacion = Grabacion::query()
                ->where('daily_recording_id', $recordingId)
                ->orWhere(function ($query) use ($recordingId, $roomName) {
                    $query->where('daily_recording_id', $recordingId)
                        ->where('daily_room_name', $roomName);
                })
                ->first();

            if ($grabacion && $grabacion->transcripcion_procesada_en) {
                $stats['skipped']++;
                continue;
            }

            $access = $this->daily->obtenerAccessLinkGrabacion($recordingId);
            $downloadLink = (string) Arr::get($access, 'download_link', '');
            if ($downloadLink === '') {
                $stats['skipped']++;
                continue;
            }

            if (! $grabacion) {
                $grabacion = new Grabacion();
                $stats['imported']++;
            }

            $grabacion->fill([
                'cita_id' => $cita->id,
                'daily_recording_id' => $recordingId,
                'daily_room_name' => $roomName,
                'url_grabacion' => $downloadLink,
                'estado' => 'pendiente_transcripcion',
                'metadata' => array_merge((array) ($grabacion->metadata ?? []), [
                    'daily_sync' => [
                        'recording' => $recording,
                        'access_link_expires' => Arr::get($access, 'expires'),
                        'synced_at' => now()->toIso8601String(),
                    ],
                ]),
                'expira_en' => now()->addDays((int) config('services.daily.recording_retention_days', 90)),
            ]);
            $grabacion->save();

            if ($processNow) {
                (new ProcesarTranscripcionJob($grabacion->id))->handle();
            } else {
                ProcesarTranscripcionJob::dispatch($grabacion->id);
            }

            $stats['processed']++;
        }

        return $stats;
    }
}
