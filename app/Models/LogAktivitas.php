<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LogAktivitas extends Model
{
    public $timestamps = false;

    protected $table = 'log_aktivitas';

    protected $fillable = [
        'user_id',
        'aksi',
        'target_type',
        'target_id',
        'keterangan',
        'ip_address',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Log aktivitas bersifat permanen dan tidak dapat diubah.');
        });

        static::deleting(function (): never {
            throw new LogicException('Log aktivitas bersifat permanen dan tidak dapat dihapus.');
        });
    }

    /**
     * Aktivitas superadmin (sebagai pelaku maupun akun sasaran) hanya terlihat oleh superadmin.
     */
    #[Scope]
    protected function terlihatOleh(Builder $query, User $user): void
    {
        if ($user->isSuperadmin()) {
            return;
        }

        $superadminIds = User::query()->where('role', 'superadmin')->pluck('id');

        $query
            ->where(fn (Builder $pelaku) => $pelaku->whereNull('user_id')->orWhereNotIn('user_id', $superadminIds))
            ->whereNot(fn (Builder $sasaran) => $sasaran
                ->where('target_type', 'User')
                ->whereIn('target_id', $superadminIds->map(fn (int $id): string => (string) $id)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
