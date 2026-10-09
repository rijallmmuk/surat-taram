<?php

namespace App\Services;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?User $actor,
        string $action,
        ?string $targetType,
        string|int|null $targetId,
        string $description,
        array $before = [],
        array $after = [],
        array $metadata = [],
    ): LogAktivitas {
        $request = app()->runningInConsole() ? null : request();

        return LogAktivitas::create([
            'user_id' => $actor?->id,
            'aksi' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'keterangan' => $description,
            'ip_address' => $request?->ip(),
            'metadata' => [
                'actor' => $actor === null ? null : [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'username' => $actor->username,
                    'role' => $actor->role,
                ],
                'before' => $before,
                'after' => $after,
                'request' => $request === null ? null : [
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                ],
                ...$metadata,
            ],
            'created_at' => now(),
        ]);
    }
}
