<?php

namespace App\Mail;

use App\Models\ComprobanteTransferencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransferenciaAprobadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ComprobanteTransferencia $comprobante) {}

    public function build(): self
    {
        return $this
            ->subject('✅ Transferencia validada — TarotEstrellas')
            ->view('emails.transferencia-aprobada', ['comprobante' => $this->comprobante]);
    }
}
