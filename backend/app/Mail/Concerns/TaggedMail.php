<?php

namespace App\Mail\Concerns;

trait TaggedMail
{
    protected function tagNotif(string $tipo, ?int $userId = null, ?int $citaId = null, ?string $plantilla = null): self
    {
        return $this->withSymfonyMessage(function ($message) use ($tipo, $userId, $citaId, $plantilla) {
            $h = $message->getHeaders();
            $h->addTextHeader('X-Notif-Tipo', $tipo);
            $h->addTextHeader('X-Notif-Plantilla', $plantilla ?: $tipo);
            if ($userId)  $h->addTextHeader('X-Notif-User-Id', (string) $userId);
            if ($citaId)  $h->addTextHeader('X-Notif-Cita-Id', (string) $citaId);
        });
    }
}
