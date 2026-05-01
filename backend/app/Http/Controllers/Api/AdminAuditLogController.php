<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettingAudit;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    /** Audit log de configuración (compat). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'string', 'max:200'],
            'user_id' => ['sometimes', 'integer'],
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $q = AppSettingAudit::query()->with('user:id,uuid,name,email');
        if ($s = $request->string('q')->toString()) {
            $q->where(fn ($w) => $w->where('setting_key', 'like', "%$s%")->orWhere('motivo', 'like', "%$s%"));
        }
        if ($u = $request->integer('user_id')) $q->where('user_id', $u);
        if ($d = $request->date('desde')) $q->where('created_at', '>=', $d);
        if ($d = $request->date('hasta')) $q->where('created_at', '<=', $d->endOfDay());

        return response()->json($q->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 30)));
    }

    /** Audit log unificado (M2). */
    public function unified(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'string', 'max:200'],
            'user_id' => ['sometimes', 'integer'],
            'action' => ['sometimes', 'string', 'max:80'],
            'auditable_type' => ['sometimes', 'string', 'max:191'],
            'auditable_id' => ['sometimes', 'integer'],
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $q = AuditLog::query()->with('user:id,uuid,name,email');
        if ($s = $request->string('q')->toString()) {
            $q->where(fn ($w) => $w->where('url', 'like', "%$s%")
                ->orWhere('user_email', 'like', "%$s%")
                ->orWhere('action', 'like', "%$s%"));
        }
        if ($u = $request->integer('user_id'))   $q->where('user_id', $u);
        if ($a = $request->string('action')->toString())         $q->where('action', $a);
        if ($t = $request->string('auditable_type')->toString()) $q->where('auditable_type', $t);
        if ($i = $request->integer('auditable_id')) $q->where('auditable_id', $i);
        if ($d = $request->date('desde')) $q->where('created_at', '>=', $d);
        if ($d = $request->date('hasta')) $q->where('created_at', '<=', $d->endOfDay());

        return response()->json($q->orderByDesc('created_at')
            ->paginate((int) $request->integer('per_page', 30)));
    }
}

