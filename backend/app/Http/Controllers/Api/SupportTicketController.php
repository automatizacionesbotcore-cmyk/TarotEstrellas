<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SupportTicketCreatedMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function storePublic(Request $request): JsonResponse
    {
        $validated = $this->validateTicket($request, false);

        $ticket = $this->createTicket($validated, null, $request);

        return response()->json([
            'message' => 'Solicitud de soporte creada correctamente. Te enviaremos actualizaciones por correo.',
            'data' => $this->ticketPayload($ticket),
        ], 201);
    }

    public function storeAuthenticated(Request $request): JsonResponse
    {
        $validated = $this->validateTicket($request, true);
        $user = $request->user();
        $validated['nombre'] = ($validated['nombre'] ?? null) ?: $user->name;
        $validated['email'] = $user->email;

        $ticket = $this->createTicket($validated, $user->id, $request);

        return response()->json([
            'message' => 'Solicitud de soporte creada correctamente.',
            'data' => $this->ticketPayload($ticket),
        ], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->withCount('messages')
            ->latest()
            ->paginate((int) $request->integer('per_page', 10));

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (SupportTicket $ticket) => $this->ticketPayload($ticket))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function showMine(Request $request, string $uuid): JsonResponse
    {
        $ticket = SupportTicket::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->with(['messages' => fn ($q) => $q->where('visible_para_cliente', true)->oldest(), 'attachments'])
            ->firstOrFail();

        return response()->json(['data' => $this->ticketPayload($ticket, true)]);
    }

    public function replyMine(Request $request, string $uuid): JsonResponse
    {
        $ticket = SupportTicket::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'mensaje' => ['required', 'string', 'min:3', 'max:3000'],
            'imagenes' => ['nullable', 'array', 'max:3'],
            'imagenes.*' => ['file', 'image', 'max:5120'],
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'autor_tipo' => 'cliente',
            'mensaje' => $validated['mensaje'],
            'visible_para_cliente' => true,
        ]);

        if ($ticket->estado === 'esperando_usuario') {
            $ticket->estado = 'en_revision';
        }
        $ticket->ultimo_cambio_at = now();
        $ticket->save();
        $this->storeAttachments($request, $ticket, $message);

        return response()->json([
            'message' => 'Respuesta enviada correctamente.',
            'data' => $this->ticketPayload($ticket->fresh(['messages', 'attachments']), true),
        ]);
    }

    protected function validateTicket(Request $request, bool $authenticated): array
    {
        return $request->validate([
            'nombre' => [$authenticated ? 'nullable' : 'required', 'string', 'max:120'],
            'email' => [$authenticated ? 'nullable' : 'required', 'email:rfc', 'max:190'],
            'tipo_error' => ['required', Rule::in(['login', 'registro', 'pago', 'agenda', 'videollamada', 'plataforma', 'otro'])],
            'asunto' => ['required', 'string', 'min:4', 'max:160'],
            'descripcion' => ['required', 'string', 'min:10', 'max:5000'],
            'imagenes' => ['nullable', 'array', 'max:3'],
            'imagenes.*' => ['file', 'image', 'max:5120'],
        ]);
    }

    protected function createTicket(array $validated, ?int $userId, Request $request): SupportTicket
    {
        $ticket = SupportTicket::create([
            'user_id' => $userId,
            'nombre' => $validated['nombre'] ?? null,
            'email' => $validated['email'],
            'tipo_error' => $validated['tipo_error'],
            'asunto' => $validated['asunto'],
            'descripcion' => $validated['descripcion'],
            'estado' => 'nuevo',
            'prioridad' => in_array($validated['tipo_error'], ['login', 'registro'], true) ? 'alta' : 'normal',
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $userId,
            'autor_tipo' => 'cliente',
            'mensaje' => $validated['descripcion'],
            'visible_para_cliente' => true,
        ]);

        $this->storeAttachments($request, $ticket, $message);

        Mail::to($ticket->email)->send(new SupportTicketCreatedMail($ticket->fresh()));

        return $ticket->fresh(['messages', 'attachments']);
    }

    protected function storeAttachments(Request $request, SupportTicket $ticket, ?SupportTicketMessage $message = null): void
    {
        foreach ($request->file('imagenes', []) as $file) {
            $path = $file->store("support/{$ticket->uuid}", 'local');

            SupportTicketAttachment::create([
                'support_ticket_id' => $ticket->id,
                'support_ticket_message_id' => $message?->id,
                'disk' => 'local',
                'path' => $path,
                'nombre_original' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    protected function ticketPayload(SupportTicket $ticket, bool $withDetails = false): array
    {
        $payload = [
            'uuid' => $ticket->uuid,
            'codigo' => $ticket->codigo,
            'nombre' => $ticket->nombre,
            'email' => $ticket->email,
            'tipo_error' => $ticket->tipo_error,
            'asunto' => $ticket->asunto,
            'descripcion' => $ticket->descripcion,
            'estado' => $ticket->estado,
            'prioridad' => $ticket->prioridad,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'ultimo_cambio_at' => $ticket->ultimo_cambio_at?->toIso8601String(),
            'resuelto_at' => $ticket->resuelto_at?->toIso8601String(),
            'messages_count' => $ticket->messages_count ?? $ticket->messages()->count(),
        ];

        if ($withDetails) {
            $payload['mensajes'] = $ticket->messages
                ->map(fn (SupportTicketMessage $message) => [
                    'id' => $message->id,
                    'autor_tipo' => $message->autor_tipo,
                    'mensaje' => $message->mensaje,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->values();
            $payload['adjuntos'] = $ticket->attachments
                ->map(fn (SupportTicketAttachment $attachment) => [
                    'id' => $attachment->id,
                    'nombre_original' => $attachment->nombre_original,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                ])
                ->values();
        }

        return $payload;
    }
}
