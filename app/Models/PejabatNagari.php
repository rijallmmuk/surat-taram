<?php

namespace App\Models;

use App\Models\Concerns\CatatAudit;
use App\Models\Concerns\HapusBerkasPrivatLama;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PejabatNagari extends Model
{
    use CatatAudit, HapusBerkasPrivatLama, HasFactory;

    protected $table = 'pejabat_nagari';

    protected $fillable = [
        'user_id',
        'nama_pejabat',
        'nip',
        'jabatan',
        'file_tanda_tangan_path',
        'tahun_mulai',
        'tahun_selesai',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tahun_mulai' => 'integer',
            'tahun_selesai' => 'integer',
            'status_aktif' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    protected function kolomBerkasPrivat(): array
    {
        return ['file_tanda_tangan_path'];
    }

    public function getTanggalMulaiAttribute(): ?string
    {
        return $this->tahun_mulai ? "{$this->tahun_mulai}-01-01" : null;
    }

    public function getTanggalSelesaiAttribute(): ?string
    {
        return $this->tahun_selesai ? "{$this->tahun_selesai}-12-31" : null;
    }

    public function setTanggalMulaiAttribute($value): void
    {
        if ($value) {
            $this->attributes['tahun_mulai'] = (int) substr((string) $value, 0, 4);
        }
    }

    public function setTanggalSelesaiAttribute($value): void
    {
        if ($value) {
            $this->attributes['tahun_selesai'] = (int) substr((string) $value, 0, 4);
        } else {
            $this->attributes['tahun_selesai'] = null;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pengajuanSurat(): HasMany
    {
        return $this->hasMany(PengajuanSurat::class, 'pejabat_penandatangan_id');
    }

    public function canBeRemoved(): bool
    {
        if ($this->status_aktif || $this->pengajuanSurat()->exists()) {
            return false;
        }

        if ($this->user_id === null) {
            return true;
        }

        return ! LogAktivitas::where('user_id', $this->user_id)->exists()
            && ! PengajuanSurat::where('diajukan_oleh_user_id', $this->user_id)->exists()
            && ! PengajuanSurat::where('diverifikasi_oleh_user_id', $this->user_id)->exists()
            && ! PengajuanSurat::where('diterbitkan_oleh_user_id', $this->user_id)->exists();
    }

    protected static function booted(): void
    {
        static::saving(function (PejabatNagari $pejabat): void {
            if ($pejabat->status_aktif) {
                Nagari::query()->orderBy('id')->lockForUpdate()->firstOrFail();

                static::withoutEvents(function () use ($pejabat): void {
                    $query = static::where('jabatan', $pejabat->jabatan)
                        ->where('status_aktif', true)
                        ->lockForUpdate();

                    if ($pejabat->exists) {
                        $query->where('id', '!=', $pejabat->id);
                    }

                    $oldPejabats = $query->get();
                    foreach ($oldPejabats as $old) {
                        $old->status_aktif = false;
                        if (! $old->tahun_selesai) {
                            $old->tahun_selesai = (int) now()->format('Y');
                        }
                        $old->save();

                        if ($old->user_id !== null) {
                            User::whereKey($old->user_id)->update(['is_active' => false]);
                        }
                    }
                });
            }
        });

        static::deleting(fn (PejabatNagari $pejabat): bool => $pejabat->canBeRemoved());

        static::deleted(function (PejabatNagari $pejabat): void {
            if ($pejabat->user_id && $user = $pejabat->user) {
                if (auth()->id() !== $user->id) {
                    $user->delete();
                }
            }
        });
    }
}
