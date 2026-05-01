<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    /** Campos siempre redactados del payload */
    public const REDACT = ['password', 'password_confirmation', 'current_password', 'token', 'access_token', 'refresh_token', 'secret', 'cvc', 'card_number', 'two_factor_code', 'recovery_code'];

    public static function log(string $action, array $opts = []): ?AuditLog
    {
        try {
            /** @var Request|null $req */
            $req = app('request');
            $user = $req?->user();

            return AuditLog::create([
                'uuid'           => (string) Str::uuid(),
                'user_id'        => $opts['user_id']    ?? $user?->id,
                'user_email'     => $opts['user_email'] ?? $user?->email,
                'user_role'      => $opts['user_role']  ?? ($user->rol ?? null),
                'action'         => $action,
                'auditable_type' => $opts['auditable_type'] ?? null,
                'auditable_id'   => $opts['auditable_id']   ?? null,
                'changes'        => $opts['changes'] ?? null,
                'route'          => $opts['route']  ?? optional($req?->route())->getName(),
                'method'         => $opts['method'] ?? $req?->method(),
                'url'            => $opts['url']    ?? $req?->fullUrl(),
                'ip'             => $opts['ip']     ?? $req?->ip(),
                'user_agent'     => $opts['user_agent'] ?? Str::limit((string) $req?->userAgent(), 480, ''),
                'payload'        => $opts['payload'] ?? self::sanitize($req?->all() ?? []),
                'status_code'    => $opts['status_code'] ?? null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[audit] no se pudo registrar: '.$e->getMessage());
            return null;
        }
    }

    public static function logModel(string $action, Model $model, array $changes = [], array $extra = []): ?AuditLog
    {
        return self::log($action, array_merge($extra, [
            'auditable_type' => $model::class,
            'auditable_id'   => $model->getKey(),
            'changes'        => $changes,
        ]));
    }

    public static function sanitize(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array((string) $k, self::REDACT, true)) {
                $data[$k] = '***';
            } elseif (is_array($v)) {
                $data[$k] = self::sanitize($v);
            }
        }
        return $data;
    }
}
