<?php

namespace App\Listeners;

use App\Models\NotificacionEnviada;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RegistrarEmailEnviado
{
    public function handleSending(MessageSending $event): void
    {
        $event->message->getHeaders()->addTextHeader('X-Notif-Uuid', (string) Str::uuid());
    }

    public function handleSent(MessageSent $event): void
    {
        try {
            $msg = $event->message;
            $headers = $msg->getHeaders();
            $h = fn (string $k) => $headers->get($k)?->getBodyAsString();

            $tos = $msg->getTo() ?? [];
            $first = $tos[0] ?? null;
            $to = $first ? (method_exists($first, 'getAddress') ? $first->getAddress() : (string) $first) : null;

            NotificacionEnviada::create([
                'uuid'         => $h('X-Notif-Uuid') ?: (string) Str::uuid(),
                'user_id'      => $h('X-Notif-User-Id') ? (int) $h('X-Notif-User-Id') : null,
                'canal'        => 'email',
                'tipo'         => $h('X-Notif-Tipo') ?: 'desconocido',
                'destinatario' => $to,
                'asunto'       => $msg->getSubject(),
                'preview'      => $this->preview($msg),
                'estado'       => 'enviado',
                'proveedor'    => config('mail.default'),
                'metadata'     => [
                    'cita_id'    => $h('X-Notif-Cita-Id'),
                    'plantilla'  => $h('X-Notif-Plantilla'),
                    'message_id' => $h('Message-ID'),
                ],
                'enviado_en'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[notif] No se pudo registrar email enviado: '.$e->getMessage());
        }
    }

    private function preview($message): ?string
    {
        try {
            $body = $message->getBody();
            $text = $body ? strip_tags($body->bodyToString()) : '';
            $text = preg_replace('/\s+/', ' ', trim($text));
            return $text ? Str::limit($text, 280) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
