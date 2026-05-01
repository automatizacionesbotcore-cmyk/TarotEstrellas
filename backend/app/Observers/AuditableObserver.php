<?php

namespace App\Observers;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function created(Model $m): void
    {
        AuditLogger::logModel('created', $m, ['new' => $this->safe($m->getAttributes())]);
    }

    public function updated(Model $m): void
    {
        $changes = $m->getChanges();
        if (empty($changes)) return;

        $original = collect($m->getOriginal())->only(array_keys($changes))->all();
        AuditLogger::logModel('updated', $m, [
            'old' => $this->safe($original),
            'new' => $this->safe($changes),
        ]);
    }

    public function deleted(Model $m): void
    {
        AuditLogger::logModel('deleted', $m, ['old' => $this->safe($m->getOriginal())]);
    }

    private function safe(array $attrs): array
    {
        return AuditLogger::sanitize($attrs);
    }
}
