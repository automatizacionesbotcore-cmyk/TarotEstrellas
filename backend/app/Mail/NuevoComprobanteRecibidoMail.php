<?php

namespace App\Mail;

use App\Models\ComprobanteTransferencia;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NuevoComprobanteRecibidoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ComprobanteTransferencia $comprobante,
        public User $cliente
    ) {}

    public function build(): self
    {
        return $this
            ->subject('🔔 Nuevo comprobante de transferencia — TarotEstrellas')
            ->view('emails.nuevo-comprobante-recibido', [
                'comprobante' => $this->comprobante,
                'cliente'     => $this->cliente,
            ]);
    }
}
