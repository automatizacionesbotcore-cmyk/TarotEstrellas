<?php

namespace App\Services;

use App\Models\AgenteConversacion;
use App\Models\Resumen;
use App\Models\Transcripcion;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AgenteIAService
{
    /**
     * Responde una pregunta sobre el historial de un cliente.
     *
     * @return array{conversacion: AgenteConversacion}
     *
     * @throws RuntimeException si la API key no está configurada o Anthropic falla.
     */
    public function responder(User $cliente, User $autor, string $autorRol, string $pregunta): array
    {
        $apiKey = (string) config('services.anthropic.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY no configurada.');
        }

        $model = (string) config('services.anthropic.agente_model', 'claude-3-5-sonnet-latest');
        $maxSesiones = (int) config('services.anthropic.agente_max_sesiones', 20);
        $maxTokens = (int) config('services.anthropic.agente_max_tokens', 1024);

        [$contexto, $contextoSesiones] = $this->construirContexto($cliente, $maxSesiones);

        $systemPrompt = $this->systemPrompt($cliente, $autorRol);
        $userMessage = $this->userMessage($contexto, $pregunta);

        $start = microtime(true);
        $respuestaTexto = null;
        $tokensIn = null;
        $tokensOut = null;
        $error = null;

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.4,
                    'system' => $systemPrompt,
                    'messages' => [[
                        'role' => 'user',
                        'content' => $userMessage,
                    ]],
                ]);

            if (! $response->successful()) {
                $error = 'Anthropic devolvio HTTP '.$response->status().': '.$response->body();
            } else {
                $json = $response->json();
                $respuestaTexto = trim((string) data_get($json, 'content.0.text', ''));
                $tokensIn = (int) data_get($json, 'usage.input_tokens', 0) ?: null;
                $tokensOut = (int) data_get($json, 'usage.output_tokens', 0) ?: null;

                if ($respuestaTexto === '') {
                    $error = 'Anthropic no devolvio contenido textual.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Excepcion llamando a Anthropic: '.$e->getMessage();
            Log::error('AgenteIAService error', ['message' => $e->getMessage()]);
        }

        $latenciaMs = (int) round((microtime(true) - $start) * 1000);

        $conversacion = AgenteConversacion::query()->create([
            'cliente_id' => $cliente->id,
            'autor_user_id' => $autor->id,
            'autor_rol' => $autorRol,
            'pregunta' => $pregunta,
            'respuesta' => $respuestaTexto,
            'proveedor' => 'anthropic',
            'modelo' => $model,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'latencia_ms' => $latenciaMs,
            'contexto_sesiones' => $contextoSesiones,
            'error' => $error,
        ]);

        if ($error !== null) {
            throw new RuntimeException($error);
        }

        return ['conversacion' => $conversacion];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function construirContexto(User $cliente, int $maxSesiones): array
    {
        $resumenes = Resumen::query()
            ->whereHas('cita', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->with('cita:id,uuid,inicio_utc,tema_principal,tipo_consulta_id')
            ->orderByDesc('created_at')
            ->limit($maxSesiones)
            ->get();

        if ($resumenes->isEmpty()) {
            return ["No hay sesiones previas registradas para este cliente.", 0];
        }

        $bloques = [];
        foreach ($resumenes as $r) {
            $fecha = optional($r->cita?->inicio_utc)->toDateString() ?? 'sin fecha';
            $tema = $r->cita?->tema_principal ?? 'sin tema';
            $bloques[] = "[Sesion {$fecha} - Tema: {$tema}]\n".trim((string) $r->contenido);
        }

        $sesionesConResumen = $resumenes->pluck('cita_id')->filter()->all();
        $transcripcionesExtra = Transcripcion::query()
            ->whereHas('cita', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->whereNotIn('cita_id', $sesionesConResumen)
            ->with('cita:id,inicio_utc,tema_principal')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        foreach ($transcripcionesExtra as $t) {
            $fecha = optional($t->cita?->inicio_utc)->toDateString() ?? 'sin fecha';
            $tema = $t->cita?->tema_principal ?? 'sin tema';
            $contenido = mb_substr((string) $t->contenido, 0, 4000);
            $bloques[] = "[Transcripcion {$fecha} - Tema: {$tema}]\n".$contenido;
        }

        return [implode("\n\n---\n\n", $bloques), $resumenes->count() + $transcripcionesExtra->count()];
    }

    private function systemPrompt(User $cliente, string $autorRol): string
    {
        $rolDescripcion = $autorRol === 'admin'
            ? "Tu interlocutor es la especialista esoterica que atiende al cliente. Habla de forma profesional y sintetica, ayudando a preparar la proxima sesion."
            : "Tu interlocutor es el propio cliente consultando su historial. Habla en segunda persona, con tono empatico y respetuoso.";

        return implode("\n", [
            "Eres el asistente IA de TarotEstrellas, una plataforma de consultas esotericas.",
            "Respondes en espanol neutro, claro y empatico, sin inventar datos.",
            "Solo usas la informacion del HISTORIAL provisto. Si no hay datos suficientes, lo dices explicitamente.",
            "Cliente sobre el que se consulta: {$cliente->name}.",
            $rolDescripcion,
            "Nunca reveles contenido textual literal de otras personas; resume y sintetiza.",
        ]);
    }

    private function userMessage(string $contexto, string $pregunta): string
    {
        return "HISTORIAL DEL CLIENTE:\n\n{$contexto}\n\n---\n\nPREGUNTA: {$pregunta}";
    }
}
