<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SupportTicketUpdatedMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query()->with('user:id,uuid,name,email')->withCount('messages');

        if ($estado = $request->string('estado')->toString()) {
            if ($estado !== 'todos') {
                $query->where('estado', $estado);
            }
        }

        if ($tipo = $request->string('tipo_error')->toString()) {
            if ($tipo !== 'todos') {
                $query->where('tipo_error', $tipo);
            }
        }

        if ($q = trim($request->string('q')->toString())) {
            $query->where(function ($w) use ($q) {
                $w->where('codigo', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('asunto', 'like', "%{$q}%");
            });
        }

        $tickets = $query->latest('ultimo_cambio_at')->paginate((int) $request->integer('per_page', 15));

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (SupportTicket $ticket) => $this->payload($ticket))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $ticket = SupportTicket::query()
            ->where('uuid', $uuid)
            ->with(['messages.user:id,name,email', 'attachments', 'user:id,uuid,name,email'])
            ->firstOrFail();

        return response()->json(['data' => $this->payload($ticket, true)]);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $ticket = SupportTicket::where('uuid', $uuid)->firstOrFail();
        $validated = $request->validate([
            'estado' => ['required', Rule::in(['nuevo', 'en_revision', 'esperando_usuario', 'resuelto', 'cerrado'])],
            'prioridad' => ['required', Rule::in(['baja', 'normal', 'alta', 'urgente'])],
            'respuesta' => ['nullable', 'string', 'max:5000'],
            'visible_para_cliente' => ['nullable', 'boolean'],
        ]);

        $estadoAnterior = $ticket->estado;
        $message = null;

        $ticket->fill([
            'estado' => $validated['estado'],
            'prioridad' => $validated['prioridad'],
            'asignado_a' => $request->user()->id,
            'ultimo_cambio_at' => now(),
            'resuelto_at' => in_array($validated['estado'], ['resuelto', 'cerrado'], true) ? now() : null,
        ]);
        $ticket->save();

        if (! empty($validated['respuesta'])) {
            $message = $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'autor_tipo' => 'admin',
                'mensaje' => $validated['respuesta'],
                'visible_para_cliente' => $validated['visible_para_cliente'] ?? true,
            ]);
        }

        if ($estadoAnterior !== $ticket->estado || $message?->visible_para_cliente) {
            Mail::to($ticket->email)->send(new SupportTicketUpdatedMail($ticket->fresh(), $message, $estadoAnterior));
        }

        return response()->json([
            'message' => 'Ticket actualizado correctamente.',
            'data' => $this->payload($ticket->fresh(['messages.user', 'attachments', 'user']), true),
        ]);
    }

    public function attachment(string $uuid, int $attachmentId): StreamedResponse
    {
        $ticket = SupportTicket::where('uuid', $uuid)->firstOrFail();
        $attachment = SupportTicketAttachment::where('support_ticket_id', $ticket->id)->findOrFail($attachmentId);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'Archivo no encontrado.');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->nombre_original);
    }

    protected function payload(SupportTicket $ticket, bool $withDetails = false): array
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
            'cliente' => $ticket->user ? [
                'uuid' => $ticket->user->uuid,
                'name' => $ticket->user->name,
                'email' => $ticket->user->email,
            ] : null,
        ];

        if ($withDetails) {
            $payload['mensajes'] = $ticket->messages
                ->map(fn (SupportTicketMessage $message) => [
                    'id' => $message->id,
                    'autor_tipo' => $message->autor_tipo,
                    'autor' => $message->user?->name,
                    'mensaje' => $message->mensaje,
                    'visible_para_cliente' => $message->visible_para_cliente,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->values();
            $payload['adjuntos'] = $ticket->attachments
                ->map(fn (SupportTicketAttachment $attachment) => [
                    'id' => $attachment->id,
                    'nombre_original' => $attachment->nombre_original,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                    'download_url' => "/api/admin/soporte/{$ticket->uuid}/adjuntos/{$attachment->id}",
                ])
                ->values();
        }

        return $payload;
    }
}
