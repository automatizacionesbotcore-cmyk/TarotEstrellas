<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettingAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
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
}
