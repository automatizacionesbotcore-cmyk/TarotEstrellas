<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarAgenteRequest;
use App\Models\AgenteConversacion;
use App\Models\User;
use App\Services\AgenteIAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgenteController extends Controller
{
    public function __construct(private readonly AgenteIAService $agente)
    {
    }

    /**
     * POST /api/agente/publico  (visitantes anonimos, solo conocimiento de plataforma)
     */
    public function consultarPublico(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pregunta' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            $resultado = $this->agente->responder(
                cliente: null,
                autor: null,
                autorRol: 'publico',
                pregunta: $data['pregunta'],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'El agente IA no esta disponible.', 'detalle' => $e->getMessage()], 502);
        }

        return response()->json($this->serialize($resultado['conversacion']), 201);
    }

    /**
     * POST /api/agente/consultar  (admin)
     * Body: { pregunta, cliente_uuid? }
     */
    public function consultarAdmin(ConsultarAgenteRequest $request): JsonResponse
    {
        $clienteUuid = (string) $request->string('cliente_uuid');
        $cliente = null;
        if ($clienteUuid !== '') {
            $cliente = User::query()->where('uuid', $clienteUuid)->first();
            if (! $cliente) {
                return response()->json(['message' => 'Cliente no encontrado.'], 404);
            }
        }

        try {
            $resultado = $this->agente->responder(
                cliente: $cliente,
                autor: $request->user(),
                autorRol: 'admin',
                pregunta: (string) $request->string('pregunta'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'El agente IA no esta disponible.', 'detalle' => $e->getMessage()], 502);
        }

        return response()->json($this->serialize($resultado['conversacion']), 201);
    }

    /**
     * POST /api/me/agente/consultar  (cliente sobre su propio historial)
     */
    public function consultarSelf(ConsultarAgenteRequest $request): JsonResponse
    {
        $cliente = $request->user();

        try {
            $resultado = $this->agente->responder(
                cliente: $cliente,
                autor: $cliente,
                autorRol: 'cliente',
                pregunta: (string) $request->string('pregunta'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'El agente IA no esta disponible.', 'detalle' => $e->getMessage()], 502);
        }

        return response()->json($this->serialize($resultado['conversacion']), 201);
    }

    /**
     * GET /api/agente/conversaciones?cliente_uuid=  (admin, paginado)
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'cliente_uuid' => ['sometimes', 'string', 'uuid'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AgenteConversacion::query()
            ->with(['autor:id,uuid,name,email', 'cliente:id,uuid,name,email']);

        $clienteUuid = (string) $request->string('cliente_uuid');
        if ($clienteUuid !== '') {
            $cliente = User::query()->where('uuid', $clienteUuid)->first();
            if (! $cliente) {
                return response()->json(['message' => 'Cliente no encontrado.'], 404);
            }
            $query->where('cliente_id', $cliente->id);
        } else {
            // Por defecto: conversaciones autoradas por el admin actual.
            $query->where('autor_user_id', $request->user()->id);
        }

        $rows = $query->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => $rows->getCollection()->map(fn ($r) => $this->serialize($r))->all(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * GET /api/me/agente/conversaciones  (cliente, su propio historial)
     */
    public function indexSelf(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = AgenteConversacion::query()
            ->where('cliente_id', $request->user()->id)
            ->where('autor_rol', 'cliente')
            ->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => $rows->getCollection()->map(fn ($r) => $this->serialize($r))->all(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    private function serialize(AgenteConversacion $c): array
    {
        return [
            'uuid' => $c->uuid,
            'cliente_id' => $c->cliente_id,
            'autor_rol' => $c->autor_rol,
            'autor' => $c->relationLoaded('autor') && $c->autor ? [
                'uuid' => $c->autor->uuid,
                'name' => $c->autor->name,
            ] : null,
            'cliente' => $c->relationLoaded('cliente') && $c->cliente ? [
                'uuid' => $c->cliente->uuid,
                'name' => $c->cliente->name,
            ] : null,
            'pregunta' => $c->pregunta,
            'respuesta' => $c->respuesta,
            'modelo' => $c->modelo,
            'tokens_in' => $c->tokens_in,
            'tokens_out' => $c->tokens_out,
            'latencia_ms' => $c->latencia_ms,
            'contexto_sesiones' => $c->contexto_sesiones,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
