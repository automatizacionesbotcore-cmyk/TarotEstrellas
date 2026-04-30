<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Resumen;
use App\Models\Transcripcion;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Genera un briefing ejecutivo para Chachita antes de una consulta:
 * resumen del cliente, temas recurrentes, ultima sesion y recomendaciones.
 */
class BriefingIAService
{
    /**
     * @param User $cliente cliente sobre el que se genera el briefing
     * @param Cita|null $citaProxima si se entrega, agrega contexto sobre la consulta agendada
     * @return array{contenido:string, generado_en:string, modelo:string, tokens_in:?int, tokens_out:?int, sesiones_consideradas:int}
     */
    public function generar(User $cliente, ?Cita $citaProxima = null): array
    {
        $apiKey = (string) config('services.anthropic.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY no configurada.');
        }

        $model = (string) config('services.anthropic.briefing_model', config('services.anthropic.agente_model', 'claude-3-5-sonnet-latest'));
        $maxSesiones = (int) config('services.anthropic.briefing_max_sesiones', 15);

        [$historial, $sesionesConsideradas] = $this->construirHistorial($cliente, $maxSesiones);
        $perfil = $this->perfilTexto($cliente);
        $proxima = $citaProxima ? $this->citaProximaTexto($citaProxima) : '';

        $systemPrompt = implode("\n", [
            'Eres Astrea, asistente IA de TarotEstrellas, generando un briefing privado para Chachita (la administradora y especialista) antes de una consulta.',
            'Devuelve la respuesta en Markdown estructurado en estas secciones EXACTAS y en este orden:',
            '## Resumen del cliente',
            '## Temas recurrentes',
            '## Ultima sesion',
            '## Datos natales relevantes',
            '## Recomendaciones para la proxima sesion',
            '',
            'Reglas:',
            '- Usa lenguaje profesional, conciso y empatico.',
            '- Si una seccion no aplica por falta de datos, escribe "Sin informacion suficiente".',
            '- Nunca inventes datos: solo apoyate en el historial provisto.',
            '- Maximo 600 palabras totales.',
        ]);

        $userMessage = "PERFIL:\n{$perfil}\n\n";
        $userMessage .= $proxima !== '' ? "PROXIMA CITA:\n{$proxima}\n\n" : '';
        $userMessage .= "HISTORIAL DEL CLIENTE:\n{$historial}\n\n";
        $userMessage .= 'Genera el briefing siguiendo el formato indicado.';

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 1500,
                    'temperature' => 0.3,
                    'system' => $systemPrompt,
                    'messages' => [['role' => 'user', 'content' => $userMessage]],
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Anthropic devolvio HTTP '.$response->status().': '.$response->body());
            }

            $json = $response->json();
            $contenido = trim((string) data_get($json, 'content.0.text', ''));
            if ($contenido === '') {
                throw new RuntimeException('Anthropic no devolvio contenido textual.');
            }

            return [
                'contenido' => $contenido,
                'generado_en' => now()->toIso8601String(),
                'modelo' => $model,
                'tokens_in' => (int) data_get($json, 'usage.input_tokens', 0) ?: null,
                'tokens_out' => (int) data_get($json, 'usage.output_tokens', 0) ?: null,
                'sesiones_consideradas' => $sesionesConsideradas,
            ];
        } catch (Throwable $e) {
            Log::error('BriefingIAService error', ['cliente_id' => $cliente->id, 'message' => $e->getMessage()]);
            throw new RuntimeException('No se pudo generar el briefing: '.$e->getMessage());
        }
    }

    /**
     * @return array{0:string,1:int}
     */
    private function construirHistorial(User $cliente, int $maxSesiones): array
    {
        $resumenes = Resumen::query()
            ->whereHas('cita', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->with('cita:id,uuid,inicio_utc,tema_principal,tipo_consulta_id,duracion_minutos')
            ->orderByDesc('created_at')
            ->limit($maxSesiones)
            ->get();

        if ($resumenes->isEmpty()) {
            $citasSimple = Cita::query()
                ->where('cliente_id', $cliente->id)
                ->where('estado', 'completada')
                ->orderByDesc('inicio_utc')
                ->limit(5)
                ->get(['inicio_utc', 'tema_principal', 'duracion_minutos']);
            if ($citasSimple->isEmpty()) {
                return ['Sin sesiones previas registradas. Es la primera consulta del cliente.', 0];
            }
            $bloques = $citasSimple->map(fn ($c) => '- '.($c->inicio_utc?->toDateString() ?? 'sin fecha').' ('.$c->duracion_minutos.' min): '.($c->tema_principal ?? 'sin tema'))->implode("\n");
            return ["Sesiones completadas (sin resumen IA disponible):\n".$bloques, $citasSimple->count()];
        }

        $bloques = [];
        foreach ($resumenes as $r) {
            $fecha = optional($r->cita?->inicio_utc)->toDateString() ?? 'sin fecha';
            $tema = $r->cita?->tema_principal ?? 'sin tema';
            $bloques[] = "[Sesion {$fecha} - Tema: {$tema}]\n".trim((string) $r->contenido);
        }

        return [implode("\n\n---\n\n", $bloques), $resumenes->count()];
    }

    private function perfilTexto(User $cliente): string
    {
        $cliente->loadMissing(['profile', 'datoNatal']);
        $p = $cliente->profile;
        $n = $cliente->datoNatal;
        $lineas = [
            'Nombre: '.($cliente->name ?? '-'),
            'Email: '.($cliente->email ?? '-'),
            'Cliente desde: '.($cliente->created_at?->toDateString() ?? '-'),
        ];
        if ($p) {
            $lineas[] = 'Pais residencia: '.($p->pais_residencia ?? '-');
            if ($p->fecha_nacimiento_publica) {
                $lineas[] = 'Fecha de nacimiento: '.$p->fecha_nacimiento_publica->toDateString();
            }
        }
        if ($n) {
            $lineas[] = 'Datos natales: '.json_encode([
                'fecha' => $n->fecha_nacimiento ?? null,
                'hora' => $n->hora_nacimiento ?? null,
                'lugar' => $n->lugar_nacimiento ?? null,
                'signo_solar' => $n->signo_solar ?? null,
            ], JSON_UNESCAPED_UNICODE);
        }
        return implode("\n", $lineas);
    }

    private function citaProximaTexto(Cita $cita): string
    {
        $cita->loadMissing('tipoConsulta:id,nombre');
        return implode("\n", [
            'Tipo: '.($cita->tipoConsulta?->nombre ?? '-'),
            'Fecha (UTC): '.($cita->inicio_utc?->toIso8601String() ?? '-'),
            'Duracion: '.$cita->duracion_minutos.' min',
            'Tema declarado: '.($cita->tema_principal ?? 'sin tema'),
            'Notas del cliente: '.($cita->notas_cliente ?? '-'),
        ]);
    }
}
