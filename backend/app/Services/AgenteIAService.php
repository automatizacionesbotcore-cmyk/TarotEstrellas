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
     * Responde una pregunta del agente IA.
     *
     * Modos:
     *  - publico:  $cliente=null, $autor=null, $autorRol='publico'  -> solo plataforma.
     *  - cliente:  $cliente=usuario, $autor=usuario, $autorRol='cliente' -> perfil + propio historial + plataforma.
     *  - admin:    $cliente=null|target, $autor=admin, $autorRol='admin' -> plataforma + (opcional) historial cliente target.
     *
     * @return array{conversacion: AgenteConversacion}
     *
     * @throws RuntimeException
     */
    public function responder(?User $cliente, ?User $autor, string $autorRol, string $pregunta): array
    {
        $apiKey = (string) config('services.anthropic.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY no configurada.');
        }

        $model = (string) config('services.anthropic.agente_model', 'claude-3-5-sonnet-latest');
        $maxSesiones = (int) config('services.anthropic.agente_max_sesiones', 20);
        $maxTokens = (int) config('services.anthropic.agente_max_tokens', 1024);

        [$historial, $contextoSesiones] = $cliente
            ? $this->construirHistorial($cliente, $maxSesiones)
            : ['', 0];

        $systemPrompt = $this->systemPrompt($cliente, $autor, $autorRol);
        $userMessage = $this->userMessage($cliente, $historial, $pregunta);

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
            'cliente_id' => $cliente?->id,
            'autor_user_id' => $autor?->id,
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
    private function construirHistorial(User $cliente, int $maxSesiones): array
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

    private function systemPrompt(?User $cliente, ?User $autor, string $autorRol): string
    {
        $rol = match ($autorRol) {
            'admin'   => "Tu interlocutor es la administradora (Chachita) de la plataforma. Habla de forma profesional y sintetica; puede preguntarte por cualquier cliente. Si necesita el historial de un cliente que no esta en el contexto, indicale que abra el panel admin del cliente o te pase su nombre/email.",
            'cliente' => "Tu interlocutor es ".($cliente?->name ?? 'el cliente').". Trato en segunda persona, calido y empatico. Puedes referirte a su historial cuando sea relevante.",
            default   => "Tu interlocutor es un visitante anonimo de la plataforma. No tienes acceso a datos personales suyos. Responde dudas generales y, si conviene, invitalo a registrarse para obtener acompanamiento personalizado.",
        };

        $perfil = '';
        if ($cliente) {
            $perfil = "\nDatos del cliente actual:\n- Nombre: {$cliente->name}\n- Email: {$cliente->email}";
            if ($cliente->created_at) {
                $perfil .= "\n- Cliente desde: ".$cliente->created_at->toDateString();
            }
        }

        return implode("\n", [
            "Eres el Asistente IA de TarotEstrellas.",
            "Responde en espanol neutro, calido, claro y conciso. Nunca inventes datos.",
            "Cuando uses el historial, sintetiza; nunca cites textualmente conversaciones de terceros.",
            "Si no tienes informacion suficiente, dilo y sugiere a quien preguntar (Chachita o soporte).",
            $rol,
            $perfil,
            "",
            AgentePlataformaContext::knowledge(),
        ]);
    }

    private function userMessage(?User $cliente, string $historial, string $pregunta): string
    {
        if ($cliente && $historial !== '') {
            return "HISTORIAL DEL CLIENTE ({$cliente->name}):\n\n{$historial}\n\n---\n\nPREGUNTA: {$pregunta}";
        }

        return "PREGUNTA: {$pregunta}";
    }
}
