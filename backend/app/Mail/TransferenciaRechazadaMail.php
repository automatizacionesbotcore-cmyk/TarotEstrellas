<?php

namespace App\Mail;

use App\Models\ComprobanteTransferencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransferenciaRechazadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ComprobanteTransferencia $comprobante,
        public string $razon
    ) {}

    public function build(): self
    {
        return $this
            ->subject('⚠️ Comprobante rechazado — TarotEstrellas')
            ->view('emails.transferencia-rechazada', [
                'comprobante' => $this->comprobante,
                'razon'       => $this->razon,
            ]);
    }
}
