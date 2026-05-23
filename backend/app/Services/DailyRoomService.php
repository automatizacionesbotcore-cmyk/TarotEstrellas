<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class DailyRoomService
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $domain = null,
    ) {
    }

    private function key(): string
    {
        return $this->apiKey ?? (string) config('services.daily.api_key', '');
    }

    private function url(): string
    {
        return rtrim($this->baseUrl ?? (string) config('services.daily.base_url', 'https://api.daily.co/v1'), '/');
    }

    private function dom(): string
    {
        return (string) ($this->domain ?? config('services.daily.domain', ''));
    }

    public function isMockMode(): bool
    {
        return $this->key() === '';
    }

    /**
     * Crea (o reutiliza) la sala Daily para la cita y persiste daily_room_url/name en la cita.
     *
     * @return array{room_url:string,room_name:string,mock:bool}
     */
    public function crearSala(Cita $cita): array
    {
        if ($cita->daily_room_url && $cita->daily_room_name) {
            return [
                'room_url' => (string) $cita->daily_room_url,
                'room_name' => (string) $cita->daily_room_name,
                'mock' => $this->isMockMode(),
            ];
        }

        $name = 'cita-' . Str::slug((string) $cita->uuid);
        $exp = $cita->fin_utc?->copy()->addMinutes(30)->getTimestamp() ?? (time() + 7200);

        $properties = [
            'exp' => $exp,
            'enable_prejoin_ui' => true,
            'enable_screenshare' => true,
            'enable_chat' => true,
            'eject_at_room_exp' => true,
            'enable_recording' => $cita->grabacion_solicitada ? 'cloud-audio-only' : 'off',
            'enable_transcription_storage' => (bool) config('services.daily.enable_transcription', true),
        ];

        if ($this->isMockMode()) {
            $url = 'https://mock.daily.co/' . $name;
            Log::info('DailyRoomService::crearSala (mock)', ['cita_id' => $cita->id, 'name' => $name]);
            $cita->forceFill([
                'daily_room_url' => $url,
                'daily_room_name' => $name,
            ])->save();
            return ['room_url' => $url, 'room_name' => $name, 'mock' => true];
        }

        $response = Http::timeout(20)
            ->withToken($this->key())
            ->acceptJson()
            ->post($this->url() . '/rooms', [
                'name' => $name,
                'privacy' => 'private',
                'properties' => $properties,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Daily.crearSala fallo: ' . $response->status() . ' ' . $response->body());
        }

        $url = (string) ($response->json('url') ?? ($this->dom() ? "https://{$this->dom()}/{$name}" : ''));
        $cita->forceFill([
            'daily_room_url' => $url,
            'daily_room_name' => $name,
        ])->save();

        return ['room_url' => $url, 'room_name' => $name, 'mock' => false];
    }

    /**
     * Crea un meeting token JWT para que el usuario entre a la sala.
     */
    public function crearMeetingToken(Cita $cita, User $user, bool $isOwner): string
    {
        if (! $cita->daily_room_name) {
            throw new RuntimeException('La cita no tiene daily_room_name; crea la sala primero.');
        }

        $exp = $cita->fin_utc?->copy()->addMinutes(30)->getTimestamp() ?? (time() + 7200);
        $userName = trim(($user->profile->nombre ?? '') . ' ' . ($user->profile->apellido ?? '')) ?: $user->email;

        if ($this->isMockMode()) {
            return base64_encode(json_encode([
                'mock' => true,
                'room' => $cita->daily_room_name,
                'user' => $userName,
                'is_owner' => $isOwner,
                'exp' => $exp,
            ]));
        }

        $response = Http::timeout(15)
            ->withToken($this->key())
            ->acceptJson()
            ->post($this->url() . '/meeting-tokens', [
                'properties' => [
                    'room_name' => $cita->daily_room_name,
                    'user_name' => $userName,
                    'user_id' => (string) $user->id,
                    'is_owner' => $isOwner,
                    'exp' => $exp,
                    'enable_recording' => $cita->grabacion_solicitada ? 'cloud-audio-only' : 'off',
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Daily.crearMeetingToken fallo: ' . $response->status() . ' ' . $response->body());
        }

        return (string) $response->json('token');
    }

    public function eliminarSala(string $roomName): void
    {
        if ($this->isMockMode() || $roomName === '') {
            return;
        }

        Http::timeout(15)
            ->withToken($this->key())
            ->delete($this->url() . '/rooms/' . $roomName);
    }

    public function eliminarGrabacion(string $recordingId): void
    {
        if ($this->isMockMode() || $recordingId === '') {
            return;
        }

        Http::timeout(15)
            ->withToken($this->key())
            ->delete($this->url() . '/recordings/' . $recordingId);
    }

    public function iniciarGrabacion(string $roomName): bool
    {
        if ($this->isMockMode()) {
            return true;
        }

        $response = Http::timeout(15)
            ->withToken($this->key())
            ->post($this->url() . '/rooms/' . $roomName . '/recordings/start');

        return $response->successful();
    }

    public function detenerGrabacion(string $roomName): bool
    {
        if ($this->isMockMode()) {
            return true;
        }

        $response = Http::timeout(15)
            ->withToken($this->key())
            ->post($this->url() . '/rooms/' . $roomName . '/recordings/stop');

        return $response->successful();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarGrabaciones(int $limit = 25): array
    {
        if ($this->isMockMode()) {
            return [];
        }

        $response = Http::timeout(20)
            ->withToken($this->key())
            ->acceptJson()
            ->get($this->url() . '/recordings', [
                'limit' => $limit,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Daily.listarGrabaciones fallo: ' . $response->status() . ' ' . $response->body());
        }

        return (array) $response->json('data', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerAccessLinkGrabacion(string $recordingId): array
    {
        if ($this->isMockMode() || $recordingId === '') {
            return [];
        }

        $response = Http::timeout(20)
            ->withToken($this->key())
            ->acceptJson()
            ->get($this->url() . '/recordings/' . $recordingId . '/access-link');

        if (! $response->successful()) {
            throw new RuntimeException('Daily.obtenerAccessLinkGrabacion fallo: ' . $response->status() . ' ' . $response->body());
        }

        return (array) $response->json();
    }
}
