<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AgendaEspecialistaReportMail extends Mailable
{
    use Queueable, SerializesModels, \App\Mail\Concerns\TaggedMail;

    /**
     * @param Collection<int, \App\Models\Cita> $citas
     */
    public function __construct(
        public User $especialista,
        public Collection $citas,
        public string $periodo,
        public string $desdeChile,
        public string $hastaChile,
    ) {
    }

    public function build(): self
    {
        $subject = $this->periodo === 'semanal'
            ? 'Reporte semanal de agenda - TarotEstrellas'
            : 'Agenda de hoy - TarotEstrellas';

        return $this
            ->subject($subject)
            ->tagNotif('agenda_especialista_'.$this->periodo)
            ->view('emails.agenda-especialista-report', [
                'especialista' => $this->especialista,
                'citas' => $this->citas,
                'periodo' => $this->periodo,
                'desdeChile' => $this->desdeChile,
                'hastaChile' => $this->hastaChile,
            ]);
    }
}
