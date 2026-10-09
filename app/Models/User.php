<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use CatatAudit, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'password_changed_at',
        'role',
        'penduduk_nik',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return in_array($this->role, ['superadmin', 'admin', 'sekretaris', 'wali_nagari', 'warga'], true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdministrator(): bool
    {
        return in_array($this->role, ['superadmin', 'admin'], true);
    }

    /**
     * Aturan sandi untuk semua peran: bebas, asal minimal 8 karakter.
     */
    public static function aturanSandi(): Password
    {
        return Password::min(8);
    }

    public static function keteranganAturanSandi(): string
    {
        return 'Minimal 8 karakter.';
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'penduduk_nik', 'nik');
    }

    public function pejabatNagari(): HasOne
    {
        return $this->hasOne(PejabatNagari::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->exists && $user->isDirty('password') && ! $user->isDirty('password_changed_at')) {
                $user->password_changed_at = null;
            }
        });

        static::saved(function (User $user): void {
            if ($user->role && ($user->wasRecentlyCreated || $user->wasChanged('role'))) {
                $user->syncRoles([$user->role]);
            }
        });
    }
}
